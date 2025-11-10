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

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

MIT License - See LICENSE file for details

## Support

For issues and questions, please open a GitHub issue.
