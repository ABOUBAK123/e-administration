# Guide Utilisateur Complet - E-Administration Connect & Sign

Version: 1.0  
Date: 30/07/2026  
Public: utilisateurs, managers, signataires, administrateurs, super administrateurs

---

## 1. Introduction

E-Administration Connect & Sign est une plateforme de gestion documentaire et de traitement de courrier administratif. Elle couvre:

- la gestion du courrier entrant/sortant,
- le cycle de vie des documents,
- les circuits de validation (workflows),
- la signature electronique,
- la reception et les imputations,
- la verification par QR code,
- la gestion RH (personnel),
- l'administration technique et fonctionnelle.

Le contenu de ce guide est elabore a partir de l'analyse des ecrans, menus et routes de l'application.

---

## 2. Profils et droits

L'application applique des permissions fines selon le profil.

### 2.1 Profils usuels

- Utilisateur: consultation, traitement selon droits attribues.
- Manager: fonctions avancees de suivi/validation selon parametrage.
- Signataire: actions de signature sur documents/processus.
- Administrateur: gestion des parametrages d'administration.
- Super administrateur: acces global multi-administrations.

### 2.2 Bonnes pratiques de gouvernance

- attribuer les droits minimum necessaires,
- separer les fonctions d'administration technique et metier,
- revoir periodiquement les droits,
- utiliser les profils applicatifs plutot que des privileges ad hoc.

---

## 3. Connexion et securite d'acces

## 3.1 Connexion

- Saisir email + mot de passe.
- Si la double authentification est active, saisir le code OTP recu.

## 3.2 OTP (double authentification)

- OTP via email ou WhatsApp selon parametrage administration.
- Possibilite de renvoi du code OTP.
- Le super admin peut etre exempt selon la regle metier configuree.
- Un administrateur peut activer/desactiver la 2FA utilisateur par utilisateur depuis Administration > Utilisateurs.

## 3.3 Mot de passe oublie

- Depuis l'ecran de connexion, utiliser "Mot de passe oublie".
- Un lien de reinitialisation est envoye a l'adresse email.

## 3.4 Langue

- Changement de langue supporte (fr, en, es, pt, ar) selon disponibilite.

---

## 4. Navigation generale

Le menu lateral donne acces aux principaux modules (selon droits):

- Tableau de bord,
- Gestion courrier,
- Mes documents,
- Templates partages,
- Workflows,
- Signatures,
- Reception,
- Demandes d'actes,
- Gestion du personnel,
- Reunions,
- Verification QR,
- Administration.

Le menu exact peut varier selon votre profil et votre perimetre d'administration.

---

## 5. Tableau de bord

Le dashboard permet un suivi rapide:

- volumes utilisateurs/documents/signatures/workflows,
- vue de synthese operationnelle,
- acces rapide aux modules cle.

Usage recommande:

- verifier quotidiennement les indicateurs,
- detecter les retards de traitement,
- prioriser les actions en attente.

---

## 6. Gestion du courrier

Le module Courrier comporte plusieurs sous-vues metier.

## 6.1 Enregistrement

- creation/enregistrement d'un courrier,
- liaison aux metadonnees attendues,
- depot de document principal et pieces associees.

## 6.2 Liste

- consultation des courriers,
- tri/recherche selon criteres,
- acces au detail et aux actions autorisees.

## 6.3 Imputation

- affectation d'un courrier a une direction, entite ou utilisateur,
- suivi des imputations en cours.

## 6.4 En traitement

- execution des actions metier sur les courriers recus,
- validation/rejet selon regles.

## 6.5 Suivi imputation

- visualisation de l'historique des affectations,
- tracabilite du circuit de traitement.

## 6.6 Traite

- courriers finalises,
- consultation des statuts de fin de cycle.

## 6.7 Archives

- consultation des courriers archives,
- affichage conditionne par regles d'archivage configurees.

## 6.8 Fonctions avancees

- scan OCR,
- validation/rejet de traitement,
- visualisation detaillee.

---

## 7. Mes documents

## 7.1 Creation et import

- creation de document,
- upload classique et upload AJAX,
- creation de nouveaux documents editables.

## 7.2 Gestion documentaire

- consultation liste/detail,
- renommage,
- deplacement,
- ajout/edition d'etiquettes,
- mise en favori,
- changement de statut,
- conversion PDF.

## 7.3 Partage et collaboration

- partage de document,
- telechargement securise via lien partage,
- consultation des versions.

## 7.4 Corbeille

- suppression logique,
- restauration,
- suppression definitive.

---

## 8. Templates partages

- consultation des templates disponibles,
- generation de document a partir d'un template,
- standardisation des actes/documents recurrents.

---

## 9. Workflows

## 9.1 Gestion des workflows

- creation,
- edition,
- suppression,
- consultation detaillee.

## 9.2 Execution

- lancer un workflow,
- faire avancer une etape,
- rejeter une etape,
- dupliquer un workflow existant.

## 9.3 Modeles de workflow

- creation de templates de workflow,
- reutilisation pour accelerer les processus standards.

---

## 10. Signatures electroniques

## 10.1 Espace signatures

- liste des demandes/actions de signature,
- suivi des statuts.

## 10.2 Signature depuis upload

- importer un document a signer,
- lancer la demande de signature.

## 10.3 Positionnement de la zone de signature

- definir l'emplacement de signature,
- valider et soumettre.

## 10.4 Integration fournisseur de signature

- workflow de signature integre,
- suivi de statut plateforme,
- recuperation document signe.

---

## 11. Reception

## 11.1 Boite de reception

- documents recus,
- marquage "recu",
- transfert/forward selon droits.

## 11.2 Sous-onglet Archives reception

- acces aux elements archivables en reception,
- comportement lie aux delais d'archivage parametres.

---

## 12. Notifications et chat

## 12.1 Notifications

- liste des notifications,
- compteur non lues,
- marquage lu/universel.

## 12.2 Messagerie interne (chat)

- liste des utilisateurs joignables,
- messages en temps reel,
- etat en ligne par administration.

---

## 13. Demandes d'actes

## 13.1 Cote public

- consultation des administrations/actes disponibles,
- depot d'une demande,
- suivi par numero/token.

## 13.2 Cote interne

- consultation des demandes recues,
- telechargement ZIP des pieces jointes,
- traitement administratif.

---

## 14. Reunions

## 14.1 Gestion des reunions

- creation et planification,
- consultation detail,
- edition du compte rendu.

## 14.2 Presence et emargement

- emargement via QR token public,
- tableau de suivi de presence,
- correction/regularisation de pointage.

## 14.3 Reporting

- vue reporting reunions,
- export CSV,
- export PDF de synthese,
- telechargement liste de presence.

## 14.4 Salles de reunion

- creation/modification/suppression de salles,
- gestion du referentiel des espaces.

---

## 15. Verification QR

- verification d'un document via QR,
- verification par numero,
- page publique de verification,
- telechargement public securise via token.

---

## 16. Profil utilisateur

Chaque utilisateur peut:

- modifier ses informations de profil,
- changer son avatar,
- mettre a jour son nom d'affichage,
- changer son mot de passe,
- changer sa langue.

---

## 17. Administration (module unifie)

Le module Administration est organise en onglets.

## 17.1 Apercu

- synthese globale,
- vue de perimetre (super admin vs administration restreinte).

## 17.2 Templates

- creation/edition/suppression de templates,
- detection de variables,
- enrichissement IA des variables,
- partage des templates,
- configuration zones et sauvegarde forcee,
- integration OnlyOffice pour edition en ligne.

## 17.3 Emetteurs et destinataires

- gestion du referentiel des administrations,
- CRUD complet.

## 17.4 Entites sous tutelle

- gestion des structures rattachees,
- lien au perimetre d'administration.

## 17.5 Actes demandes

- parametrage des types d'actes disponibles pour le public.

## 17.6 Types de direction et routage

- parametrage des types de direction,
- regles de routage automatique.

## 17.7 OnlyOffice

- jeton/configuration d'integration,
- callbacks et edition collaborative.

## 17.8 Utilisateurs

- creation/modification/suppression d'utilisateurs,
- activation/desactivation de compte,
- notification de creation de compte,
- affectation administration/sous-entite,
- activation/desactivation 2FA par utilisateur.

## 17.9 Apparence (theming)

- personnalisation visuelle par administration,
- couleur menu/fond/logo/favicon.

## 17.10 Notifications email et SMTP

- configuration SMTP par administration,
- test SMTP,
- gestion du canal OTP (email/WhatsApp) selon scope.

## 17.11 API Signature

- configuration fournisseur de signature,
- test de connectivite,
- parametres techniques (endpoint, cle, etc.).

## 17.12 Roles / Profils

- creation de profils applicatifs,
- gestion des permissions par menu et sous-fonctions,
- affectation profil-utilisateur.

## 17.13 Instructions

- gestion des instructions de traitement de courrier.

## 17.14 Archivage courrier

- parametrage delai d'archivage courrier et reception,
- scope global ou par administration (selon profil),
- affichage des statistiques associees.

## 17.15 Antivirus

- suivi des actions de scan sur les fichiers,
- supervision des journaux de protection.

## 17.16 Gestion du personnel (sous-module admin)

Sous-onglets principaux:

- tableau de bord RH,
- employes,
- espace agent,
- conges,
- formations,
- carriere.

Fonctions notables:

- import employes,
- generation de compte utilisateur depuis fiche agent,
- gestion des justificatifs et documents,
- gestion demandes de mutation,
- suivi formations et evaluations.

---

## 18. Parcours utilisateur recommandes

## 18.1 Traitement d'un courrier entrant

1. Enregistrer le courrier.
2. Imputer a la bonne direction/entite.
3. Traiter dans "En traitement".
4. Valider ou rejeter selon resultat.
5. Controler le suivi d'imputation.
6. Laisser basculer en archive selon regle.

## 18.2 Circuit de signature

1. Selectionner/importer le document.
2. Definir le workflow de signature.
3. Positionner la zone de signature.
4. Envoyer la demande.
5. Suivre le statut.
6. Recuperer la version signee.

## 18.3 Onboarding d'un nouvel utilisateur

1. Creer l'utilisateur dans Administration > Utilisateurs.
2. Assigner administration + sous-entite + profil.
3. Activer le compte.
4. Notifier l'utilisateur par email.
5. Activer la 2FA si politique de securite requise.

---

## 19. Bonnes pratiques d'utilisation

- verifier la qualite des metadonnees a l'enregistrement,
- utiliser des profils et permissions strictement necessaires,
- privilegier les templates standards,
- activer la 2FA pour les comptes sensibles,
- controler regulierement les journaux antivirus et notifications,
- documenter les regles de routage et d'archivage.

---

## 20. Checklist administrateur

Quotidien:

- verifier erreurs SMTP/signature,
- verifier files de traitement et notifications,
- controler disponibilite services critiques.

Hebdomadaire:

- audit comptes et droits,
- verification des regles de routage,
- verification des delais d'archivage.

Mensuel:

- revue des profils,
- revue des parametres de securite,
- revue des templates et workflows inactifs.

---

## 21. Limites et adaptation locale

Ce guide couvre l'ensemble fonctionnel present dans l'application telle qu'analysee. Selon le profil, certains menus peuvent etre masques. Il est recommande de completer ce guide avec:

- des captures d'ecran de votre environnement,
- vos procedures internes (SOP),
- les regles de conformite propres a votre institution.

---

## 22. Support

En cas de blocage:

- verifier les permissions du profil,
- verifier la configuration SMTP/OTP/signature,
- verifier la connectivite des services externes,
- contacter l'administrateur fonctionnel ou technique.
