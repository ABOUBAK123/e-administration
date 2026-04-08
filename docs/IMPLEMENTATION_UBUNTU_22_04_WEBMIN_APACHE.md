# Guide d'implementation du projet sur Ubuntu 22.04 avec Webmin et Apache

## 1. Objectif
Ce document explique comment deployer E-administration sur un serveur Ubuntu 22.04 avec:
- Webmin pour l'administration serveur
- Apache2 comme serveur web et reverse proxy
- Node.js pour le frontend et le backend
- MySQL (ou PostgreSQL selon votre configuration projet)

Le guide est oriente production simple et maintenable.

## 2. Prerequis
- Serveur Ubuntu 22.04 a jour
- Acces SSH avec un utilisateur sudo
- Nom de domaine pointe vers le serveur (ex: app.mondomaine.com)
- Ports ouverts:
  - 22 (SSH)
  - 80 (HTTP)
  - 443 (HTTPS)
  - 10000 (Webmin, idealement limite par IP)

## 3. Preparation du serveur

### 3.1 Mise a jour
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip ca-certificates gnupg lsb-release software-properties-common
```

### 3.2 Configuration de base (recommande)
```bash
sudo timedatectl set-timezone Africa/Abidjan
sudo hostnamectl set-hostname eadministration-prod
```

### 3.3 Pare-feu UFW
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 10000/tcp
sudo ufw enable
sudo ufw status
```

Important: en production, restreindre le port Webmin (10000) a votre IP publique.

## 4. Installation de Webmin
```bash
curl -fsSL https://download.webmin.com/jcameron-key.asc | sudo gpg --dearmor -o /usr/share/keyrings/webmin.gpg
echo "deb [signed-by=/usr/share/keyrings/webmin.gpg] https://download.webmin.com/download/repository sarge contrib" | sudo tee /etc/apt/sources.list.d/webmin.list
sudo apt update
sudo apt install -y webmin
```

Acces Webmin:
- URL: https://IP_SERVEUR:10000
- Connexion: utilisateur sudo du serveur

## 5. Installation Apache2
```bash
sudo apt install -y apache2
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl
sudo systemctl enable apache2
sudo systemctl start apache2
sudo systemctl status apache2
```

## 6. Installation Node.js 18+
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs build-essential
node -v
npm -v
```

## 7. Installation base de donnees

## 7.1 Option A: MySQL (config frequente du projet)
```bash
sudo apt install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql
sudo mysql_secure_installation
```

Creation base et utilisateur:
```sql
CREATE DATABASE e_parapheur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'epadmin_app'@'localhost' IDENTIFIED BY 'MotDePasseFort!';
GRANT ALL PRIVILEGES ON e_parapheur.* TO 'epadmin_app'@'localhost';
FLUSH PRIVILEGES;
```

## 7.2 Option B: PostgreSQL (si votre projet est configure ainsi)
```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

## 8. Recuperation du projet
```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone <URL_DU_REPO> E-administration
sudo chown -R $USER:$USER /var/www/E-administration
cd /var/www/E-administration
```

Note: remplacez <URL_DU_REPO> par l'URL Git reelle.

## 9. Configuration des variables d'environnement

Backend:
- Fichier: apps/backend/.env
- Parametres minimaux:
  - DB_TYPE
  - DB_HOST
  - DB_PORT
  - DB_USER
  - DB_PASSWORD
  - DB_NAME
  - JWT_SECRET
  - API_PORT

Exemple MySQL:
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_USER=epadmin_app
DB_PASSWORD=MotDePasseFort!
DB_NAME=e_parapheur
JWT_SECRET=ChangeThisSecretInProduction
API_PORT=3000
NODE_ENV=production
```

Frontend:
- Fichier: apps/frontend/.env
- Configurer l'URL API publique (ex: https://app.mondomaine.com/api)

## 10. Installation des dependances et build
Depuis la racine du projet:
```bash
npm install
npm run build
```

Si vous utilisez un monorepo avec scripts separes, executez les commandes prevues dans le README du projet.

## 11. Migrations base de donnees
```bash
cd /var/www/E-administration/apps/backend
npm run typeorm migration:run
```

## 12. Execution avec PM2 (recommande)

### 12.1 Installation PM2
```bash
sudo npm install -g pm2
```

### 12.2 Lancer backend
```bash
cd /var/www/E-administration/apps/backend
pm2 start dist/main.js --name eadmin-backend
```

### 12.3 Lancer frontend
Cas Vite preview (simple):
```bash
cd /var/www/E-administration/apps/frontend
pm2 start "npm run preview -- --host 0.0.0.0 --port 5173" --name eadmin-frontend
```

Sauvegarde PM2:
```bash
pm2 save
pm2 startup
```

## 13. Configuration Apache en reverse proxy
Creer un VirtualHost:
```bash
sudo nano /etc/apache2/sites-available/eadministration.conf
```

Contenu type:
```apache
<VirtualHost *:80>
    ServerName app.mondomaine.com

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "http"

    # Frontend
    ProxyPass / http://127.0.0.1:5173/
    ProxyPassReverse / http://127.0.0.1:5173/

    # Backend API
    ProxyPass /api http://127.0.0.1:3000/api
    ProxyPassReverse /api http://127.0.0.1:3000/api

    # WebSocket (chat)
    ProxyPass /socket.io/ ws://127.0.0.1:3000/socket.io/
    ProxyPassReverse /socket.io/ ws://127.0.0.1:3000/socket.io/

    ErrorLog ${APACHE_LOG_DIR}/eadministration_error.log
    CustomLog ${APACHE_LOG_DIR}/eadministration_access.log combined
</VirtualHost>
```

Activation:
```bash
sudo a2ensite eadministration.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 14. SSL HTTPS avec Let's Encrypt
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d app.mondomaine.com
```

Renouvellement auto:
```bash
sudo systemctl status certbot.timer
```

## 15. Verification fonctionnelle
- Frontend: https://app.mondomaine.com
- API health/doc: https://app.mondomaine.com/api/docs
- Login utilisateur
- Upload document
- Workflow
- Notifications/chat websocket

Verifier les logs:
```bash
pm2 logs
sudo tail -f /var/log/apache2/eadministration_error.log
```

## 16. Exploitation via Webmin
Dans Webmin, utiliser:
- System -> Bootup and Shutdown (services apache2, mysql/postgresql)
- Networking -> Linux Firewall (regles UFW)
- System -> Scheduled Cron Jobs (sauvegardes)
- Servers -> Apache Webserver (vhosts)

## 17. Sauvegarde et maintenance
Recommandations minimales:
- Sauvegarde quotidienne base de donnees
- Sauvegarde du dossier projet et des uploads
- Rotation des logs
- Mises a jour de securite hebdomadaires

Exemple sauvegarde MySQL:
```bash
mysqldump -u epadmin_app -p e_parapheur > /backup/e_parapheur_$(date +%F).sql
```

## 18. Checklist de mise en production
- DNS pointe vers le serveur
- HTTPS actif
- Variables .env verifiees
- Migrations executees
- PM2 actif au reboot
- Apache reverse proxy OK
- Tests fonctionnels passes
- Sauvegarde planifiee

## 19. Depannage rapide
- Erreur 502/503: verifier PM2 et ports 3000/5173
- Erreur DB connect: verifier DB_HOST, DB_PORT, credentials
- CORS: verifier URL frontend autorisee cote backend
- WebSocket KO: verifier module proxy_wstunnel Apache
- Certificat SSL: verifier certbot et DNS

---
Document pret pour une implementation standard Ubuntu 22.04 + Webmin + Apache sur E-administration.
