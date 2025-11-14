# Environment Configuration Guide

## 📋 Overview

This project uses a **unified environment configuration** system where the **root `.env` file is the single source of truth**.

## 🎯 Architecture

```
Root .env (SOURCE OF TRUTH)
    ↓
    ├─→ Docker Compose (reads DB_*, JWT_SECRET, etc.)
    ├─→ API Container (Framework/Env.php maps DB_* → DATABASE_*)
    ├─→ WWW Container (reads environment variables)
    └─→ Alert Container (reads environment variables)
```

### Key Features

- **Single source of truth**: Only edit root `.env`
- **Automatic mapping**: API reads root `.env` and maps `DB_*` → `DATABASE_*`
- **Backward compatible**: Can still use `api/.env` for local development
- **Docker-first**: Optimized for containerized environments

## 🔧 Setup

### 1. Create Root `.env` File

```bash
# Copy the example
cp .env.example .env

# Edit with your values
nano .env
```

### 2. Required Variables

**Database** (used by Docker Compose and API):
```bash
DB_HOST=db                    # Docker service name or localhost
DB_PORT=5432                  # PostgreSQL port
DB_NAME=webmeteor             # Database name
DB_USER=webmeteor_user        # Database username
DB_PASSWORD=your_secure_pass  # Database password (CHANGE THIS!)
```

**Security** (used by API):
```bash
JWT_SECRET=your_jwt_secret_key_here    # For authentication tokens (CHANGE THIS!)
SESSION_SECRET=your_session_secret_key # For sessions (CHANGE THIS!)
```

**Google OAuth** (used by API and Frontend):
```bash
GOOGLE_CLIENT_ID=your_google_client_id_here
```

**Ports** (used by Docker Compose):
```bash
API_PORT=4402        # API service port
WWW_PORT=4400        # Frontend service port
ALERT_PORT=4401      # WebSocket service port
```

### 3. Generate Secure Secrets

```bash
# Generate JWT secret
openssl rand -base64 32

# Generate session secret
openssl rand -base64 32
```

## 📂 Environment Variable Mapping

The API's `Framework/Env.php` automatically maps Docker variables:

| Root `.env` (Docker) | API Internal Name | Description |
|---------------------|-------------------|-------------|
| `DB_HOST` | `DATABASE_HOST` | Database host |
| `DB_PORT` | `DATABASE_PORT` | Database port |
| `DB_USER` | `DATABASE_USER` | Database username |
| `DB_PASSWORD` | `DATABASE_PASSWORD` | Database password |
| `DB_NAME` | `DATABASE_DBNAME` | Database name |
| `JWT_SECRET` | `JWT_SECRET` | JWT signing key |
| `GOOGLE_CLIENT_ID` | `GOOGLE_CLIENT_ID` | OAuth client ID |

## 🐳 Docker Environment

### How It Works

1. **Docker Compose** reads root `.env`:
   ```yaml
   environment:
     DB_HOST: db
     DB_NAME: ${DB_NAME}
     DB_USER: ${DB_USER}
     DB_PASSWORD: ${DB_PASSWORD}
   ```

2. **API Container** receives these variables and `Env.php` maps them:
   ```php
   // Automatic mapping in Framework/Env.php
   $env['DATABASE_HOST'] = $env['DB_HOST'];
   $env['DATABASE_USER'] = $env['DB_USER'];
   // etc...
   ```

3. **Application code** uses the mapped values:
   ```php
   $env = Env::get_instance();
   $dbHost = $env->DATABASE_HOST;  // Works!
   ```

## 💻 Local Development (Without Docker)

If running locally without Docker:

### Option 1: Use Root `.env` (Recommended)

Just create root `.env` as described above. The API will find and use it automatically.

### Option 2: Use `api/.env` (Legacy)

Create `api/.env` with `DATABASE_*` variables:

```bash
# In api/.env
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_USER=webmeteor_user
DATABASE_PASSWORD=your_password
DATABASE_DBNAME=webmeteor
JWT_SECRET=your_jwt_secret
GOOGLE_CLIENT_ID=your_client_id
```

**Note**: Root `.env` takes precedence if both exist.

## 🔍 Variable Lookup Order

`Framework/Env.php` searches in this order:

1. **Root `.env`** (parent directory) - Used in Docker
2. **`api/.env`** (API directory) - Used in local development
3. **Error** if neither exists

## 📝 Full Variable Reference

### Database
```bash
DB_HOST=db
DB_PORT=5432
DB_NAME=webmeteor
DB_USER=webmeteor_user
DB_PASSWORD=secure_password_here
```

### Application
```bash
APP_ENV=development         # development|staging|production
APP_NAME=WebMeteor
```

### Services
```bash
API_PORT=4402
API_URL=http://localhost:4402

WWW_PORT=4400
WWW_URL=http://localhost:4400

ALERT_PORT=4401
ALERT_URL=http://localhost:4401
```

### Security
```bash
JWT_SECRET=generated_secret_here
SESSION_SECRET=generated_secret_here
GOOGLE_CLIENT_ID=your_google_oauth_client_id
```

### CORS
```bash
CORS_ORIGIN=http://localhost:4400
```

### Email (Optional)
```bash
# ZeptoMail configuration
ZEPTOMAIL_BOUNCE_ADDRESS=
ZEPTOMAIL_SENDER_EMAIL=
ZEPTOMAIL_SENDER_NAME=
ZEPTOMAIL_SEND_MAIL_TOKEN=
```

### Deployment (Optional)
```bash
GITHUB_WEBHOOK_SECRET=your_webhook_secret_here
```

### Storage
```bash
UPLOAD_MAX_SIZE=10M
STORAGE_PATH=/var/www/html/storage
```

### Logging
```bash
LOG_LEVEL=info    # debug|info|warning|error
```

## 🔐 Production Security

### Generate Secure Secrets

```bash
# JWT Secret (32 bytes, base64 encoded)
openssl rand -base64 32

# Session Secret
openssl rand -base64 32

# GitHub Webhook Secret (if needed)
openssl rand -hex 32
```

### Protect `.env` Files

```bash
# Ensure .env is in .gitignore
echo ".env" >> .gitignore

# Set proper permissions (read/write for owner only)
chmod 600 .env
```

### Environment-Specific Configurations

**Development** (`.env`):
```bash
APP_ENV=development
LOG_LEVEL=debug
CORS_ORIGIN=http://localhost:4400
```

**Staging** (`.env`):
```bash
APP_ENV=staging
LOG_LEVEL=info
CORS_ORIGIN=https://staging.webmeteor.in
```

**Production** (`.env`):
```bash
APP_ENV=production
LOG_LEVEL=warning
CORS_ORIGIN=https://www.webmeteor.in
```

## 🧪 Testing Configuration

### Verify Environment is Loaded

```php
// In any PHP file
$env = \Framework\Env::get_instance();
var_dump($env->DATABASE_HOST);
var_dump($env->JWT_SECRET);
```

### Check Docker Environment

```bash
# Inside API container
docker exec -it webmeteor-api env | grep DB_

# Should show:
# DB_HOST=db
# DB_NAME=webmeteor
# etc.
```

## ❓ Troubleshooting

### "Missing .env file"

**Problem**: API can't find `.env` file

**Solution**:
```bash
# Make sure root .env exists
ls -la .env

# If not, create it
cp .env.example .env
```

### "Required settings missing: DATABASE_USER"

**Problem**: Root `.env` uses `DB_USER` but API expects `DATABASE_USER`

**Solution**: This should be automatically mapped. If not working:
1. Check `Framework/Env.php` has the `mapDockerEnvVars()` method
2. Verify root `.env` has `DB_USER=...`

### "Database connection failed"

**Problem**: Wrong database credentials

**Solution**:
```bash
# Check docker-compose is using correct .env
docker-compose config | grep DB_

# Restart containers
docker-compose down && docker-compose up -d
```

### Variables Not Loading in Docker

**Problem**: Container doesn't see environment variables

**Solution**:
```bash
# Make sure .env is in root directory (same level as docker-compose.yaml)
ls -la | grep -E "(\.env|docker-compose)"

# Rebuild containers
docker-compose down
docker-compose up -d --build
```

## 📚 Related Documentation

- [README.md](./README.md) - Project overview
- [docker-compose.yaml](./docker-compose.yaml) - Container configuration
- [api/Framework/Env.php](./api/Framework/Env.php) - Environment loader code

## 🎯 Quick Reference

| What | Where | File |
|------|-------|------|
| **Single source of truth** | Root directory | `.env` |
| **Example/template** | Root directory | `.env.example` |
| **Legacy local dev** | API directory | `api/.env` (optional) |
| **Environment loader** | API Framework | `api/Framework/Env.php` |
| **Docker configuration** | Root directory | `docker-compose.yaml` |

---

**Last Updated**: 2025-11-14
**Version**: 1.0.0
