# LiveKit Setup Guide

## Problem
The system is getting HTTP 503 "no response from servers" when trying to use LiveKit for recording/egress functionality.

**Root Cause:** LiveKit server is either:
1. Not deployed
2. Not running
3. API credentials are invalid/placeholder values

## Solution

### Step 1: Deploy LiveKit Server

#### Option A: Docker (Recommended)
```bash
# Create volume for config and recordings
mkdir -p ~/livekit/config ~/livekit/recordings

# Run LiveKit server
docker run -d \
  --name livekit \
  -p 7880:7880 \
  -p 7881:7881 \
  -p 7882:7882/udp \
  -e LIVEKIT_API_KEY=devkey \
  -e LIVEKIT_API_SECRET=secret \
  -v ~/livekit/config:/etc/livekit \
  -v ~/livekit/recordings:/recordings \
  livekit/livekit-server \
  --dev
```

#### Option B: Binary Installation
```bash
# Download from https://github.com/livekit/livekit-server/releases
wget https://github.com/livekit/livekit-server/releases/download/v1.x.x/livekit-server-linux-amd64.tar.gz
tar xzf livekit-server-linux-amd64.tar.gz
./livekit-server --dev
```

### Step 2: Generate API Credentials

If you used the docker example above, credentials are:
- **API Key:** `devkey`
- **API Secret:** `secret`

For production, generate new credentials:
```bash
# If running in Docker
docker exec livekit livekit-server --generate-token \
  --api-key=devkey \
  --api-secret=secret
```

### Step 3: Update `.env` Configuration

Edit `.env` with your LiveKit server details:

```env
# LiveKit Server Configuration
LIVEKIT_URL=https://distant-l-turn.ndu.edu.az
LIVEKIT_HOST=https://distant-l-turn.ndu.edu.az
LIVEKIT_API_KEY=devkey
LIVEKIT_API_SECRET=secret

# Optional: Public base URL for Egress (defaults to current domain)
PUBLIC_BASE_URL=https://distant.ndu.edu.az
```

### Step 4: Configure NGINX Reverse Proxy (Optional)

If running LiveKit on separate server, add to nginx:

```nginx
upstream livekit_backend {
    server 127.0.0.1:7880;
}

server {
    listen 443 ssl http2;
    server_name distant-l.ndu.edu.az;

    ssl_certificate /etc/letsencrypt/live/distant-l.ndu.edu.az/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/distant-l.ndu.edu.az/privkey.pem;

    location / {
        proxy_pass http://livekit_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Step 5: Verify Setup

Check if LiveKit is responding:

```bash
# Test LiveKit API
curl -X GET https://distant-l.ndu.edu.az/stats \
  -H "Authorization: Bearer $(jwt_token)"

# Or use the health endpoint (if available)
curl -k https://localhost:7881/
```

## Troubleshooting

### Error: 503 Service Unavailable
**Cause:** LiveKit server not running or unreachable
```bash
# Check if container is running
docker ps | grep livekit

# Check logs
docker logs livekit

# Restart
docker restart livekit
```

### Error: 401 Unauthorized
**Cause:** Invalid API Key or Secret
```bash
# Verify credentials match .env file
echo "API Key: $LIVEKIT_API_KEY"
echo "API Secret: $LIVEKIT_API_SECRET"
```

### Error: Connection Refused
**Cause:** SSL/TLS certificate issue or wrong port
```bash
# Test connection
curl -kv https://distant-l.ndu.edu.az:7881/

# Generate self-signed cert for testing
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
```

### Error: "twirp error unknown"
**Cause:** Authentication or malformed request
- Check Bearer token format
- Verify JSON payload structure
- Enable server debug logging:
```bash
docker exec livekit livekit-server --loglevel=debug
```

## API Endpoints

Common LiveKit API endpoints (all require `Authorization: Bearer <token>`):

```bash
# Create room
POST /twirp/livekit.RoomService/CreateRoom

# Start recording (Egress)
POST /twirp/livekit.Egress/StartRoomCompositeEgress

# Stop recording
POST /twirp/livekit.Egress/StopEgress

# Get server stats
GET /stats
```

## References

- [LiveKit Documentation](https://docs.livekit.io)
- [Access Control Guide](https://docs.livekit.io/guides/access-control/#generating-tokens)
- [Egress Recording](https://docs.livekit.io/guides/egress/)
- [Docker Setup](https://docs.livekit.io/guides/deploy/docker/)
