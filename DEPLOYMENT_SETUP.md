# GitHub Webhook Auto-Deployment Setup Guide

This guide walks through setting up automatic deployments when code is pushed to the `main` branch.

## Overview

The deployment system consists of:
1. **GitHub Webhook** → sends POST request to `/webhook/github`
2. **Webhook Endpoint** → verifies signature, adds to queue
3. **Queue Processor** → runs every minute via cron, schedules `at` jobs
4. **Deployment Script** → pulls code, builds, restarts containers

## Prerequisites

- `at` daemon must be installed and running
- User must have permissions to run `git pull`, `./build.sh`, and `docker compose`
- `/home/$USER/deploy/` directory with proper permissions

## DevOps Setup Steps

### 1. Install and Enable `at` Daemon

```bash
# Check if at is installed
which at

# If not installed:
sudo apt-get update
sudo apt-get install at

# Enable and start atd service
sudo systemctl enable atd
sudo systemctl start atd

# Verify it's running
sudo systemctl status atd
```

### 2. Create Deployment Directory

```bash
# Create directory with restricted permissions
mkdir -p /home/$USER/deploy
chmod 700 /home/$USER/deploy
```

### 3. Create Configuration File

```bash
cat > /home/$USER/deploy/config.json << 'EOF'
{
  "repo_path": "/home/user/ghbot-fullstack",
  "branch": "main",
  "log_file": "/home/user/deploy/deployment.log",
  "build_command": "./build.sh",
  "restart_command": "docker compose up -d",
  "health_check_urls": [
    "http://localhost:4400",
    "http://localhost:4401/health",
    "http://localhost:4402"
  ],
  "lock_file": "/tmp/deployment.lock"
}
EOF

# Set restrictive permissions (only owner can read/write)
chmod 600 /home/$USER/deploy/config.json
```

**Note:** Adjust paths and URLs according to your environment.

### 4. Create Queue and Log Files

```bash
# Create empty queue file (www-data needs write access)
touch /home/$USER/deploy/queue.txt
chmod 666 /home/$USER/deploy/queue.txt

# Create empty log file
touch /home/$USER/deploy/deployment.log
chmod 644 /home/$USER/deploy/deployment.log
```

### 5. Add Cron Job

```bash
# Add cron job to run queue processor every minute
(crontab -l 2>/dev/null; echo "* * * * * /usr/bin/php /home/user/ghbot-fullstack/api/scripts/process-queue.php --config=/home/$USER/deploy/config.json >> /home/$USER/deploy/deployment.log 2>&1") | crontab -

# Verify cron job was added
crontab -l
```

### 6. Generate GitHub Webhook Secret

```bash
# Generate a secure random secret
WEBHOOK_SECRET=$(openssl rand -hex 32)
echo "GitHub Webhook Secret: $WEBHOOK_SECRET"

# Save it for the next step
echo $WEBHOOK_SECRET > /tmp/webhook_secret.txt
```

### 7. Update .env File

```bash
# Add webhook secret to API .env file
cd /home/user/ghbot-fullstack/api

# If .env doesn't exist, copy from example
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Add the webhook secret
WEBHOOK_SECRET=$(cat /tmp/webhook_secret.txt)
echo "GITHUB_WEBHOOK_SECRET=$WEBHOOK_SECRET" >> .env

# Clean up temporary file
rm /tmp/webhook_secret.txt

# Verify it was added
grep GITHUB_WEBHOOK_SECRET .env
```

### 8. Restart API Container

```bash
cd /home/user/ghbot-fullstack
docker compose restart api

# Verify container is running
docker compose ps api
```

### 9. Configure GitHub Webhook

1. Go to your GitHub repository: `https://github.com/YOUR_ORG/YOUR_REPO/settings/hooks`
2. Click **"Add webhook"**
3. Configure:
   - **Payload URL:** `https://yourdomain.com/webhook/github`
   - **Content type:** `application/json`
   - **Secret:** (paste the webhook secret from step 6)
   - **Which events:** Select "Just the push event"
   - **Active:** ✓ Checked
4. Click **"Add webhook"**

### 10. Test the Webhook

```bash
# Monitor deployment log in real-time
tail -f /home/$USER/deploy/deployment.log

# In another terminal, push a commit to main branch
cd /home/user/ghbot-fullstack
git checkout main
echo "# Test deployment" >> README.md
git add README.md
git commit -m "Test webhook deployment"
git push origin main

# Watch the log for deployment activity
# You should see:
# - Webhook received
# - Deployment queued
# - Git pull
# - Build process
# - Container restart
# - Health checks
# - Deployment completed
```

### 11. Verify Deployment Queue Processor

```bash
# Check if queue processor is working
echo "deploy-test-$(date +%s)" >> /home/$USER/deploy/queue.txt

# Wait 60 seconds (or less) for cron to run
sleep 60

# Check if queue was processed (should be empty)
cat /home/$USER/deploy/queue.txt

# Check scheduled at jobs
atq

# Check deployment log
tail -20 /home/$USER/deploy/deployment.log
```

## File Locations

```
/home/user/ghbot-fullstack/
├── api/
│   ├── scripts/
│   │   ├── DeploymentLogger.php       # Logging helper
│   │   ├── deploy.php                 # Main deployment script
│   │   └── process-queue.php          # Queue processor (runs via cron)
│   ├── src/App/Routes/
│   │   └── GithubWebhookRoute.php     # Webhook endpoint
│   └── .env                           # Contains GITHUB_WEBHOOK_SECRET

/home/$USER/deploy/                     # Deployment directory (not in repo)
├── config.json                         # Deployment configuration
├── queue.txt                           # Deployment queue
└── deployment.log                      # Deployment logs
```

## Troubleshooting

### Webhook Returns 401 Unauthorized

- Verify `GITHUB_WEBHOOK_SECRET` in `/home/user/ghbot-fullstack/api/.env` matches GitHub webhook secret
- Check API container logs: `docker compose logs api`
- Verify API container was restarted after adding secret

### Deployments Not Running

```bash
# Check if cron job exists
crontab -l | grep process-queue

# Check if at daemon is running
systemctl status atd

# Check queue file permissions
ls -la /home/$USER/deploy/queue.txt

# Manually trigger queue processor
/usr/bin/php /home/user/ghbot-fullstack/api/scripts/process-queue.php --config=/home/$USER/deploy/config.json

# Check for errors
tail -50 /home/$USER/deploy/deployment.log
```

### Build Failures

```bash
# Check deployment log for errors
grep ERROR /home/$USER/deploy/deployment.log

# Manually run build to see full output
cd /home/user/ghbot-fullstack
./build.sh

# Check if deployment lock is stuck
ls -la /tmp/deployment.lock
# If stuck, remove it:
rm /tmp/deployment.lock
```

### Queue Not Being Processed

```bash
# Check cron job syntax
crontab -l

# Check cron execution logs
grep CRON /var/log/syslog

# Test queue processor manually
echo "deploy-manual-test" >> /home/$USER/deploy/queue.txt
/usr/bin/php /home/user/ghbot-fullstack/api/scripts/process-queue.php --config=/home/$USER/deploy/config.json
cat /home/$USER/deploy/queue.txt  # Should be empty
```

### Health Checks Failing

```bash
# Verify services are accessible
curl -I http://localhost:4400
curl -I http://localhost:4401/health
curl -I http://localhost:4402

# Check container status
docker compose ps

# Adjust health check URLs in config.json if needed
nano /home/$USER/deploy/config.json
```

## Security Notes

- **Queue file** (`queue.txt`) needs `666` permissions so www-data can write to it
- **Config file** (`config.json`) should be `600` (owner only) as it may contain sensitive info in the future
- **Deploy directory** should be `700` (owner only)
- **Webhook secret** must match between GitHub and `.env` file
- Webhook endpoint verifies HMAC-SHA256 signature before processing

## Log Format

```
[2025-01-14 14:30:22] [deploy-20250114-143022-abc] [INFO] Webhook received from GitHub (commit: a1b2c3d, pusher: john)
[2025-01-14 14:30:22] [deploy-20250114-143022-abc] [INFO] Added to deployment queue
[2025-01-14 14:31:05] [deploy-20250114-143022-abc] [INFO] Processing deployment from queue
[2025-01-14 14:31:05] [deploy-20250114-143022-abc] [INFO] Deployment scheduled via at command
[2025-01-14 14:31:06] [deploy-20250114-143022-abc] [INFO] Deployment started
[2025-01-14 14:31:06] [deploy-20250114-143022-abc] [INFO] Changed to repo directory
[2025-01-14 14:31:06] [deploy-20250114-143022-abc] [INFO] Starting git pull
[2025-01-14 14:31:07] [deploy-20250114-143022-abc] [INFO] Git pull completed
[2025-01-14 14:31:07] [deploy-20250114-143022-abc] [INFO] Starting build
[2025-01-14 14:32:45] [deploy-20250114-143022-abc] [INFO] Build completed successfully
[2025-01-14 14:32:46] [deploy-20250114-143022-abc] [INFO] Containers restarted
[2025-01-14 14:32:50] [deploy-20250114-143022-abc] [INFO] Health checks completed: 3/3 services responding
[2025-01-14 14:32:50] [deploy-20250114-143022-abc] [SUCCESS] Deployment completed in 104s
```

## Maintenance

### View Recent Deployments

```bash
tail -100 /home/$USER/deploy/deployment.log
```

### Clear Old Logs (Optional)

```bash
# Rotate logs (keep last 1000 lines)
tail -1000 /home/$USER/deploy/deployment.log > /home/$USER/deploy/deployment.log.tmp
mv /home/$USER/deploy/deployment.log.tmp /home/$USER/deploy/deployment.log
```

### Monitor Scheduled Jobs

```bash
# List pending at jobs
atq

# View job details
at -c JOB_NUMBER

# Remove stuck job
atrm JOB_NUMBER
```
