#!/bin/bash

# Deploy LiveKit Nginx Configuration
# This script sets up the reverse proxy for distant-l.ndu.edu.az

set -e

DOMAIN="distant-l.ndu.edu.az"
CONFIG_FILE="/etc/nginx/sites-available/$DOMAIN"
SOURCE_FILE="./distant-l.ndu.edu.az-nginx.conf"

echo "=========================================="
echo "LiveKit Nginx Configuration Deployment"
echo "=========================================="
echo ""

# 1. Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run as root"
    exit 1
fi

# 2. Copy config file
echo "1. Copying Nginx configuration..."
if [ ! -f "$SOURCE_FILE" ]; then
    echo "❌ Configuration file not found: $SOURCE_FILE"
    exit 1
fi
cp "$SOURCE_FILE" "$CONFIG_FILE"
echo "✅ Config copied to $CONFIG_FILE"

# 3. Create symlink if it doesn't exist
echo ""
echo "2. Creating site symlink..."
if [ -L "/etc/nginx/sites-enabled/$DOMAIN" ]; then
    rm "/etc/nginx/sites-enabled/$DOMAIN"
fi
ln -s "$CONFIG_FILE" "/etc/nginx/sites-enabled/$DOMAIN"
echo "✅ Symlink created"

# 4. Test Nginx configuration
echo ""
echo "3. Testing Nginx configuration..."
if nginx -t; then
    echo "✅ Nginx configuration is valid"
else
    echo "❌ Nginx configuration has errors"
    exit 1
fi

# 5. Reload Nginx
echo ""
echo "4. Reloading Nginx..."
systemctl reload nginx
echo "✅ Nginx reloaded"

# 6. Configure Firewall (UFW)
echo ""
echo "5. Configuring firewall rules..."
ufw allow 7880/tcp
ufw allow 7881/tcp
ufw allow 7882/tcp
ufw allow 7882/udp
ufw allow 49152:65535/udp
echo "✅ Firewall rules added"

# 7. Summary
echo ""
echo "=========================================="
echo "✅ DEPLOYMENT COMPLETE"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Verify LiveKit container is running:"
echo "   docker ps | grep livekit"
echo ""
echo "2. Test the configuration:"
echo "   curl -k https://$DOMAIN/stats"
echo ""
echo "3. Run the diagnostic script:"
echo "   ./check-livekit.sh"
echo ""
echo "4. Monitor logs:"
echo "   tail -f /var/log/nginx/distant-l_error.log"
echo ""
