#!/bin/bash

#================================================================
# E-Administration Post-Deployment Verification
# Check if all services are running and responding correctly
#================================================================

set -euo pipefail

# === Colors ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Function: Check command
check_pass() {
    echo -e "${GREEN}✓${NC} $1"
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    FAILED=$((FAILED + 1))
}

check_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

check_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

# Initialize counters
PASSED=0
FAILED=0
WARNINGS=0

# Configuration
DOMAIN="${1:-e-administration.dyula.ci}"
APP_DIR="/var/www/html/e-administration"
APP_USER="eadmin"

# === Banner ===
echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║    E-Administration Post-Deployment Verification       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# === System Checks ===
echo -e "${BLUE}[1] System Services${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    check_pass "Node.js installed: $NODE_VERSION"
    PASSED=$((PASSED + 1))
else
    check_fail "Node.js not found"
    FAILED=$((FAILED + 1))
fi

# Check npm
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm -v)
    check_pass "npm installed: $NPM_VERSION"
    PASSED=$((PASSED + 1))
else
    check_fail "npm not found"
    FAILED=$((FAILED + 1))
fi

# Check MariaDB
if sudo systemctl is-active --quiet mariadb; then
    check_pass "MariaDB service running"
    PASSED=$((PASSED + 1))
else
    check_fail "MariaDB service not running"
    FAILED=$((FAILED + 1))
fi

# Check Apache2
if sudo systemctl is-active --quiet apache2; then
    check_pass "Apache2 service running"
    PASSED=$((PASSED + 1))
else
    check_fail "Apache2 service not running"
    FAILED=$((FAILED + 1))
fi

# Check PM2
if command -v pm2 &> /dev/null; then
    check_pass "PM2 installed"
    PASSED=$((PASSED + 1))
else
    check_fail "PM2 not installed"
    FAILED=$((FAILED + 1))
fi

echo ""

# === Port Checks ===
echo -e "${BLUE}[2] Port Availability${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check Backend port (3000)
if sudo netstat -tulpn 2>/dev/null | grep -q :3000; then
    check_pass "Backend port 3000 listening"
    PASSED=$((PASSED + 1))
else
    check_fail "Backend port 3000 NOT listening"
    FAILED=$((FAILED + 1))
fi

# Check Frontend port (5173)
if sudo netstat -tulpn 2>/dev/null | grep -q :5173; then
    check_pass "Frontend port 5173 listening"
    PASSED=$((PASSED + 1))
else
    check_fail "Frontend port 5173 NOT listening"
    FAILED=$((FAILED + 1))
fi

# Check HTTP (80)
if sudo netstat -tulpn 2>/dev/null | grep -q :80; then
    check_pass "HTTP port 80 listening"
    PASSED=$((PASSED + 1))
else
    check_fail "HTTP port 80 NOT listening"
    FAILED=$((FAILED + 1))
fi

# Check HTTPS (443)
if sudo netstat -tulpn 2>/dev/null | grep -q :443; then
    check_pass "HTTPS port 443 listening"
    PASSED=$((PASSED + 1))
else
    check_warning "HTTPS port 443 NOT listening (ensure SSL is configured)"
    WARNINGS=$((WARNINGS + 1))
fi

# Check MariaDB port (3306)
if sudo netstat -tulpn 2>/dev/null | grep -q :3306; then
    check_pass "MariaDB port 3306 listening"
    PASSED=$((PASSED + 1))
else
    check_fail "MariaDB port 3306 NOT listening"
    FAILED=$((FAILED + 1))
fi

echo ""

# === PM2 Status ===
echo -e "${BLUE}[3] PM2 Processes${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if pm2 status 2>/dev/null | grep -q "e-admin-backend"; then
    if pm2 status 2>/dev/null | grep "e-admin-backend" | grep -q "online"; then
        check_pass "Backend (e-admin-backend) online"
        PASSED=$((PASSED + 1))
    else
        check_fail "Backend (e-admin-backend) not online"
        FAILED=$((FAILED + 1))
    fi
else
    check_fail "Backend (e-admin-backend) not in PM2"
    FAILED=$((FAILED + 1))
fi

if pm2 status 2>/dev/null | grep -q "e-admin-frontend"; then
    if pm2 status 2>/dev/null | grep "e-admin-frontend" | grep -q "online"; then
        check_pass "Frontend (e-admin-frontend) online"
        PASSED=$((PASSED + 1))
    else
        check_fail "Frontend (e-admin-frontend) not online"
        FAILED=$((FAILED + 1))
    fi
else
    check_fail "Frontend (e-admin-frontend) not in PM2"
    FAILED=$((FAILED + 1))
fi

echo ""

# === Connectivity Tests ===
echo -e "${BLUE}[4] Connectivity Tests${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test Backend local
if curl -s http://127.0.0.1:3000 > /dev/null 2>&1; then
    check_pass "Backend responding on localhost:3000"
    PASSED=$((PASSED + 1))
else
    check_warning "Backend not responding on localhost:3000"
    WARNINGS=$((WARNINGS + 1))
fi

# Test Frontend local
if curl -s http://127.0.0.1:5173 > /dev/null 2>&1; then
    check_pass "Frontend responding on localhost:5173"
    PASSED=$((PASSED + 1))
else
    check_warning "Frontend not responding on localhost:5173"
    WARNINGS=$((WARNINGS + 1))
fi

# Test Database connection
if mysql -u eadmin_app -e "SELECT 1" > /dev/null 2>&1; then
    check_pass "Database connection OK"
    PASSED=$((PASSED + 1))
else
    check_warning "Database connection failed (check credentials)"
    WARNINGS=$((WARNINGS + 1))
fi

# Test Apache config
if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
    check_pass "Apache configuration valid"
    PASSED=$((PASSED + 1))
else
    check_fail "Apache configuration invalid"
    FAILED=$((FAILED + 1))
fi

echo ""

# === File & Permissions ===
echo -e "${BLUE}[5] Files & Permissions${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check .env files
if [ -f "$APP_DIR/apps/backend/.env" ]; then
    check_pass "Backend .env file exists"
    PASSED=$((PASSED + 1))
    
    if [ "$(stat -c %a $APP_DIR/apps/backend/.env)" == "600" ]; then
        check_pass "Backend .env permissions correct (600)"
        PASSED=$((PASSED + 1))
    else
        check_warning "Backend .env permissions not 600 (security issue)"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    check_fail "Backend .env file missing"
    FAILED=$((FAILED + 1))
fi

if [ -f "$APP_DIR/apps/frontend/.env" ]; then
    check_pass "Frontend .env file exists"
    PASSED=$((PASSED + 1))
else
    check_warning "Frontend .env file missing"
    WARNINGS=$((WARNINGS + 1))
fi

# Check build directories
if [ -d "$APP_DIR/apps/backend/dist" ]; then
    check_pass "Backend dist directory exists"
    PASSED=$((PASSED + 1))
else
    check_fail "Backend dist directory missing (not built?)"
    FAILED=$((FAILED + 1))
fi

if [ -d "$APP_DIR/apps/frontend/dist" ]; then
    check_pass "Frontend dist directory exists"
    PASSED=$((PASSED + 1))
else
    check_fail "Frontend dist directory missing (not built?)"
    FAILED=$((FAILED + 1))
fi

# Check logs directory
if [ -d "$APP_DIR/logs" ]; then
    check_pass "Logs directory exists"
    PASSED=$((PASSED + 1))
else
    check_warning "Logs directory not found"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# === SSL Certificate ===
echo -e "${BLUE}[6] SSL Certificate${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

CERT_PATH="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
if [ -f "$CERT_PATH" ]; then
    check_pass "SSL certificate found"
    PASSED=$((PASSED + 1))
    
    # Check expiration
    EXPIRY=$(sudo openssl x509 -enddate -noout -in "$CERT_PATH" | cut -d= -f2)
    check_info "Certificate expires: $EXPIRY"
else
    check_warning "SSL certificate not found at $CERT_PATH"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# === Summary ===
echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Verification Summary               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

echo -e "${GREEN}Passed:${NC}   $PASSED checks ✓"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS checks ⚠"
echo -e "${RED}Failed:${NC}   $FAILED checks ✗"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All critical checks passed!${NC}"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "  1. Test frontend: https://${DOMAIN}"
    echo "  2. Test API docs: https://${DOMAIN}/api/docs"
    echo "  3. Check logs: pm2 logs"
    echo "  4. View dashboard: pm2 monit"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Some checks failed. Please review above.${NC}"
    echo ""
    echo -e "${YELLOW}Troubleshooting tips:${NC}"
    echo "  - Check service status: sudo systemctl status <service>"
    echo "  - View PM2 logs: pm2 logs"
    echo "  - View full system logs: sudo journalctl -xe"
    echo "  - Check Apache config: sudo apache2ctl configtest"
    echo ""
    exit 1
fi
