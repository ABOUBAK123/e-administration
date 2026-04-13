# 📋 Cheat Sheet — E-Administration Ubuntu 22.04

Commandes courantes pour gérer E-Administration en production.

---

## 🚀 Démarrage & Arrêt

```bash
# Démarrer tous les services
pm2 start ecosystem.config.js

# Arrêter tous les services
pm2 stop all

# Redémarrer tous les services
pm2 restart all

# Redémarrer seulement le backend
pm2 restart e-admin-backend

# Redémarrer seulement le frontend
pm2 restart e-admin-frontend

# Voir l'état des processus
pm2 status
pm2 list
pm2 info e-admin-backend
```

---

## 📊 Monitoring & Logs

```bash
# Dashboard temps réel
pm2 monit

# Voir tous les logs
pm2 logs

# Logs d'un service spécifique
pm2 logs e-admin-backend
pm2 logs e-admin-frontend

# Logs avec filtrage
pm2 logs | grep ERROR
pm2 logs e-admin-backend | tail -100

# Logs Apache
sudo tail -f /var/log/apache2/e-administration-error.log
sudo tail -f /var/log/apache2/e-administration-access.log

# Logs système complets
journalctl -u e-administration -f
journalctl -xe | tail -50

# Sauvegarder les logs
pm2 logs > app-logs-$(date +%Y%m%d).txt
```

---

## 🔄 Mise à Jour / Redéploiement

```bash
# Aller au répertoire
cd /var/www/e-administration

# Récupérer le code source
git pull origin main

# Installer les dépendances
npm install

# Lancer les migrations DB
npm run migration:run

# Compiler le backend et frontend
npm run backend:build
npm run frontend:build

# Redémarrer les services
pm2 restart all

# Vérifier que tout est OK
pm2 status
curl https://e-administration.dyula.ci
```

---

## 🗄️ Base de Données

### Sauvegarde

```bash
# Exporter la base
mysqldump -u eadmin_app -p'PASSWORD' e_parapheur > backup.sql

# Exporter et compresser
mysqldump -u eadmin_app -p'PASSWORD' e_parapheur | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Exporter avec toutes les bases (root)
sudo mysqldump -A > all_databases_$(date +%Y%m%d).sql

# Vérifier intégrité fichier
file backup.sql.gz
gunzip -t backup.sql.gz
```

### Restauration

```bash
# Restaurer depuis fichier
mysql -u eadmin_app -p'PASSWORD' e_parapheur < backup.sql

# Restaurer depuis fichier compressé
gunzip < backup.sql.gz | mysql -u eadmin_app -p'PASSWORD' e_parapheur

# Restaurer une table spécifique
mysql -u eadmin_app -p'PASSWORD' e_parapheur < backup.sql --table=users
```

### Gestion

```bash
# Accéder à MariaDB
sudo mysql
# ou avec password:
mysql -u eadmin_app -p

# Dans MariaDB CLI:
USE e_parapheur;                    # Sélectionner base
SHOW TABLES;                         # Lister tables
SELECT COUNT(*) FROM users;         # Compter lignes
DESCRIBE users;                     # Structure table
SELECT * FROM users LIMIT 5;        # Voir données
```

---

## 🔒 Permissions & Sécurité

```bash
# Vérifier propriétaire du répertoire
ls -ld /var/www/e-administration

# Corriger propriétaire
sudo chown -R eadmin:www-data /var/www/e-administration

# Corriger permissions
sudo chmod -R 755 /var/www/e-administration
sudo chmod 600 /var/www/e-administration/apps/backend/.env

# Afficher fichiers .env (ne pas leaker!)
sudo cat /var/www/e-administration/apps/backend/.env
sudo cat /var/www/e-administration/apps/frontend/.env

# Vérifier permissions .env
ls -la /var/www/e-administration/apps/backend/.env
# Doit afficher: -rw------- (600)
```

---

## 🌐 Apache & Reverse Proxy

```bash
# Tester config Apache
sudo apache2ctl configtest
# output: "Syntax OK"

# Recharger Apache (sans downtime)
sudo systemctl reload apache2

# Redémarrer Apache (peut avoir interruption)
sudo systemctl restart apache2

# Voir status Apache
sudo systemctl status apache2

# Activer/désactiver modules
sudo a2enmod ssl          # Activer SSL
sudo a2dismod deflate     # Désactiver compression
sudo a2ensite e-administration.dyula.ci.conf      # Activer site

# Lister sites actifs
sudo a2query -s          # Sites enabled
sudo ls -la /etc/apache2/sites-enabled/

# Voir config Apache active
sudo cat /etc/apache2/sites-available/e-administration.dyula.ci.conf
```

---

## 🔐 SSL / Let's Encrypt

```bash
# Lister les certificats
sudo certbot certificates

# Afficher détails certificat
sudo openssl x509 -in /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem -text -noout

# Vérifier expiration
sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem

# Renouveler certificat (dry-run)
sudo certbot renew --dry-run

# Renouveler certificat (réel)
sudo certbot renew

# Voir timer renouvellement automatique
sudo systemctl status certbot.timer

# Ajouter/retirer domaines
sudo certbot --expand -d e-administration.dyula.ci -d www.e-administration.dyula.ci

# Test redirection www -> domaine principal
curl -I https://www.e-administration.dyula.ci
```

---

## 🔥 Firewall (UFW)

```bash
# Statut firewall
sudo ufw status
sudo ufw status verbose

# Activer firewall
sudo ufw enable

# Désactiver firewall
sudo ufw disable

# Ajouter règles
sudo ufw allow 22/tcp           # SSH
sudo ufw allow 80/tcp           # HTTP
sudo ufw allow 443/tcp          # HTTPS
sudo ufw allow 3306/tcp         # MySQL (local only!)

# Restreindre à IP spécifique
sudo ufw allow from 192.168.1.100 to any port 3306

# Retirer règle
sudo ufw delete allow 3306/tcp

# Bloquer IP
sudo ufw deny from 192.168.1.50

# Lister règles numérotées
sudo ufw status numbered
```

---

## 📈 Performance & Diagnostique

```bash
# Voir les ports en écoute
sudo netstat -tulpn
sudo netstat -tulpn | grep LISTEN

# Voir processus Node
ps aux | grep node
ps aux | grep npm

# Voir ressources utilisées
top
htop                           # Si installé

# Voir usage disque
df -h                          # Espace disque
du -sh /var/www/e-administration

# Voir usage mémoire
free -h

# Vérifier uptime
uptime

# Voir logs système
dmesg                          # Kernel logs
journalctl -n 50              # Derniers 50 logs

# Bench HTTP
ab -n 100 -c 10 https://e-administration.dyula.ci/
# ou avec curl:
time curl https://e-administration.dyula.ci
```

---

## 🚨 Dépannage Rapide

```bash
# "502 Bad Gateway"
pm2 status                  # Vérifier que backend tourne
pm2 restart e-admin-backend # Redémarrer backend
pm2 logs                    # Voir erreurs

# "Connection refused"
sudo netstat -tulpn | grep 3000  # Port écoute?
pm2 status                       # Processus OK?
pm2 logs e-admin-backend         # Erreur démarrage?

# "Database error"
mysql -u eadmin_app -p'PASSWORD' e_parapheur -e "SELECT 1;"
cat /var/www/e-administration/apps/backend/.env | grep DB_

# "WebSocket timeout"
sudo cat /etc/apache2/sites-available/e-administration.dyula.ci.conf | grep socket.io
sudo apache2ctl configtest

# "SSL error"
sudo certbot certificates
sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem

# "Permission denied"
sudo chown -R eadmin:www-data /var/www/e-administration
ls -la /var/www/e-administration/apps/backend/.env
```

---

## 📞 Informations Utiles

```bash
# Version système
lsb_release -a
cat /etc/os-release

# Version Node
node -v
npm -v

# Version MariaDB
mysql --version

# Version Apache
apache2 -v

# PM2 version
pm2 -v

# Uptime système
uptime

# Date/heure serveur
date
timedatectl

# DNS resolver
nslookup e-administration.dyula.ci
dig e-administration.dyula.ci
```

---

## 🔧 Configuration Rapide

### Ajouter/modifier variable .env

```bash
# Backend
sudo nano /var/www/e-administration/apps/backend/.env

# Frontend
sudo nano /var/www/e-administration/apps/frontend/.env

# Frontend build après modif:
cd /var/www/e-administration
sudo -u eadmin npm run frontend:build
pm2 restart e-admin-frontend
```

### Recompiler seul le backend

```bash
cd /var/www/e-administration
sudo -u eadmin npm run backend:build
pm2 restart e-admin-backend
```

### Purger cache PM2

```bash
pm2 kill          # Arrête complètement PM2
pm2 start ecosystem.config.js  # Redémarre
pm2 save          # Sauvegarde config
```

---

## 💡 Tips Productivité

```bash
# Créer alias bash (dans ~/.bashrc)
alias eadmin-logs="pm2 logs"
alias eadmin-status="pm2 status"
alias eadmin-restart="pm2 restart all"
alias eadmin-cd="cd /var/www/e-administration"

# Exemple alias Apache
alias apache-test="sudo apache2ctl configtest"
alias apache-reload="sudo systemctl reload apache2"

# Puis sourcer:
source ~/.bashrc
```

---

## 📚 Ressources

- **PM2 Doc**: https://pm2.keymetrics.io/docs
- **Apache Reverse Proxy**: https://httpd.apache.org/docs/current/mod/mod_proxy.html
- **MariaDB Doc**: https://mariadb.org/documentation/
- **Let's Encrypt**: https://letsencrypt.org/
- **UFW Firewall**: https://help.ubuntu.com/community/UFW

---

**Dernière mise à jour**: 2024
Aide mémoire E-Administration
