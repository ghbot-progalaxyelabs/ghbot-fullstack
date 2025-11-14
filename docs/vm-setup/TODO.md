# WebMeteor VM Implementation Checklist

This document provides a step-by-step checklist for implementing the complete WebMeteor platform on an Azure VM.

## Legend

- 🔴 **Critical**: Must be completed before proceeding
- 🟡 **Important**: Should be completed for production readiness
- 🟢 **Optional**: Nice-to-have or future enhancements
- ⚡ **Parallel**: Can be done in parallel with other tasks
- ⏭️ **Sequential**: Must wait for prerequisites

---

## PHASE 1: Infrastructure Setup (Foundation)

### ⚡ Set 1 - Start Immediately

- [ ] 🔴 **Task 1**: Create traefik-public Docker network
  - Command: `docker network create traefik-public`
  - Verify: `docker network ls | grep traefik-public`
  - Prerequisites: None

- [ ] 🔴 ⚡ **Task 5**: Configure wildcard DNS (*.webmeteor.in) to point to VM
  - Action: Update DNS provider with wildcard A record
  - Record: `*.webmeteor.in A <VM_PUBLIC_IP>`
  - Verify: `dig test.webmeteor.in` (should return VM IP)
  - Prerequisites: None

- [ ] 🔴 ⚡ **Task 13**: Install and enable at daemon for deployment queue
  - Commands:
    ```bash
    sudo apt-get update
    sudo apt-get install at
    sudo systemctl enable atd
    sudo systemctl start atd
    sudo systemctl status atd
    ```
  - Verify: `which at`
  - Prerequisites: None

---

### ⏭️ Set 2 - After Task 1 Completes

- [ ] 🔴 **Task 2**: Set up standalone Traefik container with SSL configuration
  - Location: `/datadisk0/projects/traefik/`
  - Files: `docker-compose.yaml`, `traefik.yaml`, `acme.json`
  - Commands:
    ```bash
    cd /datadisk0/projects/traefik
    touch acme.json acme-dns.json
    chmod 600 acme.json acme-dns.json
    docker compose up -d
    ```
  - Verify: `docker ps | grep traefik`
  - Prerequisites: Task 1 (traefik-public network)

- [ ] 🔴 ⚡ **Task 3**: Enable Apache proxy modules
  - Commands:
    ```bash
    sudo a2enmod proxy
    sudo a2enmod proxy_http
    sudo a2enmod proxy_wstunnel
    sudo a2enmod ssl
    sudo a2enmod headers
    sudo a2enmod rewrite
    ```
  - Verify: `apache2ctl -M | grep proxy`
  - Prerequisites: None

- [ ] 🔴 **Task 4**: Update Apache configuration for Traefik proxy and webhook routing
  - File: `/etc/apache2/sites-available/000-webmeteor-gateway.conf`
  - Commands:
    ```bash
    sudo a2dissite 001-api.webmeteor.in.conf
    sudo a2ensite 000-webmeteor-gateway.conf
    sudo apache2ctl configtest
    sudo systemctl reload apache2
    ```
  - Verify: `curl -I http://localhost`
  - Prerequisites: Task 3 (Apache modules)

- [ ] 🔴 ⚡ **Task 6**: Clone ghbot-fullstack repository to production directory
  - Commands:
    ```bash
    cd /datadisk0/projects
    git clone git@github.com:ghbot-progalaxyelabs/ghbot-fullstack.git ghbot-fullstack-production
    cd ghbot-fullstack-production
    git checkout prod  # Switch to prod branch
    ```
  - Verify: `ls -la /datadisk0/projects/ghbot-fullstack-production`
  - Prerequisites: None (SSH key already configured)

---

## PHASE 2: Repository Setup

### ⚡ Set 3 - After Task 6 Completes

- [ ] 🔴 ⚡ **Task 7**: Create staging .env file with staging-specific configuration
  - File: `/datadisk0/projects/ghbot-fullstack/.env`
  - Template: Copy from `.env.example`
  - Update: Ports (4400, 4402, 4401), domains, secrets
  - Prerequisites: None

- [ ] 🔴 ⚡ **Task 8**: Create production .env file with production-specific configuration
  - File: `/datadisk0/projects/ghbot-fullstack-production/.env`
  - Template: Copy from `.env.example`
  - Update: Ports (5400, 5402, 5401), domains, secrets
  - Prerequisites: Task 6

- [ ] 🔴 ⚡ **Task 9**: Update staging docker-compose.yaml with Traefik labels and network
  - File: `/datadisk0/projects/ghbot-fullstack/docker-compose.yaml`
  - Add: `traefik-public` external network
  - Add: Traefik labels for www, api, alert services
  - Prerequisites: None

- [ ] 🔴 ⚡ **Task 10**: Update production docker-compose.yaml with Traefik labels and network
  - File: `/datadisk0/projects/ghbot-fullstack-production/docker-compose.yaml`
  - Add: `traefik-public` external network
  - Add: Traefik labels for www, api, alert services
  - Prerequisites: Task 6

---

## PHASE 3: Deployment System Configuration

### ⚡ Set 4 - After Set 3 Completes

- [ ] 🔴 ⚡ **Task 11**: Create staging deployment config
  - Directory: `/home/azureuser/deploy/staging/`
  - Files:
    ```bash
    mkdir -p /home/azureuser/deploy/staging
    touch /home/azureuser/deploy/staging/queue.txt
    touch /home/azureuser/deploy/staging/deployment.log
    chmod 666 /home/azureuser/deploy/staging/queue.txt
    chmod 644 /home/azureuser/deploy/staging/deployment.log
    ```
  - Create: `config.json` with staging paths
  - Prerequisites: Task 7

- [ ] 🔴 ⚡ **Task 12**: Create production deployment config
  - Directory: `/home/azureuser/deploy/production/`
  - Files:
    ```bash
    mkdir -p /home/azureuser/deploy/production
    touch /home/azureuser/deploy/production/queue.txt
    touch /home/azureuser/deploy/production/deployment.log
    chmod 666 /home/azureuser/deploy/production/queue.txt
    chmod 644 /home/azureuser/deploy/production/deployment.log
    ```
  - Create: `config.json` with production paths
  - Prerequisites: Task 8

- [ ] 🔴 ⚡ **Task 18**: Generate GitHub webhook secrets for staging and production
  - Commands:
    ```bash
    # Staging
    STAGING_SECRET=$(openssl rand -hex 32)
    echo "Staging Webhook Secret: $STAGING_SECRET"

    # Production
    PROD_SECRET=$(openssl rand -hex 32)
    echo "Production Webhook Secret: $PROD_SECRET"
    ```
  - Save these secrets for Task 19, 20
  - Add to respective `.env` files
  - Prerequisites: None

- [ ] 🔴 **Task 14**: Set up cron job for staging deployment queue processor
  - Command:
    ```bash
    (crontab -l 2>/dev/null; echo "* * * * * /usr/bin/php /datadisk0/projects/ghbot-fullstack/api/scripts/process-queue.php --config=/home/azureuser/deploy/staging/config.json >> /home/azureuser/deploy/staging/deployment.log 2>&1") | crontab -
    ```
  - Verify: `crontab -l`
  - Prerequisites: Task 13 (at daemon)

- [ ] 🔴 **Task 15**: Set up cron job for production deployment queue processor
  - Command:
    ```bash
    (crontab -l 2>/dev/null; echo "* * * * * /usr/bin/php /datadisk0/projects/ghbot-fullstack-production/api/scripts/process-queue.php --config=/home/azureuser/deploy/production/config.json >> /home/azureuser/deploy/production/deployment.log 2>&1") | crontab -
    ```
  - Verify: `crontab -l`
  - Prerequisites: Task 13 (at daemon)

---

## PHASE 4: Build and Deploy Environments

### ⚡ Set 5 - After Tasks 1, 2, 7, 9 Complete

- [ ] 🔴 ⚡ **Task 16**: Build and start staging environment containers
  - Commands:
    ```bash
    cd /datadisk0/projects/ghbot-fullstack
    ./build.sh
    docker compose up -d
    docker compose ps
    docker compose logs -f
    ```
  - Verify: All containers healthy
  - Prerequisites: Tasks 1, 2, 7, 9

- [ ] 🔴 ⚡ **Task 17**: Build and start production environment containers
  - Commands:
    ```bash
    cd /datadisk0/projects/ghbot-fullstack-production
    ./build.sh
    docker compose up -d
    docker compose ps
    docker compose logs -f
    ```
  - Verify: All containers healthy
  - Prerequisites: Tasks 1, 2, 8, 10

---

## PHASE 5: Webhook Integration & Testing

### 🔧 Set 6 - User Actions on GitHub

- [ ] 🔴 **Task 19**: Configure GitHub webhook for staging branch
  - Location: `https://github.com/ghbot-progalaxyelabs/ghbot-fullstack/settings/hooks`
  - Settings:
    - Payload URL: `https://api.webmeteor.in/webhook/github`
    - Content type: `application/json`
    - Secret: (from Task 18 - staging secret)
    - Events: "Just the push event"
    - Branches: Configure to trigger only on `staging` branch
    - Active: ✓
  - Prerequisites: Task 18

- [ ] 🔴 **Task 20**: Configure GitHub webhook for prod branch
  - Location: `https://github.com/ghbot-progalaxyelabs/ghbot-fullstack/settings/hooks`
  - Settings:
    - Payload URL: `https://api.webmeteor.in/webhook/github`
    - Content type: `application/json`
    - Secret: (from Task 18 - production secret)
    - Events: "Just the push event"
    - Branches: Configure to trigger only on `prod` branch
    - Active: ✓
  - Prerequisites: Task 18

### ⏭️ Sequential Testing

- [ ] 🔴 **Task 21**: Test staging deployment via webhook
  - Action:
    ```bash
    cd /datadisk0/projects/ghbot-fullstack
    echo "# Test staging webhook" >> README.md
    git add README.md
    git commit -m "Test staging webhook"
    git push origin staging
    ```
  - Monitor:
    ```bash
    tail -f /home/azureuser/deploy/staging/deployment.log
    ```
  - Verify: Deployment succeeds, services restart
  - Prerequisites: Task 19

- [ ] 🔴 **Task 22**: Test production deployment via webhook
  - Action:
    ```bash
    cd /datadisk0/projects/ghbot-fullstack-production
    echo "# Test production webhook" >> README.md
    git add README.md
    git commit -m "Test production webhook"
    git push origin prod
    ```
  - Monitor:
    ```bash
    tail -f /home/azureuser/deploy/production/deployment.log
    ```
  - Verify: Deployment succeeds, services restart
  - Prerequisites: Task 20

---

## PHASE 6: SSL & DNS Finalization

### ⚡ Can Run in Parallel with Phase 5

- [ ] 🟡 **Task 23**: Set up wildcard SSL certificate (Let's Encrypt)
  - Method 1: DNS Challenge (recommended for wildcard)
    - Configure DNS provider API credentials
    - Update Traefik config with DNS challenge
  - Method 2: HTTP Challenge (individual certificates)
    - Automatic via Traefik
  - Verify: `curl -v https://staging.webmeteor.in`
  - Prerequisites: Task 5 (DNS configured)

### ⏭️ Sequential - After Everything Works

- [ ] 🟡 **Task 24**: Update DNS to point away from Azure Static Web App to this VM
  - Action: Update `www.webmeteor.in` A record
  - From: Azure Static Web App IP
  - To: VM Public IP
  - Verify:
    ```bash
    dig www.webmeteor.in
    curl -I https://www.webmeteor.in
    ```
  - Prerequisites: Tasks 21, 22, 23 all passing

---

## PHASE 7: Cleanup

### ⏭️ Sequential - After Phase 6 Complete and Verified

- [ ] 🟢 **Task 25**: Archive old PHP app from /var/www/api.webmeteor.in
  - Commands:
    ```bash
    cd /var/www
    sudo tar -czf api.webmeteor.in.backup.$(date +%Y%m%d).tar.gz api.webmeteor.in
    sudo mv api.webmeteor.in.backup.*.tar.gz /home/azureuser/backups/
    # Test new platform thoroughly before removing
    # sudo rm -rf /var/www/api.webmeteor.in
    ```
  - Prerequisites: New platform fully functional

---

## PHASE 8: Customer Site System (Future)

### Implementation Order

- [ ] 🟡 ⚡ **Task 26**: Create customer site template structure and docker-compose template
  - Directory: `/datadisk0/projects/site-templates/`
  - Templates:
    - Portfolio template
    - Business template
    - E-commerce template
    - Blog template
  - Each with: `docker-compose.yaml.template`, `.env.template`
  - Prerequisites: Platform stable

- [ ] 🟡 **Task 27**: Implement customer site creation API endpoint
  - Endpoint: `POST /api/sites/create`
  - Features:
    - Generate customer_uuid and app_id
    - Create site directory structure
    - Generate docker-compose.yaml from template
    - Generate .env file
    - Create Git repository (GitHub/Bitbucket)
    - Set up webhook
    - Start containers
    - Create DNS record (if API available)
  - Prerequisites: Task 26

- [ ] 🟡 **Task 28**: Implement customer site webhook handler for deployments
  - Endpoint: `POST /api/webhook/customer-site`
  - Features:
    - Verify webhook signature
    - Identify site from repository URL
    - Queue deployment
    - Process queue (similar to platform deployments)
    - Execute site-specific deployment script
  - Prerequisites: Task 27

- [ ] 🟡 **Task 29**: Test customer site creation and deployment workflow
  - Test: Create test site
  - Test: Push code to test site repo
  - Test: Verify webhook triggers deployment
  - Test: Verify site updates correctly
  - Test: Verify SSL certificate auto-provisioned
  - Prerequisites: Task 27, 28

---

## Parallelization Summary

### Batch 1 (Start Immediately)
```
Tasks: 1, 5, 13
Time: ~5 minutes
```

### Batch 2 (After Batch 1, Task 1)
```
Tasks: 2, 3, 4, 6
Time: ~10 minutes
```

### Batch 3 (After Batch 2, Task 6)
```
Tasks: 7, 8, 9, 10, 11, 12, 18
Time: ~15 minutes
```

### Batch 4 (After Batch 3 + Task 13)
```
Tasks: 14, 15
Time: ~2 minutes
```

### Batch 5 (After Batch 3 + Batch 4)
```
Tasks: 16, 17
Time: ~10 minutes (build time)
```

### Batch 6 (After Batch 5)
```
Tasks: 19, 20 (user actions), 23
Time: ~5 minutes
```

### Batch 7 (Sequential Testing)
```
Tasks: 21, 22
Time: ~5 minutes per test
```

### Batch 8 (Final Steps)
```
Tasks: 24, 25
Time: ~5 minutes
```

### Batch 9 (Future - Customer Sites)
```
Tasks: 26, 27, 28, 29
Time: Several hours (development work)
```

---

## Estimated Total Time

- **Phase 1-7 (Platform Setup)**: ~2-3 hours
- **Phase 8 (Customer Sites)**: ~8-12 hours (development)
- **Total**: ~10-15 hours for complete implementation

---

## Verification Checklist

After completing all phases, verify:

### Platform Services
- [ ] Traefik dashboard accessible: `http://traefik.webmeteor.in:8080`
- [ ] Staging frontend accessible: `https://staging.webmeteor.in`
- [ ] Staging API accessible: `https://staging-api.webmeteor.in`
- [ ] Production frontend accessible: `https://www.webmeteor.in`
- [ ] Production API accessible: `https://api.webmeteor.in`
- [ ] SSL certificates valid on all domains
- [ ] All containers healthy: `docker ps`

### Deployment System
- [ ] Staging webhook working: Push to `staging` branch triggers deployment
- [ ] Production webhook working: Push to `prod` branch triggers deployment
- [ ] Deployment logs show success
- [ ] Health checks pass after deployment
- [ ] Cron jobs running: `crontab -l`
- [ ] At daemon running: `systemctl status atd`

### Networking
- [ ] Traefik routing working for all services
- [ ] Apache proxying to Traefik correctly
- [ ] WebSocket connections work (Socket.IO)
- [ ] DNS wildcard working: Test random subdomain

### Security
- [ ] Webhook signatures verified
- [ ] SSL certificates auto-renewing
- [ ] Database credentials unique per environment
- [ ] Secrets generated securely
- [ ] File permissions correct (queue: 666, config: 600)

---

## Troubleshooting Guide

See individual documentation files for detailed troubleshooting:

- [VM_SETUP.md](./VM_SETUP.md#troubleshooting)
- [ARCHITECTURE.md](./ARCHITECTURE.md)
- [CUSTOMER_SITES.md](./CUSTOMER_SITES.md#troubleshooting)

---

## Next Steps After Completion

1. **Monitoring Setup**
   - Install monitoring tools (Prometheus, Grafana)
   - Set up alerting (PagerDuty, email)
   - Configure log aggregation

2. **Backup Strategy**
   - Automate database backups
   - Set up backup rotation
   - Test restore procedures

3. **Performance Optimization**
   - Configure caching (Redis)
   - Set up CDN (Cloudflare, Azure CDN)
   - Optimize Docker images

4. **Documentation**
   - Create user guides
   - Document operational procedures
   - Create runbooks for common issues

5. **Security Hardening**
   - Regular security audits
   - Dependency updates
   - Penetration testing

---

## Related Documentation

- [VM_SETUP.md](./VM_SETUP.md) - Detailed VM setup guide
- [ARCHITECTURE.md](./ARCHITECTURE.md) - System architecture overview
- [CUSTOMER_SITES.md](./CUSTOMER_SITES.md) - Customer site implementation
- [DEPLOYMENT_SETUP.md](../../DEPLOYMENT_SETUP.md) - Deployment system details
