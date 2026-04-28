# Quick Deploy Commands

Copy-paste these commands on your server to deploy the LiveKit Nginx configuration.

## IMPORTANT: First Step - Update Main Nginx Config

Before deploying the LiveKit site, add rate limiting zones to `/etc/nginx/nginx.conf` http block:

```bash
sudo nano /etc/nginx/nginx.conf
```

Find the `http { ... }` block and add these lines inside it:

```nginx
# Rate limiting zones for LiveKit
limit_req_zone $binary_remote_addr zone=livekit_api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=livekit_ws:10m rate=1000r/m;
```

Then save and test: `sudo nginx -t`

## One-Liner (Complete Deployment)

```bash
sudo cp distant-l.ndu.edu.az-nginx.conf /etc/nginx/sites-available/distant-l.ndu.edu.az && \
sudo ln -s /etc/nginx/sites-available/distant-l.ndu.edu.az /etc/nginx/sites-enabled/distant-l.ndu.edu.az && \
sudo nginx -t && \
sudo systemctl reload nginx && \
sudo ufw allow 7880/tcp && sudo ufw allow 7881/tcp && sudo ufw allow 7882/tcp && sudo ufw allow 7882/udp && sudo ufw allow 49152:65535/udp && \
echo "✅ Deployment complete"
```

## Step-by-Step Commands

```bash
# 1. Copy config file
sudo cp distant-l.ndu.edu.az-nginx.conf /etc/nginx/sites-available/distant-l.ndu.edu.az

# 2. Create symlink
sudo ln -s /etc/nginx/sites-available/distant-l.ndu.edu.az /etc/nginx/sites-enabled/distant-l.ndu.edu.az

# 3. Test Nginx
sudo nginx -t

# 4. Reload Nginx
sudo systemctl reload nginx

# 5. Add firewall rules
sudo ufw allow 7880/tcp
sudo ufw allow 7881/tcp
sudo ufw allow 7882/tcp
sudo ufw allow 7882/udp
sudo ufw allow 49152:65535/udp
```

## Verification Commands

```bash
# Check Nginx status
sudo systemctl status nginx

# Test stats endpoint
curl -k https://distant-l.ndu.edu.az/stats

# Run diagnostic
./check-livekit.sh

# Monitor error log
sudo tail -f /var/log/nginx/distant-l_error.log
```

## Rollback Commands (if needed)

```bash
# Disable site
sudo rm /etc/nginx/sites-enabled/distant-l.ndu.edu.az

# Remove config
sudo rm /etc/nginx/sites-available/distant-l.ndu.edu.az

# Reload Nginx
sudo nginx -t && sudo systemctl reload nginx
```

## What Gets Fixed

✅ HTTP 404 on /stats endpoint
✅ HTTP 000 (timeout) on /twirp/ Egress API
✅ WebSocket connections (signaling)
✅ SSL/TLS for distant-l.ndu.edu.az
✅ Rate limiting and performance tuning
✅ Proper gRPC-Web protocol handling

---

**Expected Results After Deployment:**

```
$ curl -k https://distant-l.ndu.edu.az/stats
{"activeRooms":1,"nodes":[...],...}

$ ./check-livekit.sh
✅ Docker container running
✅ Ports 7880/7881 listening
✅ Port 7882 listening
✅ /stats endpoint: HTTP 200
✅ /twirp/ endpoints: responding
✅ SSL certificate valid
```
