# Deploiement E-administration (version pre-remplie)

## Cible
- Domaine: e-administration.dyula.ci
- IP serveur: 141.95.84.126
- OS: Ubuntu 22.04
- Admin serveur: Webmin
- Reverse proxy: Apache2

## 1. DNS (obligatoire avant SSL)
Chez votre fournisseur DNS, configurez:
- Type: A
- Nom: @
- Valeur: 141.95.84.126
- TTL: 300

Optionnel (si vous voulez aussi www):
- Type: A
- Nom: www
- Valeur: 141.95.84.126

Verification:
```bash
dig +short e-administration.dyula.ci
```
La commande doit retourner: 141.95.84.126

## 2. Connexion serveur et mise a jour
```bash
ssh <UTILISATEUR_SUDO>@141.95.84.126
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip ca-certificates gnupg lsb-release software-properties-common
sudo timedatectl set-timezone Africa/Abidjan
sudo hostnamectl set-hostname eadministration-prod
```

## 3. Pare-feu
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 10000/tcp
sudo ufw enable
sudo ufw status
```

## 4. Installation Webmin
```bash
curl -fsSL https://download.webmin.com/jcameron-key.asc | sudo gpg --dearmor -o /usr/share/keyrings/webmin.gpg
echo "deb [signed-by=/usr/share/keyrings/webmin.gpg] https://download.webmin.com/download/repository sarge contrib" | sudo tee /etc/apt/sources.list.d/webmin.list
sudo apt update
sudo apt install -y webmin
```

Acces Webmin:
- https://141.95.84.126:10000

## 5. Installation Apache + modules proxy
```bash
sudo apt install -y apache2
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl
sudo systemctl enable apache2
sudo systemctl start apache2
```

## 6. Installation Node.js 18
```bash
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs build-essential
node -v
npm -v
```

## 7. Installation MySQL (alignement avec votre backend actuel)
```bash
sudo apt install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql
sudo mysql_secure_installation
```

Creation DB + user:
```bash
sudo mysql -e "CREATE DATABASE IF NOT EXISTS e_parapheur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'epadmin_app'@'localhost' IDENTIFIED BY 'MotDePasseFort!';"
sudo mysql -e "GRANT ALL PRIVILEGES ON e_parapheur.* TO 'epadmin_app'@'localhost'; FLUSH PRIVILEGES;"
```

## 8. Recuperation du projet
```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone <URL_DU_REPO> E-administration
sudo chown -R $USER:$USER /var/www/E-administration
cd /var/www/E-administration
```

## 9. Variables d'environnement backend (pre-remplies)
Editez le fichier apps/backend/.env:
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_USER=epadmin_app
DB_PASSWORD=MotDePasseFort!
DB_NAME=e_parapheur
DB_SYNCHRONIZE=false
DB_LOGGING=false
DB_SSL=false

JWT_SECRET=ChangeThisSecretNow_UseAVeryLongRandomValue
JWT_EXPIRATION=3600

NODE_ENV=production
API_PORT=3000
API_URL=https://e-administration.dyula.ci

REDIS_HOST=localhost
REDIS_PORT=6379
```

## 10. Build du projet
Depuis /var/www/E-administration:
```bash
npm install
npm run build
```

## 11. Migrations
```bash
cd /var/www/E-administration/apps/backend
npm run typeorm migration:run
```

## 12. Lancement des services avec PM2
```bash
sudo npm install -g pm2

cd /var/www/E-administration/apps/backend
pm2 start dist/main.js --name eadmin-backend

cd /var/www/E-administration/apps/frontend
pm2 start "npm run preview -- --host 0.0.0.0 --port 5173" --name eadmin-frontend

pm2 save
pm2 startup
```

## 13. Configuration Apache pre-remplie
Creer le fichier:
```bash
sudo nano /etc/apache2/sites-available/e-administration.dyula.ci.conf
```

Coller:
```apache
<VirtualHost *:80>
    ServerName e-administration.dyula.ci
    ServerAlias www.e-administration.dyula.ci

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "http"

    # Frontend
    ProxyPass / http://127.0.0.1:5173/
    ProxyPassReverse / http://127.0.0.1:5173/

    # Backend API
    ProxyPass /api http://127.0.0.1:3000/api
    ProxyPassReverse /api http://127.0.0.1:3000/api

    # WebSocket
    ProxyPass /socket.io/ ws://127.0.0.1:3000/socket.io/
    ProxyPassReverse /socket.io/ ws://127.0.0.1:3000/socket.io/

    ErrorLog ${APACHE_LOG_DIR}/e-administration_error.log
    CustomLog ${APACHE_LOG_DIR}/e-administration_access.log combined
</VirtualHost>
```

Activation:
```bash
sudo a2ensite e-administration.dyula.ci.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 14. SSL Let's Encrypt (pre-rempli)
```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d e-administration.dyula.ci -d www.e-administration.dyula.ci
sudo systemctl status certbot.timer
```

## 15. Verification finale
- URL application: https://e-administration.dyula.ci
- API docs: https://e-administration.dyula.ci/api/docs
- Webmin: https://141.95.84.126:10000

Commandes utiles:
```bash
pm2 status
pm2 logs eadmin-backend --lines 100
pm2 logs eadmin-frontend --lines 100
sudo tail -f /var/log/apache2/e-administration_error.log
```

## 16. Checklist rapide
- DNS A record OK vers 141.95.84.126
- Certificat SSL emis
- Backend repond sur /api/v1/*
- Frontend charge correctement
- Upload/avatar/chat testes
- PM2 persistant au reboot

## 17. Deuxieme passe securite production (verrouillage)

### 17.1 Limiter Webmin a votre IP d'administration
Remplacez <IP_ADMIN> par votre IP publique fixe (poste administrateur).

```bash
sudo ufw delete allow 10000/tcp
sudo ufw allow from <IP_ADMIN> to any port 10000 proto tcp
sudo ufw status numbered
```

Durcissement Webmin (SSL uniquement et refus explicite hors IP admin):
```bash
sudo cp /etc/webmin/miniserv.conf /etc/webmin/miniserv.conf.bak
sudo sed -i 's/^ssl=.*/ssl=1/' /etc/webmin/miniserv.conf
sudo sed -i '/^allow=/d' /etc/webmin/miniserv.conf
echo 'allow=<IP_ADMIN>' | sudo tee -a /etc/webmin/miniserv.conf
sudo systemctl restart webmin
```

### 17.2 Installer et configurer fail2ban
```bash
sudo apt install -y fail2ban
```

Creer le filtre Webmin:
```bash
sudo tee /etc/fail2ban/filter.d/webmin-auth.conf > /dev/null <<'EOF'
[Definition]
failregex = ^.*Failed login as .* from <HOST>$
ignoreregex =
EOF
```

Creer la configuration locale fail2ban:
```bash
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = ssh
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
```

Activer et verifier:
```bash
sudo systemctl enable fail2ban
sudo systemctl restart fail2ban
sudo fail2ban-client status
sudo fail2ban-client status sshd
sudo fail2ban-client status webmin-auth
```

### 17.3 SSH hardening (recommande)
Creer un fichier de surcharge propre:
```bash
sudo tee /etc/ssh/sshd_config.d/99-hardening.conf > /dev/null <<'EOF'
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
```

Tester puis recharger SSH:
```bash
sudo sshd -t
sudo systemctl reload ssh
```

Important: assurez-vous qu'une cle SSH fonctionnelle est deja en place avant de couper PasswordAuthentication.

### 17.4 Sauvegardes automatiques (quotidiennes)
Un script est fourni dans le projet:
- scripts/prod_backup.sh

Deployment script sur le serveur:
```bash
sudo mkdir -p /opt/eadministration/scripts
sudo cp /var/www/E-administration/scripts/prod_backup.sh /opt/eadministration/scripts/prod_backup.sh
sudo chmod 700 /opt/eadministration/scripts/prod_backup.sh
sudo mkdir -p /var/backups/e-administration
sudo chmod 700 /var/backups/e-administration
```

Definir le mot de passe DB pour cron (root):
```bash
echo 'DB_PASSWORD=MotDePasseFort!' | sudo tee -a /etc/environment
```

Planification cron (tous les jours a 02:10):
```bash
echo '10 2 * * * root . /etc/environment; /opt/eadministration/scripts/prod_backup.sh >> /var/log/eadmin-backup.log 2>&1' | sudo tee /etc/cron.d/eadmin-backup
sudo chmod 644 /etc/cron.d/eadmin-backup
sudo systemctl restart cron
```

Test manuel:
```bash
sudo -E DB_PASSWORD=MotDePasseFort! /opt/eadministration/scripts/prod_backup.sh
ls -lah /var/backups/e-administration
```

### 17.5 Controle post-verrouillage
```bash
sudo ufw status
sudo fail2ban-client status
sudo systemctl status ssh webmin fail2ban apache2 --no-pager
```

Resultat attendu:
- Webmin accessible uniquement depuis <IP_ADMIN>
- Brute force bloque (SSH, Apache, Webmin)
- SSH root desactive et auth par cle active
- Sauvegardes quotidiennes et retention 14 jours

## 18. Troisieme passe securite (profil strict)

### 18.0 Application en un seul script (copier-coller unique)
Script unique idempotent fourni:
- scripts/apply_prod_security_pass2_pass3.sh

Commande unique (mode S3):
```bash
cd /var/www/E-administration
sudo IP_ADMIN=<IP_ADMIN> DB_PASSWORD='MotDePasseFort!' SSH_PORT=22222 EXTERNAL_MODE=s3 S3_BUCKET=s3://e-administration-backup-prod bash scripts/apply_prod_security_pass2_pass3.sh
```

Commande unique (mode rsync):
```bash
cd /var/www/E-administration
sudo IP_ADMIN=<IP_ADMIN> DB_PASSWORD='MotDePasseFort!' SSH_PORT=22222 EXTERNAL_MODE=rsync RSYNC_TARGET=backupuser@BACKUP_SERVER:/srv/backups/e-administration bash scripts/apply_prod_security_pass2_pass3.sh
```

Commande unique (sans externalisation):
```bash
cd /var/www/E-administration
sudo IP_ADMIN=<IP_ADMIN> DB_PASSWORD='MotDePasseFort!' SSH_PORT=22222 EXTERNAL_MODE=none bash scripts/apply_prod_security_pass2_pass3.sh
```

### 18.1 SSH sur port non standard (exemple: 22222)
Important: gardez une session SSH ouverte pendant le changement pour eviter toute coupure.

Mettre a jour la conf SSH:
```bash
sudo tee /etc/ssh/sshd_config.d/98-port.conf > /dev/null <<'EOF'
Port 22222
EOF
```

Adapter le pare-feu:
```bash
sudo ufw allow 22222/tcp
sudo ufw delete allow OpenSSH
sudo ufw status numbered
```

Tester puis recharger SSH:
```bash
sudo sshd -t
sudo systemctl reload ssh
```

Test de connexion depuis votre poste:
```bash
ssh -p 22222 <UTILISATEUR_SUDO>@141.95.84.126
```

### 18.2 fail2ban avec bantime progressif
Remplacer la section [DEFAULT] par un mode progressif:
```bash
sudo tee /etc/fail2ban/jail.d/99-progressive.local > /dev/null <<'EOF'
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
```

Pour SSH sur port custom:
```bash
sudo sed -i 's/^port\s*=.*/port = 22222/' /etc/fail2ban/jail.local
sudo systemctl restart fail2ban
sudo fail2ban-client status sshd
sudo fail2ban-client status recidive
```

### 18.3 Sauvegarde externalisee (S3 ou rsync)
Script fourni:
- scripts/backup_external_sync.sh

Deployment:
```bash
sudo cp /var/www/E-administration/scripts/backup_external_sync.sh /opt/eadministration/scripts/backup_external_sync.sh
sudo chmod 700 /opt/eadministration/scripts/backup_external_sync.sh
```

Option A: S3 (AWS CLI)
```bash
sudo apt install -y awscli
aws configure
MODE=s3 S3_BUCKET=s3://e-administration-backup-prod /opt/eadministration/scripts/backup_external_sync.sh
```

Option B: rsync vers serveur de backup
```bash
MODE=rsync RSYNC_TARGET=backupuser@BACKUP_SERVER:/srv/backups/e-administration /opt/eadministration/scripts/backup_external_sync.sh
```

Planification quotidienne (02:40), apres backup local (02:10):
```bash
echo '40 2 * * * root MODE=s3 S3_BUCKET=s3://e-administration-backup-prod /opt/eadministration/scripts/backup_external_sync.sh >> /var/log/eadmin-backup-external.log 2>&1' | sudo tee /etc/cron.d/eadmin-backup-external
sudo chmod 644 /etc/cron.d/eadmin-backup-external
sudo systemctl restart cron
```

### 18.4 Auditd (traçabilite securite)
Installation:
```bash
sudo apt install -y auditd audispd-plugins
sudo systemctl enable auditd
```

Regles pretes a l'emploi:
- scripts/auditd-eadministration.rules

Deployment des regles:
```bash
sudo cp /var/www/E-administration/scripts/auditd-eadministration.rules /etc/audit/rules.d/99-eadministration.rules
sudo augenrules --load
sudo systemctl restart auditd
sudo auditctl -l
```

Exemples de recherche d'evenements:
```bash
sudo ausearch -k sshd -ts today
sudo ausearch -k app_files -ts today
sudo aureport -x --summary
```

### 18.5 Verification stricte finale
```bash
sudo ss -tulpn | grep -E '(:80|:443|:22222|:10000|:3000|:5173)'
sudo ufw status
sudo fail2ban-client status
sudo fail2ban-client status sshd
sudo fail2ban-client status recidive
sudo systemctl status ssh fail2ban auditd webmin apache2 --no-pager
```

Objectif atteint (profil strict):
- SSH deplace sur 22222 avec auth par cle
- Blocage progressif des attaques repetitives
- Sauvegardes externalisees hors serveur primaire
- Audit trail systeme actif

---
Ce document est la version operationnelle pre-remplie pour votre domaine e-administration.dyula.ci et votre IP 141.95.84.126.
