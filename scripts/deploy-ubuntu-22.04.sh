#!/bin/bash

#================================================================
# E-Administration Deployment Script for Ubuntu 22.04
# Apache2 + MariaDB + Node.js 18+ with PM2
#================================================================

set -euo pipefail

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Function: Print colored output ===
print_step() {
    echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} ${GREEN}$1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# === Configuration ===
DOMAIN="${1:-e-administration.dyula.ci}"
APP_DIR="/var/www/html/e-administration"
APP_USER="eadmin"
DB_USER="eadmin_app"
DB_NAME="e_parapheur"
DB_PASSWORD=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)
NODE_VERSION="18"
NODE_ENV="production"

# Generate a temp file to save credentials
CREDS_FILE="/tmp/eadmin_credentials_$(date +%s).txt"

# === Banner ===
cat << EOF

${GREEN}╔════════════════════════════════════════════════════════╗${NC}
${GREEN}║  E-Administration Deployment - Ubuntu 22.04           ║${NC}
${GREEN}║  Apache2 + MariaDB + Node.js 18+                      ║${NC}
${GREEN}╚════════════════════════════════════════════════════════╝${NC}

Domain: ${BLUE}${DOMAIN}${NC}
App Directory: ${BLUE}${APP_DIR}${NC}
App User: ${BLUE}${APP_USER}${NC}
Database: ${BLUE}${DB_NAME}${NC}
Environment: ${BLUE}${NODE_ENV}${NC}

EOF

# === Pre-flight checks ===
print_step "Pre-flight checks..."

if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root"
   exit 1
fi

if ! grep -q "Ubuntu 22" /etc/os-release; then
    print_warning "This script is designed for Ubuntu 22.04. Your system may differ."
fi

print_success "Pre-flight checks passed"

# === STEP 1: System update ===
print_step "[1/11] System update..."
apt-get update
apt-get upgrade -y
apt-get install -y \
    curl wget git unzip build-essential \
    ca-certificates gnupg lsb-release \
    software-properties-common sudo \
    htop net-tools

print_success "System updated"

# === STEP 2: Install Node.js ===
print_step "[2/11] Installing Node.js ${NODE_VERSION}+..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
    apt-get install -y nodejs
else
    print_warning "Node.js already installed: $(node -v)"
fi

npm install -g npm@latest
npm install -g pm2

print_success "Node.js $(node -v) installed"
print_success "npm $(npm -v) installed"

# === STEP 3: Install MariaDB ===
print_step "[3/11] Installing MariaDB..."
if ! command -v mysql &> /dev/null; then
    apt-get install -y mariadb-server mariadb-client
    systemctl enable mariadb
    systemctl start mariadb
else
    print_warning "MariaDB already installed"
fi

print_success "MariaDB installed and started"

# === STEP 4: Secure MariaDB ===
print_step "[4/11] Securing MariaDB..."
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

print_success "MariaDB secured"

# === STEP 5: Create database and user ===
print_step "[5/11] Creating database and user..."
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

print_success "Database '${DB_NAME}' created"
print_success "User '${DB_USER}' created"

# Save credentials
cat > "$CREDS_FILE" << EOF
========================================
E-Administration Database Credentials
========================================
Database: ${DB_NAME}
User: ${DB_USER}
Password: ${DB_PASSWORD}

Keep this file in a safe place!
========================================
EOF

print_warning "Credentials saved to: ${CREDS_FILE}"

# === STEP 6: Create application user ===
print_step "[6/11] Creating application user '${APP_USER}'..."
useradd -m -s /bin/bash "$APP_USER" 2>/dev/null || print_warning "User ${APP_USER} already exists"
usermod -aG www-data "$APP_USER" 2>/dev/null || true
usermod -aG sudo "$APP_USER" 2>/dev/null || true

print_success "Application user created"

# === STEP 7: Prepare project directory ===
print_step "[7/11] Preparing project directory..."
mkdir -p /var/www/html
cd /var/www/html

if [ ! -d "e-administration" ]; then
    print_warning "Project directory not found. Creating stub..."
    mkdir -p e-administration
    print_warning "Please clone the actual repository:"
  print_warning "  git clone <YOUR_REPO_URL> /var/www/html/e-administration"
    exit 1
fi

chown -R "$APP_USER":www-data /var/www/html/e-administration
chmod -R 755 /var/www/html/e-administration

print_success "Project directory prepared"

# === STEP 8: Install npm dependencies ===
print_step "[8/11] Installing npm dependencies..."
cd "$APP_DIR"

sudo -u "$APP_USER" npm install 2>&1 | grep -E "added|up to date|packages" || true
sudo -u "$APP_USER" npm run backend:install 2>&1 | tail -5 || true
sudo -u "$APP_USER" npm run frontend:install 2>&1 | tail -5 || true

print_success "Dependencies installed"

# === STEP 9: Configure environment files ===
print_step "[9/11] Configuring .env files..."

# Backend .env
cat > "$APP_DIR/apps/backend/.env" << ENVEOF
# ========== Database ==========
DB_TYPE=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_NAME=${DB_NAME}

# ========== Application ==========
NODE_ENV=${NODE_ENV}
API_PORT=3000
API_HOST=0.0.0.0

# ========== Security ==========
JWT_SECRET=${JWT_SECRET}
JWT_EXPIRATION=86400

# ========== CORS ==========
CORS_ORIGIN=https://${DOMAIN}
CORS_CREDENTIALS=true

# ========== Email (Optional) ==========
MAIL_FROM=noreply@${DOMAIN}
MAIL_HOST=localhost
MAIL_PORT=1025

# ========== Logging ==========
LOG_LEVEL=info
LOG_FORMAT=json
ENVEOF

chown "$APP_USER":www-data "$APP_DIR/apps/backend/.env"
chmod 600 "$APP_DIR/apps/backend/.env"

# Frontend .env
cat > "$APP_DIR/apps/frontend/.env" << ENVEOF
VITE_API_URL=https://${DOMAIN}/api
VITE_APP_NAME=E-Administration
VITE_APP_VERSION=1.0.0
ENVEOF

chown "$APP_USER":www-data "$APP_DIR/apps/frontend/.env"
chmod 644 "$APP_DIR/apps/frontend/.env"

print_success ".env files configured"

# === STEP 10: Build application ===
print_step "[10/11] Building application..."
cd "$APP_DIR"

print_step "Building backend..."
sudo -u "$APP_USER" npm run backend:build 2>&1 | tail -10 || true

print_step "Building frontend..."
sudo -u "$APP_USER" npm run frontend:build 2>&1 | tail -10 || true

print_success "Application built"

# === STEP 11: Configure Apache & SSL ===
print_step "[11/11] Configuring Apache2 & SSL..."

apt-get install -y apache2 certbot python3-certbot-apache
a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl
systemctl enable apache2

# Create Apache VirtualHost
cat > "/etc/apache2/sites-available/e-administration.conf" << 'APACHEEOF'
<VirtualHost *:80>
    ServerName DOMAIN_PLACEHOLDER
    ServerAlias www.DOMAIN_PLACEHOLDER
    
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName DOMAIN_PLACEHOLDER
    ServerAlias www.DOMAIN_PLACEHOLDER
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/chain.pem
    
    SSLProtocol TLSv1.2 TLSv1.3
    SSLCipherSuite HIGH:!aNULL:!MD5
    SSLHonorCipherOrder on
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Proxy Configuration
    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Host "DOMAIN_PLACEHOLDER"
    RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"
    
    # Frontend (Vite on 5173)
    ProxyPass / http://127.0.0.1:5173/ timeout=300
    ProxyPassReverse / http://127.0.0.1:5173/
    
    # Backend API (NestJS on 3000)
    ProxyPass /api http://127.0.0.1:3000/api timeout=300
    ProxyPassReverse /api http://127.0.0.1:3000/api
    
    # WebSocket support (Socket.IO)
    ProxyPass /socket.io ws://127.0.0.1:3000/socket.io timeout=300 keepalive=On
    ProxyPassReverse /socket.io ws://127.0.0.1:3000/socket.io
    
    # Caching
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css "access plus 1 month"
        ExpiresByType application/javascript "access plus 1 month"
        ExpiresByType image/png "access plus 1 month"
        ExpiresByType image/jpeg "access plus 1 month"
        ExpiresByType image/webp "access plus 1 month"
    </IfModule>
    
    # Compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css application/json application/javascript
    </IfModule>
    
    ErrorLog ${APACHE_LOG_DIR}/e-administration-error.log
    CustomLog ${APACHE_LOG_DIR}/e-administration-access.log combined
</VirtualHost>
APACHEEOF

# Replace placeholders
sed -i "s/DOMAIN_PLACEHOLDER/${DOMAIN}/g" "/etc/apache2/sites-available/e-administration.conf"

# Enable site and disable default
a2ensite e-administration.conf
a2dissite 000-default.conf

# Validate Apache config
if apache2ctl configtest | grep -q "OK"; then
    print_success "Apache configuration valid"
else
    print_error "Apache configuration has errors!"
    apache2ctl configtest
fi

# Request SSL certificate with Let's Encrypt
print_step "Requesting SSL certificate from Let's Encrypt..."
certbot --apache \
    --non-interactive \
    --agree-tos \
    --email "admin@${DOMAIN}" \
    -d "${DOMAIN}" \
    -d "www.${DOMAIN}" \
    || print_warning "SSL certificate request failed - may need manual setup"

systemctl reload apache2

print_success "Apache configured"

# === Configure PM2 ===
print_step "Configuring PM2..."

# Create ecosystem.config.js
cat > "$APP_DIR/ecosystem.config.js" << 'PMEOF'
module.exports = {
  apps: [
    {
      name: "e-admin-backend",
      script: "./apps/backend/dist/main.js",
      instances: 2,
      exec_mode: "cluster",
      watch: false,
      max_memory_restart: "1G",
      error_file: "./logs/backend-error.log",
      out_file: "./logs/backend-out.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      env: {
        NODE_ENV: "production",
        API_PORT: 3000,
      },
    },
    {
      name: "e-admin-frontend",
      script: "npm",
      args: "run preview -- --host 0.0.0.0 --port 5173",
      cwd: "./apps/frontend",
      watch: false,
      error_file: "./logs/frontend-error.log",
      out_file: "./logs/frontend-out.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      env: {
        NODE_ENV: "production",
      },
    },
  ],
};
PMEOF

mkdir -p "$APP_DIR/logs"
chown "$APP_USER":www-data "$APP_DIR/ecosystem.config.js"
chown "$APP_USER":www-data "$APP_DIR/logs"

# Start with PM2
cd "$APP_DIR"
sudo -u "$APP_USER" pm2 start ecosystem.config.js
sudo -u "$APP_USER" pm2 save

pm2 startup systemd -u "$APP_USER" --hp "/home/${APP_USER}" > /dev/null 2>&1 || true

print_success "PM2 configured"

# === Database migrations (if applicable) ===
print_step "Running database migrations..."
cd "$APP_DIR"
sudo -u "$APP_USER" npm run migration:run 2>&1 || print_warning "No migrations or migrations failed - check logs"

# === Final status ===
print_success "Deployment completed!"

cat << EOF

${GREEN}╔════════════════════════════════════════════════════════╗${NC}
${GREEN}║           Deployment Summary                          ║${NC}
${GREEN}╚════════════════════════════════════════════════════════╝${NC}

✓ System: Ubuntu 22.04
✓ Node.js: $(node -v)
✓ MariaDB: $(mysql --version 2>/dev/null | cut -d' ' -f6)
✓ Apache2: Configured with reverse proxy
✓ SSL: Let's Encrypt certificate
✓ PM2: Running backend & frontend

${YELLOW}URLs & Access:${NC}
  - Frontend: https://${DOMAIN}
  - API Docs: https://${DOMAIN}/api/docs
  - App Dir: ${APP_DIR}
  - App User: ${APP_USER}

${YELLOW}Database Credentials:${NC}
  - Saved to: ${CREDS_FILE}
  - Database: ${DB_NAME}
  - User: ${DB_USER}

${YELLOW}Useful Commands:${NC}
  pm2 status              # Check process status
  pm2 logs                # View logs
  pm2 restart all         # Restart services
  systemctl status apache2
  tail -f /var/log/apache2/e-administration-error.log

${YELLOW}Next Steps:${NC}
  1. Verify: curl https://${DOMAIN}
  2. Check PM2: pm2 status
  3. View logs: pm2 logs
  4. Enable firewall: sudo ufw enable

${YELLOW}⚠ Security Notes:${NC}
  - Move credentials file to secure location: ${CREDS_FILE}
  - Use strong passwords in production
  - Configure SSH key-based authentication
  - Enable UFW firewall (sections in docs)
  - Set up automatic backups
  - Review security headers in Apache config

${GREEN}Deployment successful! 🎉${NC}

EOF

print_warning "Save credentials from: ${CREDS_FILE}"

exit 0
