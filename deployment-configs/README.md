# Deployment Configuration Files

This directory contains the generated configuration files for staging and production environments as part of the WebMeteor VM Implementation (Tasks 7-12, 18).

## Directory Structure

```
deployment-configs/
├── staging/
│   └── config.json          # Staging deployment configuration
├── production/
│   ├── .env                 # Production environment variables
│   ├── docker-compose.yaml  # Production Docker Compose with Traefik labels
│   └── config.json          # Production deployment configuration
└── README.md                # This file
```

## Generated Secrets

### Staging Environment
- **Webhook Secret**: `95c52f15fd455f1b250644d127a7db2c58cdbec3462a5c832e66696e3bbe61bd`
- **JWT Secret**: `0d2640d54f810200d6e2fd67fe60469cea7a10e47362653f84901bd67b01f2f8`
- **Session Secret**: `b79af9af3a47db758edbd68b056c616da64c38cea08fbd6dec9abb2ffa744c29`
- **DB Password**: `Ihfe/j6CNQTS8dHGpJOcQu31RRCbKIFraEItQDhJDz8=`

### Production Environment
- **Webhook Secret**: `d74933d86c8d5f54d8a92cc4c9512f26e81d6642c404f6a10140e1a15a96af91`
- **JWT Secret**: `84e3c4e1107bce63b36f91510bf36ec2572f647f12c517e45b350e0e1ec0fab4`
- **Session Secret**: `1dbaded5e47aaf7a6f9bdfb7a02730c21173ce106b82a34fe52b256e2f735259`
- **DB Password**: `e4GBGMiLAr5ftWuSRiBSDFBJlCUOjlNaIkuIAwdh/EM=`

## Installation Instructions

### Staging Environment (Current Repository)

The staging `.env` file has been created at:
```
/home/user/ghbot-fullstack/.env
```

The staging `docker-compose.yaml` has been updated with Traefik labels at:
```
/home/user/ghbot-fullstack/docker-compose.yaml
```

### Production Environment

Copy the production files to the production repository on the VM:

```bash
# On the VM, after cloning ghbot-fullstack-production
cd /datadisk0/projects/ghbot-fullstack-production

# Copy the .env file
cp /path/to/deployment-configs/production/.env .

# Copy the docker-compose.yaml
cp /path/to/deployment-configs/production/docker-compose.yaml .
```

### Deployment Configuration Files

Create the deployment directories and copy config files on the VM:

```bash
# Staging
mkdir -p /home/azureuser/deploy/staging
touch /home/azureuser/deploy/staging/queue.txt
touch /home/azureuser/deploy/staging/deployment.log
chmod 666 /home/azureuser/deploy/staging/queue.txt
chmod 644 /home/azureuser/deploy/staging/deployment.log
cp /path/to/deployment-configs/staging/config.json /home/azureuser/deploy/staging/

# Production
mkdir -p /home/azureuser/deploy/production
touch /home/azureuser/deploy/production/queue.txt
touch /home/azureuser/deploy/production/deployment.log
chmod 666 /home/azureuser/deploy/production/queue.txt
chmod 644 /home/azureuser/deploy/production/deployment.log
cp /path/to/deployment-configs/production/config.json /home/azureuser/deploy/production/
```

## Key Configuration Details

### Staging Environment
- **Domains**:
  - Frontend: `https://staging.webmeteor.in`
  - API: `https://staging-api.webmeteor.in`
  - Alert: `https://staging-alert.webmeteor.in`
- **Ports**: 4400 (www), 4402 (api), 4401 (alert)
- **Container Names**: `webmeteor-*` (db, api, alert, www)
- **Database**: `webmeteor_staging`

### Production Environment
- **Domains**:
  - Frontend: `https://www.webmeteor.in`
  - API: `https://api.webmeteor.in`
  - Alert: `https://alert.webmeteor.in`
- **Ports**: 5400 (www), 5402 (api), 5401 (alert)
- **Container Names**: `webmeteor-prod-*` (db, api, alert, www)
- **Database**: `webmeteor_production`

## Traefik Integration

Both environments are configured to work with Traefik reverse proxy:

- All services connect to the `traefik-public` external network
- Automatic SSL/TLS certificates via Let's Encrypt
- Services exposed via Traefik labels with appropriate hostnames
- Health checks configured for all services

## GitHub Webhook Configuration

When setting up GitHub webhooks (Tasks 19-20):

1. **Staging Webhook**:
   - URL: `https://api.webmeteor.in/webhook/github`
   - Secret: `95c52f15fd455f1b250644d127a7db2c58cdbec3462a5c832e66696e3bbe61bd`
   - Branch: `staging`

2. **Production Webhook**:
   - URL: `https://api.webmeteor.in/webhook/github`
   - Secret: `d74933d86c8d5f54d8a92cc4c9512f26e81d6642c404f6a10140e1a15a96af91`
   - Branch: `prod`

## Security Notes

⚠️ **IMPORTANT**: These files contain sensitive secrets.

- Do NOT commit these files to version control
- Keep them secure on the VM only
- The `.env` files are already in `.gitignore`
- Regularly rotate secrets in production
- Use environment-specific secrets (never share between staging/prod)

## Next Steps

After deploying these configuration files, continue with the VM setup:

1. Complete Task 1-6 (Infrastructure setup)
2. Deploy these configuration files (Tasks 7-12) ✓ COMPLETED
3. Continue with Task 13-17 (Cron jobs and container deployment)
4. Set up GitHub webhooks (Tasks 19-20)
5. Test deployments (Tasks 21-22)

See [/docs/vm-setup/TODO.md](../../docs/vm-setup/TODO.md) for the complete checklist.

## Support

If you need to regenerate any secrets, use:

```bash
# Generate webhook/JWT/session secrets
openssl rand -hex 32

# Generate database passwords
openssl rand -base64 32
```
