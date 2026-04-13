# CI/CD GitHub Actions - Deploiement automatique dyula.ci

Ce document configure un deploiement automatique vers:
- Serveur: dyula.ci
- Repertoire: /var/www/html/e-administration
- Branche source: main

## 1. Workflow ajoute

Le workflow est dans:
- .github/workflows/deploy-dyula.yml

Il fait:
1. Build complet du monorepo
2. Connexion SSH au serveur
3. Synchronisation code avec origin/main
4. Build backend + frontend
5. Migration DB (si apps/backend/.env existe)
6. Restart PM2
7. Reload Apache

## 2. Secrets GitHub a definir

Dans GitHub > Settings > Secrets and variables > Actions > New repository secret:

1. DYULA_SSH_HOST
- Exemple: e-administration.dyula.ci

2. DYULA_SSH_USER
- Exemple: ubuntu

3. DYULA_SSH_PRIVATE_KEY
- Cle privee SSH complete (format OpenSSH), incluant BEGIN/END lines

4. DYULA_SSH_PORT
- Exemple: 22

## 3. Preparation serveur (une seule fois)

Executer sur le serveur:

```bash
sudo mkdir -p /var/www/html
sudo chown -R ubuntu:ubuntu /var/www/html

# Installer prerequis
sudo apt update
sudo apt install -y git nodejs npm

# PM2 + Apache (si pas deja faits)
sudo npm install -g pm2
sudo systemctl enable apache2
```

Important:
- L'utilisateur DYULA_SSH_USER doit pouvoir ecrire dans /var/www/html/e-administration
- Si sudo demande un mot de passe en SSH non interactif, autoriser NOPASSWD pour les commandes utilisees

## 4. Verification apres push

Apres un push sur main, verifier:

```bash
cd /var/www/html/e-administration
git rev-parse --short HEAD
pm2 status
sudo systemctl status apache2 --no-pager
```

Consulter les logs Actions dans l'onglet "Actions" du repo.

## 5. Lancer manuellement le deploy

Le workflow supporte aussi workflow_dispatch:
- GitHub > Actions > CI and Deploy to dyula.ci > Run workflow

## 6. Securite recommandee

- Utiliser une cle SSH dediee au deploy
- Restreindre la cle dans authorized_keys (from=IP GitHub Actions si possible)
- Ne jamais mettre de secrets dans le repo
- Conserver apps/backend/.env uniquement sur le serveur
