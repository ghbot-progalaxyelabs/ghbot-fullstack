# WebMeteor VM Setup Guide

This guide provides step-by-step instructions for setting up the WebMeteor platform on an Azure VM with Traefik as the gateway for dynamic customer site routing.

## Overview

The VM serves as a unified gateway for:
- **Platform Services**: Staging and Production environments
- **Customer Websites**: Dynamically routed user-generated sites
- **Auto-Deployment**: GitHub webhook-triggered deployments

## Architecture Summary

```
Internet → Apache (Port 80/443) → Traefik (Port 8888/8443) → Docker Containers
                   ↓
            Webhook Endpoints (Direct to API)
```

**Key Components:**
- **Apache**: Minimal reverse proxy, handles webhooks directly (never restarts)
- **Traefik**: Dynamic routing for all services, auto-SSL via Let's Encrypt
- **Platform**: Staging + Production deployments from GitHub branches
- **Customer Sites**: Each site in its own directory with docker-compose

## Prerequisites

- Azure VM (Ubuntu 22.04 or later)
- Docker & Docker Compose installed
- Apache2 installed
- Git configured with SSH access to GitHub
- Domain with wildcard DNS configured: `*.webmeteor.in → VM_IP`

## Directory Structure

```
/datadisk0/
├── projects/
│   ├── traefik/                              # Standalone Traefik gateway
│   │   ├── docker-compose.yaml
│   │   ├── traefik.yaml
│   │   ├── acme.json
│   │   └── acme-dns.json
│   │
│   ├── ghbot-fullstack/                      # Staging environment
│   │   ├── docker-compose.yaml
│   │   ├── .env
│   │   ├── api/, www/, alert/
│   │   └── docs/
│   │
│   └── ghbot-fullstack-production/           # Production environment
│       ├── docker-compose.yaml
│       ├── .env
│       └── api/, www/, alert/
│
├── sites/                                     # Customer websites
│   └── {customer_uuid}/
│       └── {app_id}/
│           ├── docker-compose.yaml
│           ├── .env
│           ├── api/, www/, alert/
│           └── .git/
│
└── docker-data/                               # Docker volumes (optional)

/home/azureuser/
└── deploy/
    ├── staging/
    │   ├── config.json
    │   ├── queue.txt
    │   └── deployment.log
    └── production/
        ├── config.json
        ├── queue.txt
        └── deployment.log
```

## Implementation Plan

See [TODO.md](./TODO.md) for the complete implementation checklist.

### Phase 1: Infrastructure Setup

1. Create Docker network for Traefik
2. Set up standalone Traefik container
3. Configure Apache as reverse proxy
4. Install and enable `at` daemon

### Phase 2: Repository Setup

5. Clone production repository
6. Create environment files (.env) for staging and production
7. Update docker-compose files with Traefik labels

### Phase 3: Deployment System

8. Create deployment configs
9. Set up cron jobs for queue processing
10. Generate webhook secrets

### Phase 4: Build & Deploy

11. Build and start staging environment
12. Build and start production environment
13. Configure GitHub webhooks

### Phase 5: SSL & DNS

14. Set up wildcard SSL certificates
15. Update DNS from Azure Static Web App to VM

### Phase 6: Customer Sites (Future)

16. Create customer site templates
17. Implement site creation API
18. Implement customer deployment webhooks

## Branch Strategy

```
main (development)
  ↓ (release/tag)
staging (QA/testing)
  ↓ (merge after validation)
prod (production)
```

**Deployment Flow:**
1. Commit/tag release to `main`
2. Sync `staging` with release
3. Push to `staging` → Webhook → Deploy to `/datadisk0/projects/ghbot-fullstack`
4. Test staging environment
5. If OK: Merge `staging` → `prod`
6. Push to `prod` → Webhook → Deploy to `/datadisk0/projects/ghbot-fullstack-production`
7. If staging fails: Fix in `staging`, then merge to both `main` and `prod`

## Port Mapping

### Platform Services

| Service | Internal Port | Exposed Port | Domain |
|---------|--------------|--------------|--------|
| Traefik HTTP | 80 | 8888 | - |
| Traefik HTTPS | 443 | 8443 | - |
| Traefik Dashboard | 8080 | 8080 | traefik.webmeteor.in |
| Apache HTTP | - | 80 | *.webmeteor.in |
| Apache HTTPS | - | 443 | *.webmeteor.in |

### Staging Environment

| Service | Internal Port | Exposed Port | Domain |
|---------|--------------|--------------|--------|
| WWW | 4200 | 4400 | staging.webmeteor.in |
| API | 80 | 4402 | staging-api.webmeteor.in |
| Alert | 3001 | 4401 | - |
| DB | 5432 | - | - |

### Production Environment

| Service | Internal Port | Exposed Port | Domain |
|---------|--------------|--------------|--------|
| WWW | 4200 | 5400 | www.webmeteor.in |
| API | 80 | 5402 | api.webmeteor.in |
| Alert | 3001 | 5401 | - |
| DB | 5432 | - | - |

## DNS Configuration Required

```
# Wildcard
*.webmeteor.in              A    <VM_PUBLIC_IP>

# Platform
www.webmeteor.in            A    <VM_PUBLIC_IP>
api.webmeteor.in            A    <VM_PUBLIC_IP>
staging.webmeteor.in        A    <VM_PUBLIC_IP>
staging-api.webmeteor.in    A    <VM_PUBLIC_IP>
traefik.webmeteor.in        A    <VM_PUBLIC_IP>

# Customer sites (created dynamically via API)
{customer-site}.webmeteor.in  A    <VM_PUBLIC_IP>
```

## Security Considerations

1. **Webhook Secrets**: Generate unique secrets for each environment
2. **Deployment Locks**: Prevent concurrent deployments
3. **Queue Files**: Proper permissions (666 for www-data write access)
4. **Config Files**: Restricted permissions (600, owner only)
5. **SSL Certificates**: Automatic via Let's Encrypt
6. **Traefik Dashboard**: Protect with basic auth in production

## Monitoring & Logs

### Deployment Logs
```bash
# Staging
tail -f /home/azureuser/deploy/staging/deployment.log

# Production
tail -f /home/azureuser/deploy/production/deployment.log
```

### Container Logs
```bash
# Staging
cd /datadisk0/projects/ghbot-fullstack
docker compose logs -f

# Production
cd /datadisk0/projects/ghbot-fullstack-production
docker compose logs -f

# Traefik
cd /datadisk0/projects/traefik
docker compose logs -f
```

### Apache Logs
```bash
tail -f /var/log/apache2/webmeteor-error.log
tail -f /var/log/apache2/webmeteor-access.log
```

## Troubleshooting

### Webhooks Not Triggering Deployments

1. Check webhook secret matches `.env` file
2. Verify API container is running: `docker compose ps api`
3. Check Apache routing: Test `curl http://localhost:4402/webhook/github`
4. Review deployment logs: `tail -50 /home/azureuser/deploy/staging/deployment.log`

### Traefik Not Routing Traffic

1. Check Traefik is running: `docker ps | grep traefik`
2. View Traefik dashboard: `http://traefik.webmeteor.in:8080`
3. Verify container labels: `docker inspect <container_name>`
4. Check traefik-public network: `docker network inspect traefik-public`

### Containers Not Starting

1. Check logs: `docker compose logs <service_name>`
2. Verify environment variables in `.env`
3. Check port conflicts: `ss -tlnp | grep <port>`
4. Verify networks exist: `docker network ls`

### SSL Certificates Not Generated

1. Verify DNS is pointing to VM: `dig www.webmeteor.in`
2. Check Traefik logs: `docker compose logs traefik`
3. Verify acme.json permissions: `chmod 600 /datadisk0/projects/traefik/acme.json`
4. Test Let's Encrypt challenge: Check port 80 is accessible

## Next Steps

After completing the VM setup:

1. Review [ARCHITECTURE.md](./ARCHITECTURE.md) for detailed system architecture
2. Review [CUSTOMER_SITES.md](./CUSTOMER_SITES.md) for customer site implementation
3. Follow [TODO.md](./TODO.md) for step-by-step implementation
4. Configure monitoring and alerting
5. Set up backup strategy for databases and customer sites

## References

- [Traefik Documentation](https://doc.traefik.io/traefik/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [GitHub Webhooks Documentation](https://docs.github.com/en/webhooks)
