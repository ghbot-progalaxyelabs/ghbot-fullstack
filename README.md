# WebMeteor Fullstack

A complete Docker-based website builder platform with StoneScriptPHP backend, Angular frontend, and Socket.IO notifications.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                    WebMeteor                        │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌────────┐│
│  │   WWW   │  │   API   │  │ Alert  │  │   DB   ││
│  │ Angular │◄─┤  PHP    │  │Socket.IO│  │Postgres││
│  │  :80    │  │  :8080  │  │  :3001 │  │ :5432  ││
│  └─────────┘  └─────────┘  └─────────┘  └────────┘│
│                                                     │
└─────────────────────────────────────────────────────┘
```

## Services

### 1. **www** (Frontend)
- **Tech**: Angular 19
- **Port**: 80
- **Purpose**: Website builder UI
- **Supports**: Portfolio, Business, E-commerce, Blog sites

### 2. **api** (Backend)
- **Tech**: StoneScriptPHP (PHP 8.3)
- **Port**: 8080
- **Purpose**: RESTful API with type-safe routes
- **Features**: Auto-generated TypeScript client, DTOs, Interfaces

### 3. **alert** (Notifications)
- **Tech**: Node.js + Socket.IO
- **Port**: 3001
- **Purpose**: Real-time user notifications

### 4. **db** (Database)
- **Tech**: PostgreSQL 16
- **Port**: 5432
- **Purpose**: Data persistence

## Quick Start

### Prerequisites
- Docker & Docker Compose
- Git
- PHP 8.3+ (for generating API client locally)

### Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd ghbot-fullstack

# 2. Configure environment
# Copy root .env for docker-compose
cp .env.example .env
# Edit .env with your database credentials and settings

# Generate API .env file
cd api
php generate env
# Edit api/.env and set DATABASE_USER, DATABASE_PASSWORD, DATABASE_DBNAME
cd ..

# 3. Build containers in correct order
./build.sh

# 4. Start all services
docker compose up

# 5. Check service health
docker compose ps

# 6. View logs
docker compose logs -f
```

### Build Order

The build script (`build.sh`) ensures containers are built in the correct order:

1. **Generate API Client** - Creates TypeScript client from PHP routes
2. **Pull Database Image** - PostgreSQL 16
3. **Build API Container** - PHP backend with StoneScriptPHP
4. **Build Alert Service** - Socket.IO notification server
5. **Build WWW Container** - Angular frontend (requires api-client from step 1)

**Why build order matters:**
- The `www` container needs the `api-client` package during build
- The `api-client` is generated from the PHP backend routes
- Building in order ensures all dependencies are available

### Accessing Services

- **Frontend**: http://localhost:4400
- **API**: http://localhost:4402
- **Alert Service**: http://localhost:4401
- **Database**: Internal network only (not exposed)

## Development

### Backend API Development

```bash
# Generate a new route
cd api
php generate route post /users

# Edit DTOs
# api/src/App/DTO/UsersRequest.php
# api/src/App/DTO/UsersResponse.php

# Generate TypeScript client (IMPORTANT: Do this after any route/DTO changes!)
php generate client --output=../www/api-client

# Rebuild frontend container to use updated client
cd ..
docker compose build www
docker compose restart www
```

**When to regenerate the API client:**
- After adding/modifying/removing routes
- After changing Request/Response DTO properties
- After changing DTO types or making fields optional/required

The TypeScript client provides full type safety between frontend and backend.

### Frontend Development

```bash
cd www

# Install dependencies
npm install

# Start dev server
npm start

# Build for production
npm run build
```

### Alert Service Development

```bash
cd alert

# Install dependencies
npm install

# Start dev server
npm run dev
```

## Database Migrations

```bash
# Run migrations
docker-compose exec api php generate migrate up

# Check status
docker-compose exec api php generate migrate status
```

## Testing

```bash
# Run PHP tests
docker-compose exec api vendor/bin/phpunit

# Run Angular tests
docker-compose exec www npm test
```

## Production Deployment

### VM Deployment (Recommended)

For production deployment on Azure VM with Traefik gateway and auto-deployment:

📚 **See comprehensive VM setup documentation**: [docs/vm-setup/](./docs/vm-setup/)

**Key Features:**
- Apache + Traefik hybrid architecture for zero-downtime deployments
- Automatic SSL certificates via Let's Encrypt
- GitHub webhook-triggered deployments
- Staging and Production environments
- Dynamic customer site routing with wildcard domains
- Customer site isolation with dedicated containers and databases

**Quick Links:**
- [VM Setup Guide](./docs/vm-setup/VM_SETUP.md) - Complete setup instructions
- [Architecture Overview](./docs/vm-setup/ARCHITECTURE.md) - System architecture details
- [Customer Sites Implementation](./docs/vm-setup/CUSTOMER_SITES.md) - Customer site management
- [Implementation Checklist](./docs/vm-setup/TODO.md) - Step-by-step tasks

**Branch Strategy:**
- `main` → Development/features
- `staging` → Testing environment (auto-deploys to staging VM)
- `prod` → Production environment (auto-deploys to production VM)

### Manual Production Deployment

For manual deployment without VM setup:

1. Update `.env` with production values
2. Generate secure keys for `JWT_SECRET` and `SESSION_SECRET`
3. Set `APP_ENV=production`
4. Use proper HTTPS configuration
5. Configure proper CORS origins

```bash
# Build for production
docker-compose -f docker-compose.yaml -f docker-compose.prod.yaml up -d
```

## Website Types Supported

1. **Portfolio** - Showcase work, skills, availability
2. **Business** - Company websites with services/products
3. **E-commerce** - Online stores with product catalog
4. **Blog** - Content publishing platform

## Project Structure

```
ghbot-fullstack/
├── docker-compose.yaml          # Service orchestration
├── .env                          # Environment configuration
├── api/                          # StoneScriptPHP backend
│   ├── Dockerfile
│   ├── Framework/               # Core framework
│   ├── src/App/                 # Application code
│   │   ├── Routes/              # Route handlers
│   │   ├── Contracts/           # Interface contracts
│   │   └── DTO/                 # Data transfer objects
│   ├── generate                 # CLI tool
│   └── docker/                  # Docker configs
├── www/                          # Angular frontend
│   ├── Dockerfile
│   ├── src/app/
│   │   ├── pages/
│   │   │   ├── editor/          # Website editor
│   │   │   └── website-wizard/  # Website type selector
│   │   └── services/
│   └── docker/
├── alert/                        # Socket.IO service
│   ├── Dockerfile
│   ├── server.js
│   └── package.json
└── README.md                     # This file
```

## Contributing

### Parallel Development Workflow

When multiple developers work in parallel, follow our coordination workflow to avoid conflicts:

📚 **Quick Start**: [PARALLEL_DEV_QUICKSTART.md](./PARALLEL_DEV_QUICKSTART.md)
📖 **Full Guide**: [DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md)

**Key Tools:**
```bash
# Check available work
./scripts/assign-issue.sh list

# Lock files before working
./scripts/dev-workflow.sh lock --developer "Your Name" --issue "Issue #12" --files "file1,file2"

# Check lock status
./scripts/dev-workflow.sh status

# Unlock when done
./scripts/dev-workflow.sh unlock --lock-id <lock-id>
```

### Standard Contribution Process

1. Check [issues.md](./issues.md) for available work
2. Assign yourself using `./scripts/assign-issue.sh assign --issue <num> --developer "Your Name"`
3. Create a feature branch: `git checkout -b feature/issue-<num>-<description>`
4. Lock your files using `./scripts/dev-workflow.sh lock`
5. Make your changes
6. Commit and push
7. Create a Pull Request
8. Release locks using `./scripts/dev-workflow.sh unlock`

## License

MIT License - See LICENSE file for details

## Support

For issues and questions, please open a GitHub issue.
