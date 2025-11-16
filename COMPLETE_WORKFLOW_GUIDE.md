# Complete Workflow Guide - WebMeteor Development & Deployment

## 📋 Table of Contents

1. [Project Overview](#project-overview)
2. [Repository Structure](#repository-structure)
3. [Git Workflow](#git-workflow)
4. [Environment Setup](#environment-setup)
5. [Development Workflow](#development-workflow)
6. [Deployment Process](#deployment-process)
7. [Docker Management](#docker-management)
8. [Database Safety](#database-safety)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## Project Overview

**WebMeteor** is a full-stack website builder platform with:
- **Frontend**: Angular 19
- **Backend**: PHP 8.3 with StoneScriptPHP framework
- **Database**: PostgreSQL 16
- **Real-time**: Node.js Socket.IO alert service
- **Infrastructure**: Docker, Traefik reverse proxy

### Architecture

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│   Angular   │────▶│ StoneScript  │────▶│  PostgreSQL  │
│  Frontend   │     │   PHP API    │     │   Database   │
│  (www)      │     │   (api)      │     │   (db)       │
└─────────────┘     └──────────────┘     └──────────────┘
                           │
                           ▼
                    ┌──────────────┐
                    │  Socket.IO   │
                    │  Alerts      │
                    │  (alert)     │
                    └──────────────┘
```

---

## Repository Structure

### Deployment Folders on VM

```
/datadisk0/projects/
├── ghbot-fullstack-dev/         # Development work (main branch)
│   └── .env                     # Dev environment config
├── ghbot-fullstack-staging/     # Test deployment (production branch)
│   └── .env                     # Staging environment config
└── ghbot-fullstack-production/  # Live deployment (production branch)
    └── .env                     # Production environment config
```

### Git Branches

- **`main`** - Development branch for local work
- **`production`** - Deployment branch for both staging and production

---

## Git Workflow

### 🔄 Complete Development Cycle

```
┌─────────────────┐
│  Developers     │
│  Work on main   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Commit & Push  │
│  to main branch │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Merge main →   │
│  production     │
└────────┬────────┘
         │
         ├──────────────────┐
         ▼                  ▼
┌──────────────────┐  ┌──────────────────┐
│ Deploy to        │  │ Deploy to        │
│ staging folder   │  │ production folder│
│ (test first)     │  │ (if staging OK)  │
└──────────────────┘  └──────────────────┘
```

### Development Workflow Commands

#### 1. Local Development (on dev machine or ghbot-fullstack-dev)

```bash
cd /datadisk0/projects/ghbot-fullstack-dev

# Make changes
# ...

# Commit
git add .
git commit -m "Your changes"

# Push to GitHub
git push origin main
```

#### 2. Merge to Production Branch

```bash
# Switch to production branch
git checkout production

# Merge main
git merge main

# Push to GitHub
git push origin production
```

#### 3. Deploy to Staging (Test Deployment)

```bash
cd /datadisk0/projects/ghbot-fullstack-staging

# Pull latest production branch
git pull origin production

# Rebuild and restart
docker compose down
docker compose up -d --build

# Test the deployment
# Check logs: docker compose logs -f
```

#### 4. Deploy to Production (After Staging Success)

```bash
cd /datadisk0/projects/ghbot-fullstack-production

# Pull latest production branch
git pull origin production

# Rebuild and restart
docker compose down
docker compose up -d --build

# Monitor
docker compose logs -f
```

### Important Git Rules

✅ **DO:**
- Always work on `main` branch for development
- Merge `main` → `production` for deployments
- Test in staging before deploying to production
- Keep `.env` files out of git (they're in `.gitignore`)

❌ **DON'T:**
- Don't commit `.env` files
- Don't work directly on `production` branch
- Don't skip staging deployment testing
- Don't push without pulling first

---

## Environment Setup

### Environment Files

Each environment has its own template:

| File | Used By | Purpose |
|------|---------|---------|
| `.env.example.main` | Development | Local development without Traefik |
| `.env.example.staging` | Staging | Test deployment with Traefik |
| `.env.example.production` | Production | Live deployment with Traefik |

### Setting Up a New Environment

#### Development Setup

```bash
cd /datadisk0/projects/ghbot-fullstack-dev

# Copy template
cp .env.example.main .env

# Generate secrets
echo "DB_PASSWORD=$(openssl rand -base64 32)"
echo "JWT_SECRET=$(openssl rand -base64 32)"
echo "SESSION_SECRET=$(openssl rand -base64 32)"
echo "GITHUB_WEBHOOK_SECRET=$(openssl rand -hex 32)"

# Edit .env and replace placeholders with generated secrets
nano .env

# Create Docker volumes
docker volume create webmeteor_dev_postgres_data
docker volume create webmeteor_dev_api_logs

# Start services
docker compose up -d
```

#### Staging Setup

```bash
cd /datadisk0/projects/ghbot-fullstack-staging

# Copy template
cp .env.example.staging .env

# Generate unique secrets (different from dev!)
echo "DB_PASSWORD=$(openssl rand -base64 32)"
echo "JWT_SECRET=$(openssl rand -base64 32)"
echo "SESSION_SECRET=$(openssl rand -base64 32)"
echo "GITHUB_WEBHOOK_SECRET=$(openssl rand -hex 32)"

# Edit .env
nano .env

# Create Docker volumes (already done, but command for reference)
# These were created in the initial setup:
# docker volume create webmeteor_staging_postgres_data
# docker volume create webmeteor_staging_api_logs

# Start services
docker compose up -d
```

#### Production Setup

```bash
cd /datadisk0/projects/ghbot-fullstack-production

# Copy template
cp .env.example.production .env

# Generate unique secrets (different from staging!)
echo "DB_PASSWORD=$(openssl rand -base64 32)"
echo "JWT_SECRET=$(openssl rand -base64 32)"
echo "SESSION_SECRET=$(openssl rand -base64 32)"
echo "GITHUB_WEBHOOK_SECRET=$(openssl rand -hex 32)"

# Edit .env
nano .env

# Create Docker volumes (already done)
# docker volume create webmeteor_production_postgres_data
# docker volume create webmeteor_production_api_logs

# Start services
docker compose up -d
```

### Environment Variables Reference

| Variable | Development | Staging | Production |
|----------|-------------|---------|------------|
| `APP_ENV` | `development` | `staging` | `production` |
| `DB_NAME` | `webmeteor_dev` | `webmeteor_staging` | `webmeteor_production` |
| `DB_USER` | `webmeteor_user` | `webmeteor_staging_user` | `webmeteor_prod_user` |
| `WWW_PORT` | `4400` | `4400` | `5400` |
| `ALERT_PORT` | `4401` | `4401` | `5401` |
| `API_PORT` | `4402` | `4402` | `5402` |
| `WWW_URL` | `http://localhost:4400` | `https://staging.webmeteor.in` | `https://www.webmeteor.in` |
| `API_URL` | `http://localhost:4402` | `https://staging-api.webmeteor.in` | `https://api.webmeteor.in` |
| `LOG_LEVEL` | `debug` | `info` | `warning` |

---

## Development Workflow

### Starting Development

```bash
cd /datadisk0/projects/ghbot-fullstack-dev

# Make sure you're on main branch
git checkout main

# Pull latest changes
git pull origin main

# Start Docker containers
docker compose up -d

# View logs
docker compose logs -f
```

### Making Changes

1. **Choose what to work on**
   - Check existing issues
   - Coordinate with team to avoid conflicts

2. **Make your changes**
   - Frontend: `www/src/app/`
   - Backend: `api/src/App/`
   - Database migrations: `api/database/migrations/`

3. **Test locally**
   ```bash
   # Run unit tests
   cd api && vendor/bin/phpunit
   cd www && npm test

   # Run E2E tests
   docker compose -f docker-compose.testing.yaml up -d
   docker compose -f docker-compose.testing.yaml run --rm e2e
   ```

4. **Commit changes**
   ```bash
   git add .
   git commit -m "Brief description of changes"
   ```

5. **Push to GitHub**
   ```bash
   git push origin main
   ```

### Code Organization

```
├── www/                    # Angular Frontend
│   ├── src/app/
│   │   ├── pages/         # Page components
│   │   ├── components/    # Reusable components
│   │   └── services/      # Angular services
│   └── e2e/               # E2E tests
├── api/                   # PHP Backend
│   ├── src/App/
│   │   ├── Routes/       # API endpoints
│   │   ├── DTO/          # Data transfer objects
│   │   └── Models/       # Database models
│   └── database/
│       └── migrations/   # SQL migrations
└── alert/                # Socket.IO service
    └── server.js
```

---

## Deployment Process

### Pre-Deployment Checklist

Before deploying to staging:

- [ ] All tests passing locally
- [ ] Code reviewed (if applicable)
- [ ] Database migrations tested
- [ ] No breaking changes to API contracts
- [ ] `.env` secrets are different per environment

### Staging Deployment

```bash
# 1. Merge to production branch
cd /datadisk0/projects/ghbot-fullstack-dev
git checkout production
git merge main
git push origin production

# 2. Deploy to staging folder
cd /datadisk0/projects/ghbot-fullstack-staging
git pull origin production

# 3. Rebuild if needed
docker compose down
docker compose up -d --build

# 4. Check logs
docker compose logs -f

# 5. Test functionality
# - Visit https://staging.webmeteor.in
# - Test critical features
# - Check for errors in logs
```

### Production Deployment

**⚠️ Only after successful staging testing!**

```bash
# 1. Deploy to production folder
cd /datadisk0/projects/ghbot-fullstack-production
git pull origin production

# 2. Rebuild
docker compose down
docker compose up -d --build

# 3. Monitor closely
docker compose logs -f

# 4. Verify
# - Visit https://www.webmeteor.in
# - Test critical paths
# - Monitor error rates
```

### Rollback Procedure

If something goes wrong:

```bash
cd /datadisk0/projects/ghbot-fullstack-production

# Find previous working commit
git log --oneline -10

# Checkout previous version
git checkout <commit-hash>

# Restart containers
docker compose down
docker compose up -d

# Or revert to previous branch state
git reset --hard origin/production~1
docker compose down
docker compose up -d
```

---

## Docker Management

### Docker Volumes (Data Persistence)

All data is stored on Azure persistent disk `/datadisk0`:

```
/datadisk0/docker-data/volumes/
├── webmeteor_dev_postgres_data/         # Dev database
├── webmeteor_staging_postgres_data/     # Staging database
└── webmeteor_production_postgres_data/  # Production database
```

### Important Docker Commands

#### View Running Containers

```bash
docker compose ps
docker ps
```

#### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f www
docker compose logs -f api
docker compose logs -f db
```

#### Restart Services

```bash
# Restart all
docker compose restart

# Restart specific service
docker compose restart api
```

#### Rebuild After Code Changes

```bash
# Rebuild and restart
docker compose up -d --build

# Rebuild specific service
docker compose up -d --build api
```

#### Stop Everything

```bash
# Stop containers (keeps volumes)
docker compose down

# ⚠️ DANGER: Stop and remove volumes
# docker compose down -v  # DON'T USE THIS IN PRODUCTION!
```

#### Check Disk Usage

```bash
# Docker disk usage
docker system df

# Volume sizes
docker volume ls
sudo du -sh /datadisk0/docker-data/volumes/webmeteor_*
```

### Docker Volume Safety

**✅ SAFE Commands:**
```bash
docker compose down              # Stops containers, keeps volumes
docker compose restart           # Restarts containers
docker volume ls                 # Lists volumes
docker volume inspect <name>     # Inspects volume
```

**⚠️ DANGEROUS Commands:**
```bash
docker compose down -v           # DELETES volumes!
docker volume rm <name>          # Deletes specific volume
docker volume prune              # Deletes unused volumes
```

**External Volumes:**
All production/staging/dev databases use **external volumes** which means:
- `docker compose down -v` will NOT delete them
- Must be explicitly deleted with `docker volume rm`
- Survive container recreation
- Persist across deployments

---

## Database Safety

### Backup Procedures

#### Manual Backup

**Production:**
```bash
mkdir -p /datadisk0/backups/production

docker exec webmeteor-production-db pg_dump \
  -U webmeteor_prod_user \
  -d webmeteor_production \
  -F c \
  -f /tmp/backup.dump

docker cp webmeteor-production-db:/tmp/backup.dump \
  /datadisk0/backups/production/backup_$(date +%Y%m%d_%H%M%S).dump
```

**Staging:**
```bash
mkdir -p /datadisk0/backups/staging

docker exec webmeteor-staging-db pg_dump \
  -U webmeteor_staging_user \
  -d webmeteor_staging \
  -F c \
  -f /tmp/backup.dump

docker cp webmeteor-staging-db:/tmp/backup.dump \
  /datadisk0/backups/staging/backup_$(date +%Y%m%d_%H%M%S).dump
```

#### Restore from Backup

```bash
# Copy backup into container
docker cp /datadisk0/backups/production/backup.dump \
  webmeteor-production-db:/tmp/

# Restore
docker exec webmeteor-production-db pg_restore \
  -U webmeteor_prod_user \
  -d webmeteor_production \
  -c \
  /tmp/backup.dump
```

### Database Migrations

Migrations are automatically run on container startup from:
```
api/database/migrations/
├── 001_create_users_table.sql
├── 001_create_websites_table.sql
└── 002_add_content_to_websites.sql
```

**Adding New Migration:**

1. Create file with next number: `003_your_migration.sql`
2. Write SQL:
   ```sql
   -- Add your DDL here
   CREATE TABLE IF NOT EXISTS ...;
   ALTER TABLE ...;
   ```
3. Test in dev:
   ```bash
   docker compose down
   docker compose up -d
   docker compose logs db
   ```
4. Commit and deploy through normal workflow

---

## Testing

### Unit Tests

**Backend (PHP):**
```bash
cd api
vendor/bin/phpunit
```

**Frontend (Angular):**
```bash
cd www
npm test
```

### E2E Tests (Playwright)

```bash
# Start test environment
docker compose -f docker-compose.testing.yaml up -d db-test api-test www-test

# Run tests
docker compose -f docker-compose.testing.yaml run --rm e2e

# View report
docker compose -f docker-compose.testing.yaml run --rm e2e npm run e2e:report

# Cleanup
docker compose -f docker-compose.testing.yaml down
```

See [E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md) for detailed testing documentation.

---

## Troubleshooting

### Common Issues

#### Container Won't Start

```bash
# Check logs
docker compose logs <service-name>

# Common fixes:
# 1. Port already in use
docker ps -a | grep <port>
docker stop <conflicting-container>

# 2. Volume permission issues
docker compose down
docker volume rm <volume-name>
docker compose up -d
```

#### Database Connection Failed

```bash
# Check database is running
docker compose ps db

# Check database logs
docker compose logs db

# Verify credentials in .env
cat .env | grep DB_

# Test connection
docker exec -it webmeteor-<env>-db psql -U <user> -d <database>
```

#### Cannot Access Website

**Development (localhost):**
- Check containers running: `docker compose ps`
- Check port not blocked: `curl http://localhost:4400`
- Check logs: `docker compose logs www`

**Staging/Production (domains):**
- Check Traefik routing: `docker logs traefik`
- Check DNS: `nslookup staging.webmeteor.in`
- Check SSL certificate: `curl -I https://staging.webmeteor.in`

#### Git Merge Conflicts

```bash
# If conflict on docker-compose.yaml (shouldn't happen now)
git checkout --ours docker-compose.yaml
git add docker-compose.yaml

# For other conflicts, resolve manually
git status
# Edit conflicting files
git add <resolved-files>
git commit
```

#### Out of Disk Space

```bash
# Check disk usage
df -h /datadisk0

# Clean Docker
docker system prune -a
docker volume prune  # ⚠️ Careful! Check volumes first

# Clean old images
docker images
docker rmi <old-image-id>
```

### Getting Help

1. **Check logs first:**
   ```bash
   docker compose logs -f
   ```

2. **Check service health:**
   ```bash
   docker compose ps
   docker ps
   ```

3. **Check documentation:**
   - This guide
   - [E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md)
   - [DATABASE_SAFETY_GUIDE.md](../DATABASE_SAFETY_GUIDE.md)
   - [DOCKER_COMPOSE_UNIFIED.md](../DOCKER_COMPOSE_UNIFIED.md)

4. **Check GitHub Issues:**
   - Review open issues
   - Search for similar problems

---

## Quick Reference

### Daily Development

```bash
# Start working
cd /datadisk0/projects/ghbot-fullstack-dev
git pull origin main
docker compose up -d

# After changes
git add .
git commit -m "description"
git push origin main
```

### Deploy to Staging

```bash
# In dev folder
git checkout production
git merge main
git push origin production

# In staging folder
cd /datadisk0/projects/ghbot-fullstack-staging
git pull origin production
docker compose up -d --build
```

### Deploy to Production

```bash
cd /datadisk0/projects/ghbot-fullstack-production
git pull origin production
docker compose up -d --build
docker compose logs -f
```

### Emergency Commands

```bash
# Stop everything
docker compose down

# Restart service
docker compose restart <service>

# View logs
docker compose logs -f <service>

# Rollback
git reset --hard <previous-commit>
docker compose up -d
```

---

## Additional Resources

- [DATABASE_SAFETY_GUIDE.md](../DATABASE_SAFETY_GUIDE.md) - Backup and restore procedures
- [VOLUME_MANAGEMENT.md](../VOLUME_MANAGEMENT.md) - Docker volume management
- [DOCKER_COMPOSE_UNIFIED.md](../DOCKER_COMPOSE_UNIFIED.md) - Unified docker-compose strategy
- [SECURITY_GUIDE.md](../SECURITY_GUIDE.md) - Environment variables and secrets
- [E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md) - End-to-end testing with Playwright
