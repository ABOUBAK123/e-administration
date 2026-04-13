#!/usr/bin/env bash
# ============================================================
#  Script de déploiement — exécuté sur le serveur via SSH
#  Appelé par le pipeline GitLab CI/CD
# ============================================================
set -euo pipefail

# ── Configuration ────────────────────────────────────────────
APP_DIR="${DEPLOY_PATH:-/var/www/e-administration}"
GIT_BRANCH="main"
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
command -v git   >/dev/null 2>&1 || fail "Git non trouvé"
command -v pm2   >/dev/null 2>&1 || fail "PM2 non trouvé (npm install -g pm2)"

log "Node  : $(node -v)"
log "npm   : $(npm -v)"
log "PM2   : $(pm2 -v)"

[ -d "$APP_DIR" ] || fail "Répertoire $APP_DIR introuvable — premier déploiement ? Clonez d'abord le repo."
[ -d "$APP_DIR/.git" ] || fail "$APP_DIR n'est pas un dépôt git"

# ── Déplacement dans le répertoire de l'app ──────────────────
cd "$APP_DIR"

# ── Récupération du code source ──────────────────────────────
log "Récupération de la branche $GIT_BRANCH..."

git fetch origin "$GIT_BRANCH"
git reset --hard "origin/$GIT_BRANCH"
git clean -fd

COMMIT=$(git rev-parse --short HEAD)
ok "Code mis à jour — commit $COMMIT"

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
