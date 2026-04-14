# Guide Final de Déploiement E-Administration (Ubuntu 22.04)
## Apache2 + MariaDB + Node.js 18 + PM2 + GitLab CI/CD

Ce guide est adapté a votre configuration actuelle:
- Domaine: e-administration.dyula.ci
- Repository: https://gitlab.com/ABOUBAK123/e-administration.git
- Repertoire de deploiement: /var/www/e-administration
- Stack: Apache reverse proxy + NestJS + Vite + MariaDB + PM2

---

## 1. Prerequis serveur

- Ubuntu 22.04 LTS
- Acces SSH avec sudo
- DNS configure vers l'IP du serveur:
  - e-administration.dyula.ci
  - www.e-administration.dyula.ci
- Ports ouverts: 22, 80, 443

---

## 2. Installation systeme

```bash
sudo apt update
sudo apt upgrade -y

sudo apt install -y \
  curl wget git unzip build-essential \
  ca-certificates gnupg lsb-release software-properties-common \
  apache2 mariadb-server mariadb-client \
  certbot python3-certbot-apache
```

---

## 3. Installation Node.js 18

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

node -v
npm -v
```

---

## 4. MariaDB: activation et securisation

```bash
sudo systemctl enable mariadb
sudo systemctl start mariadb
sudo mysql_secure_installation
```

---

## 5. Creation base de donnees

Choisissez un mot de passe fort. Exemple:

```bash
DB_NAME="e_parapheur"
DB_USER="eadmin_app"
DB_PASSWORD="CHANGE_ME_STRONG_DB_PASSWORD"

sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

---

## 6. Utilisateur applicatif + arborescence

```bash
sudo useradd -m -s /bin/bash eadmin 2>/dev/null || true
sudo usermod -aG www-data eadmin

sudo mkdir -p /var/www
cd /var/www
```

---

## 7. Clonage du projet GitLab

```bash
sudo git clone https://gitlab.com/ABOUBAK123/e-administration.git /var/www/e-administration
sudo chown -R eadmin:www-data /var/www/e-administration
```

---

## 8. Variables d'environnement (obligatoire)

### 8.1 Backend

Creer /var/www/e-administration/apps/backend/.env

```env
NODE_ENV=production
API_PORT=3000
API_HOST=0.0.0.0

DB_TYPE=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=eadmin_app
DB_PASSWORD=CHANGE_ME_STRONG_DB_PASSWORD
DB_NAME=e_parapheur

JWT_SECRET=CHANGE_ME_LONG_RANDOM_SECRET
JWT_EXPIRATION=86400

CORS_ORIGIN=https://e-administration.dyula.ci
CORS_CREDENTIALS=true

LOG_LEVEL=info
LOG_FORMAT=json
```

### 8.2 Frontend

Creer /var/www/e-administration/apps/frontend/.env

```env
VITE_API_URL=https://e-administration.dyula.ci/api
VITE_APP_VERSION=1.0.0
```

Appliquer les droits:

```bash
sudo chown eadmin:www-data /var/www/e-administration/apps/backend/.env
sudo chown eadmin:www-data /var/www/e-administration/apps/frontend/.env
sudo chmod 600 /var/www/e-administration/apps/backend/.env
sudo chmod 600 /var/www/e-administration/apps/frontend/.env
```

---

## 9. Installation dependances + build + migration

```bash
cd /var/www/e-administration

sudo -u eadmin npm ci
sudo -u eadmin npm run backend:build
sudo -u eadmin npm run frontend:build
sudo -u eadmin npm run migration:run
```

---

## 10. PM2 (execution en production)

```bash
sudo npm install -g pm2

cd /var/www/e-administration
sudo -u eadmin pm2 start ecosystem.config.js
sudo -u eadmin pm2 save
sudo pm2 startup systemd -u eadmin --hp /home/eadmin
```

Verification:

```bash
pm2 status
pm2 logs --lines 100
```

---

## 11. Configuration Apache2 (reverse proxy)

Activer modules:

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl expires deflate
```

Creer le fichier:

```bash
sudo tee /etc/apache2/sites-available/e-administration.conf > /dev/null << 'APACHECONF'
<VirtualHost *:80>
  ServerName e-administration.dyula.ci
  ServerAlias www.e-administration.dyula.ci

  RewriteEngine On
  RewriteRule ^ https://e-administration.dyula.ci%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
  ServerName www.e-administration.dyula.ci

  SSLEngine on
  SSLCertificateFile /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/e-administration.dyula.ci/privkey.pem

  RewriteEngine On
  RewriteRule ^ https://e-administration.dyula.ci%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
  ServerName e-administration.dyula.ci

  SSLEngine on
  SSLCertificateFile /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/e-administration.dyula.ci/privkey.pem

  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"

  ProxyPreserveHost On
  ProxyRequests Off
  RequestHeader set X-Forwarded-Proto "https"
  RequestHeader set X-Forwarded-Host "e-administration.dyula.ci"
  RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"

  ProxyPass /api http://127.0.0.1:3000/api timeout=300
  ProxyPassReverse /api http://127.0.0.1:3000/api

  ProxyPass /socket.io/ ws://127.0.0.1:3000/socket.io/ retry=0 timeout=300
  ProxyPassReverse /socket.io/ ws://127.0.0.1:3000/socket.io/

  ProxyPass / http://127.0.0.1:5173/ timeout=300
  ProxyPassReverse / http://127.0.0.1:5173/

  ErrorLog ${APACHE_LOG_DIR}/e-administration-error.log
  CustomLog ${APACHE_LOG_DIR}/e-administration-access.log combined
</VirtualHost>
APACHECONF
```

Activer site + verifier:

```bash
sudo a2ensite e-administration.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

---

## 12. SSL Let's Encrypt

```bash
sudo certbot \
  --apache \
  -n \
  --agree-tos \
  --email admin@e-administration.dyula.ci \
  -d e-administration.dyula.ci \
  -d www.e-administration.dyula.ci
```

Verifier le renouvellement:

```bash
sudo systemctl list-timers | grep certbot
```

---

## 13. Firewall UFW

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

---

## 14. Verification finale

```bash
curl -I https://e-administration.dyula.ci
curl -I https://e-administration.dyula.ci/api/docs
curl -I https://e-administration.dyula.ci/api/v1

pm2 status
sudo systemctl status apache2
sudo systemctl status mariadb
```

---

## 15. Integration GitLab CI/CD (deja en place)

Votre pipeline actuel transfere les fichiers vers le serveur puis execute le script de deploiement.

Variables GitLab a fournir:

Obligatoires:
- SSH_PRIVATE_KEY
- SERVER_HOST
- SERVER_USER

Optionnelles:
- SSH_PORT (defaut 22)
- DEPLOY_PATH (defaut /var/www/e-administration)

Pour creation automatique de la base via pipeline:
- AUTO_CREATE_DB=true
- DB_HOST=127.0.0.1
- DB_PORT=3306
- DB_NAME=e_parapheur
- DB_USER=eadmin_app
- DB_PASSWORD=...
- DB_ROOT_USER=root
- DB_ROOT_PASSWORD=...

---

## 16. Procedure de mise a jour

```bash
cd /var/www/e-administration

sudo -u eadmin git pull origin main
sudo -u eadmin npm ci
sudo -u eadmin npm run backend:build
sudo -u eadmin npm run frontend:build
sudo -u eadmin npm run migration:run
sudo -u eadmin pm2 restart all
sudo -u eadmin pm2 save
```

---

## 17. Checklist pre-production

- [ ] DNS ok (domaine + www)
- [ ] Certificat SSL valide
- [ ] Backend /api repond
- [ ] Swagger /api/docs accessible
- [ ] PM2 en online
- [ ] Connexion MariaDB ok
- [ ] Migration executee
- [ ] Upload de document teste
- [ ] Pipeline GitLab vert sur main

---

Derniere mise a jour: 2026-04-13
