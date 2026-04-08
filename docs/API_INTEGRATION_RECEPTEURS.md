# Guide d'integration API - Administrations receptrices

## 1. Objectif
Ce guide explique comment integrer E-Administration dans une application metier (ERP, GED, courrier, BPM, etc.) pour:
- authentifier un compte technique
- recuperer les documents recus
- consulter les metadonnees et versions
- verifier l'authenticite
- ouvrir la version numerique d'un document

Le guide est independant du langage: toute application capable de faire du HTTP/JSON peut l'utiliser.

## 2. URL et prerequis

### Base URL
- Dev: `http://localhost:3000/api/v1`
- Prod: `https://<votre-domaine>/api/v1`

### Prerequis
- Un utilisateur actif (compte technique recommande)
- HTTPS en production
- Horloge serveur synchronisee (NTP)

## 3. Authentification JWT

### 3.1 Login
`POST /auth/login`

Request:
```json
{
  "email": "integration@admin.local",
  "password": "VotreMotDePasse"
}
```

Response:
```json
{
  "accessToken": "...",
  "refreshToken": "...",
  "user": {
    "id": "uuid",
    "email": "integration@admin.local",
    "username": "integration",
    "fullName": "Compte Integration",
    "role": "user",
    "avatar": "/storage/avatars/..."
  }
}
```

Utiliser ensuite l'entete:
`Authorization: Bearer <accessToken>`

### 3.2 Refresh token
`POST /auth/refresh`

Request:
```json
{
  "refreshToken": "..."
}
```

Si `401 Unauthorized`, refaire un login complet.

## 4. Endpoints utiles pour une administration receptrice

### 4.1 Verifier le token
`GET /users/profile`

Usage: test de connectivite apres login/refresh.

### 4.2 Lister les documents
`GET /documents?page=1&limit=20&search=...`

Response type:
```json
{
  "data": [
    {
      "id": "uuid",
      "title": "Courrier entrant 2026-03-27",
      "description": "...",
      "status": "draft",
      "createdAt": "2026-03-27T10:00:00.000Z",
      "updatedAt": "2026-03-27T10:05:00.000Z"
    }
  ],
  "pagination": {
    "total": 120,
    "page": 1,
    "limit": 20,
    "pages": 6
  }
}
```

### 4.3 Consulter un document
`GET /documents/:id`

Retourne le document, ses signatures, versions et QR codes associes.

### 4.4 Consulter les versions
`GET /documents/:id/versions`

Permet de suivre les revisions et l'historique de modification.

### 4.5 Ouvrir la version numerique (public)
`GET /documents/public/:id/digital-version`

- Endpoint public de lecture du fichier
- Type `inline` (affichage navigateur)
- Utilisable dans une iframe ou un lien externe

### 4.6 Verification d'authenticite par numero de document (public)
`GET /qrcode/public/verify/:documentNumber`

Usage: verification rapide depuis une application metier tierce.

### 4.7 Signatures en attente (si besoin de workflow cote recepteur)
`GET /signatures/pending/:userId`

Necessite JWT.

## 5. Strategie d'integration recommandee

## Mode Pull (recommande)
1. Login (ou refresh)
2. Polling periodique `GET /documents`
3. Dedupliquer par `id` + `updatedAt`
4. Pour chaque document nouveau/modifie:
- appeler `GET /documents/:id`
- archiver metadonnees dans votre SI
- exposer un lien vers `GET /documents/public/:id/digital-version`

### Frequence conseillee
- Critique: 30s a 60s
- Standard: 2 a 5 min
- Faible charge: 10 a 15 min

## Mode Push
Aucun webhook de reception standard n'est expose actuellement.
Alternative: polling + notification email cote partage.

## 6. Gestion des erreurs

### Reponses frequentes
- `400`: requete invalide
- `401`: token absent/invalide/expire
- `404`: document introuvable
- `500`: erreur serveur

### Bonnes pratiques
- Si `401`: tenter `POST /auth/refresh`, puis rejouer la requete
- Timeout client: 30s minimum
- Retry exponentiel sur `5xx` (ex: 1s, 2s, 4s, 8s, max 5 essais)
- Journaliser `requestId` interne, URL, code HTTP, message

## 7. Exemples multi-langages

## 7.1 cURL
```bash
# Login
curl -X POST "http://localhost:3000/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"integration@admin.local","password":"secret"}'

# Liste documents
curl -X GET "http://localhost:3000/api/v1/documents?page=1&limit=20" \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

## 7.2 JavaScript (Node.js / navigateur)
```javascript
const baseUrl = "http://localhost:3000/api/v1";

async function login(email, password) {
  const r = await fetch(`${baseUrl}/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ email, password })
  });
  if (!r.ok) throw new Error(`Login failed: ${r.status}`);
  return r.json();
}

async function listDocuments(accessToken) {
  const r = await fetch(`${baseUrl}/documents?page=1&limit=20`, {
    headers: { Authorization: `Bearer ${accessToken}` }
  });
  if (!r.ok) throw new Error(`List failed: ${r.status}`);
  return r.json();
}
```

## 7.3 Python
```python
import requests

BASE = "http://localhost:3000/api/v1"

resp = requests.post(f"{BASE}/auth/login", json={
    "email": "integration@admin.local",
    "password": "secret"
}, timeout=30)
resp.raise_for_status()
access_token = resp.json()["accessToken"]

docs = requests.get(
    f"{BASE}/documents",
    params={"page": 1, "limit": 20},
    headers={"Authorization": f"Bearer {access_token}"},
    timeout=30,
)
docs.raise_for_status()
print(docs.json())
```

## 7.4 Java (HttpClient)
```java
HttpClient client = HttpClient.newHttpClient();

HttpRequest loginReq = HttpRequest.newBuilder()
    .uri(URI.create("http://localhost:3000/api/v1/auth/login"))
    .header("Content-Type", "application/json")
    .POST(HttpRequest.BodyPublishers.ofString(
        "{\"email\":\"integration@admin.local\",\"password\":\"secret\"}"
    ))
    .build();

HttpResponse<String> loginRes = client.send(loginReq, HttpResponse.BodyHandlers.ofString());
```

## 7.5 C# (.NET)
```csharp
using var http = new HttpClient();

var loginPayload = new StringContent(
    "{\"email\":\"integration@admin.local\",\"password\":\"secret\"}",
    Encoding.UTF8,
    "application/json"
);

var loginRes = await http.PostAsync("http://localhost:3000/api/v1/auth/login", loginPayload);
loginRes.EnsureSuccessStatusCode();
```

## 7.6 PHP
```php
$base = 'http://localhost:3000/api/v1';
$ch = curl_init("$base/auth/login");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode([
    'email' => 'integration@admin.local',
    'password' => 'secret'
  ])
]);
$response = curl_exec($ch);
curl_close($ch);
```

## 7.7 Go
```go
client := &http.Client{Timeout: 30 * time.Second}
body := strings.NewReader(`{"email":"integration@admin.local","password":"secret"}`)
req, _ := http.NewRequest("POST", "http://localhost:3000/api/v1/auth/login", body)
req.Header.Set("Content-Type", "application/json")
res, err := client.Do(req)
if err != nil {
    log.Fatal(err)
}
defer res.Body.Close()
```

## 8. Securite
- Ne jamais exposer `accessToken`/`refreshToken` dans les logs
- Stocker les secrets dans un coffre (Vault, KMS, Secret Manager)
- Limiter les droits du compte technique
- Faire une rotation periodique du mot de passe compte technique
- Forcer HTTPS en production

## 9. Check-list de mise en production
- [ ] Compte technique cree et teste
- [ ] Login + refresh valides
- [ ] Recuperation `/documents` validee
- [ ] Traitement `401/404/500` implemente
- [ ] Monitoring + alerting en place
- [ ] Documentation interne du SI mise a jour

## 10. Support et tests
- Swagger UI: `/api/docs`
- Validation token: `GET /users/profile`
- Test smoke minimal: login -> users/profile -> documents

## 11. SLA et exploitation (runbook)

### 11.1 SLA technique recommande (cote client)
- Disponibilite cible integration: 99.5% minimum
- Latence cible API (hors fichiers volumineux): P95 < 1.5s
- Timeout HTTP client: 30s (lecture), 10s (connexion)
- Delai max de reprise apres incident: 15 minutes

### 11.2 Politique de retry
- `401`: tenter un seul refresh token, puis relogin si echec
- `429`: backoff exponentiel + jitter (attente aleatoire)
- `5xx`: retry exponentiel (1s, 2s, 4s, 8s, 16s), max 5 tentatives
- `4xx` metier (`400`, `404`): ne pas retry en boucle, corriger la requete

### 11.3 Supervision minimale
- Compteurs par endpoint: volume, succes, echec
- Taux d'erreur par classe HTTP (2xx/4xx/5xx)
- Temps de reponse P50/P95/P99
- Alarmes:
  - taux `401` > 5% sur 10 min
  - taux `5xx` > 2% sur 5 min
  - absence de polling reussi > 15 min

### 11.4 Journalisation et tracabilite
- Journaliser un `correlationId` par transaction metier
- Conserver: endpoint, methode, status, duree, date, id document
- Ne jamais journaliser les tokens JWT complets

### 11.5 Capacite et volumetrie
- Polling initial: `limit=20`, puis ajuster selon charge
- Si backlog important: traiter par lot et reprendre avec pagination
- Utiliser une file interne (queue) pour decoupler reception API et traitement SI

## 12. Modele de mapping metier (API -> SI interne)

### 12.1 Exemple de table de correspondance
| Champ API | Type | Champ SI cible | Regle de mapping |
|---|---|---|---|
| `id` | UUID | `document_externe_id` | Cle technique externe unique |
| `title` | string | `objet_courrier` | Trim + longueur max SI |
| `description` | string | `resume` | Valeur optionnelle |
| `status` | string | `etat_traitement` | Table de correspondance statuts |
| `createdAt` | datetime ISO | `date_creation_source` | Convertir timezone locale SI |
| `updatedAt` | datetime ISO | `date_maj_source` | Sert a la deduplication |
| `filePath` | string | `url_document` | Utiliser endpoint public digital-version |
| `subEntityCode` | string | `code_direction` | Associer a la nomenclature interne |

### 12.2 Statuts (exemple)
| Statut API | Statut SI |
|---|---|
| `draft` | `A_TRAITER` |
| `signed` | `VALIDE` |
| `archived` | `ARCHIVE` |

### 12.3 Regles de deduplication
- Cle primaire de synchronisation: `id`
- Cle secondaire: `updatedAt`
- Si `id` existe et `updatedAt` a change: mise a jour locale
- Si `id` absent: creation locale

### 12.4 Regles de qualite de donnees
- Rejeter les documents sans `id`
- Tracer les valeurs hors schema (champ inconnu)
- Appliquer une validation de format email/date/codes metier

## 13. Versioning de contrat API (gouvernance)

### 13.1 Principe
- Base URL versionnee: `/api/v1`
- Changements compatibles: ajout de champs optionnels
- Changements non compatibles: nouvelle version majeure (`/api/v2`)

### 13.2 Regles de compatibilite
- Ne pas renommer/supprimer un champ sans periode de transition
- Ne pas changer le type d'un champ existant en v1
- Ajouter les nouveaux champs en optionnel

### 13.3 Deprecation
- Annoncer la deprecation avec preavis (ex: 90 jours)
- Publier un changelog clair (endpoint, champ, date d'effet)
- Fournir une periode de double support si possible

### 13.4 Contrat de test inter-equipes
- Jeu de tests contractuels partage (Postman/newman ou equivalent)
- Cas minimum:
  - login + refresh
  - listing documents pagine
  - lecture document detail
  - verification QR publique
  - gestion des erreurs 401/404/500

### 13.5 Recommandation gouvernance
- Geler un schema JSON de reference par endpoint critique
- Versionner la documentation avec date + numero de revision
- Valider chaque evolution API en comite applicatif (metier + tech)

---

Version: 1.1
Date: 2026-03-27
