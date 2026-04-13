# Deploiement pas a pas sur Ubuntu (Webmin + Apache + PostgreSQL)

Ce guide deploie E-administration en production sur un serveur Ubuntu avec:
- Webmin deja installe
- Apache2 comme serveur web
- PostgreSQL comme base de donnees
- Backend NestJS en service systemd (port local 3000)
- Frontend React/Vite servi par Apache

## 1. Architecture cible

- Frontend statique: /var/www/e-administration/frontend (servi par Apache)
- Backend API: processus Node.js sur 127.0.0.1:3000
- Reverse proxy Apache:
  - /api -> http://127.0.0.1:3000/api
  - /socket.io -> http://127.0.0.1:3000/socket.io
- Base PostgreSQL locale

## 2. Prerequis systeme

Connectez-vous en sudo:

```bash
sudo -i
apt update && apt upgrade -y
```

Installer dependances:

```bash
apt install -y git curl unzip build-essential apache2 postgresql postgresql-contrib
```

Installer Node.js 20 LTS:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
node -v
npm -v
```

Activer modules Apache necessaires:

```bash
a2enmod rewrite headers proxy proxy_http proxy_wstunnel ssl
systemctl restart apache2
```

## 3. Utilisateur de deploiement et dossiers

```bash
adduser --system --group --home /opt/e-administration eadmin
mkdir -p /opt/e-administration
chown -R eadmin:eadmin /opt/e-administration
mkdir -p /var/www/e-administration/frontend
chown -R www-data:www-data /var/www/e-administration
```

## 4. Recuperer le code

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration
# Remplacer par votre URL git
git clone <URL_DU_REPO> app
cd app
npm ci
'
```

## 5. Configurer PostgreSQL

Creer base et utilisateur (adaptez mots de passe):

```bash
sudo -u postgres psql <<'SQL'
CREATE USER ep_admin_prod WITH PASSWORD 'CHANGER_MOT_DE_PASSE_FORT';
CREATE DATABASE e_parapheur_prod OWNER ep_admin_prod;
GRANT ALL PRIVILEGES ON DATABASE e_parapheur_prod TO ep_admin_prod;
SQL
```

Test de connexion:

```bash
PGPASSWORD='CHANGER_MOT_DE_PASSE_FORT' psql -h 127.0.0.1 -U ep_admin_prod -d e_parapheur_prod -c '\conninfo'
```

## 6. Variables d'environnement backend

Creer le fichier d'environnement:

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app/apps/backend
cp .env.example .env
'
```

Editer /opt/e-administration/app/apps/backend/.env avec au minimum:

```env
NODE_ENV=production
API_PORT=3000
API_URL=https://votre-domaine.tld
FRONTEND_URL=https://votre-domaine.tld

DB_TYPE=postgres
DB_HOST=127.0.0.1
DB_PORT=5432
DB_USER=ep_admin_prod
DB_PASSWORD=CHANGER_MOT_DE_PASSE_FORT
DB_NAME=e_parapheur_prod
DB_SYNCHRONIZE=false
DB_LOGGING=false
DB_SSL=false

JWT_SECRET=CHANGER_SECRET_TRES_LONG
JWT_EXPIRATION=3600

STORAGE_PATH=/opt/e-administration/storage
MAX_FILE_SIZE=104857600

ONLYOFFICE_URL=https://onlyoffice.votre-domaine.tld
```

Creer le dossier de stockage:

```bash
mkdir -p /opt/e-administration/storage
chown -R eadmin:eadmin /opt/e-administration/storage
```

## 7. Variables d'environnement frontend

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app/apps/frontend
cp .env.example .env
'
```

Editer /opt/e-administration/app/apps/frontend/.env:

```env
VITE_API_URL=https://votre-domaine.tld/api/v1
VITE_ONLYOFFICE_URL=https://onlyoffice.votre-domaine.tld
VITE_APP_NAME=E-Parapheur Connect & Sign
VITE_ENV=production
```

## 8. Build backend et frontend

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app
npm ci
npm run build
'
```

Copier le frontend build vers Apache:

```bash
rsync -av --delete /opt/e-administration/app/apps/frontend/dist/ /var/www/e-administration/frontend/
chown -R www-data:www-data /var/www/e-administration/frontend
```

## 9. Migrations base de donnees

Lancer les migrations depuis le backend:

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app/apps/backend
npm run migration:run
'
```

Optionnel: creer le compte administrateur initial:

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app/apps/backend
npm run admin:create
'
```

## 10. Creer le service systemd backend

Creer /etc/systemd/system/e-administration-backend.service:

```ini
[Unit]
Description=E-administration Backend (NestJS)
After=network.target postgresql.service

[Service]
Type=simple
User=eadmin
Group=eadmin
WorkingDirectory=/opt/e-administration/app/apps/backend
Environment=NODE_ENV=production
ExecStart=/usr/bin/node /opt/e-administration/app/apps/backend/dist/main.js
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

Activer et demarrer:

```bash
systemctl daemon-reload
systemctl enable e-administration-backend
systemctl start e-administration-backend
systemctl status e-administration-backend --no-pager
```

Verifier API locale:

```bash
curl -I http://127.0.0.1:3000/api/v1
```

## 11. Configurer Apache VirtualHost

Creer /etc/apache2/sites-available/e-administration.dyula.ci.conf:

```apache
<VirtualHost *:80>
    ServerName votre-domaine.tld
    ServerAlias www.votre-domaine.tld

    DocumentRoot /var/www/e-administration/frontend

    <Directory /var/www/e-administration/frontend>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.html
    </Directory>

    # SPA fallback React
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ /index.html [L]

    # Proxy API
    ProxyPreserveHost On
    ProxyPass /api http://127.0.0.1:3000/api
    ProxyPassReverse /api http://127.0.0.1:3000/api

    # WebSocket (si utilise)
    ProxyPass /socket.io ws://127.0.0.1:3000/socket.io
    ProxyPassReverse /socket.io ws://127.0.0.1:3000/socket.io

    ErrorLog ${APACHE_LOG_DIR}/e-administration-error.log
    CustomLog ${APACHE_LOG_DIR}/e-administration-access.log combined
</VirtualHost>
```

Activer le site:

```bash
a2dissite 000-default.conf
a2ensite e-administration.dyula.ci.conf
apache2ctl configtest
systemctl reload apache2
```

## 12. SSL LetsEncrypt

Installer certbot:

```bash
apt install -y certbot python3-certbot-apache
```

Generer certificat:

```bash
certbot --apache -d votre-domaine.tld -d www.votre-domaine.tld
```

Test renouvellement:

```bash
certbot renew --dry-run
```

## 13. Firewall

```bash
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw enable
ufw status
```

## 14. Checks finaux

```bash
# Backend
systemctl status e-administration-backend --no-pager
journalctl -u e-administration-backend -n 100 --no-pager

# Apache
systemctl status apache2 --no-pager
tail -n 100 /var/log/apache2/e-administration-error.log

# PostgreSQL
systemctl status postgresql --no-pager
```

Verification fonctionnelle:
- Ouvrir https://votre-domaine.tld
- Se connecter
- Verifier qu'un appel API repond (ex: connexion)
- Verifier upload de document

## 15. Mise a jour applicative (runbook)

```bash
sudo -u eadmin -H bash -lc '
cd /opt/e-administration/app
git pull
npm ci
npm run build
cd apps/backend
npm run migration:run
'

rsync -av --delete /opt/e-administration/app/apps/frontend/dist/ /var/www/e-administration/frontend/
systemctl restart e-administration-backend
systemctl reload apache2
```

## 16. Variante via Webmin (UI)

Si vous preferez la GUI Webmin:
- Serveurs > Apache Webserver:
  - Creer Virtual Server avec DocumentRoot /var/www/e-administration/frontend
  - Ajouter directives proxy /api et /socket.io vers 127.0.0.1:3000
  - Ajouter regle Rewrite SPA vers /index.html
- Serveurs > PostgreSQL Database Server:
  - Creer utilisateur ep_admin_prod
  - Creer base e_parapheur_prod et assigner owner
- System > Bootup and Shutdown:
  - Verifier apache2, postgresql et e-administration-backend en auto-start
- System > Scheduled Cron Jobs:
  - Ajouter tache pour sauvegarde base si necessaire

## 17. Sauvegardes minimales recommandees

- PostgreSQL dump quotidien:

```bash
mkdir -p /opt/backups/postgres
cat >/etc/cron.daily/backup-eadministration-db <<'SH'
#!/bin/bash
set -e
export PGPASSWORD='CHANGER_MOT_DE_PASSE_FORT'
pg_dump -h 127.0.0.1 -U ep_admin_prod -d e_parapheur_prod | gzip > /opt/backups/postgres/e_parapheur_$(date +%F).sql.gz
find /opt/backups/postgres -type f -mtime +14 -delete
SH
chmod +x /etc/cron.daily/backup-eadministration-db
```

- Sauvegarder aussi:
  - /opt/e-administration/storage
  - /opt/e-administration/app/apps/backend/.env
  - /etc/apache2/sites-available/e-administration.dyula.ci.conf

---

Si vous voulez, je peux aussi vous generer une version 100% automatisable en script bash unique (install + config + service + vhost).