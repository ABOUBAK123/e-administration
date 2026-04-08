#!/usr/bin/env bash
set -euo pipefail

# Automated production deployment for:
# Ubuntu + Webmin + Apache + PostgreSQL
# Project: E-administration
#
# Usage:
#   sudo REPO_URL="https://...git" DOMAIN="example.com" ADMIN_EMAIL="admin@example.com" \
#        DB_PASSWORD="StrongPassword" JWT_SECRET="VeryLongSecret" \
#        bash scripts/deploy_ubuntu_webmin_apache_postgresql.sh
#
# Optional environment variables:
#   APP_USER=eadmin
#   APP_HOME=/opt/e-administration
#   APP_PATH=/opt/e-administration/app
#   DB_NAME=e_parapheur_prod
#   DB_USER=ep_admin_prod
#   DB_PORT=5432
#   API_PORT=3000
#   ENABLE_SSL=true
#   ENABLE_UFW=true
#   ONLYOFFICE_URL=https://onlyoffice.example.com
#   FRONTEND_URL=https://example.com
#   API_URL=https://example.com

#######################################
# Parameters
#######################################
APP_USER="${APP_USER:-eadmin}"
APP_GROUP="${APP_GROUP:-$APP_USER}"
APP_HOME="${APP_HOME:-/opt/e-administration}"
APP_PATH="${APP_PATH:-$APP_HOME/app}"
REPO_URL="${REPO_URL:-}"
DOMAIN="${DOMAIN:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"

DB_NAME="${DB_NAME:-e_parapheur_prod}"
DB_USER="${DB_USER:-ep_admin_prod}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_PORT="${DB_PORT:-5432}"

API_PORT="${API_PORT:-3000}"
ENABLE_SSL="${ENABLE_SSL:-true}"
ENABLE_UFW="${ENABLE_UFW:-true}"

FRONTEND_URL="${FRONTEND_URL:-}"
API_URL="${API_URL:-}"
ONLYOFFICE_URL="${ONLYOFFICE_URL:-}"
JWT_SECRET="${JWT_SECRET:-}"

#######################################
# Helpers
#######################################
log() { echo "[INFO] $*"; }
warn() { echo "[WARN] $*"; }
err() { echo "[ERROR] $*" >&2; }

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    err "Run as root (or with sudo)."
    exit 1
  fi
}

require_vars() {
  local missing=0
  for v in REPO_URL DOMAIN DB_PASSWORD JWT_SECRET; do
    if [[ -z "${!v:-}" ]]; then
      err "Missing required env var: ${v}"
      missing=1
    fi
  done
  if [[ "${ENABLE_SSL}" == "true" && -z "${ADMIN_EMAIL}" ]]; then
    err "ADMIN_EMAIL is required when ENABLE_SSL=true"
    missing=1
  fi
  if [[ "$missing" -eq 1 ]]; then
    exit 1
  fi
}

set_kv() {
  local file="$1"
  local key="$2"
  local value="$3"

  if grep -qE "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    echo "${key}=${value}" >> "$file"
  fi
}

#######################################
# Steps
#######################################
install_system_packages() {
  log "Installing system packages..."
  apt update
  apt install -y ca-certificates curl gnupg unzip rsync build-essential git apache2 \
    postgresql postgresql-contrib

  if ! command -v node >/dev/null 2>&1; then
    log "Installing Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
  fi

  a2enmod rewrite headers proxy proxy_http proxy_wstunnel ssl
  systemctl enable apache2
  systemctl restart apache2
}

create_user_and_dirs() {
  log "Creating deployment user and directories..."
  if ! id "$APP_USER" >/dev/null 2>&1; then
    adduser --system --group --home "$APP_HOME" "$APP_USER"
  fi

  mkdir -p "$APP_HOME"
  mkdir -p /var/www/e-administration/frontend
  mkdir -p /opt/e-administration/storage

  chown -R "$APP_USER:$APP_GROUP" "$APP_HOME"
  chown -R "$APP_USER:$APP_GROUP" /opt/e-administration/storage
  chown -R www-data:www-data /var/www/e-administration
}

clone_or_update_repo() {
  log "Cloning/updating repository..."
  if [[ -d "$APP_PATH/.git" ]]; then
    sudo -u "$APP_USER" -H bash -lc "cd '$APP_PATH' && git fetch --all && git pull --ff-only"
  else
    sudo -u "$APP_USER" -H bash -lc "cd '$APP_HOME' && git clone '$REPO_URL' app"
  fi
}

configure_postgresql() {
  log "Configuring PostgreSQL database and user..."

  sudo -u postgres psql <<SQL
DO
\$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
    CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';
  ELSE
    ALTER ROLE ${DB_USER} WITH PASSWORD '${DB_PASSWORD}';
  END IF;
END
\$\$;
SQL

  sudo -u postgres psql <<SQL
DO
\$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_database WHERE datname = '${DB_NAME}') THEN
    CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};
  END IF;
END
\$\$;
SQL

  sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
}

configure_env_files() {
  log "Configuring backend and frontend environment files..."

  if [[ -z "$FRONTEND_URL" ]]; then
    FRONTEND_URL="https://${DOMAIN}"
  fi
  if [[ -z "$API_URL" ]]; then
    API_URL="https://${DOMAIN}"
  fi
  if [[ -z "$ONLYOFFICE_URL" ]]; then
    ONLYOFFICE_URL="https://onlyoffice.${DOMAIN}"
  fi

  local backend_env="$APP_PATH/apps/backend/.env"
  local frontend_env="$APP_PATH/apps/frontend/.env"

  sudo -u "$APP_USER" -H bash -lc "
    cd '$APP_PATH/apps/backend' && cp -n .env.example .env
    cd '$APP_PATH/apps/frontend' && cp -n .env.example .env
  "

  set_kv "$backend_env" "NODE_ENV" "production"
  set_kv "$backend_env" "API_PORT" "$API_PORT"
  set_kv "$backend_env" "API_URL" "$API_URL"
  set_kv "$backend_env" "FRONTEND_URL" "$FRONTEND_URL"

  set_kv "$backend_env" "DB_TYPE" "postgres"
  set_kv "$backend_env" "DB_HOST" "127.0.0.1"
  set_kv "$backend_env" "DB_PORT" "$DB_PORT"
  set_kv "$backend_env" "DB_USER" "$DB_USER"
  set_kv "$backend_env" "DB_PASSWORD" "$DB_PASSWORD"
  set_kv "$backend_env" "DB_NAME" "$DB_NAME"
  set_kv "$backend_env" "DB_SYNCHRONIZE" "false"
  set_kv "$backend_env" "DB_LOGGING" "false"
  set_kv "$backend_env" "DB_SSL" "false"

  set_kv "$backend_env" "JWT_SECRET" "$JWT_SECRET"
  set_kv "$backend_env" "JWT_EXPIRATION" "3600"

  set_kv "$backend_env" "STORAGE_PATH" "/opt/e-administration/storage"
  set_kv "$backend_env" "MAX_FILE_SIZE" "104857600"
  set_kv "$backend_env" "ONLYOFFICE_URL" "$ONLYOFFICE_URL"

  set_kv "$frontend_env" "VITE_API_URL" "${API_URL}/api/v1"
  set_kv "$frontend_env" "VITE_ONLYOFFICE_URL" "$ONLYOFFICE_URL"
  set_kv "$frontend_env" "VITE_ENV" "production"

  chown "$APP_USER:$APP_GROUP" "$backend_env" "$frontend_env"
}

install_and_build() {
  log "Installing node dependencies and building project..."
  sudo -u "$APP_USER" -H bash -lc "
    cd '$APP_PATH'
    npm ci
    npm run build
  "

  log "Publishing frontend build to Apache document root..."
  rsync -av --delete "$APP_PATH/apps/frontend/dist/" /var/www/e-administration/frontend/
  chown -R www-data:www-data /var/www/e-administration/frontend
}

run_migrations() {
  log "Running database migrations..."
  sudo -u "$APP_USER" -H bash -lc "
    cd '$APP_PATH/apps/backend'
    npm run migration:run
  "
}

create_systemd_service() {
  log "Creating systemd service for backend..."
  cat >/etc/systemd/system/e-administration-backend.service <<EOF
[Unit]
Description=E-administration Backend (NestJS)
After=network.target postgresql.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_PATH}/apps/backend
Environment=NODE_ENV=production
ExecStart=/usr/bin/node ${APP_PATH}/apps/backend/dist/main.js
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable e-administration-backend
  systemctl restart e-administration-backend
}

configure_apache_vhost() {
  log "Configuring Apache virtual host..."

  cat >/etc/apache2/sites-available/e-administration.conf <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}

    DocumentRoot /var/www/e-administration/frontend

    <Directory /var/www/e-administration/frontend>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.html
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ /index.html [L]

    ProxyPreserveHost On
    ProxyPass /api http://127.0.0.1:${API_PORT}/api
    ProxyPassReverse /api http://127.0.0.1:${API_PORT}/api

    ProxyPass /socket.io ws://127.0.0.1:${API_PORT}/socket.io
    ProxyPassReverse /socket.io ws://127.0.0.1:${API_PORT}/socket.io

    ErrorLog \${APACHE_LOG_DIR}/e-administration-error.log
    CustomLog \${APACHE_LOG_DIR}/e-administration-access.log combined
</VirtualHost>
EOF

  a2dissite 000-default.conf || true
  a2ensite e-administration.conf
  apache2ctl configtest
  systemctl reload apache2
}

configure_ssl_if_enabled() {
  if [[ "$ENABLE_SSL" != "true" ]]; then
    warn "SSL skipped (ENABLE_SSL=false)."
    return
  fi

  log "Installing and configuring certbot SSL..."
  apt install -y certbot python3-certbot-apache
  certbot --apache --non-interactive --agree-tos -m "$ADMIN_EMAIL" -d "$DOMAIN" -d "www.${DOMAIN}" --redirect
  certbot renew --dry-run || true
}

configure_ufw_if_enabled() {
  if [[ "$ENABLE_UFW" != "true" ]]; then
    warn "UFW skipped (ENABLE_UFW=false)."
    return
  fi

  log "Configuring UFW firewall..."
  ufw allow OpenSSH || true
  ufw allow "Apache Full" || true
  yes | ufw enable || true
}

health_checks() {
  log "Running health checks..."
  systemctl --no-pager --full status postgresql | sed -n '1,12p' || true
  systemctl --no-pager --full status apache2 | sed -n '1,12p' || true
  systemctl --no-pager --full status e-administration-backend | sed -n '1,20p' || true

  if curl -fsS "http://127.0.0.1:${API_PORT}/api/v1" >/dev/null 2>&1; then
    log "Backend API reachable on localhost:${API_PORT}"
  else
    warn "Backend API check failed on localhost:${API_PORT}"
  fi

  log "Deployment completed."
  log "Open: ${FRONTEND_URL}"
}

#######################################
# Main
#######################################
require_root
require_vars
install_system_packages
create_user_and_dirs
clone_or_update_repo
configure_postgresql
configure_env_files
install_and_build
run_migrations
create_systemd_service
configure_apache_vhost
configure_ssl_if_enabled
configure_ufw_if_enabled
health_checks
