# Traefik Setup Documentation

## Overview

Traefik runs as a **standalone infrastructure service** outside the application repository at `/datadisk0/projects/traefik/`.

This is intentional - Traefik is infrastructure that serves multiple applications and environments, not part of the application code itself.

## Location

```
/datadisk0/projects/traefik/
├── docker-compose.yaml
├── traefik.yaml
├── acme.json (SSL certificates)
└── acme-dns.json (DNS challenge certs)
```

## Configuration Files

### docker-compose.yaml

```yaml
version: '3.8'

services:
  traefik:
    image: traefik:v2.11
    container_name: traefik
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    networks:
      - traefik-public
    ports:
      - "8080:8080"   # Traefik dashboard
      - "80:80"       # HTTP
      - "443:443"     # HTTPS
    environment:
      - TZ=UTC
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./traefik.yaml:/traefik.yaml:ro
      - ./acme.json:/acme.json
      - ./acme-dns.json:/acme-dns.json
    labels:
      # Dashboard
      - "traefik.enable=true"
      - "traefik.http.routers.traefik.rule=Host(`traefik.webmeteor.in`)"
      - "traefik.http.routers.traefik.entrypoints=http"
      - "traefik.http.routers.traefik.service=api@internal"
      - "traefik.http.services.traefik.loadbalancer.server.port=8080"

networks:
  traefik-public:
    external: true
```

### traefik.yaml

```yaml
api:
  dashboard: true
  insecure: true

entryPoints:
  http:
    address: ":80"
  https:
    address: ":443"

providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: traefik-public

certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@webmeteor.in
      storage: /acme.json
      httpChallenge:
        entryPoint: http

  letsencrypt-dns:
    acme:
      email: admin@webmeteor.in
      storage: /acme-dns.json
      dnsChallenge:
        provider: manual

log:
  level: INFO

accessLog: {}
```

## Setup Steps

### 1. Create Directory

```bash
sudo mkdir -p /datadisk0/projects/traefik
sudo chown azureuser:azureuser /datadisk0/projects/traefik
cd /datadisk0/projects/traefik
```

### 2. Create Configuration Files

Create `docker-compose.yaml` and `traefik.yaml` with the content above.

### 3. Create SSL Certificate Files

```bash
touch acme.json acme-dns.json
chmod 600 acme.json acme-dns.json
```

**Important:** These files must have 600 permissions for Traefik to use them.

### 4. Create Docker Network

```bash
docker network create traefik-public
```

### 5. Start Traefik

```bash
cd /datadisk0/projects/traefik
docker compose up -d
```

### 6. Verify

```bash
# Check container is running
docker ps | grep traefik

# Check logs
docker logs traefik

# Test dashboard (local only)
curl -I http://localhost:8080/api/overview
```

## Accessing Traefik Dashboard

- **Local**: http://localhost:8080/dashboard/
- **Via hostname**: http://traefik.webmeteor.in:8080/dashboard/ (if DNS configured)

**Note:** Currently configured as `insecure: true` for testing. In production, should be secured with authentication.

## How Applications Connect to Traefik

Applications join the `traefik-public` network and use Docker labels to configure routing:

```yaml
# In your app's docker-compose.yaml
services:
  www:
    # ... other config ...
    networks:
      - default
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.myapp.rule=Host(`myapp.webmeteor.in`)"
      - "traefik.http.routers.myapp.entrypoints=http"
      - "traefik.http.services.myapp.loadbalancer.server.port=80"

networks:
  traefik-public:
    external: true
```

## Backup & Restore

### Backup

```bash
# Backup all Traefik configuration
cd /datadisk0/projects
sudo tar -czf traefik-backup-$(date +%Y%m%d).tar.gz traefik/

# Copy to safe location
sudo cp traefik-backup-*.tar.gz /home/azureuser/backups/
```

### Restore

```bash
# Extract backup
cd /datadisk0/projects
sudo tar -xzf /path/to/traefik-backup-YYYYMMDD.tar.gz

# Restart Traefik
cd traefik
docker compose down
docker compose up -d
```

## SSL Certificates

### HTTP Challenge (Default)

Automatic SSL certificates for individual domains using Let's Encrypt HTTP challenge:

```yaml
labels:
  - "traefik.http.routers.myapp.tls=true"
  - "traefik.http.routers.myapp.tls.certresolver=letsencrypt"
```

### DNS Challenge (For Wildcards)

For wildcard certificates (`*.webmeteor.in`), you need DNS challenge with your DNS provider API:

1. Update `traefik.yaml` with your DNS provider
2. Add provider credentials as environment variables
3. Use `letsencrypt-dns` cert resolver

## Maintenance

### View Logs

```bash
docker logs traefik
docker logs -f traefik  # Follow logs
```

### Restart Traefik

```bash
cd /datadisk0/projects/traefik
docker compose restart
```

### Update Traefik

```bash
cd /datadisk0/projects/traefik
docker compose pull
docker compose down
docker compose up -d
```

## Troubleshooting

### Traefik not routing to container

1. Check container is on `traefik-public` network:
   ```bash
   docker inspect <container> | grep traefik-public
   ```

2. Check Traefik sees the container:
   ```bash
   curl http://localhost:8080/api/http/routers | jq
   ```

3. Check labels are correct:
   ```bash
   docker inspect <container> | grep -A 10 Labels
   ```

### SSL certificates not generating

1. Check Let's Encrypt rate limits (5 per week per domain)
2. Verify port 80 is accessible from internet
3. Check Traefik logs for ACME errors:
   ```bash
   docker logs traefik | grep acme
   ```

### Dashboard not accessible

- Dashboard is on port 8080 (not 80/443)
- Check `insecure: true` is set in `traefik.yaml`
- Verify dashboard labels on Traefik container

## Production Security

For production, secure the Traefik dashboard:

1. Remove `insecure: true` from `traefik.yaml`
2. Add basic auth middleware
3. Use HTTPS for dashboard access
4. Limit dashboard to internal network or VPN

Example with basic auth:

```yaml
# Create password hash
htpasswd -nb admin yourpassword

# Add to docker-compose labels:
labels:
  - "traefik.http.routers.traefik.middlewares=auth"
  - "traefik.http.middlewares.auth.basicauth.users=admin:$$apr1$$..."
```

## Related Documentation

- [Architecture Overview](./ARCHITECTURE.md)
- [VM Setup Guide](./VM_SETUP.md)
- [Deployment Setup](../../DEPLOYMENT_SETUP.md)
