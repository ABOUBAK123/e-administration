#!/usr/bin/env bash
# ============================================================
#  Script de déploiement — exécuté sur le serveur via SSH
#  Appelé par le pipeline GitLab CI/CD
# ============================================================
set -euo pipefail

# ── Configuration ────────────────────────────────────────────
APP_DIR="${DEPLOY_PATH:-/var/www/e-administration}"
ARCHIVE_PATH="${ARCHIVE_PATH:-}"
APP_OWNER="${APP_OWNER:-eadmin}"
LOG_FILE="/var/log/e-administration-deploy.log"

# ── Couleurs pour les logs ───────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log()  { echo -e "${BLUE}[$(date '+%H:%M:%S')] ==> $*${NC}"; }
ok()   { echo -e "${GREEN}[$(date '+%H:%M:%S')] ✅ $*${NC}"; }
warn() { echo -e "${YELLOW}[$(date '+%H:%M:%S')] ⚠️  $*${NC}"; }
fail() { echo -e "${RED}[$(date '+%H:%M:%S')] ❌ $*${NC}"; exit 1; }

# ── Initialisation ───────────────────────────────────────────
echo "" | tee -a "$LOG_FILE" 2>/dev/null || true
log "============================================" | tee -a "$LOG_FILE" 2>/dev/null || true
log "Déploiement démarré — $(date)" | tee -a "$LOG_FILE" 2>/dev/null || true
log "Répertoire : $APP_DIR" | tee -a "$LOG_FILE" 2>/dev/null || true
log "============================================" | tee -a "$LOG_FILE" 2>/dev/null || true

# Ajout de node/npm au PATH si installé via nvm ou nodesource
export PATH="/usr/local/bin:/usr/bin:$HOME/.nvm/versions/node/$(ls $HOME/.nvm/versions/node 2>/dev/null | tail -1)/bin:$PATH"

# ── Vérifications préalables ─────────────────────────────────
log "Vérification des prérequis..."

command -v node  >/dev/null 2>&1 || fail "Node.js non trouvé"
command -v npm   >/dev/null 2>&1 || fail "npm non trouvé"
command -v tar   >/dev/null 2>&1 || fail "tar non trouvé"
command -v pm2   >/dev/null 2>&1 || fail "PM2 non trouvé (npm install -g pm2)"

log "Node  : $(node -v)"
log "npm   : $(npm -v)"
log "PM2   : $(pm2 -v)"

# ── Création/Préparation du répertoire applicatif ───────────
mkdir -p "$APP_DIR"

# ── Déplacement dans le répertoire de l'app ──────────────────
cd "$APP_DIR"

# ── Transfert/Extraction du code source ──────────────────────
if [ -n "$ARCHIVE_PATH" ] && [ -f "$ARCHIVE_PATH" ]; then
  log "Extraction des fichiers déployés depuis $ARCHIVE_PATH..."
  tar -xzf "$ARCHIVE_PATH" -C "$APP_DIR"
  rm -f "$ARCHIVE_PATH"
  ok "Fichiers transférés et extraits dans $APP_DIR"
else
  fail "Archive de déploiement introuvable (ARCHIVE_PATH=$ARCHIVE_PATH)"
fi

# Harmonise les droits pour éviter les EACCES sur npm ci
if id "$APP_OWNER" >/dev/null 2>&1; then
  log "Ajustement des permissions du projet pour l'utilisateur $APP_OWNER..."
  if getent group www-data >/dev/null 2>&1; then
    chown -R "$APP_OWNER":www-data "$APP_DIR"
  else
    chown -R "$APP_OWNER":"$APP_OWNER" "$APP_DIR"
  fi
  ok "Permissions synchronisées"
else
  warn "Utilisateur $APP_OWNER introuvable, permissions non modifiées"
fi

if [ -d "$APP_DIR/.git" ]; then
  COMMIT=$(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo "n/a")
else
  COMMIT="archive"
fi

# ── Création DB optionnelle (MariaDB/MySQL) ──────────────────
if [ "${AUTO_CREATE_DB:-false}" = "true" ]; then
  log "Création/validation de la base de données..."

  command -v mysql >/dev/null 2>&1 || fail "mysql client non trouvé pour la création DB"
  [ -n "${DB_NAME:-}" ] || fail "DB_NAME manquant"
  [ -n "${DB_USER:-}" ] || fail "DB_USER manquant"
  [ -n "${DB_PASSWORD:-}" ] || fail "DB_PASSWORD manquant"

  MYSQL_BASE_CMD=(mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_ROOT_USER:-root}")
  if [ -n "${DB_ROOT_PASSWORD:-}" ]; then
    MYSQL_PWD="${DB_ROOT_PASSWORD}" "${MYSQL_BASE_CMD[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
  else
    "${MYSQL_BASE_CMD[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
  fi
  ok "Base de données prête"
fi

# ── Installation des dépendances ─────────────────────────────
log "Installation des dépendances npm..."
npm ci --prefer-offline 2>&1 | tail -5
ok "Dépendances installées"

# ── Build backend ─────────────────────────────────────────────
log "Compilation NestJS (backend)..."
npm run backend:build 2>&1
ok "Backend compilé"

# ── Build frontend ────────────────────────────────────────────
log "Compilation Vite/React (frontend)..."
npm run frontend:build 2>&1
ok "Frontend compilé"

# ── Migrations base de données ────────────────────────────────
log "Exécution des migrations base de données..."
if npm run migration:run 2>&1; then
  ok "Migrations appliquées"
else
  warn "Aucune migration à appliquer (ou erreur migration non-bloquante)"
fi

# ── Redémarrage PM2 ───────────────────────────────────────────
log "Redémarrage des services PM2..."

if pm2 list | grep -q "e-admin"; then
  # Services déjà enregistrés → redémarrage
  pm2 restart all --update-env
  ok "Services PM2 redémarrés"
else
  # Premier démarrage → utilise l'ecosystem
  if [ -f "$APP_DIR/ecosystem.config.js" ]; then
    pm2 start "$APP_DIR/ecosystem.config.js"
    ok "Services PM2 démarrés depuis ecosystem.config.js"
  else
    warn "ecosystem.config.js introuvable — démarrage manuel requis"
  fi
fi

pm2 save

# ── Rapport final ─────────────────────────────────────────────
echo ""
ok "============================================"
ok "Déploiement terminé avec succès !"
ok "Commit : $COMMIT"
ok "Heure  : $(date)"
ok "============================================"
