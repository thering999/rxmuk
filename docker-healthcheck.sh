#!/bin/bash
# Health check script for rxmuk application

set -e

echo "=== rxmuk Health Check ===" 

# Check PHP
echo "✓ Checking PHP..."
php --version | head -n 1

# Check Apache
echo "✓ Checking Apache..."
apache2ctl -v | head -n 1

# Check MySQL connectivity
echo "✓ Checking MySQL..."
if ! mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" --silent 2>/dev/null; then
    echo "✗ MySQL connection failed"
    exit 1
fi
echo "✓ MySQL connected"

# Check database tables
echo "✓ Checking database tables..."
if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null | grep -q '[0-9]'; then
    echo "✓ Database tables exist"
else
    echo "✗ No tables found"
    exit 1
fi

# Check application
echo "✓ Checking application..."
if curl -f http://localhost/ >/dev/null 2>&1; then
    echo "✓ Application responding"
else
    echo "✗ Application not responding"
    exit 1
fi

# Check uploads directory
echo "✓ Checking uploads directory..."
if [ -w /var/www/html/uploads ]; then
    echo "✓ Uploads directory writable"
else
    echo "✗ Uploads directory not writable"
    exit 1
fi

echo ""
echo "=== All checks passed ✓ ==="
exit 0
