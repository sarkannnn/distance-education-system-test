#!/bin/bash

# LiveKit Server Diagnostic Script
# Checks if LiveKit is deployed, running, and responding correctly

LIVEKIT_URL="https://distant-l.ndu.edu.az"
LIVEKIT_API_KEY="APIEXye2FeuBjUm"
LIVEKIT_API_SECRET="vXd9mmlM7cM3HbbCA4l3eqfIfojJBfrj0WpaNLYc33yA"
LIVEKIT_HOST="distant-l.ndu.edu.az"
LIVEKIT_PORTS=(7880 7881 7882)
TURN_SERVER="distant-l-turn.ndu.edu.az"

echo "=========================================="
echo "LIVEKIT SERVER DIAGNOSTIC REPORT"
echo "=========================================="
echo ""

# 1. CHECK IF DOCKER CONTAINER IS RUNNING
echo "1. DOCKER CONTAINER STATUS"
echo "-------------------"
if command -v docker &> /dev/null; then
    LIVEKIT_CONTAINERS=$(docker ps --filter "name=livekit" --format "{{.Names}}")
    if [ -z "$LIVEKIT_CONTAINERS" ]; then
        echo "❌ No running LiveKit Docker containers found"
        echo ""
        echo "Available containers:"
        docker ps --format "table {{.Names}}\t{{.Status}}"
    else
        echo "✅ Found LiveKit container(s): $LIVEKIT_CONTAINERS"
        docker ps --filter "name=livekit"
    fi
else
    echo "⚠️  Docker not installed, skipping container check"
fi
echo ""

# 2. CHECK SYSTEMD SERVICE (if installed)
echo "2. SYSTEMD SERVICE STATUS"
echo "-------------------"
if systemctl list-unit-files | grep -q livekit; then
    systemctl status livekit --no-pager 2>/dev/null || echo "⚠️  LiveKit systemd service not found or not active"
else
    echo "ℹ️  LiveKit systemd service not configured (using Docker is normal)"
fi
echo ""

# 3. CHECK PORT AVAILABILITY
echo "3. PORT AVAILABILITY"
echo "-------------------"
for PORT in "${LIVEKIT_PORTS[@]}"; do
    if nc -zv -w 2 127.0.0.1 $PORT &> /dev/null; then
        echo "✅ Port $PORT is LISTENING"
    else
        echo "❌ Port $PORT is NOT LISTENING"
    fi
done
echo ""

# 4. CHECK NETWORK CONNECTIVITY TO LIVEKIT HOST
echo "4. NETWORK CONNECTIVITY"
echo "-------------------"
if ping -c 1 -W 2 "$LIVEKIT_HOST" &> /dev/null; then
    echo "✅ $LIVEKIT_HOST is reachable (ping successful)"
else
    echo "⚠️  $LIVEKIT_HOST is not responding to ping (firewall may block ICMP)"
fi

# Try connecting via HTTP
if curl -sk -m 5 "$LIVEKIT_URL" &> /dev/null; then
    echo "✅ $LIVEKIT_URL is reachable (HTTP successful)"
else
    echo "❌ $LIVEKIT_URL is NOT reachable (HTTP failed)"
fi
echo ""

# 5. CHECK LIVEKIT HEALTH ENDPOINT
echo "5. LIVEKIT HEALTH/STATS ENDPOINT"
echo "-------------------"
HEALTH_RESPONSE=$(curl -sk -m 5 -w "%{http_code}" "$LIVEKIT_URL/stats" 2>/dev/null)
HTTP_CODE="${HEALTH_RESPONSE: -3}"
if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ LiveKit stats endpoint responding (HTTP 200)"
    echo "Response: ${HEALTH_RESPONSE:0:-3}"
elif [ "$HTTP_CODE" = "401" ]; then
    echo "⚠️  LiveKit requires authentication (HTTP 401) - expected behavior"
elif [ "$HTTP_CODE" = "404" ]; then
    echo "❌ LiveKit stats endpoint not found (HTTP 404)"
else
    echo "❌ LiveKit stats endpoint error (HTTP $HTTP_CODE)"
fi
echo ""

# 6. CHECK SSL CERTIFICATE
echo "6. SSL CERTIFICATE STATUS"
echo "-------------------"
CERT_INFO=$(echo | openssl s_client -connect "$LIVEKIT_HOST:443" 2>/dev/null | openssl x509 -noout -text 2>/dev/null)
if [ -n "$CERT_INFO" ]; then
    echo "✅ SSL certificate is valid"
    CERT_EXPIRY=$(echo "$CERT_INFO" | grep "Not After" | cut -d'=' -f2)
    echo "Certificate expires: $CERT_EXPIRY"
else
    echo "❌ Could not verify SSL certificate"
fi
echo ""

# 7. GENERATE JWT TOKEN
echo "7. JWT TOKEN GENERATION TEST"
echo "-------------------"
generate_jwt() {
    local header=$(echo -n '{"typ":"JWT","alg":"HS256"}' | base64 | tr '+/' '-_' | tr -d '=')
    local exp=$(($(date +%s) + 3600))
    local payload=$(echo -n "{\"iss\":\"$LIVEKIT_API_KEY\",\"sub\":\"admin\",\"name\":\"Admin\",\"exp\":$exp,\"video\":{\"room\":\"test\",\"roomJoin\":true,\"canPublish\":true,\"canSubscribe\":true}}" | base64 | tr '+/' '-_' | tr -d '=')
    local signature=$(echo -n "$header.$payload" | openssl dgst -sha256 -mac HMAC -macopt key:"$LIVEKIT_API_SECRET" -binary | base64 | tr '+/' '-_' | tr -d '=')
    echo "$header.$payload.$signature"
}

TOKEN=$(generate_jwt)
if [ -n "$TOKEN" ]; then
    echo "✅ JWT token generated successfully"
    echo "Token (first 50 chars): ${TOKEN:0:50}..."
else
    echo "❌ Failed to generate JWT token"
fi
echo ""

# 8. TEST EGRESS API ENDPOINT
echo "8. TEST EGRESS API ENDPOINT"
echo "-------------------"
echo "Attempting to call LiveKit Egress API..."

EGRESS_DATA='{
  "room_name": "test_room_diagnostic",
  "layout": "custom",
  "custom_base_url": "https://distant.ndu.edu.az/teacher/live-record_view.php?id=999",
  "file": {
    "filepath": "recordings/diagnostic_test.mp4"
  }
}'

EGRESS_RESPONSE=$(curl -sk -m 10 \
  -X POST "$LIVEKIT_URL/twirp/livekit.Egress/StartRoomCompositeEgress" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "$EGRESS_DATA" \
  -w "\n%{http_code}" 2>&1)

EGRESS_HTTP_CODE=$(echo "$EGRESS_RESPONSE" | tail -1)
EGRESS_BODY=$(echo "$EGRESS_RESPONSE" | head -n-1)

echo "HTTP Status: $EGRESS_HTTP_CODE"
echo "Response: $EGRESS_BODY"

if [ "$EGRESS_HTTP_CODE" = "200" ]; then
    echo "✅ LiveKit Egress API working correctly"
elif [ "$EGRESS_HTTP_CODE" = "401" ]; then
    echo "❌ Authentication failed - Invalid API Key/Secret"
elif [ "$EGRESS_HTTP_CODE" = "503" ]; then
    echo "❌ Service unavailable - LiveKit server not responding to API"
elif [ "$EGRESS_HTTP_CODE" = "000" ]; then
    echo "❌ Connection timeout or refused - LiveKit not reachable"
else
    echo "⚠️  Unexpected HTTP code $EGRESS_HTTP_CODE"
fi
echo ""

# 9. CHECK NGINX REVERSE PROXY (if applicable)
echo "9. NGINX REVERSE PROXY STATUS"
echo "-------------------"
if systemctl is-active --quiet nginx; then
    echo "✅ Nginx is running"
    if nginx -T 2>&1 | grep -q "distant-l"; then
        echo "✅ Nginx has LiveKit configuration"
        echo "Checking LiveKit upstream..."
        curl -sk -m 5 "https://distant-l.ndu.edu.az/stats" &> /dev/null && echo "✅ Nginx reverse proxy is working" || echo "⚠️  Nginx reverse proxy may have issues"
    else
        echo "⚠️  No LiveKit configuration found in nginx"
    fi
else
    echo "⚠️  Nginx is not running or not installed"
fi
echo ""

# 10. CHECK FIREWALL RULES
echo "10. FIREWALL STATUS"
echo "-------------------"
if command -v ufw &> /dev/null; then
    echo "UFW Rules for LiveKit ports:"
    ufw status | grep -E "7880|7881|7882" || echo "⚠️  No UFW rules for LiveKit ports"
else
    echo "ℹ️  UFW not installed"
fi
echo ""

# 11. CHECK DOCKER LOGS
echo "11. LIVEKIT DOCKER LOGS (last 30 lines)"
echo "-------------------"
if command -v docker &> /dev/null && docker ps --filter "name=livekit" --format "{{.Names}}" | grep -q livekit; then
    docker logs --tail 30 $(docker ps --filter "name=livekit" --format "{{.Names}}") 2>&1 | tail -30
else
    echo "ℹ️  Docker or LiveKit container not available"
fi
echo ""

# 12. SYSTEM RESOURCES
echo "12. SYSTEM RESOURCES"
echo "-------------------"
if command -v docker &> /dev/null && docker ps --filter "name=livekit" --format "{{.Names}}" | grep -q livekit; then
    echo "LiveKit container resource usage:"
    docker stats --no-stream $(docker ps --filter "name=livekit" --format "{{.Names}}")
else
    echo "ℹ️  Container stats not available"
fi
echo ""

# 13. TURN SERVER STATUS (verification)
echo "13. TURN SERVER STATUS (Required for LiveKit)"
echo "-------------------"
if systemctl is-active --quiet coturn; then
    echo "✅ TURN server (coturn) is running"
    for PORT in 3478 5349; do
        if nc -zv -w 2 127.0.0.1 $PORT &> /dev/null; then
            echo "✅ TURN port $PORT is listening"
        else
            echo "❌ TURN port $PORT is NOT listening"
        fi
    done
else
    echo "⚠️  TURN server is not running"
fi
echo ""

# 14. SUMMARY & RECOMMENDATIONS
echo "14. DIAGNOSTIC SUMMARY & RECOMMENDATIONS"
echo "-------------------"
echo ""
echo "Quick Checks:"
echo "- Is Docker running with LiveKit container? $(docker ps --filter 'name=livekit' --format '{{.Names}}' | grep -q livekit && echo '✅ YES' || echo '❌ NO')"
echo "- Are ports 7880/7881/7882 listening? $(nc -zv -w 1 127.0.0.1 7880 &>/dev/null && echo '✅ YES' || echo '❌ NO')"
echo "- Can we reach the API? $(curl -sk -m 2 "$LIVEKIT_URL/stats" &>/dev/null && echo '✅ YES' || echo '❌ NO')"
echo ""
echo "If LiveKit is not working:"
echo "1. Check if Docker is running: docker ps"
echo "2. Check Docker logs: docker logs livekit (or your container name)"
echo "3. Restart LiveKit: docker restart livekit"
echo "4. Check ports are not blocked: ufw status"
echo "5. Verify SSL certificates: echo | openssl s_client -connect $LIVEKIT_HOST:443"
echo "6. Check Nginx config: nginx -T | grep -A 20 'distant-l'"
echo ""

echo "=========================================="
echo "END OF REPORT"
echo "=========================================="
