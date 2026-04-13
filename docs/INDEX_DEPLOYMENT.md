# 📚 E-Administration — Documentation Déploiement Ubuntu 22.04

## 📂 Structure Documentation Créée

Cette section contient tous les guides et scripts nécessaires pour déployer E-Administration sur Ubuntu 22.04 avec Apache2, MariaDB et Node.js.

---

## 🚀 Démarrage Rapide (5 minutes)

Si vous êtes pressé, voici les 3 étapes essentielles:

### 1. Préparer le script
```bash
# Sur Ubuntu 22.04, télécharger et exécuter:
curl -O https://raw.githubusercontent.com/ABOUBAK123/e-administration/main/scripts/deploy-ubuntu-22.04.sh
sudo bash deploy-ubuntu-22.04.sh "e-administration.dyula.ci"
```

### 2. Vérifier l'installation
```bash
bash scripts/verify-deployment.sh "e-administration.dyula.ci"
```

### 3. Voir les logs
```bash
pm2 logs
```

**C'est tout!** L'application doit maintenant être accessible sur `https://e-administration.dyula.ci`

---

## 📖 Fichiers de Documentation

### 🔴 Priorité 1 - Démarrer ici

#### [UBUNTU_DEPLOYMENT_QUICKSTART.md](UBUNTU_DEPLOYMENT_QUICKSTART.md)
- **Objectif**: Vue d'ensemble rapide (5-10 min)
- **Contenu**: 
  - Commandes d'installation automatisée
  - Vérifications post-déploiement
  - Gestion standard des services
- **Pour qui**: Tous les utilisateurs
- **Temps de lecture**: 10 minutes

### 🟡 Priorité 2 - Guide complet

#### [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md)
- **Objectif**: Documentation complète (30-45 min)
- **Contenu**:
  - Installation manuelle pas-à-pas
  - Configuration Apache reverse proxy + SSL
  - Setup MariaDB sécurisé
  - Gestion PM2 & logs
  - Sauvegarde & maintenance
  - Dépannage complet
- **Pour qui**: Administrateurs, qui veulent comprendre chaque étape
- **Temps de lecture**: 30 minutes

### 🟢 Priorité 3 - Référence

#### [CHEATSHEET.md](CHEATSHEET.md)
- **Objectif**: Commandes courantes pour l'administration quotidienne
- **Contenu**:
  - Démarrage/arrêt services
  - Monitoring & logs
  - Mise à jour application
  - Gestion base de données
  - Firewall & sécurité
  - Dépannage rapide
- **Pour qui**: Administrateurs système, opérations
- **Temps de lecture**: Référence (consulter au besoin)

#### [IMPLEMENTATION_UBUNTU_22_04_WEBMIN_APACHE.md](IMPLEMENTATION_UBUNTU_22_04_WEBMIN_APACHE.md)
- **Objectif**: Guide original (v2.0 avec améliorations)
- **Contenu**: Vue d'ensemble architecture, bases
- **Pour qui**: Référence historique, consultation spécifique
- **Temps de lecture**: Consulter au besoin

---

## 🛠️ Scripts d'Installation & Vérification

### [scripts/deploy-ubuntu-22.04.sh](../scripts/deploy-ubuntu-22.04.sh)
**Script d'installation automatisée complète**

```bash
# Exécution
sudo bash scripts/deploy-ubuntu-22.04.sh "e-administration.dyula.ci"

# Ce que le script fait:
✓ Mise à jour système
✓ Installation Node.js 18+
✓ Installation MariaDB
✓ Création base de données
✓ Configuration .env
✓ Build backend & frontend
✓ Configuration Apache2 + reverse proxy
✓ Installation SSL Let's Encrypt
✓ Setup PM2 + startup automatique
✓ Exécution migrations

# Durée: 10-15 minutes
# Sortie: credentials DB + URLs d'accès
```

**À la fin du script, conservez les credentials affichés!**

### [scripts/verify-deployment.sh](../scripts/verify-deployment.sh)
**Script de vérification post-installation**

```bash
# Exécution
bash scripts/verify-deployment.sh "e-administration.dyula.ci"

# Vérifie:
✓ Services système (Node, npm, MariaDB, Apache, PM2)
✓ Ports (3000, 5173, 80, 443, 3306)
✓ Processus PM2 (backend & frontend running)
✓ Connectivité (localhost:3000, localhost:5173)
✓ Configuration Apache
✓ Fichiers .env
✓ Répertoires build (dist)
✓ Certificat SSL

# Sortie: Rapport détaillé avec état de chaque élément
```

**Utile après chaque déploiement ou après troubleshooting**

---

## ⚙️ Configuration

### [ecosystem.config.js](../ecosystem.config.js)
**Configuration PM2 pour gestion des processus**

Contient:
- Configuration backend NestJS (cluster mode, 2 instances)
- Configuration frontend Vite preview (:5173)
- Logs, memory limits, restart policies
- Deploy configuration (optionnel)

Utilisation:
```bash
pm2 start ecosystem.config.js      # Démarrer
pm2 restart ecosystem.config.js    # Redémarrer
pm2 logs                           # Voir logs
pm2 monit                          # Dashboard
```

---

## 🎯 Flux d'Installation Recommandé

### Première installation

1. **Préparation** (5 min)
   - Avoir un serveur Ubuntu 22.04 neuf
   - DNS pointé vers le serveur
   - Accès SSH root ou sudo

2. **Installation automatisée** (10-15 min)
   ```bash
   sudo bash scripts/deploy-ubuntu-22.04.sh "e-administration.dyula.ci"
   ```

3. **Vérification** (2 min)
   ```bash
   bash scripts/verify-deployment.sh "e-administration.dyula.ci"
   ```

4. **Test dans le navigateur** (1 min)
   - Frontend: https://e-administration.dyula.ci
   - API Docs: https://e-administration.dyula.ci/api/docs

5. **Sécurisation** (10 min)
   - Consulter "Sécurité Essentiels" dans [UBUNTU_DEPLOYMENT_QUICKSTART.md](UBUNTU_DEPLOYMENT_QUICKSTART.md)
   - Activer UFW firewall
   - Configurer fail2ban
   - Sauvegardes automatiques

### Mise à jour application

```bash
cd /var/www/e-administration
git pull origin main
npm install
npm run migration:run
npm run backend:build
npm run frontend:build
pm2 restart all
```

Consulter [CHEATSHEET.md](CHEATSHEET.md) rubrique "Mise à Jour / Redéploiement"

### Troubleshooting

1. **Consulter d'abord**: [CHEATSHEET.md](CHEATSHEET.md) rubrique "Dépannage Rapide"
2. **Vérifier l'installation**: `bash scripts/verify-deployment.sh`
3. **Voir les logs**: `pm2 logs`
4. **Guide complet**: [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md) section 8

---

## 📚 Choix du document par cas d'usage

| Cas d'usage | Document à consulter |
|-------------|----------------------|
| **Je veux tout installer rapidement** | [UBUNTU_DEPLOYMENT_QUICKSTART.md](UBUNTU_DEPLOYMENT_QUICKSTART.md) |
| **Je veux comprendre chaque étape** | [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md) |
| **J'ai besoin d'une commande pour...** | [CHEATSHEET.md](CHEATSHEET.md) |
| **Le service ne démarre pas** | [CHEATSHEET.md](CHEATSHEET.md) → Dépannage Rapide |
| **Je veux sauvegarder la BDD** | [CHEATSHEET.md](CHEATSHEET.md) → Base de Données |
| **Je dois renouveller le certificat SSL** | [CHEATSHEET.md](CHEATSHEET.md) → SSL / Let's Encrypt |
| **Je ne sais pas quoi faire ensuite** | [UBUNTU_DEPLOYMENT_QUICKSTART.md](UBUNTU_DEPLOYMENT_QUICKSTART.md) → Prochaines étapes |

---

## ✅ Checklist Pré-Déploiement

Avant d'exécuter le script, vérifiez:

- [ ] Serveur Ubuntu 22.04 LTS (fraîchement créé ou clean)
- [ ] Accès SSH en tant que root ou utilisateur avec sudo
- [ ] Nom de domaine prêt et controllable (pour DNS)
- [ ] Espace disque disponible: minimum 20 GB
- [ ] RAM disponible: minimum 2 GB (4 GB idéal)
- [ ] Connectivité internet stable
- [ ] Port 80/443 accessibles depuis Internet (firewall configuré)
- [ ] Aucun autre service n'utilise les ports 3000, 5173, 80, 443

---

## 🔒 Sécurité Post-Installation

**À faire immédiatement après déploiement:**

1. **Credentials**
   ```bash
   # Les credentials DB sont affichés à la fin du script
   # Transférez-les dans un gestionnaire de secrets (LastPass, Vault, etc)
   # NE les committez JAMAIS dans git
   ```

2. **Firewall**
   ```bash
   sudo ufw enable
   sudo ufw allow 22/tcp
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   ```

3. **SSH Hardening**
   ```bash
   # Désactiver login root
   # Implémenter SSH keys
   # Changer port SSH par défaut (optionnel)
   ```

4. **Fail2Ban** (protection brute-force)
   ```bash
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   ```

5. **Backups**
   - Configurer sauvegarde BDD automatique
   - Configurer sauvegarde uploads automatique
   - Tester restauration

Voir détails: [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md) section 8

---

## 📞 Support & Accès

| Élément | Accès |
|---------|-------|
| **Frontend** | https://e-administration.dyula.ci |
| **API Documentation** | https://e-administration.dyula.ci/api/docs |
| **Logs** | `pm2 logs` |
| **Dashboard PM2** | `pm2 monit` |
| **Logs Apache** | `/var/log/apache2/e-administration-error.log` |
| **Config Backend** | `/var/www/e-administration/apps/backend/.env` |
| **Config Frontend** | `/var/www/e-administration/apps/frontend/.env` |

---

## 🔄 Maintenance Courante

### Quotidien
```bash
# Vérifier status
pm2 status

# Consulter logs
pm2 logs
```

### Hebdomadaire
```bash
# Vérifier certificat SSL
sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/e-administration.dyula.ci/fullchain.pem

# Vérifier espace disque
df -h

# Vérifier updates système
sudo apt list --upgradable
```

### Mensuel
- [ ] Appliquer mises à jour système: `sudo apt upgrade`
- [ ] Vérifier sauvegardes BDD
- [ ] Vérifier space disque disponible
- [ ] Consulter logs erreurs for patterns

---

## 🎓 Ressources Externales

- **PM2**: https://pm2.keymetrics.io/docs
- **Apache**: https://httpd.apache.org/docs/
- **MariaDB**: https://mariadb.org/documentation/
- **Let's Encrypt**: https://letsencrypt.org/docs/
- **NestJS**: https://docs.nestjs.com/
- **Vite**: https://vitejs.dev/guide/

---

## 📝 Notes Importantes

1. **Pas d'accès git sur le serveur?**
   - Consultez les commandes de patch manuel dans les docs précédentes
   - Alternative: cloner le repo en local, packager en .zip, uploader

2. **MariaDB vs MySQL?**
   - MariaDB est un fork MySQL, compatible 100%
   - Natif sous Ubuntu 22.04 (plus à jour)
   - Pour les anciens scripts MySQL, changer `mysql` → `mariadb` seulement

3. **Veux-tu PostgreSQL?**
   - Pas d'implémentation incluse
   - Adapter le script `deploy-ubuntu-22.04.sh` si nécessaire
   - Consulter le code NestJS TypeORM pour adapter connection string

4. **Variables .env**
   - Modifier `.env` nécessite redémarrage du service affecté
   - Backend: `pm2 restart e-admin-backend`
   - Frontend: `npm run build` + restart

---

## 🆘 Demander de l'aide

Si vous êtes bloqué:

1. Exécutez: `bash scripts/verify-deployment.sh`
2. Consultez: `pm2 logs | tail -50`
3. Lisez: [CHEATSHEET.md](CHEATSHEET.md) rubrique "Dépannage Rapide"
4. Consultez: [DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md](DEPLOYMENT_UBUNTU_22_04_PRODUCTION.md) section 8

---

## 📋 Version & Changelog

**Version**: 1.0.0 (2024)

### Inclus:
- ✅ Installation automatisée script bash
- ✅ Guide complet manuel
- ✅ Configuration PM2 (ecosystem.config.js)
- ✅ Script de vérification post-déploiement
- ✅ Cheat sheet commandes courantes
- ✅ Guides Apache + SSL + MariaDB
- ✅ Procédures sauvegarde & maintenance
- ✅ Troubleshooting détaillé

---

**Dernière mise à jour**: 12 avril 2024
Document d'index E-Administration Ubuntu 22.04 Deployment
