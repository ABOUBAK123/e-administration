#!/usr/bin/env bash
set -euo pipefail

# Production backup script for E-administration (MySQL + uploads/storage + retention)
# Usage:
#   DB_PASSWORD='...' /opt/eadministration/scripts/prod_backup.sh
# Or define DB_PASSWORD in /etc/environment (recommended for non-interactive cron usage).

BACKUP_ROOT="/var/backups/e-administration"
PROJECT_ROOT="/var/www/E-administration"
DB_HOST="localhost"
DB_PORT="3306"
DB_NAME="e_parapheur"
DB_USER="epadmin_app"
RETENTION_DAYS="14"

if [[ -z "${DB_PASSWORD:-}" ]]; then
  echo "ERROR: DB_PASSWORD is not set."
  exit 1
fi

TIMESTAMP="$(date +%F_%H-%M-%S)"
TARGET_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
mkdir -p "${TARGET_DIR}"

# 1) Database dump
mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USER}" \
  --password="${DB_PASSWORD}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  "${DB_NAME}" > "${TARGET_DIR}/database.sql"

gzip -f "${TARGET_DIR}/database.sql"

# 2) Storage backup (adjust paths if needed)
if [[ -d "${PROJECT_ROOT}/apps/backend/storage" ]]; then
  tar -czf "${TARGET_DIR}/backend-storage.tar.gz" -C "${PROJECT_ROOT}/apps/backend" storage
fi

if [[ -d "${PROJECT_ROOT}/storage" ]]; then
  tar -czf "${TARGET_DIR}/root-storage.tar.gz" -C "${PROJECT_ROOT}" storage
fi

# 3) Keep minimal restore metadata
cat > "${TARGET_DIR}/meta.txt" <<EOF
created_at=${TIMESTAMP}
hostname=$(hostname -f 2>/dev/null || hostname)
project_root=${PROJECT_ROOT}
database=${DB_NAME}
EOF

# 4) Cleanup old backups
find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime +"${RETENTION_DAYS}" -exec rm -rf {} +

echo "Backup completed: ${TARGET_DIR}"
