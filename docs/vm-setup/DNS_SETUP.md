# DNS Configuration for WebMeteor

## Current Status

- **VM Public IP**: `4.240.76.167`
- **Domain**: `webmeteor.in`
- **DNS Provider**: (Your DNS provider - e.g., Cloudflare, GoDaddy, Namecheap, etc.)

## Required DNS Records

Add these records to your DNS provider:

### 1. Wildcard Record (Critical for customer sites and staging/prod)

```
Type: A
Name: *
Value: 4.240.76.167
TTL: 300 (5 minutes) or Auto
```

This enables:
- `staging.webmeteor.in` → Staging environment
- `api.webmeteor.in` → API (already configured ✅)
- `staging-api.webmeteor.in` → Staging API
- `customer123.webmeteor.in` → Customer sites
- Any subdomain you need

### 2. Root Domain (Optional - for webmeteor.in)

```
Type: A
Name: @
Value: 4.240.76.167
TTL: 300
```

### 3. WWW Record (Will update later after testing)

```
Type: A
Name: www
Value: 4.240.76.167
TTL: 300
```

**Note**: Currently `www.webmeteor.in` points to Azure Static Web App. We'll update this **after** everything is tested and working on the VM.

## DNS Configuration Steps

### Option A: Using Cloudflare (Recommended)

1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Select your domain: `webmeteor.in`
3. Go to **DNS** → **Records**
4. Add the wildcard record:
   - Click **Add record**
   - Type: `A`
   - Name: `*`
   - IPv4 address: `4.240.76.167`
   - Proxy status: **DNS only** (gray cloud, not orange) for testing
   - TTL: Auto
   - Click **Save**

5. (Optional) Update root domain:
   - Click **Add record**
   - Type: `A`
   - Name: `@`
   - IPv4 address: `4.240.76.167`
   - Proxy status: **DNS only**
   - Click **Save**

### Option B: Using GoDaddy

1. Log in to [GoDaddy](https://dcc.godaddy.com/domains)
2. Find `webmeteor.in` and click **DNS**
3. Click **Add New Record**
4. Add wildcard:
   - Type: `A`
   - Name: `*`
   - Value: `4.240.76.167`
   - TTL: 1 Hour (or 5 minutes)
   - Click **Save**

### Option C: Using Namecheap

1. Log in to [Namecheap](https://www.namecheap.com/myaccount/login/)
2. Go to **Domain List** → Click **Manage** for `webmeteor.in`
3. Go to **Advanced DNS** tab
4. Click **Add New Record**
5. Add wildcard:
   - Type: `A Record`
   - Host: `*`
   - Value: `4.240.76.167`
   - TTL: 5 min (or Automatic)

### Option D: Using Azure DNS

If you're using Azure DNS:

```bash
# Get resource group (if using Azure DNS)
az network dns zone list --query "[?name=='webmeteor.in'].resourceGroup" -o tsv

# Add wildcard record
az network dns record-set a add-record \
  --resource-group <YOUR_RESOURCE_GROUP> \
  --zone-name webmeteor.in \
  --record-set-name "*" \
  --ipv4-address 4.240.76.167

# Verify
az network dns record-set a show \
  --resource-group <YOUR_RESOURCE_GROUP> \
  --zone-name webmeteor.in \
  --name "*"
```

## Verification

After adding the DNS records, test with these commands on the VM:

```bash
# Test wildcard DNS
dig staging.webmeteor.in +short
dig test123.webmeteor.in +short
dig customer-site.webmeteor.in +short

# All should return: 4.240.76.167
```

**DNS Propagation Time**:
- Local/Direct DNS: 1-5 minutes
- ISP DNS cache: 15-30 minutes
- Global propagation: Up to 48 hours (but usually much faster)

## Current DNS Status (Before Changes)

```
webmeteor.in          → 15.197.225.128, 3.33.251.168 (Azure)
www.webmeteor.in      → Azure Static Web App (20.2.51.235)
api.webmeteor.in      → 4.240.76.167 (VM) ✅
*.webmeteor.in        → NOT CONFIGURED YET ❌
```

## Target DNS Status (After Changes)

```
webmeteor.in                    → 4.240.76.167 (VM)
www.webmeteor.in                → 4.240.76.167 (VM) - AFTER TESTING
api.webmeteor.in                → 4.240.76.167 (VM) ✅
*.webmeteor.in                  → 4.240.76.167 (VM) ✅
staging.webmeteor.in            → 4.240.76.167 (VM) ✅
staging-api.webmeteor.in        → 4.240.76.167 (VM) ✅
customer-xxx.webmeteor.in       → 4.240.76.167 (VM) ✅
```

## Testing After DNS Changes

Once you've added the wildcard DNS record, run this script to test:

```bash
#!/bin/bash

echo "Testing DNS Configuration for WebMeteor"
echo "========================================"
echo ""

VM_IP="4.240.76.167"

test_domains=(
  "webmeteor.in"
  "api.webmeteor.in"
  "staging.webmeteor.in"
  "staging-api.webmeteor.in"
  "test.webmeteor.in"
  "random123.webmeteor.in"
)

for domain in "${test_domains[@]}"; do
  result=$(dig +short "$domain" | grep -E '^[0-9.]+$' | head -1)

  if [ "$result" = "$VM_IP" ]; then
    echo "✅ $domain → $result"
  elif [ -z "$result" ]; then
    echo "❌ $domain → NO RESPONSE (DNS not propagated yet)"
  else
    echo "⚠️  $domain → $result (expected $VM_IP)"
  fi
done

echo ""
echo "Expected result: All domains should point to $VM_IP"
```

Save this as `test-dns.sh` and run:

```bash
chmod +x test-dns.sh
./test-dns.sh
```

## Troubleshooting

### DNS not propagating?

```bash
# Check with different DNS servers
dig @8.8.8.8 staging.webmeteor.in +short        # Google DNS
dig @1.1.1.1 staging.webmeteor.in +short        # Cloudflare DNS
dig @208.67.222.222 staging.webmeteor.in +short # OpenDNS

# Check DNS at authoritative nameserver
dig webmeteor.in NS +short  # Get nameservers
dig @<nameserver> staging.webmeteor.in +short
```

### Test with curl

```bash
# Force curl to use specific IP (before DNS propagates)
curl -H "Host: staging.webmeteor.in" http://4.240.76.167

# Test SSL (after Traefik is set up)
curl -H "Host: staging.webmeteor.in" https://4.240.76.167 -k
```

## Next Steps

After DNS is configured:

1. ✅ **Task 5 Complete**: Wildcard DNS configured
2. ⏭️ Continue with **Task 1**: Create traefik-public network
3. ⏭️ Continue with **Task 2**: Set up Traefik container

## Important Notes

- **Don't update www.webmeteor.in yet** - Wait until staging/prod are fully tested
- Use **DNS only** mode if using Cloudflare (no proxy) during initial setup
- Keep TTL low (5 minutes) during testing for faster changes
- After everything is stable, you can increase TTL to 1 hour or more
