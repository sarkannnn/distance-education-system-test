# LiveKit 503 Error Troubleshooting Guide

## Current Issue
```
HTTP 503: twirp error unknown: no response from servers
```

This means LiveKit API server is responding, but internal services are failing.

## Quick Diagnostic Steps

### Step 1: Run the Diagnostic Script
```bash
chmod +x check-livekit.sh
./check-livekit.sh
```

Look for these key indicators:
- ✅ Docker container running
- ✅ Ports 7880, 7881, 7882 listening
- ✅ HTTPS connection works
- ✅ Nginx reverse proxy active

### Step 2: Check Docker Container Status
```bash
# List running containers
docker ps | grep livekit

# If not running, start it
docker start livekit

# Check resource usage
docker stats livekit

# View recent logs
docker logs -f --tail 50 livekit
```

### Step 3: Check LiveKit Logs for "no response from servers"
```bash
# In LiveKit container
docker exec livekit tail -f /var/log/livekit/livekit.log

# Look for errors like:
# - "unable to connect to store"
# - "connection refused"
# - "timeout"
# - "unhealthy server"
```

### Step 4: Verify Network Configuration
```bash
# Check if ports are listening
netstat -tulpn | grep -E '7880|7881|7882'

# Or use lsof
lsof -i :7880 -i :7881 -i :7882

# Test connectivity
curl -kv https://distant-l.ndu.edu.az:7880/
```

### Step 5: Test JWT Token Generation
```bash
# Verify token is being generated correctly
curl -X POST https://distant-l.ndu.edu.az/twirp/livekit.RoomService/CreateRoom \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"name":"test_room"}'
```

## Common Causes of 503 Errors

| Symptom | Cause | Solution |
|---------|-------|----------|
| **503 immediately** | LiveKit not running | `docker restart livekit` |
| **503 after timeout (20s+)** | Internal service failure | Check `docker logs livekit` |
| **503 + DNS errors** | Cannot reach internal store | Check `docker network inspect` |
| **503 + "GRPC unavailable"** | Proto-RPC server down | Restart container + check config |
| **503 + Auth header missing** | Missing or invalid JWT | Regenerate token with correct credentials |

## LiveKit Internal Architecture

LiveKit has multiple internal services that must communicate:

```
Nginx (Reverse Proxy)
    ↓
LiveKit API Server (Port 7880)
    ↓
LiveKit Signaling (Port 7881)
    ↓
LiveKit Media (Port 7882 UDP)
    ↓
Redis (Internal Store)
    ↓
PostgreSQL (Database)
```

**If any of these fail, you get 503.**

## Fix: Complete Restart

If diagnostic shows LiveKit is stuck:

```bash
# Stop all containers
docker-compose down

# Remove volumes (if safe)
docker-compose down -v

# Rebuild
docker-compose up -d

# Wait 30 seconds for services to start
sleep 30

# Run diagnostic
./check-livekit.sh
```

## Check Environment Variables

Verify `.env` has correct settings:

```bash
echo "=== LiveKit Configuration ==="
echo "API Key: $LIVEKIT_API_KEY"
echo "API Secret: $LIVEKIT_API_SECRET"
echo "Host: $LIVEKIT_HOST"
echo "Request Timeout: $LIVEKIT_REQUEST_TIMEOUT"
echo "Connect Timeout: $LIVEKIT_CONNECT_TIMEOUT"
echo "Verify SSL: $LIVEKIT_VERIFY_SSL"
```

## Check Nginx Configuration

```bash
# Validate nginx config
nginx -t

# Check LiveKit upstream
grep -A 20 "upstream livekit" /etc/nginx/sites-available/* /etc/nginx/conf.d/*

# Check if proxy is working
curl -kv https://distant-l.ndu.edu.az/stats
```

## Monitor Logs in Real-Time

```bash
# Terminal 1: LiveKit logs
docker logs -f livekit

# Terminal 2: Nginx error logs
tail -f /var/log/nginx/error.log

# Terminal 3: PHP-FPM logs
tail -f /var/log/php-fpm.log

# Terminal 4: Run your test
curl -X POST https://distant-test.ndu.edu.az/api/start_egress.php \
  -H "Content-Type: application/json" \
  -d '{"lesson_id": 247, "room_name": "247"}'
```

## Advanced: Manual API Test

```bash
#!/bin/bash

LIVEKIT_URL="https://distant-l.ndu.edu.az"
API_KEY="APIEXye2FeuBjUm"
API_SECRET="vXd9mmlM7cM3HbbCA4l3eqfIfojJBfrj0WpaNLYc33yA"

# Generate JWT
HEADER=$(echo -n '{"typ":"JWT","alg":"HS256"}' | base64 -w0 | tr '+/' '-_' | tr -d '=')
PAYLOAD=$(echo -n "{\"iss\":\"$API_KEY\",\"sub\":\"test\",\"exp\":$(($(date +%s)+3600))}" | base64 -w0 | tr '+/' '-_' | tr -d '=')
SIGNATURE=$(echo -n "$HEADER.$PAYLOAD" | openssl dgst -sha256 -hmac "$API_SECRET" -binary | base64 -w0 | tr '+/' '-_' | tr -d '=')
TOKEN="$HEADER.$PAYLOAD.$SIGNATURE"

# Test RoomService
echo "=== Testing RoomService ==="
curl -kv -X POST "$LIVEKIT_URL/twirp/livekit.RoomService/CreateRoom" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"diagnostic_test_room"}'

# Test Egress
echo -e "\n=== Testing Egress Service ==="
curl -kv -X POST "$LIVEKIT_URL/twirp/livekit.Egress/StartRoomCompositeEgress" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "room_name": "diagnostic_test_room",
    "layout": "custom",
    "custom_base_url": "https://distant.ndu.edu.az/test",
    "file": {"filepath": "test.mp4"}
  }'
```

## When to Contact Support

If you've verified:
- ✅ Docker container is running
- ✅ Ports are listening
- ✅ Network connectivity works
- ✅ API keys are correct
- ✅ SSL certificates are valid

But still getting **503 "no response from servers"**, then:

1. Check LiveKit GitHub issues: https://github.com/livekit/livekit-server/issues
2. Review LiveKit server logs for specific error messages
3. Verify docker-compose.yml configuration
4. Consider rebuilding the container fresh

## Success Indicators

After fixing, you should see in logs:
```
✅ Egress service responding to API calls
✅ Recording starting without errors
✅ TURN servers providing credentials
✅ WebRTC connections establishing
```
