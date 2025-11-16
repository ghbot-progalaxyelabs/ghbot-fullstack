# WebMeteor - Full-Stack Website Builder

A complete Docker-based website builder platform enabling users to create professional websites without coding.

## 🏗️ Architecture

```
┌──────────────────────────────────────────────┐
│              WebMeteor Platform              │
├──────────────────────────────────────────────┤
│                                              │
│  ┌──────────┐  ┌──────────┐  ┌───────────┐ │
│  │ Angular  │  │ StoneScript│ │PostgreSQL │ │
│  │ Frontend │◄─┤  PHP API  │◄┤ Database  │ │
│  │   www    │  │    api    │  │    db     │ │
│  └──────────┘  └─────┬─────┘  └───────────┘ │
│                      │                       │
│                      ▼                       │
│              ┌──────────────┐               │
│              │  Socket.IO   │               │
│              │    Alert     │               │
│              └──────────────┘               │
│                                              │
└──────────────────────────────────────────────┘
```

## 📦 Services

| Service | Technology | Port | Purpose |
|---------|-----------|------|---------|
| **www** | Angular 19 | 4200 | Website builder UI |
| **api** | PHP 8.3 + StoneScriptPHP | 80 | Type-safe REST API |
| **alert** | Node.js + Socket.IO | 3001 | Real-time notifications |
| **db** | PostgreSQL 16 | 5432 | Data persistence |

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git
- OpenSSL (for generating secrets)

### Local Development Setup

```bash
# 1. Clone repository
git clone git@github.com:ghbot-progalaxyelabs/ghbot-fullstack.git
cd ghbot-fullstack

# 2. Checkout main branch (development)
git checkout main

# 3. Copy environment template
cp .env.example.main .env

# 4. Generate secure secrets
echo "DB_PASSWORD=$(openssl rand -base64 32)"
echo "JWT_SECRET=$(openssl rand -base64 32)"
echo "SESSION_SECRET=$(openssl rand -base64 32)"
echo "GITHUB_WEBHOOK_SECRET=$(openssl rand -hex 32)"

# 5. Edit .env and replace GENERATE_WITH_* placeholders
nano .env

# 6. Create Docker volumes
docker volume create webmeteor_dev_postgres_data
docker volume create webmeteor_dev_api_logs

# 7. Build and start services
docker compose up -d

# 8. View logs
docker compose logs -f
```

### Access the Application

- **Frontend**: http://localhost:4400
- **API**: http://localhost:4402
- **API Docs**: http://localhost:4402/docs (if available)

## 📚 Documentation

### Primary Guide

**[COMPLETE_WORKFLOW_GUIDE.md](COMPLETE_WORKFLOW_GUIDE.md)** - Everything you need to know:
- Development workflow
- Git branching strategy
- Deployment process
- Docker management
- Database operations
- Testing procedures
- Troubleshooting

### Additional Documentation

- **[E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md)** - End-to-end testing with Playwright
- **[TEST_COVERAGE_REPORT.md](TEST_COVERAGE_REPORT.md)** - Test coverage analysis

### Server Documentation (in `/datadisk0/projects/`)

- **[DATABASE_SAFETY_GUIDE.md](../DATABASE_SAFETY_GUIDE.md)** - Backup and restore procedures
- **[VOLUME_MANAGEMENT.md](../VOLUME_MANAGEMENT.md)** - Docker volume management
- **[DOCKER_COMPOSE_UNIFIED.md](../DOCKER_COMPOSE_UNIFIED.md)** - Unified docker-compose strategy
- **[SECURITY_GUIDE.md](../SECURITY_GUIDE.md)** - Environment variables and secrets

## 🔄 Development Workflow

### Daily Development

```bash
# Start development
cd /datadisk0/projects/ghbot-fullstack-dev
git pull origin main
docker compose up -d

# Make changes to code...

# Commit and push
git add .
git commit -m "Your changes"
git push origin main
```

### Deployment Flow

```
main branch (dev) → production branch → staging folder (test) → production folder (live)
```

See [COMPLETE_WORKFLOW_GUIDE.md](COMPLETE_WORKFLOW_GUIDE.md) for detailed deployment instructions.

## 🏷️ Environment Templates

| Template | Usage | Traefik | Ports |
|----------|-------|---------|-------|
| `.env.example.main` | Local development | No | 4400-4402 |
| `.env.example.staging` | Staging deployment | Yes | 4400-4402 |
| `.env.example.production` | Production deployment | Yes | 5400-5402 |

## 🧪 Testing

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

### E2E Tests

```bash
docker compose -f docker-compose.testing.yaml up -d db-test api-test www-test
docker compose -f docker-compose.testing.yaml run --rm e2e
docker compose -f docker-compose.testing.yaml down
```

See [E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md) for details.

## 🗂️ Project Structure

```
├── www/                          # Angular Frontend
│   ├── src/app/
│   │   ├── pages/               # Page components
│   │   ├── components/          # Reusable components
│   │   └── services/            # Angular services
│   └── e2e/                     # Playwright E2E tests
├── api/                         # PHP Backend
│   ├── src/App/
│   │   ├── Routes/             # API endpoints
│   │   ├── DTO/                # Data transfer objects
│   │   └── Models/             # Database models
│   └── database/migrations/    # SQL migrations
├── alert/                       # Socket.IO Service
│   └── server.js
├── docker-compose.yaml          # Main compose file
├── docker-compose.testing.yaml  # Testing compose file
└── .env                        # Environment config (not in git)
```

## 🛠️ Common Commands

### Docker Management

```bash
# View running containers
docker compose ps

# View logs
docker compose logs -f [service]

# Restart service
docker compose restart [service]

# Rebuild after code changes
docker compose up -d --build

# Stop all services
docker compose down
```

### Database Operations

```bash
# Backup database
docker exec webmeteor-dev-db pg_dump -U webmeteor_user -d webmeteor_dev -F c -f /tmp/backup.dump
docker cp webmeteor-dev-db:/tmp/backup.dump ./backup.dump

# Restore database
docker cp ./backup.dump webmeteor-dev-db:/tmp/
docker exec webmeteor-dev-db pg_restore -U webmeteor_user -d webmeteor_dev -c /tmp/backup.dump

# Access PostgreSQL shell
docker exec -it webmeteor-dev-db psql -U webmeteor_user -d webmeteor_dev
```

## 🔐 Security

- ✅ All `.env` files are excluded from git via `.gitignore`
- ✅ Secrets are generated using `openssl rand`
- ✅ Different credentials for dev, staging, and production
- ✅ JWT-based authentication
- ✅ CORS properly configured per environment

**Never commit:**
- `.env` files
- Database credentials
- JWT secrets
- API keys

## 🌍 Deployment Environments

### Development
- **Location**: `/datadisk0/projects/ghbot-fullstack-dev`
- **Branch**: `main`
- **URL**: http://localhost:4400
- **Purpose**: Local development

### Staging
- **Location**: `/datadisk0/projects/ghbot-fullstack-staging`
- **Branch**: `production`
- **URL**: https://staging.webmeteor.in
- **Purpose**: Test deployment before production

### Production
- **Location**: `/datadisk0/projects/ghbot-fullstack-production`
- **Branch**: `production`
- **URL**: https://www.webmeteor.in
- **Purpose**: Live deployment

## 📊 Features

### Website Types Supported
- 📱 Portfolio websites
- 💼 Business websites
- 🛒 E-commerce sites
- 📝 Blog platforms

### Key Features
- ✨ Drag-and-drop website editor
- 🎨 Multiple professional templates
- 🔄 Real-time content updates
- 💾 Auto-save functionality
- 🔒 User authentication (JWT)
- 📱 Responsive design
- 🖼️ Image library (Unsplash integration)

## 🤝 Contributing

1. Work on the `main` branch for all development
2. Follow the git workflow documented in [COMPLETE_WORKFLOW_GUIDE.md](COMPLETE_WORKFLOW_GUIDE.md)
3. Test locally before pushing
4. Deployment happens through staging → production

## 📞 Support

- **Documentation**: See [COMPLETE_WORKFLOW_GUIDE.md](COMPLETE_WORKFLOW_GUIDE.md)
- **Issues**: Check existing issues or create a new one
- **Testing**: See [E2E_TESTING_GUIDE.md](E2E_TESTING_GUIDE.md)

## 📝 License

[Your License Here]

---

## 🎯 Quick Reference

### First Time Setup
```bash
cp .env.example.main .env
# Generate and add secrets to .env
docker volume create webmeteor_dev_postgres_data
docker compose up -d
```

### Daily Development
```bash
git pull origin main
docker compose up -d
# ... make changes ...
git commit -am "description" && git push origin main
```

### Need Help?
Read [COMPLETE_WORKFLOW_GUIDE.md](COMPLETE_WORKFLOW_GUIDE.md) - it has everything!
