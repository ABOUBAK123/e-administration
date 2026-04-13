# Installation Ubuntu 22.04 - Guide Rapide

Ce répertoire contient tous les fichiers nécessaires pour déployer E-Administration sur Ubuntu 22.04 avec Apache2, MariaDB et Node.js.

## 📋 Documentation

- **[DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md)** — Guide complet pas-à-pas
  - Installation manuelle détaillée
  - Configuration Apache & SSL
  - Gestion PM2
  - Sauvegarde & maintenance
  - Dépannage

- **[IMPLEMENTATION_UBUNTU_22_04_WEBMIN_APACHE.md](IMPLEMENTATION_UBUNTU_22_04_WEBMIN_APACHE.md)** — Guide d'implémentation original
  - Webmin (optionnel)
  - Configuration initiale
  - Bases de données

## 🚀 Installation Automatisée (Recommandée)

### Sur votre serveur Ubuntu 22.04:

```bash
# Télécharger le script
curl -O https://raw.githubusercontent.com/ABOUBAK123/e-administration/main/scripts/deploy-ubuntu-22.04.sh

# Donner permission d'exécution
chmod +x deploy-ubuntu-22.04.sh

# Exécuter (remplacer par votre domaine)
sudo bash deploy-ubuntu-22.04.sh "e-administration.dyula.ci"
```

Le script fait automatiquement:
- ✅ Mise à jour système
- ✅ Installation Node.js 18+
- ✅ Installation et sécurisation MariaDB
- ✅ Création base de données
- ✅ Configuration variables d'environnement
- ✅ Build backend & frontend
- ✅ Configuration Apache2 en reverse proxy
- ✅ Installation SSL Let's Encrypt
- ✅ Configuration PM2 pour démarrage automatique
- ✅ Gestion des permissions

**Durée estimée**: 10-15 minutes

**Important**: À la fin du script, conservez les identifiants de base de données affichés dans le fichier créé.

---

## 📝 Installation Manuelle

Si vous préférez installer étape par étape, consultez [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md) section 4.

---

## 🔍 Vérification Post-Installation

Après le déploiement, vérifiez que tout fonctionne:

```bash
# 1. Statut des processus
pm2 status

# 2. Test frontend
curl -I https://e-administration.dyula.ci

# 3. Test API
curl -I https://e-administration.dyula.ci/api/docs

# 4. Vérifier logs
pm2 logs

# 5. Vérifier ports
sudo netstat -tulpn | grep -E ':(3000|5173|80|443)'

# 6. Vérifier Apache
sudo systemctl status apache2

# 7. Vérifier MariaDB
sudo systemctl status mariadb
```

---

## 📚 Architecture Déployée

```
Public Internet (HTTPS)
        ↓
   Apache2 (reverse proxy)
   Port 80/443
        ↓
    ┌───┴───┐
    ↓       ↓
Frontend  Backend
:5173    :3000/socket.io
React+    NestJS +
Vite      TypeORM
    ↓       ↓
    └───┬───┘
        ↓
    MariaDB :3306
    e_parapheur (base)
```

---

## 🛠️ Gestion Standard Post-Installation

### Démarrage/Arrêt Services

```bash
# Voir status
pm2 status

# Redémarrer tous les services
pm2 restart all

# Redémarrer seulement le backend
pm2 restart e-admin-backend

# Arrêt propre
pm2 stop all

# Voir les logs
pm2 logs
pm2 logs e-admin-backend
pm2 logs e-admin-frontend
```

### Mise à jour Application

```bash
# Aller dans le répertoire
cd /var/www/e-administration

# Récupérer dernière version
git pull origin main

# Installer nouvelles dépendances
npm install

# Exécuter migrations BDD (si nécessaire)
npm run migration:run

# Rebuild
npm run backend:build
npm run frontend:build

# Redémarrer
pm2 restart all

# Vérifier
pm2 status
```

### Consulter Logs

```bash
# Apache errors
sudo tail -f /var/log/apache2/e-administration-error.log

# Apache access
sudo tail -f /var/log/apache2/e-administration-access.log

# PM2/Application
pm2 logs

# MariaDB
sudo tail -f /var/log/mysql/error.log

# Système
journalctl -xe
```

---

## 🔒 Sécurité Essentiels

Après installation, sécurisez votre serveur:

```bash
# 1. Activer UFW Firewall
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw status

# 2. Fail2Ban (protection brute-force)
sudo apt install -y fail2ban
sudo systemctl enable fail2ban

# 3. SSH hardening
sudo nano /etc/ssh/sshd_config
# Importer les bonnes pratiques

# 4. Certificat SSL auto-renouvellement
sudo systemctl enable certbot.timer

# 5. Vérifier droits fichiers
ls -la /var/www/e-administration/apps/backend/.env
# Doit être: -rw------- (600)
```

---

## 🗄️ Sauvegarde Base de Données

### Sauvegarde manuelle

```bash
# Exporter BDD
mysqldump -u eadmin_app -p e_parapheur > backup_$(date +%Y%m%d).sql

# Compresser
gzip backup_*.sql

# Restaurer (si besoin)
gunzip backup_*.sql.gz
mysql -u eadmin_app -p e_parapheur < backup_*.sql
```

### Sauvegarde automatique (crontab)

```bash
# Éditer crontab
crontab -e

# Ajouter (sauvegarde quotidienne à 2h du matin)
0 2 * * * mysqldump -u eadmin_app -p'PASSWORD' e_parapheur | gzip > /backup/db_$(date +\%Y\%m\%d).sql.gz
```

---

## ⚠️ Dépannage Rapide

| Problème | Solution |
|----------|----------|
| **502 Bad Gateway** | `pm2 restart all` puis vérifier `pm2 logs` |
| **Connection refused :3000** | Vérifier `sudo netstat -tulpn \| grep 3000` et `pm2 status` |
| **Erreur base de données** | Vérifier identifiants `.env` vs créés dans MariaDB |
| **SSL non valide** | `sudo certbot certificates` et `sudo certbot renew --dry-run` |
| **WebSocket timeout** | Vérifier `/etc/apache2/sites-available/e-administration.dyula.ci.conf` |
| **Permissions denied** | `sudo chown -R eadmin:www-data /var/www/e-administration` |

---

## 📞 Support et Ressources

- **Documentation API**: https://e-administration.dyula.ci/api/docs (Swagger)
- **Logs Application**: `pm2 logs`
- **GitHub Issues**: Consultez le repo principal
- **PM2 CLI Guide**: `pm2 help`

---

## 🎯 Checklist Pré-Production

Avant de mettre en ligne, vérifiez:

- [ ] Domaine DNS configuré
- [ ] HTTPS/SSL actif et durable
- [ ] Variables `.env` correctes (JWT_SECRET, DB_PASSWORD, etc.)
- [ ] Migrations BDD exécutées
- [ ] Backend et frontend buildés
- [ ] PM2 services démarrés automatiquement
- [ ] Apache reverse proxy validé
- [ ] Logs consultables et sans erreurs
- [ ] Firewall (UFW) activé
- [ ] Sauvegarde automatique planifiée
- [ ] Test fonctionnel: login OK
- [ ] Test WebSocket: notifications OK

---

**Document d'aide** — E-Administration Deployment v1.0
Adapté pour: Ubuntu 22.04 LTS + Apache2 + MariaDB + PM2
