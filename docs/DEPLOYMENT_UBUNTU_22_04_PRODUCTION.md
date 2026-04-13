o# Guide Complet : Déploiement E-Administration sur Ubuntu 22.04 Serveur
## Apache2 + MariaDB + Node.js 18+

---

## 1. Vue d'ensemble architecture

```
Internet
   └─> Firewall UFW (80, 443, 22)
       └─> Apache2 (reverse proxy)
           ├─> Frontend (Vite, :5173)
           ├─> Backend API (NestJS, :3000)
           └─> WebSocket (:3000/socket.io)
               └─> MariaDB (port 3306)
                   └─ Base de données e_parapheur
```

---

## 2. Prérequis

- Serveur Ubuntu 22.04 LTS (accès SSH root ou sudo)
- Domaine DNS pointant vers le serveur (ex: `e-administration.dyula.ci`)
- Ports ouverts: 22, 80, 443, 10000 (Webmin optionnel)
- Espace disque: minimum 20 GB disponible
- RAM: minimum 2 GB (4 GB recommandé)

---

## 3. Installation automatisée (script bash)

### 3.1 Créer le script d'installation

```bash
cat > /tmp/deploy-eadmin.sh << 'EOF'
#!/bin/bash
set -e

# Configuration
DOMAIN=${1:-"e-administration.dyula.ci"}
APP_DIR="/var/www/html/e-administration"
APP_USER="eadmin"
DB_USER="eadmin_app"
DB_NAME="e_parapheur"
DB_PASSWORD=$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)
NODE_ENV="production"

echo "========================================="
echo "Déploiement E-Administration"
echo "========================================="
echo "Domaine: $DOMAIN"
echo "Répertoire: $APP_DIR"
echo "Utilisateur app: $APP_USER"
echo "DB MariaDB: $DB_NAME"
echo ""

# === STEP 1: Mise à jour système ===
echo "[1/10] Mise à jour du système..."
sudo apt update
sudo apt upgrade -y
sudo apt install -y \
  curl wget git unzip build-essential \
  ca-certificates gnupg lsb-release \
  software-properties-common sudo

# === STEP 2: Installation Node.js 18+ ===
echo "[2/10] Installation Node.js 18+..."
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v

# === STEP 3: Installation MariaDB ===
echo "[3/10] Installation MariaDB..."
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Configuration sécurisée MariaDB
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$(openssl rand -base64 16)';"
sudo mysql -e "FLUSH PRIVILEGES;"

# === STEP 4: Création base et utilisateur DB ===
echo "[4/10] Création base de données et utilisateur..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

echo "Credentials DB (à sauvegarder sécurisé!):"
echo "  User: $DB_USER"
echo "  Password: $DB_PASSWORD"
echo "  Database: $DB_NAME"
echo ""

# === STEP 5: Création utilisateur applicatif ===
echo "[5/10] Création utilisateur applicatif..."
sudo useradd -m -s /bin/bash -G sudo $APP_USER 2>/dev/null || true
sudo usermod -aG www-data $APP_USER 2>/dev/null || true

# === STEP 6: Clonage/préparation du projet ===
echo "[6/10] Préparation du projet..."
sudo mkdir -p /var/www
cd /var/www

# Si le repo n'existe pas, cloner depuis Git
if [ ! -d "e-administration" ]; then
  echo "Clone du repository (remplacer par votre URL Git)..."
  # sudo git clone https://github.com/votre-org/e-administration.git
  # Pour test: créer structure de base
  sudo mkdir -p e-administration
fi

sudo chown -R $APP_USER:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR

# === STEP 7: Installation dépendances npm ===
echo "[7/10] Installation dépendances npm..."
cd $APP_DIR
sudo -u $APP_USER npm install
sudo -u $APP_USER npm run backend:install
sudo -u $APP_USER npm run frontend:install

# === STEP 8: Configuration variables d'environnement ===
echo "[8/10] Configuration fichiers .env..."

# Backend .env
cat > $APP_DIR/apps/backend/.env << ENVEOF
# Database
DB_TYPE=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_NAME=$DB_NAME

# Application
NODE_ENV=$NODE_ENV
API_PORT=3000
API_HOST=0.0.0.0

# Security
JWT_SECRET=$JWT_SECRET
JWT_EXPIRATION=86400

# Email (optionnel)
MAIL_FROM=noreply@$DOMAIN
MAIL_HOST=localhost
MAIL_PORT=1025

# Logging
LOG_LEVEL=info
LOG_FORMAT=json

# CORS
CORS_ORIGIN=https://$DOMAIN
CORS_CREDENTIALS=true
ENVEOF

# Frontend .env
cat > $APP_DIR/apps/frontend/.env << ENVEOF
VITE_API_URL=https://$DOMAIN/api
VITE_APP_NAME="E-Administration"
VITE_APP_VERSION=1.0.0
ENVEOF

sudo chown $APP_USER:www-data $APP_DIR/apps/backend/.env
sudo chown $APP_USER:www-data $APP_DIR/apps/frontend/.env
sudo chmod 600 $APP_DIR/apps/backend/.env
sudo chmod 600 $APP_DIR/apps/frontend/.env

echo "✓ Fichiers .env créés"

# === STEP 9: Build de l'application ===
echo "[9/10] Build de l'application..."
cd $APP_DIR
sudo -u $APP_USER npm run backend:build
sudo -u $APP_USER npm run frontend:build

# === STEP 10: Configuration Apache2 ===
echo "[10/10] Configuration Apache2..."
sudo apt install -y apache2 certbot python3-certbot-apache
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl
sudo systemctl enable apache2

# VirtualHost Apache (durci: www -> domaine principal)
cat > /etc/apache2/sites-available/e-administration.dyula.ci.conf << 'APACHEEOF'
<VirtualHost *:80>
    ServerName DOMAIN_PLACEHOLDER
    ServerAlias www.DOMAIN_PLACEHOLDER
    
    # Redirect tous les traffics HTTP vers le domaine principal en HTTPS
    RewriteEngine On
    RewriteRule ^ https://DOMAIN_PLACEHOLDER%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName www.DOMAIN_PLACEHOLDER
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem
    
    RewriteEngine On
    RewriteRule ^ https://DOMAIN_PLACEHOLDER%{REQUEST_URI} [L,R=301]

    ErrorLog ${APACHE_LOG_DIR}/e-administration-www-redirect-error.log
    CustomLog ${APACHE_LOG_DIR}/e-administration-www-redirect-access.log combined
  </VirtualHost>

  <VirtualHost *:443>
    ServerName DOMAIN_PLACEHOLDER

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/DOMAIN_PLACEHOLDER/privkey.pem

    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Proxy configuration
    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Host "DOMAIN_PLACEHOLDER"
    RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"

    # Backend API
    ProxyPass /api http://127.0.0.1:3000/api timeout=300
    ProxyPassReverse /api http://127.0.0.1:3000/api

    # WebSocket
    ProxyPass /socket.io/ ws://127.0.0.1:3000/socket.io/ retry=0 timeout=300
    ProxyPassReverse /socket.io/ ws://127.0.0.1:3000/socket.io/

    # Frontend (Vite)
    ProxyPass / http://127.0.0.1:5173/ timeout=300
    ProxyPassReverse / http://127.0.0.1:5173/

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/e-administration-error.log
    CustomLog ${APACHE_LOG_DIR}/e-administration-access.log combined

    # Performance
    <IfModule mod_deflate.c>
      AddOutputFilterByType DEFLATE text/html text/plain text/xml application/json
    </IfModule>

    <IfModule mod_expires.c>
      ExpiresActive On
      ExpiresByType text/css "access plus 1 month"
      ExpiresByType application/javascript "access plus 1 month"
      ExpiresByType image/png "access plus 1 month"
      ExpiresByType image/jpeg "access plus 1 month"
    </IfModule>
</VirtualHost>
APACHEEOF

# Remplacer le placeholder de domaine
sed -i "s/DOMAIN_PLACEHOLDER/$DOMAIN/g" /etc/apache2/sites-available/e-administration.dyula.ci.conf

# Activer le site et désactiver le défaut
sudo a2ensite e-administration.dyula.ci.conf
sudo a2dissite 000-default.conf

# Valider config Apache
sudo apache2ctl configtest

# === Configuration SSL Let's Encrypt ===
echo "Configuration SSL avec Let's Encrypt..."
sudo certbot --apache -n --agree-tos --register-unsafely-without-email -d $DOMAIN -d www.$DOMAIN || echo "SSL peut être configuré manuellement"

# Recharger Apache
sudo systemctl reload apache2

# === Migrations BDD ===
echo "Exécution des migrations..."
cd $APP_DIR
sudo -u $APP_USER npm run migration:run 2>/dev/null || echo "Migrations: pas de migrations à appliquer"

# === Résumé ===
echo ""
echo "========================================="
echo "✓ Déploiement complété!"
echo "========================================="
echo ""
echo "Configuration:"
echo "  - Domaine: https://$DOMAIN"
echo "  - App dir: $APP_DIR"
echo "  - User app: $APP_USER"
echo "  - DB: $DB_NAME (user: $DB_USER)"
echo ""
echo "Étapes suivantes (PM2):"
echo "  sudo npm install -g pm2"
echo "  cd $APP_DIR"
echo "  pm2 start ecosystem.config.js"
echo "  pm2 save"
echo "  pm2 startup"
echo ""
echo "Vérification:"
echo "  - https://$DOMAIN (frontend)"
echo "  - https://$DOMAIN/api/docs (Swagger API)"
echo "  - sudo systemctl status apache2"
echo "  - sudo systemctl status mariadb"
echo ""
EOF

chmod +x /tmp/deploy-eadmin.sh
```

### 3.2 Exécuter le script

```bash
sudo bash /tmp/deploy-eadmin.sh "e-administration.dyula.ci"
```

---

## 4. Installation manuelle (pas à pas)

### 4.1 Mise à jour du système

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip build-essential ca-certificates gnupg lsb-release software-properties-common
```

### 4.2 Installation Node.js 18+

```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

### 4.3 Installation MariaDB

```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Sécuriser installation
sudo mysql_secure_installation
```

**Script sécurité MariaDB:**

```bash
sudo mysql << 'EOF'
-- Supprimer utilisateurs anonymes
DELETE FROM mysql.user WHERE User='';

-- Supprimer accès root distant
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Supprimer base test
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

FLUSH PRIVILEGES;
EOF
```

### 4.4 Créer base et utilisateur

```bash
# Variables
DB_USER="eadmin_app"
DB_PASSWORD=$(openssl rand -base64 32)
DB_NAME="e_parapheur"

echo "DB_USER=$DB_USER"
echo "DB_PASSWORD=$DB_PASSWORD"  # À garder en lieu sûr!
echo "DB_NAME=$DB_NAME"

# Créer
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 4.5 Créer utilisateur applicatif

```bash
sudo useradd -m -s /bin/bash eadmin
sudo usermod -aG www-data eadmin
```

### 4.6 Cloner le projet

```bash
sudo mkdir -p /var/www/html
cd /var/www/html

# Remplacer par votre URL Git réelle
sudo git clone https://github.com/ABOUBAK123/e-administration.git
sudo chown -R eadmin:www-data /var/www/html/e-administration
cd /var/www/html/e-administration
```

### 4.7 Installation dépendances npm

```bash
sudo -u eadmin npm install
sudo -u eadmin npm run backend:install
sudo -u eadmin npm run frontend:install
```

### 4.8 Configuration fichiers .env

**Backend (`apps/backend/.env`):**

```env
# Database - MariaDB
DB_TYPE=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_USER=eadmin_app
DB_PASSWORD=VotreMotDePasse!
DB_NAME=e_parapheur

# Server
NODE_ENV=production
API_PORT=3000
API_HOST=0.0.0.0

# JWT Security
JWT_SECRET=$(openssl rand -base64 32)
JWT_EXPIRATION=86400

# CORS
CORS_ORIGIN=https://e-administration.dyula.ci
CORS_CREDENTIALS=true

# Logging
LOG_LEVEL=info
LOG_FORMAT=json
```

**Frontend (`apps/frontend/.env`):**

```env
VITE_API_URL=https://e-administration.dyula.ci/api
VITE_APP_VERSION=1.0.0
```

### 4.9 Build de l'application

```bash
cd /var/www/html/e-administration

# Backend
npm run backend:build

# Frontend
npm run frontend:build
```

### 4.10 Migrations base de données

```bash
cd /var/www/html/e-administration/apps/backend
npm run migration:run
```

### 4.11 Installation Apache2

```bash
sudo apt install -y apache2 certbot python3-certbot-apache

# Activer modules
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl
sudo systemctl enable apache2
```

### 4.12 Configuration VirtualHost Apache (version durcie)

Créer `/etc/apache2/sites-available/e-administration.dyula.ci.conf`:

```apache
<VirtualHost *:80>
  ServerName e-administration.dyula.ci
  ServerAlias www.e-administration.dyula.ci

  # Forcer HTTP -> HTTPS sur le domaine principal
  RewriteEngine On
  RewriteRule ^ https://e-administration.dyula.ci%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
  ServerName www.e-administration.dyula.ci

  SSLEngine on
  SSLCertificateFile /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/e-administration.dyula.ci/privkey.pem

  # Redirection explicite de www vers le domaine principal
  RewriteEngine On
  RewriteRule ^ https://e-administration.dyula.ci%{REQUEST_URI} [L,R=301]

  ErrorLog ${APACHE_LOG_DIR}/e-administration-www-redirect-error.log
  CustomLog ${APACHE_LOG_DIR}/e-administration-www-redirect-access.log combined
</VirtualHost>

<VirtualHost *:443>
  ServerName e-administration.dyula.ci

  SSLEngine on
  SSLCertificateFile /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/e-administration.dyula.ci/privkey.pem

  SSLProtocol TLSv1.2 TLSv1.3
  SSLCipherSuite HIGH:!aNULL:!MD5
  SSLHonorCipherOrder On

  # Security Headers
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-XSS-Protection "1; mode=block"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

  # Proxy Settings
  ProxyPreserveHost On
  ProxyRequests Off
  RequestHeader set X-Forwarded-Proto "https"
  RequestHeader set X-Forwarded-Host "e-administration.dyula.ci"
  RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"

  # Backend API (port 3000)
  ProxyPass /api http://127.0.0.1:3000/api timeout=300
  ProxyPassReverse /api http://127.0.0.1:3000/api

  # WebSocket support (Socket.IO)
  ProxyPass /socket.io/ ws://127.0.0.1:3000/socket.io/ retry=0 timeout=300
  ProxyPassReverse /socket.io/ ws://127.0.0.1:3000/socket.io/

  # Frontend Vite (port 5173)
  ProxyPass / http://127.0.0.1:5173/ timeout=300
  ProxyPassReverse / http://127.0.0.1:5173/

  # Static files caching
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
```

### 4.13 Activation du site

```bash
sudo a2ensite e-administration.dyula.ci.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 4.14 Certificat SSL Let's Encrypt

```bash
sudo certbot \
  --apache \
  -n \
  --agree-tos \
  --email admin@e-administration.dyula.ci \
  -d e-administration.dyula.ci \
  -d www.e-administration.dyula.ci
```

Note: même si l'application sert uniquement `e-administration.dyula.ci`, le certificat inclut `www` pour permettre la redirection HTTPS propre de `www` vers le domaine principal.

Renouvellement automatique:

```bash
sudo systemctl list-timers certbot
```

---

## 5. Lancement avec PM2 (Process Manager)

### 5.1 Installation PM2

```bash
sudo npm install -g pm2
sudo pm2 startup systemd -u eadmin --hp /home/eadmin
```

### 5.2 Créer fichier `ecosystem.config.js`

Créer à la racine du projet `/var/www/html/e-administration/ecosystem.config.js`:

```javascript
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
      log_file: "./logs/backend-combined.log",
      time_format: "YYYY-MM-DD HH:mm:ss Z",
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
      log_file: "./logs/frontend-combined.log",
      time_format: "YYYY-MM-DD HH:mm:ss Z",
      env: {
        NODE_ENV: "production",
      },
    },
  ],
};
```

### 5.3 Lancer avec PM2

```bash
cd /var/www/html/e-administration

# Créer répertoire logs
mkdir -p logs

# Démarrer
sudo -u eadmin pm2 start ecosystem.config.js

# Sauvegarder config (important!)
sudo -u eadmin pm2 save
sudo pm2 startup systemd -u eadmin --hp /home/eadmin

# Vérifier
pm2 status
pm2 logs
```

### 5.4 Gestion PM2

```bash
# Tableau de bord
pm2 monit

# Logs
pm2 logs e-admin-backend
pm2 logs e-admin-frontend

# Redémarrage
pm2 restart all
pm2 restart e-admin-backend

# Arrêter
pm2 stop all

# Supprimer
pm2 delete all
```

---

## 6. UFW Firewall (Optionnel mais recommandé)

```bash
# Activer UFW
sudo ufw enable

# Rules de base
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow 3306/tcp    # MySQL (local only - voir ci-dessous)

# Restreindre MySQL à localhost uniquement
sudo ufw allow from 127.0.0.1 to 127.0.0.1 port 3306

# Lister les règles
sudo ufw status verbose

# Port Webmin (optionnel - restreindre à votre IP)
sudo ufw allow from YOUR_IP to any port 10000
```

---

## 7. Sauvegarde et Maintenance

### 7.1 Sauvegarde automatique MariaDB

Créer `/home/eadmin/backup-db.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/backup/mariadb"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
mysqldump -u eadmin_app -p'VotrePassword' e_parapheur | gzip > $BACKUP_DIR/e_parapheur_$DATE.sql.gz

# Garder seulement les 30 derniers fichiers
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup: $BACKUP_DIR/e_parapheur_$DATE.sql.gz"
```

Ajouter au cron:

```bash
crontab -e
# 0 2 * * * /home/eadmin/backup-db.sh  # Tous les jours à 2h
```

### 7.2 Sauvegarde upload/data

```bash
#!/bin/bash
BACKUP_DIR="/backup/eadmin"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR
tar -czf $BACKUP_DIR/eadmin_data_$DATE.tar.gz \
  /var/www/html/e-administration/apps/backend/uploads \
  /var/www/html/e-administration/apps/backend/.env

find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 7.3 Monitoring des logs

```bash
# Apache errors
sudo tail -f /var/log/apache2/e-administration-error.log

# PM2 logs
pm2 logs

# MariaDB
sudo tail -f /var/log/mysql/error.log

# Système
journalctl -u e-administration -f
```

---

## 8. Dépannage et Diagnostic

### 8.1 Tests rapides

```bash
# Backend health
curl https://e-administration.dyula.ci/api/health

# Frontend
curl https://e-administration.dyula.ci

# Ports en écoute
sudo netstat -tulpn | grep -E ':(3000|5173|80|443|3306)'

# Processus Node
ps aux | grep node

# Vérifier certificat SSL
sudo openssl x509 -in /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem -text -noout
```

### 8.2 Erreurs courantes

| Erreur | Cause | Solution |
|--------|-------|----------|
| `502 Bad Gateway` | Backend arrêté | `pm2 restart e-admin-backend` |
| `Connection refused :3000` | Port fermé | `sudo netstat -tulpn \| grep 3000` |
| `SSL certificate error` | Cert expiré | `sudo certbot renew --dry-run` |
| `Database connection` | Credentials invalides | Vérifier `.env` et utilisateur MySQL |
| `WebSocket timeout` | Apache proxy mal configuré | Vérifier `ProxyPass /socket.io` |

### 8.3 Logs complets

```bash
# Tous les erreurs Apache
grep ERROR /var/log/apache2/e-administration-error.log | tail -20

# Erreurs d'accès
grep "5[0-9][0-9]" /var/log/apache2/e-administration-access.log | tail -10

# Journaux système
journalctl -xe | tail -50
```

---

## 9. Mode Maintenance

### 9.1 Maintenance page

Créer `/var/www/html/e-administration/maintenance.html`:

```html
<!DOCTYPE html>
<html>
<head><title>En Maintenance</title></head>
<body style="text-align:center; margin-top:50px; font-family:Arial">
  <h1>Maintenance en cours</h1>
  <p>Le service sera rétabli dans quelques minutes.</p>
  <p>Temps estimé: 15 minutes</p>
</body>
</html>
```

### 9.2 Activer maintenance

```bash
# Dans Apache config
<VirtualHost *:443>
  ...
  RewriteCond %{REQUEST_FILENAME} !^/maintenance.html$
  RewriteRule !^(maintenance\.html)$ - [R=503,L]
  ErrorDocument 503 /maintenance.html
  ...
</VirtualHost>
```

### 9.3 Désactiver maintenance

```bash
# Enlever les rules et recharger
sudo systemctl reload apache2
```

---

## 10. Checklist Pré-Production

- [ ] Domaine DNS configuré
- [ ] SSL HTTPS actif et valide
- [ ] Base de données MariaDB créée
- [ ] Variables `.env` renseignées (JWT_SECRET, CORS_ORIGIN, etc.)
- [ ] Migrations BDD exécutées
- [ ] Build backend/frontend réussis
- [ ] PM2 démarrage automatique configuré
- [ ] Apache reverse proxy OK
- [ ] UFW firewall configuré
- [ ] Sauvegarde automatique planifiée
- [ ] SSL auto-renouvellement actif
- [ ] Monitoring logs en place
- [ ] Test fonctionnel : login utilisateur
- [ ] Test fonctionnel : upload document
- [ ] Test WebSocket : chat/notifications

---

## 11. Mise à jour de l'application

```bash
cd /var/www/html/e-administration

# Arrêter les services
pm2 stop all

# Récupérer les changements
git pull origin main

# Installer dépendances
npm install

# Exécuter migrations
npm run migration:run

# Rebuild
npm run backend:build
npm run frontend:build

# Redémarrer
pm2 restart all

# Vérification
pm2 status
curl https://e-administration.dyula.ci/api/health
```

---

## 12. Support et Documentation

- API Docs: `https://e-administration.dyula.ci/api/docs` (Swagger)
- Logs PM2: `pm2 logs`
- Documentation backend: `apps/backend/README.md`
- Bugs/Issues: `git log --oneline -n 20`

---

**Dernière mise à jour**: 2024
Document d'aide au déploiement E-Administration sur les serveurs Ubuntu 22.04.
