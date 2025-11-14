# Customer Site Implementation Guide

This document describes how customer websites are created, deployed, and managed within the WebMeteor platform.

## Overview

Each customer can create multiple websites using the WebMeteor platform. Each website:
- Runs in isolated Docker containers
- Has its own database
- Has its own Git repository (GitHub/Bitbucket)
- Gets automatic SSL certificate
- Can use a subdomain (`site.webmeteor.in`) or custom domain
- Uses the same tech stack: Angular, PHP (StoneScriptPHP), Socket.IO, PostgreSQL

## Directory Structure

```
/datadisk0/sites/
└── {customer_uuid}/              # Unique customer identifier
    ├── {app_id_1}/              # First site
    │   ├── docker-compose.yaml
    │   ├── .env
    │   ├── .git/
    │   ├── api/
    │   │   ├── Dockerfile
    │   │   ├── Framework/       # Shared framework (symlink or copy)
    │   │   └── src/
    │   ├── www/
    │   │   ├── Dockerfile
    │   │   └── src/
    │   └── alert/
    │       ├── Dockerfile
    │       └── server.js
    │
    └── {app_id_2}/              # Second site by same customer
        └── ...
```

## Site Creation Workflow

### 1. User Creates Site via Platform

**User Action**: Click "Create New Site" in WebMeteor dashboard

**Platform API** (`POST /api/sites/create`):

```json
{
  "site_type": "portfolio|business|ecommerce|blog",
  "site_name": "My Awesome Site",
  "subdomain": "mysite",  // Optional: defaults to generated ID
  "custom_domain": null   // Optional: for custom domains
}
```

### 2. Backend Processing

```php
// Pseudocode for site creation
function createCustomerSite($userId, $siteData) {
    // 1. Get or create customer UUID
    $customerUuid = getCustomerUuid($userId);

    // 2. Generate unique app ID
    $appId = generateUniqueId(); // e.g., "app-abc123"

    // 3. Determine domain
    $domain = $siteData['subdomain']
        ? "{$siteData['subdomain']}.webmeteor.in"
        : "{$appId}.webmeteor.in";

    // 4. Create site directory
    $sitePath = "/datadisk0/sites/{$customerUuid}/{$appId}";
    mkdir($sitePath, 0755, true);

    // 5. Initialize from template
    copyTemplate($siteData['site_type'], $sitePath);

    // 6. Generate environment file
    generateEnvFile($sitePath, [
        'CUSTOMER_UUID' => $customerUuid,
        'APP_ID' => $appId,
        'APP_DOMAIN' => $domain,
        'DB_NAME' => "site_{$appId}",
        'DB_USER' => "user_{$appId}",
        'DB_PASSWORD' => generateSecurePassword(),
        'JWT_SECRET' => generateSecret(),
        'SESSION_SECRET' => generateSecret(),
    ]);

    // 7. Generate docker-compose.yaml
    generateDockerCompose($sitePath, $customerUuid, $appId, $domain);

    // 8. Create Git repository (GitHub/Bitbucket)
    $repoUrl = createGitRepository($customerUuid, $appId);

    // 9. Initialize Git and push initial code
    initializeGit($sitePath, $repoUrl);

    // 10. Set up webhook on Git repository
    $webhookSecret = generateSecret();
    createGitWebhook($repoUrl, $webhookSecret);

    // 11. Save webhook secret to .env
    appendToEnv($sitePath, 'REPO_WEBHOOK_SECRET', $webhookSecret);

    // 12. Start containers
    startContainers($sitePath);

    // 13. Create DNS record (if using API)
    createDnsRecord($domain, VM_PUBLIC_IP);

    // 14. Wait for Traefik to provision SSL
    waitForSsl($domain);

    // 15. Save site info to database
    saveSiteToDatabase([
        'user_id' => $userId,
        'customer_uuid' => $customerUuid,
        'app_id' => $appId,
        'domain' => $domain,
        'repo_url' => $repoUrl,
        'status' => 'active',
        'created_at' => now(),
    ]);

    return [
        'app_id' => $appId,
        'domain' => $domain,
        'url' => "https://{$domain}",
        'repo_url' => $repoUrl,
    ];
}
```

## Docker Compose Template

```yaml
# /datadisk0/sites/{customer_uuid}/{app_id}/docker-compose.yaml

services:
  db:
    image: postgres:16
    container_name: ${CUSTOMER_UUID}-${APP_ID}-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_NAME}
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/postgresql/data
    networks:
      - site-internal
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USER} -d ${DB_NAME}"]
      interval: 10s
      timeout: 5s
      retries: 5

  api:
    build:
      context: ./api
      dockerfile: Dockerfile
    container_name: ${CUSTOMER_UUID}-${APP_ID}-api
    restart: unless-stopped
    environment:
      DB_HOST: db
      DB_PORT: 5432
      DB_NAME: ${DB_NAME}
      DB_USER: ${DB_USER}
      DB_PASSWORD: ${DB_PASSWORD}
      APP_DOMAIN: ${APP_DOMAIN}
      APP_ENV: production
    depends_on:
      db:
        condition: service_healthy
    networks:
      - site-internal
      - traefik-public
    labels:
      # Enable Traefik
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"

      # API routing - handle /api/* paths
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-api.rule=Host(`${APP_DOMAIN}`) && PathPrefix(`/api`)"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-api.entrypoints=websecure"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-api.tls=true"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-api.tls.certresolver=letsencrypt"

      # Service definition
      - "traefik.http.services.${CUSTOMER_UUID}-${APP_ID}-api.loadbalancer.server.port=80"

      # Strip /api prefix before forwarding to container
      - "traefik.http.middlewares.${CUSTOMER_UUID}-${APP_ID}-api-stripprefix.stripprefix.prefixes=/api"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-api.middlewares=${CUSTOMER_UUID}-${APP_ID}-api-stripprefix"

  alert:
    build:
      context: ./alert
      dockerfile: Dockerfile
    container_name: ${CUSTOMER_UUID}-${APP_ID}-alert
    restart: unless-stopped
    environment:
      ALERT_PORT: 3001
      WWW_URL: https://${APP_DOMAIN}
      NODE_ENV: production
    networks:
      - site-internal
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"

      # Socket.IO routing
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-alert.rule=Host(`${APP_DOMAIN}`) && PathPrefix(`/socket.io`)"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-alert.entrypoints=websecure"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-alert.tls=true"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-alert.tls.certresolver=letsencrypt"
      - "traefik.http.services.${CUSTOMER_UUID}-${APP_ID}-alert.loadbalancer.server.port=3001"

  www:
    build:
      context: ./www
      dockerfile: Dockerfile
      args:
        API_URL: https://${APP_DOMAIN}/api
        ALERT_URL: https://${APP_DOMAIN}
    container_name: ${CUSTOMER_UUID}-${APP_ID}-www
    restart: unless-stopped
    environment:
      API_URL: https://${APP_DOMAIN}/api
      ALERT_URL: https://${APP_DOMAIN}
    depends_on:
      - api
      - alert
    networks:
      - traefik-public
    labels:
      - "traefik.enable=true"
      - "traefik.docker.network=traefik-public"

      # HTTPS routing
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.rule=Host(`${APP_DOMAIN}`)"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.entrypoints=websecure"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.tls=true"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.tls.certresolver=letsencrypt"
      - "traefik.http.services.${CUSTOMER_UUID}-${APP_ID}-www.loadbalancer.server.port=4200"

      # HTTP to HTTPS redirect
      - "traefik.http.middlewares.${CUSTOMER_UUID}-${APP_ID}-https-redirect.redirectscheme.scheme=https"
      - "traefik.http.middlewares.${CUSTOMER_UUID}-${APP_ID}-https-redirect.redirectscheme.permanent=true"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www-http.rule=Host(`${APP_DOMAIN}`)"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www-http.entrypoints=web"
      - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www-http.middlewares=${CUSTOMER_UUID}-${APP_ID}-https-redirect"

networks:
  traefik-public:
    external: true
  site-internal:
    name: ${CUSTOMER_UUID}-${APP_ID}-network
    driver: bridge

volumes:
  db_data:
    name: ${CUSTOMER_UUID}-${APP_ID}-db-data
    driver: local
```

## Environment File Template

```bash
# /datadisk0/sites/{customer_uuid}/{app_id}/.env

# Site Identity
CUSTOMER_UUID=cust_abc123def456
APP_ID=app_xyz789
APP_DOMAIN=mysite.webmeteor.in

# Application
APP_ENV=production
APP_NAME=My Awesome Site

# Database
DB_HOST=db
DB_PORT=5432
DB_NAME=site_app_xyz789
DB_USER=user_app_xyz789
DB_PASSWORD=<generated_secure_password_32_chars>

# Security
JWT_SECRET=<generated_secret_64_chars>
SESSION_SECRET=<generated_secret_64_chars>

# CORS
CORS_ORIGIN=https://mysite.webmeteor.in

# Storage
UPLOAD_MAX_SIZE=10M
STORAGE_PATH=/var/www/html/storage

# Logging
LOG_LEVEL=info

# Git Repository Webhook
REPO_WEBHOOK_SECRET=<generated_secret_64_chars>

# Email (Optional - shared or per-site)
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@mysite.webmeteor.in
MAIL_FROM_NAME=My Awesome Site
```

## Site Templates

### Portfolio Template

**Features**:
- About page
- Projects/Work showcase
- Skills section
- Contact form
- Resume download

**Database Schema**:
- `profiles` table
- `projects` table
- `skills` table
- `contact_messages` table

### Business Template

**Features**:
- Home page with services
- About Us
- Services/Products catalog
- Team members
- Contact information
- Testimonials

**Database Schema**:
- `company_info` table
- `services` table
- `team_members` table
- `testimonials` table
- `contact_inquiries` table

### E-commerce Template

**Features**:
- Product catalog
- Shopping cart
- Checkout process
- Order management
- Payment integration
- Inventory tracking

**Database Schema**:
- `products` table
- `categories` table
- `orders` table
- `order_items` table
- `customers` table
- `inventory` table
- `payments` table

### Blog Template

**Features**:
- Article/Post management
- Categories and tags
- Comments system
- Author profiles
- RSS feed
- Search

**Database Schema**:
- `posts` table
- `categories` table
- `tags` table
- `comments` table
- `authors` table

## Customer Deployment System

### Webhook Endpoint

**Platform API**: `/api/webhook/customer-site`

```php
// Handle customer site deployments
POST /api/webhook/customer-site

Headers:
  X-Hub-Signature-256: sha256=<hmac_signature>
  Content-Type: application/json

Body:
{
  "ref": "refs/heads/main",
  "repository": {
    "full_name": "customer/site-repo"
  },
  "after": "abc123...",
  "pusher": {
    "name": "customer"
  }
}
```

### Deployment Flow

1. **Receive Webhook**
   - Verify signature using site's `REPO_WEBHOOK_SECRET`
   - Extract repository info and commit details

2. **Identify Site**
   - Look up site by repository URL in database
   - Get `customer_uuid` and `app_id`

3. **Queue Deployment**
   - Add to customer deployment queue
   - Format: `{customer_uuid}/{app_id}/{deployment_id}`

4. **Process Queue** (cron every minute)
   - Read queue file
   - For each entry, schedule deployment

5. **Execute Deployment**
   ```bash
   cd /datadisk0/sites/{customer_uuid}/{app_id}
   git pull origin main
   docker compose build
   docker compose up -d
   ```

6. **Health Check**
   - Wait for containers to be healthy
   - Test HTTPS endpoint
   - Verify SSL certificate

7. **Notify Customer**
   - Send deployment status via Socket.IO
   - Email notification (optional)
   - Log deployment history

### Deployment Script Example

```php
#!/usr/bin/env php
<?php
// deploy-customer-site.php

$options = getopt('', ['customer-uuid:', 'app-id:', 'deployment-id:']);

$customerUuid = $options['customer-uuid'];
$appId = $options['app-id'];
$deploymentId = $options['deployment-id'];

$sitePath = "/datadisk0/sites/{$customerUuid}/{$appId}";
$logFile = "{$sitePath}/deployment.log";

$logger = new DeploymentLogger($logFile, $deploymentId);
$logger->info('Customer site deployment started');

try {
    // Change to site directory
    if (!chdir($sitePath)) {
        throw new Exception("Failed to change to site directory: {$sitePath}");
    }

    // Git pull
    $logger->info('Pulling latest code');
    exec('git pull origin main 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        throw new Exception("Git pull failed: " . implode("\n", $output));
    }

    // Build containers
    $logger->info('Building containers');
    exec('docker compose build 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        throw new Exception("Build failed: " . implode("\n", $output));
    }

    // Start containers
    $logger->info('Starting containers');
    exec('docker compose up -d 2>&1', $output, $returnCode);
    if ($returnCode !== 0) {
        throw new Exception("Container start failed: " . implode("\n", $output));
    }

    // Health check
    $logger->info('Running health checks');
    sleep(5); // Wait for containers

    $envVars = parse_ini_file('.env');
    $domain = $envVars['APP_DOMAIN'];

    $ch = curl_init("https://{$domain}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400) {
        $logger->success('Deployment completed successfully');
    } else {
        $logger->warning("Site responded with status {$httpCode}");
    }

} catch (Exception $e) {
    $logger->error('Deployment failed', ['error' => $e->getMessage()]);
    exit(1);
}

exit(0);
```

## Site Management API

### List Customer Sites

```php
GET /api/sites

Response:
[
  {
    "app_id": "app_xyz789",
    "site_name": "My Awesome Site",
    "site_type": "portfolio",
    "domain": "mysite.webmeteor.in",
    "custom_domain": null,
    "status": "active",
    "url": "https://mysite.webmeteor.in",
    "created_at": "2025-01-14T10:30:00Z",
    "last_deployed_at": "2025-01-14T15:45:00Z"
  }
]
```

### Get Site Details

```php
GET /api/sites/{app_id}

Response:
{
  "app_id": "app_xyz789",
  "site_name": "My Awesome Site",
  "site_type": "portfolio",
  "domain": "mysite.webmeteor.in",
  "custom_domain": null,
  "repo_url": "https://github.com/customer/my-site",
  "status": "active",
  "container_status": {
    "www": "running",
    "api": "running",
    "alert": "running",
    "db": "running"
  },
  "ssl_status": "valid",
  "ssl_expires_at": "2025-04-14T00:00:00Z",
  "deployment_history": [
    {
      "deployment_id": "deploy-20250114-154500",
      "status": "success",
      "commit_hash": "abc123",
      "deployed_at": "2025-01-14T15:45:00Z",
      "duration": "45s"
    }
  ]
}
```

### Update Site Settings

```php
PATCH /api/sites/{app_id}

Body:
{
  "site_name": "New Site Name",
  "custom_domain": "mybusiness.com"
}
```

### Delete Site

```php
DELETE /api/sites/{app_id}

Process:
1. Stop containers: docker compose down
2. Remove volumes: docker volume rm {customer_uuid}-{app_id}-db-data
3. Archive site directory (optional backup)
4. Remove DNS record
5. Delete from database
6. Delete Git repository (optional)
```

## Resource Management

### Per-Site Resource Limits

Add to docker-compose.yaml:

```yaml
services:
  www:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
        reservations:
          cpus: '0.25'
          memory: 256M
```

### Monitoring

Track per-site metrics:
- Container CPU/memory usage
- Request count and latency
- Database size
- Disk space usage
- Error rates

### Quotas

Implement quota system:
- Max database size (e.g., 1GB for free tier)
- Max file storage (e.g., 5GB)
- Max API requests per day
- Max bandwidth per month

## Custom Domains

### Adding Custom Domain

1. **User provides domain**: `mybusiness.com`

2. **Platform generates DNS instructions**:
   ```
   Add these DNS records:

   A     @              <VM_PUBLIC_IP>
   A     www            <VM_PUBLIC_IP>
   TXT   _acme-challenge <verification_code>
   ```

3. **Verify domain ownership**:
   - Check DNS TXT record
   - Or use HTTP challenge

4. **Update docker-compose labels**:
   ```yaml
   - "traefik.http.routers.${CUSTOMER_UUID}-${APP_ID}-www.rule=Host(`mybusiness.com`,`www.mybusiness.com`)"
   ```

5. **Restart containers**:
   ```bash
   docker compose up -d
   ```

6. **Traefik provisions SSL**:
   - Automatic Let's Encrypt certificate
   - Covers both apex and www subdomain

## Troubleshooting

### Site Not Accessible

1. Check container status:
   ```bash
   cd /datadisk0/sites/{customer_uuid}/{app_id}
   docker compose ps
   ```

2. Check Traefik routing:
   - Visit Traefik dashboard
   - Verify router exists for domain

3. Check DNS:
   ```bash
   dig mysite.webmeteor.in
   ```

4. Check SSL certificate:
   ```bash
   curl -v https://mysite.webmeteor.in
   ```

### Deployment Failures

1. Check deployment log:
   ```bash
   cat /datadisk0/sites/{customer_uuid}/{app_id}/deployment.log
   ```

2. Check git access:
   ```bash
   cd /datadisk0/sites/{customer_uuid}/{app_id}
   git pull origin main
   ```

3. Check docker build:
   ```bash
   docker compose build --no-cache
   ```

### Database Issues

1. Check database container:
   ```bash
   docker compose logs db
   ```

2. Connect to database:
   ```bash
   docker compose exec db psql -U {db_user} -d {db_name}
   ```

3. Check database size:
   ```sql
   SELECT pg_size_pretty(pg_database_size('site_app_xyz789'));
   ```

## Best Practices

1. **Backup Strategy**
   - Automated daily database backups
   - Keep 30 days of backups
   - Test restore procedures

2. **Security**
   - Unique credentials per site
   - Regular security updates
   - Monitor for vulnerabilities

3. **Performance**
   - Monitor resource usage
   - Implement caching strategies
   - Optimize database queries

4. **Reliability**
   - Health checks for all services
   - Automatic container restart
   - Deployment rollback capability

5. **Cost Management**
   - Track resource usage per site
   - Implement tiered pricing
   - Auto-archive inactive sites

## Related Documentation

- [VM_SETUP.md](./VM_SETUP.md) - VM setup instructions
- [ARCHITECTURE.md](./ARCHITECTURE.md) - System architecture
- [TODO.md](./TODO.md) - Implementation checklist
