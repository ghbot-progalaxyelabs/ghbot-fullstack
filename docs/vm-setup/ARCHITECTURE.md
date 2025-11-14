# WebMeteor Platform Architecture

This document describes the complete architecture of the WebMeteor platform, including infrastructure, networking, deployment systems, and customer site management.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                           Internet                               │
└────────────────────────────┬────────────────────────────────────┘
                             │
                    ┌────────▼────────┐
                    │     Apache      │
                    │   (Gateway)     │
                    │  Port 80/443    │
                    └─────────────────┘
                             │
              ┌──────────────┼──────────────┐
              │                             │
      ┌───────▼────────┐          ┌────────▼─────────┐
      │  Webhook API   │          │     Traefik      │
      │  (Stable)      │          │  (Dynamic Proxy) │
      │                │          │  Port 8888/8443  │
      └────────────────┘          └──────────────────┘
                                           │
          ┌────────────────────────────────┼────────────────────────┐
          │                                │                        │
    ┌─────▼──────┐                  ┌─────▼─────┐          ┌──────▼──────┐
    │  Platform  │                  │  Platform │          │  Customer   │
    │  (Staging) │                  │  (Prod)   │          │    Sites    │
    └────────────┘                  └───────────┘          └─────────────┘
          │                                │                       │
    ┌─────┴─────┐                    ┌────┴────┐            ┌─────┴─────┐
    │ WWW, API, │                    │WWW, API,│            │ Multiple  │
    │Alert, DB  │                    │Alert, DB│            │ Sites     │
    └───────────┘                    └─────────┘            └───────────┘
```

## Component Details

### 1. Apache (Stable Gateway Layer)

**Purpose**: Minimal, stable reverse proxy that never restarts during deployments

**Configuration**:
- Listens on ports 80 and 443
- Routes `/webhook/*` directly to platform API containers
- Routes all other traffic to Traefik
- Handles SSL termination for initial connection
- WebSocket support for Socket.IO

**Why Apache?**:
- Existing infrastructure
- Webhook endpoints remain stable during platform updates
- Prevents chicken-and-egg problem (API needs to be up to receive deployment webhooks)
- Can keep old PHP app during transition

**Key Routes**:
```
api.webmeteor.in/webhook/*      → localhost:4402/webhook (staging API)
api.webmeteor.in/webhook/*      → localhost:5402/webhook (production API)
*.webmeteor.in/*                → Traefik (localhost:8888)
```

---

### 2. Traefik (Dynamic Routing Layer)

**Purpose**: Container-native reverse proxy with automatic service discovery

**Features**:
- Automatic container discovery via Docker labels
- Dynamic routing without config reloads
- Automatic SSL certificate generation (Let's Encrypt)
- Wildcard SSL support via DNS challenge
- WebSocket support (native)
- Load balancing across multiple containers
- Health checks and circuit breakers

**Configuration**:
- Runs as standalone Docker container (separate from app stacks)
- Never restarts when platform/customer containers update
- Monitors Docker socket for new containers
- Routes based on container labels

**Network**:
- Creates external `traefik-public` network
- All routable containers join this network
- Internal app networks remain isolated

**Why Traefik?**:
- Dynamic customer site routing without manual config
- Wildcard subdomain support (`*.webmeteor.in`)
- Automatic SSL for all customer sites
- Container-native (perfect for Docker-based architecture)
- No reload required when adding/removing sites

---

### 3. Platform Services

#### Architecture Comparison

| Aspect | Staging | Production |
|--------|---------|------------|
| **Location** | `/datadisk0/projects/ghbot-fullstack` | `/datadisk0/projects/ghbot-fullstack-production` |
| **Git Branch** | `staging` | `prod` |
| **Domains** | `staging.webmeteor.in`, `staging-api.webmeteor.in` | `www.webmeteor.in`, `api.webmeteor.in` |
| **Ports** | WWW: 4400, API: 4402, Alert: 4401 | WWW: 5400, API: 5402, Alert: 5401 |
| **Database** | Separate PostgreSQL instance | Separate PostgreSQL instance |
| **SSL** | Let's Encrypt (Traefik) | Let's Encrypt (Traefik) |
| **Auto-Deploy** | On push to `staging` branch | On push to `prod` branch |

#### Service Stack (Each Environment)

```
┌──────────────────────────────────────────────────┐
│              Platform Environment                 │
├──────────────────────────────────────────────────┤
│                                                   │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌────┐ │
│  │   WWW   │  │   API   │  │  Alert  │  │ DB │ │
│  │ Angular │◄─┤  PHP    │  │Socket.IO│  │PG16│ │
│  │  :4200  │  │  :80    │  │  :3001  │  │5432│ │
│  └─────────┘  └─────────┘  └─────────┘  └────┘ │
│       │             │             │              │
│       └─────────────┼─────────────┘              │
│                     │                            │
│         ┌───────────▼────────────┐               │
│         │  traefik-public network│               │
│         └────────────────────────┘               │
└──────────────────────────────────────────────────┘
```

**Services**:

1. **WWW (Frontend)**
   - Technology: Angular 19
   - Build: Multi-stage Docker build
   - Serves: Static assets + client-side app
   - API Client: Auto-generated from PHP backend

2. **API (Backend)**
   - Technology: StoneScriptPHP (PHP 8.3)
   - Framework: Custom MVC with auto-routing
   - Features: Type-safe DTOs, Interface contracts
   - Responsibilities:
     - RESTful API endpoints
     - User management
     - Site creation/management
     - Webhook handling (GitHub, customer repos)
     - Deployment queue management

3. **Alert (Notifications)**
   - Technology: Node.js + Socket.IO
   - Purpose: Real-time push notifications
   - Use cases: Deployment status, site updates, user alerts

4. **DB (Database)**
   - Technology: PostgreSQL 16
   - Persistence: Docker volume
   - Migrations: Managed via PHP CLI tool
   - Isolation: Not exposed externally

---

### 4. Customer Site Architecture

#### Site Structure

```
/datadisk0/sites/{customer_uuid}/{app_id}/
├── docker-compose.yaml          # Site-specific compose file
├── .env                         # Site configuration
├── .git/                        # Git repository
├── api/                         # PHP backend
│   ├── Dockerfile
│   ├── src/
│   └── Framework/
├── www/                         # Angular frontend
│   ├── Dockerfile
│   ├── src/
│   └── api-client/             # Generated from API
├── alert/                       # Socket.IO service
│   ├── Dockerfile
│   └── server.js
└── docker-compose.yaml
```

#### Site Services

Each customer site runs the same stack as the platform:
- **WWW**: Angular frontend (user's site)
- **API**: PHP backend (user's site logic)
- **Alert**: Socket.IO (user's site notifications)
- **DB**: PostgreSQL (user's site data)

#### Dynamic Routing via Traefik Labels

```yaml
# Example customer site service with Traefik labels
services:
  www:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.rule=Host(`${APP_DOMAIN}`)"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.entrypoints=websecure"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.tls.certresolver=letsencrypt"
      - "traefik.http.services.${CUSTOMER_UUID}-${APP_ID}-www.loadbalancer.server.port=4200"
```

**How it works**:
1. Site created via platform API
2. API generates docker-compose.yaml with unique labels
3. API runs `docker compose up -d`
4. Traefik detects new containers via Docker socket
5. Traefik creates routes based on labels
6. Traefik requests SSL certificate from Let's Encrypt
7. Site immediately accessible at configured domain

---

## Deployment System Architecture

### Platform Deployment Flow

```
┌────────────┐
│  Developer │
└──────┬─────┘
       │ git push
       ▼
┌────────────────┐
│  GitHub Repo   │
│  Branch: staging│
└────────┬───────┘
         │ webhook
         ▼
┌─────────────────────┐
│ Apache              │
│ → API:4402/webhook  │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────┐
│ GithubWebhookRoute   │
│ - Verify signature   │
│ - Check branch       │
│ - Add to queue       │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ Queue File           │
│ ~/deploy/queue.txt   │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ Cron (every minute)  │
│ process-queue.php    │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ at Daemon            │
│ Schedule deploy.php  │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ deploy.php           │
│ 1. git pull          │
│ 2. ./build.sh        │
│ 3. docker compose up │
│ 4. Health checks     │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ Deployment Complete  │
│ Log to file          │
└──────────────────────┘
```

**Key Features**:
- **Signature Verification**: HMAC-SHA256 webhook validation
- **Queue System**: Prevents concurrent deployments
- **Lock Mechanism**: Single deployment at a time
- **Health Checks**: Verify services after deployment
- **Detailed Logging**: Track deployment progress
- **Rollback Safety**: Git history preserved

### Customer Site Deployment Flow

```
┌────────────┐
│  Customer  │
└──────┬─────┘
       │ git push
       ▼
┌────────────────────┐
│ GitHub/Bitbucket   │
│ Customer Repo      │
└──────────┬─────────┘
           │ webhook
           ▼
┌─────────────────────────┐
│ Platform API            │
│ /webhook/customer-site  │
└──────────┬──────────────┘
           │
           ▼
┌────────────────────────┐
│ Customer Deploy Queue  │
└──────────┬─────────────┘
           │
           ▼
┌────────────────────────┐
│ Process Queue          │
│ Per-site deployment    │
└──────────┬─────────────┘
           │
           ▼
┌─────────────────────────┐
│ Site Directory          │
│ 1. cd /datadisk0/sites/ │
│    {uuid}/{app_id}      │
│ 2. git pull             │
│ 3. docker compose build │
│ 4. docker compose up -d │
└─────────────────────────┘
```

---

## Networking Architecture

### Docker Networks

```
┌─────────────────────────────────────────────────────┐
│           traefik-public (External)                  │
│  - Traefik gateway                                   │
│  - All platform WWW, API, Alert services             │
│  - All customer WWW, API, Alert services             │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│      webmeteor-staging-network (Bridge)              │
│  - Staging: WWW, API, Alert, DB                      │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│      webmeteor-production-network (Bridge)           │
│  - Production: WWW, API, Alert, DB                   │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  {customer_uuid}-{app_id}-network (Bridge)           │
│  - Customer site: WWW, API, Alert, DB                │
│  (One network per customer site)                     │
└─────────────────────────────────────────────────────┘
```

**Network Isolation**:
- Public-facing services join `traefik-public`
- Internal services (DB) only on private networks
- Each customer site has isolated network for DB
- No cross-customer network access

---

## Security Architecture

### Defense in Depth

1. **Network Layer**
   - Firewall rules (Azure NSG)
   - Only ports 80, 443, 22 exposed
   - Internal services not accessible externally

2. **Application Layer**
   - Webhook signature verification (HMAC-SHA256)
   - JWT authentication for API
   - CORS policies
   - Input validation and sanitization

3. **Container Layer**
   - Non-root users in containers
   - Read-only filesystems where possible
   - Resource limits (CPU, memory)
   - Network isolation

4. **Data Layer**
   - Database credentials per site
   - Encrypted connections (SSL/TLS)
   - Volume encryption (Azure disk encryption)
   - Regular backups

5. **Deployment Layer**
   - Deployment locks prevent conflicts
   - Queue file permissions (666 for write, restricted dir)
   - Config file permissions (600, owner only)
   - Secure secret generation

---

## Scalability Considerations

### Vertical Scaling (Current)
- Increase VM size (CPU, RAM)
- Increase disk space (datadisk0)
- Suitable for 100s of customer sites

### Horizontal Scaling (Future)

**Option 1: Multiple VMs with Load Balancer**
```
Internet → Azure Load Balancer → Multiple VMs (each with Traefik)
                                  → Shared PostgreSQL cluster
                                  → Shared Redis for sessions
```

**Option 2: Kubernetes Migration**
```
Internet → Ingress Controller → Kubernetes Pods
                               → StatefulSets for databases
                               → Helm charts for customer sites
```

**Option 3: Hybrid**
- Platform on dedicated VMs
- Customer sites on separate VM pool
- Shared database cluster
- CDN for static assets

---

## Monitoring & Observability

### Metrics to Track

1. **Infrastructure**
   - CPU, memory, disk usage
   - Network bandwidth
   - Docker container count

2. **Application**
   - API response times
   - Error rates
   - Active user connections
   - Deployment success/failure rates

3. **Customer Sites**
   - Site uptime
   - Response times
   - Resource usage per site
   - SSL certificate expiration

### Logging Strategy

1. **Centralized Logging**
   - Collect logs from all containers
   - Use log aggregation (ELK stack, Loki)
   - Retention policies

2. **Log Types**
   - Access logs (Apache, Traefik)
   - Application logs (API, WWW)
   - Deployment logs
   - Error logs

3. **Alerting**
   - Deployment failures
   - High error rates
   - Resource exhaustion
   - SSL certificate expiration

---

## Backup & Disaster Recovery

### Backup Strategy

1. **Databases**
   - Automated daily backups (pg_dump)
   - Retention: 30 days
   - Storage: Azure Blob Storage

2. **Customer Code**
   - Git repositories (GitHub/Bitbucket)
   - No additional backup needed

3. **Configuration**
   - `.env` files backed up
   - `docker-compose.yaml` in git
   - Traefik `acme.json` backed up

4. **Docker Volumes**
   - Daily snapshots
   - Azure disk snapshots

### Recovery Procedures

1. **Platform Recovery**
   - Clone from GitHub
   - Restore `.env` files
   - Run `docker compose up -d`
   - Restore database from backup

2. **Customer Site Recovery**
   - Clone from customer repo
   - Restore `.env` file
   - Restore database from backup
   - Run `docker compose up -d`

3. **Full VM Recovery**
   - Provision new VM
   - Install Docker, Apache
   - Clone repositories
   - Restore databases
   - Update DNS

---

## Future Enhancements

1. **Multi-Region Support**
   - Deploy to multiple Azure regions
   - Global traffic routing
   - Data replication

2. **CDN Integration**
   - Azure CDN or Cloudflare
   - Static asset caching
   - DDoS protection

3. **Advanced Monitoring**
   - APM (Application Performance Monitoring)
   - Real-time dashboards
   - Anomaly detection

4. **Auto-Scaling**
   - Scale customer sites based on traffic
   - Auto-provision new VMs
   - Container orchestration

5. **Custom Domains**
   - Automated DNS configuration
   - SSL certificate automation
   - Domain verification

6. **Site Templates**
   - Pre-built site templates
   - One-click deployments
   - Theme marketplace

---

## Related Documentation

- [VM_SETUP.md](./VM_SETUP.md) - VM setup instructions
- [CUSTOMER_SITES.md](./CUSTOMER_SITES.md) - Customer site implementation
- [TODO.md](./TODO.md) - Implementation checklist
- [DEPLOYMENT_SETUP.md](../../DEPLOYMENT_SETUP.md) - Deployment system details
