#!/usr/bin/env bash
set -euo pipefail

# Idempotent hardening script for E-administration (Pass 2 + Pass 3)
# Target: Ubuntu 22.04
#
# Applies:
# - Webmin restriction by admin IP + UFW
# - SSH hardening + custom SSH port
# - fail2ban baseline + progressive bantime + recidive
# - local backup cron + external backup cron (S3 or rsync)
# - auditd setup with predefined rules
#
# Required env vars:
#   IP_ADMIN        ex: 203.0.113.10
#   DB_PASSWORD     MySQL password for epadmin_app
#
# Optional env vars:
#   SSH_PORT        default: 22222
#   PROJECT_ROOT    default: /var/www/E-administration
#   SCRIPTS_ROOT    default: /opt/eadministration/scripts
#   BACKUP_ROOT     default: /var/backups/e-administration
#   EXTERNAL_MODE   s3 | rsync | none (default: none)
#   S3_BUCKET       required if EXTERNAL_MODE=s3
#   RSYNC_TARGET    required if EXTERNAL_MODE=rsync
#
# Usage example:
#   sudo IP_ADMIN=203.0.113.10 DB_PASSWORD='StrongPass!' EXTERNAL_MODE=s3 S3_BUCKET=s3://my-bucket/e-administration bash scripts/apply_prod_security_pass2_pass3.sh

IP_ADMIN="${IP_ADMIN:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
SSH_PORT="${SSH_PORT:-22222}"
PROJECT_ROOT="${PROJECT_ROOT:-/var/www/E-administration}"
SCRIPTS_ROOT="${SCRIPTS_ROOT:-/opt/eadministration/scripts}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/e-administration}"
EXTERNAL_MODE="${EXTERNAL_MODE:-none}"
S3_BUCKET="${S3_BUCKET:-}"
RSYNC_TARGET="${RSYNC_TARGET:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { printf '[INFO] %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; }
err() { printf '[ERR ] %s\n' "$*" >&2; }

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    err "Run as root (sudo)."
    exit 1
  fi
}

require_env() {
  if [[ -z "${IP_ADMIN}" ]]; then
    err "IP_ADMIN is required."
    exit 1
  fi
  if [[ -z "${DB_PASSWORD}" ]]; then
    err "DB_PASSWORD is required."
    exit 1
  fi
  if [[ "${EXTERNAL_MODE}" == "s3" && -z "${S3_BUCKET}" ]]; then
    err "S3_BUCKET is required when EXTERNAL_MODE=s3."
    exit 1
  fi
  if [[ "${EXTERNAL_MODE}" == "rsync" && -z "${RSYNC_TARGET}" ]]; then
    err "RSYNC_TARGET is required when EXTERNAL_MODE=rsync."
    exit 1
  fi
  if [[ ! "${EXTERNAL_MODE}" =~ ^(none|s3|rsync)$ ]]; then
    err "EXTERNAL_MODE must be one of: none, s3, rsync."
    exit 1
  fi
}

install_packages() {
  log "Installing required packages..."
  apt update -y
  apt install -y fail2ban auditd audispd-plugins rsync

  if [[ "${EXTERNAL_MODE}" == "s3" ]]; then
    apt install -y awscli
  fi
}

configure_webmin_lockdown() {
  log "Applying Webmin lockdown to admin IP ${IP_ADMIN}..."

  # UFW rules (idempotent enough; duplicates are harmless)
  ufw allow from "${IP_ADMIN}" to any port 10000 proto tcp || true

  # Ensure broad 10000 rule is removed if present
  if ufw status numbered | grep -q '10000/tcp'; then
    # Remove all generic rules matching 10000/tcp to avoid public exposure.
    while ufw status numbered | grep -q '10000/tcp'; do
      local n
      n="$(ufw status numbered | awk '/10000\/tcp/{print $1; exit}' | tr -d '[]')"
      [[ -n "${n}" ]] || break
      yes | ufw delete "${n}" >/dev/null 2>&1 || true
    done
    ufw allow from "${IP_ADMIN}" to any port 10000 proto tcp || true
  fi

  # Harden Webmin config
  local miniserv="/etc/webmin/miniserv.conf"
  if [[ -f "${miniserv}" ]]; then
    cp -n "${miniserv}" "${miniserv}.bak" || true

    if grep -q '^ssl=' "${miniserv}"; then
      sed -i 's/^ssl=.*/ssl=1/' "${miniserv}"
    else
      echo 'ssl=1' >> "${miniserv}"
    fi

    sed -i '/^allow=/d' "${miniserv}"
    echo "allow=${IP_ADMIN}" >> "${miniserv}"

    systemctl restart webmin || warn "Could not restart webmin immediately."
  else
    warn "Webmin config not found at ${miniserv}."
  fi
}

configure_ssh_hardening() {
  log "Applying SSH hardening and custom port ${SSH_PORT}..."

  mkdir -p /etc/ssh/sshd_config.d

  cat > /etc/ssh/sshd_config.d/99-hardening.conf <<'EOF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no
X11Forwarding no
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
AllowTcpForwarding no
EOF

  cat > /etc/ssh/sshd_config.d/98-port.conf <<EOF
Port ${SSH_PORT}
EOF

  # UFW for SSH custom port
  ufw allow "${SSH_PORT}/tcp" || true

  # Remove generic OpenSSH rule if present
  if ufw status | grep -q 'OpenSSH'; then
    ufw delete allow OpenSSH || true
  fi

  sshd -t
  systemctl reload ssh
}

configure_fail2ban() {
  log "Configuring fail2ban with progressive bantime..."

  mkdir -p /etc/fail2ban/filter.d /etc/fail2ban/jail.d

  cat > /etc/fail2ban/filter.d/webmin-auth.conf <<'EOF'
[Definition]
failregex = ^.*Failed login as .* from <HOST>$
ignoreregex =
EOF

  cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ${SSH_PORT}
logpath = %(sshd_log)s

[apache-auth]
enabled = true
port    = http,https
logpath = /var/log/apache2/*error.log

[apache-badbots]
enabled = true
port    = http,https
logpath = /var/log/apache2/*access.log

[webmin-auth]
enabled = true
port    = 10000
filter  = webmin-auth
logpath = /var/webmin/miniserv.log
maxretry = 3
EOF

  cat > /etc/fail2ban/jail.d/99-progressive.local <<'EOF'
[DEFAULT]
bantime = 15m
bantime.increment = true
bantime.factor = 2
bantime.maxtime = 72h
findtime = 10m
maxretry = 5
backend = systemd

[recidive]
enabled = true
logpath = /var/log/fail2ban.log
banaction = ufw
bantime = 1w
findtime = 1d
maxretry = 3
EOF

  systemctl enable fail2ban
  systemctl restart fail2ban
}

set_or_replace_env_key() {
  local key="$1"
  local value="$2"
  local env_file="/etc/environment"

  touch "${env_file}"
  if grep -q "^${key}=" "${env_file}"; then
    sed -i "s#^${key}=.*#${key}=${value}#" "${env_file}"
  else
    echo "${key}=${value}" >> "${env_file}"
  fi
}

deploy_backup_scripts() {
  log "Deploying backup scripts and cron jobs..."

  mkdir -p "${SCRIPTS_ROOT}"
  chmod 700 "${SCRIPTS_ROOT}"

  install -m 700 "${SCRIPT_DIR}/prod_backup.sh" "${SCRIPTS_ROOT}/prod_backup.sh"
  install -m 700 "${SCRIPT_DIR}/backup_external_sync.sh" "${SCRIPTS_ROOT}/backup_external_sync.sh"

  mkdir -p "${BACKUP_ROOT}"
  chmod 700 "${BACKUP_ROOT}"

  set_or_replace_env_key "DB_PASSWORD" "${DB_PASSWORD}"

  cat > /etc/cron.d/eadmin-backup <<EOF
10 2 * * * root . /etc/environment; ${SCRIPTS_ROOT}/prod_backup.sh >> /var/log/eadmin-backup.log 2>&1
EOF
  chmod 644 /etc/cron.d/eadmin-backup

  if [[ "${EXTERNAL_MODE}" == "s3" ]]; then
    cat > /etc/cron.d/eadmin-backup-external <<EOF
40 2 * * * root MODE=s3 S3_BUCKET=${S3_BUCKET} ${SCRIPTS_ROOT}/backup_external_sync.sh >> /var/log/eadmin-backup-external.log 2>&1
EOF
    chmod 644 /etc/cron.d/eadmin-backup-external
  elif [[ "${EXTERNAL_MODE}" == "rsync" ]]; then
    cat > /etc/cron.d/eadmin-backup-external <<EOF
40 2 * * * root MODE=rsync RSYNC_TARGET=${RSYNC_TARGET} ${SCRIPTS_ROOT}/backup_external_sync.sh >> /var/log/eadmin-backup-external.log 2>&1
EOF
    chmod 644 /etc/cron.d/eadmin-backup-external
  else
    rm -f /etc/cron.d/eadmin-backup-external
  fi

  systemctl restart cron
}

configure_auditd() {
  log "Applying auditd rules..."

  install -m 640 "${SCRIPT_DIR}/auditd-eadministration.rules" /etc/audit/rules.d/99-eadministration.rules

  systemctl enable auditd
  augenrules --load
  systemctl restart auditd
}

final_checks() {
  log "Running final checks..."

  systemctl reload apache2 || true
  systemctl status ssh --no-pager >/dev/null || true
  systemctl status fail2ban --no-pager >/dev/null || true
  systemctl status auditd --no-pager >/dev/null || true

  echo
  echo "=== HARDENING COMPLETED ==="
  echo "Admin IP allowed for Webmin : ${IP_ADMIN}"
  echo "SSH custom port             : ${SSH_PORT}"
  echo "External backup mode        : ${EXTERNAL_MODE}"
  echo
  echo "Validate now:"
  echo "1) SSH: ssh -p ${SSH_PORT} <UTILISATEUR_SUDO>@141.95.84.126"
  echo "2) fail2ban: sudo fail2ban-client status"
  echo "3) auditd: sudo auditctl -l"
  echo "4) backup local test: sudo -E DB_PASSWORD='***' ${SCRIPTS_ROOT}/prod_backup.sh"
}

main() {
  require_root
  require_env
  install_packages
  configure_webmin_lockdown
  configure_ssh_hardening
  configure_fail2ban
  deploy_backup_scripts
  configure_auditd
  final_checks
}

main "$@"
