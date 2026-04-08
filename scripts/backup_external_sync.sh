#!/usr/bin/env bash
set -euo pipefail

# Sync latest local backup folder to external target (S3 or rsync)
# Requirements:
#  - local backups already exist under /var/backups/e-administration
#  - for S3 mode: aws cli configured (aws configure or IAM role)
#  - for rsync mode: SSH key-based auth configured
#
# Usage examples:
#  MODE=s3 S3_BUCKET=s3://my-bucket/e-administration /opt/eadministration/scripts/backup_external_sync.sh
#  MODE=rsync RSYNC_TARGET=user@backup-host:/srv/backups/e-administration /opt/eadministration/scripts/backup_external_sync.sh

BACKUP_ROOT="/var/backups/e-administration"
MODE="${MODE:-s3}"
S3_BUCKET="${S3_BUCKET:-}"
RSYNC_TARGET="${RSYNC_TARGET:-}"

LATEST_DIR="$(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d | sort | tail -n 1)"
if [[ -z "${LATEST_DIR}" ]]; then
  echo "ERROR: no local backup directory found in ${BACKUP_ROOT}" >&2
  exit 1
fi

echo "Latest backup: ${LATEST_DIR}"

if [[ "${MODE}" == "s3" ]]; then
  if [[ -z "${S3_BUCKET}" ]]; then
    echo "ERROR: S3_BUCKET is required in MODE=s3" >&2
    exit 1
  fi

  if ! command -v aws >/dev/null 2>&1; then
    echo "ERROR: aws CLI is not installed" >&2
    exit 1
  fi

  aws s3 sync "${LATEST_DIR}" "${S3_BUCKET}/$(basename "${LATEST_DIR}")" --only-show-errors
  echo "S3 sync completed"
elif [[ "${MODE}" == "rsync" ]]; then
  if [[ -z "${RSYNC_TARGET}" ]]; then
    echo "ERROR: RSYNC_TARGET is required in MODE=rsync" >&2
    exit 1
  fi

  rsync -az --delete "${LATEST_DIR}/" "${RSYNC_TARGET}/$(basename "${LATEST_DIR}")/"
  echo "Rsync sync completed"
else
  echo "ERROR: MODE must be s3 or rsync" >&2
  exit 1
fi
