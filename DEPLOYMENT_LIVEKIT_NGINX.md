# LiveKit Nginx Configuration Deployment Guide

## Overview

The current Nginx configuration is **for the main application** (`distant.ndu.edu.az`), but LiveKit needs its own **reverse proxy configuration** (`distant-l.ndu.edu.az`) to properly handle:
- Twirp API endpoints (`/twirp/livekit.Egress/*`)
- WebSocket connections (signaling)
- gRPC-Web protocol

## Files Provided

- **`distant-l.ndu.edu.az-nginx.conf`** - Main Nginx configuration
- **`deploy-livekit-nginx.sh`** - Automated deployment script

## Deployment Steps

### Step 1: Update nginx.conf HTTP Block

Before deploying the LiveKit site configuration, you must add rate limiting zones to your main nginx.conf file.

```bash
sudo nano /etc/nginx/nginx.conf
```

Inside the `http { ... }` block, add:

```nginx
# Rate limiting zones for LiveKit
limit_req_zone $binary_remote_addr zone=livekit_api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=livekit_ws:10m rate=1000r/m;
```

See **NGINX_HTTP_BLOCK_CONFIG.md** for detailed instructions.

### Step 2: Copy Configuration to Server

```bash
# On your server:
scp distant-l.ndu.edu.az-nginx.conf root@your-server:/tmp/
cd /tmp
```

### Step 3: Review Configuration

```bash
cat distant-l.ndu.edu.az-nginx.conf
```

Key sections:
- **Line 33-36**: Upstream definition (proxies to localhost:7880/7881)
- **Line 55-67**: `/stats` endpoint
- **Line 72-99**: `/twirp/` Twirp API (Egress, RoomService)
- **Line 104-131**: WebSocket signaling

### Step 3: Deploy Configuration

#### Option A: Automated (Recommended)

```bash
# Upload deployment script
scp deploy-livekit-nginx.sh root@your-server:/opt/
cd /opt

# Make executable
chmod +x deploy-livekit-nginx.sh

# Run deployment
./deploy-livekit-nginx.sh
```

#### Option B: Manual

```bash
# 1. Copy config
cp distant-l.ndu.edu.az-nginx.conf /etc/nginx/sites-available/distant-l.ndu.edu.az

# 2. Create symlink
ln -s /etc/nginx/sites-available/distant-l.ndu.edu.az /etc/nginx/sites-enabled/distant-l.ndu.edu.az

# 3. Test configuration
nginx -t

# 4. Reload Nginx
systemctl reload nginx

# 5. Add firewall rules
ufw allow 7880/tcp
ufw allow 7881/tcp
ufw allow 7882/tcp
ufw allow 7882/udp
ufw allow 49152:65535/udp
```

### Step 4: Verify Deployment

```bash
# 1. Check Nginx status
systemctl status nginx

# 2. Check if site is enabled
ls -la /etc/nginx/sites-enabled/ | grep distant-l

# 3. Test SSL certificate
echo | openssl s_client -connect distant-l.ndu.edu.az:443

# 4. Test stats endpoint
curl -k https://distant-l.ndu.edu.az/stats

# 5. Run diagnostic
./check-livekit.sh
```

## What This Fixes

| Issue | Root Cause | Fix |
|-------|-----------|-----|
| **HTTP 404 on /stats** | No routing rule | Added location block for `/stats` |
| **HTTP 000 on /twirp/** | No proxying | Added gRPC-Web proxy configuration |
| **Timeouts (20s+)** | Wrong upstream | Properly configured upstream to localhost |
| **Port 7882 not open** | Firewall | UFW rules added in deployment |

## Troubleshooting

### Issue: "Connection refused" after deployment

```bash
# Check if Docker container is running
docker ps | grep livekit

# If not running
docker start livekit

# Check container logs
docker logs -f livekit
```

### Issue: SSL certificate errors

```bash
# Verify certificate exists
ls -la /etc/letsencrypt/live/distant-l.ndu.edu.az/

# If missing, regenerate
certbot certonly --standalone -d distant-l.ndu.edu.az
```

### Issue: Nginx won't reload

```bash
# Test config
nginx -t

# Check for syntax errors
grep -n "error" /var/log/nginx/error.log

# View specific errors
cat /var/log/nginx/distant-l_error.log
```

### Issue: Still getting 503 errors

```bash
# Check if LiveKit internal services are healthy
docker exec livekit /bin/bash
ps aux | grep livekit

# Check Redis connection (if used)
redis-cli ping

# Check logs for specific errors
docker logs --tail 100 livekit | grep -i error
```

## Verification Checklist

After deployment, verify:

- [ ] Nginx configuration test passes (`nginx -t`)
- [ ] Nginx reloads without errors
- [ ] `/stats` endpoint returns HTTP 200
- [ ] `/twirp/` endpoints accessible via cURL
- [ ] WebSocket connections work (signaling functional)
- [ ] Firewall rules allow all required ports
- [ ] SSL certificate is valid
- [ ] Diagnostic script shows ✅ for all items
- [ ] Egress API responds to start/stop requests

## Files to Keep

Commit these to your repository:
- `distant-l.ndu.edu.az-nginx.conf`
- `deploy-livekit-nginx.sh`
- This deployment guide

## Support

If issues persist after following this guide:

1. **Check all logs:**
   ```bash
   tail -f /var/log/nginx/distant-l_error.log
   docker logs -f livekit
   tail -f /var/log/php-fpm.log
   ```

2. **Run diagnostic script** and analyze output

3. **Verify network connectivity** to Docker container

4. **Check resource usage:**
   ```bash
   docker stats livekit
   free -h
   df -h
   ```

## Next Steps

Once deployment is complete:

1. Re-run `check-livekit.sh` diagnostic
2. Test Egress recording via application
3. Monitor `/var/log/nginx/distant-l_access.log` for requests
4. Verify recordings are created successfully
