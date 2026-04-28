# Nginx.conf HTTP Block Configuration

Add these sections to your main `/etc/nginx/nginx.conf` file in the `http {}` block.

## Step 1: Backup Your Config

```bash
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup
```

## Step 2: Find the HTTP Block

Open your nginx.conf:
```bash
sudo nano /etc/nginx/nginx.conf
```

Find the `http {` block (usually near the top, after directives like `user`, `worker_processes`, etc.)

## Step 3: Add Rate Limiting Zones

Inside the `http { ... }` block, add these lines (before the `server {}` or `include` statements):

```nginx
# Rate limiting zones for LiveKit
limit_req_zone $binary_remote_addr zone=livekit_api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=livekit_ws:10m rate=1000r/m;
```

## Step 4: Add SSL Configuration (Optional but Recommended)

Also in the `http {}` block, add SSL settings for better security:

```nginx
# SSL Configuration
ssl_protocols             TLSv1.2 TLSv1.3;
ssl_ciphers               ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
ssl_prefer_server_ciphers off;
ssl_session_timeout       1d;
ssl_session_cache         shared:SSL:10m;
ssl_session_tickets       off;
```

## Example HTTP Block

Here's what the `http {}` block should look like:

```nginx
http {
    include       mime.types;
    default_type  application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile            on;
    tcp_nopush          on;
    tcp_nodelay         on;
    keepalive_timeout   65;
    types_hash_max_size 2048;

    # ========== ADD THESE SECTIONS ==========
    
    # Rate limiting zones for LiveKit
    limit_req_zone $binary_remote_addr zone=livekit_api:10m rate=100r/m;
    limit_req_zone $binary_remote_addr zone=livekit_ws:10m rate=1000r/m;

    # SSL Configuration
    ssl_protocols             TLSv1.2 TLSv1.3;
    ssl_ciphers               ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout       1d;
    ssl_session_cache         shared:SSL:10m;
    ssl_session_tickets       off;

    # ========== END NEW SECTIONS ==========

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

## Step 5: Test Configuration

```bash
sudo nginx -t
```

Expected output:
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration will be tested by running daemon
```

## Step 6: Reload Nginx

```bash
sudo systemctl reload nginx
```

## Deployment Script Update

If using the automated deployment script, add this command before `./deploy-livekit-nginx.sh`:

```bash
# Add to nginx.conf http block if not already present
if ! grep -q "zone=livekit_api" /etc/nginx/nginx.conf; then
    sudo sed -i '/^http {/a\    # Rate limiting zones for LiveKit\n    limit_req_zone $binary_remote_addr zone=livekit_api:10m rate=100r\/m;\n    limit_req_zone $binary_remote_addr zone=livekit_ws:10m rate=1000r\/m;' /etc/nginx/nginx.conf
    echo "✅ Rate limiting zones added to nginx.conf"
fi
```

## Verification

After all changes:

```bash
# Test syntax
sudo nginx -t

# Check zones are defined
sudo grep -A 2 "Rate limiting zones" /etc/nginx/nginx.conf

# Reload and check status
sudo systemctl reload nginx
sudo systemctl status nginx
```

---

**Why This Was Needed:**

- `limit_req_zone` defines **shared memory zones** that must be scoped at the `http` level
- Placing them in a `server {}` block creates a zero-size zone, causing the error
- `limit_req` directives (which reference these zones) can stay in location/server blocks
