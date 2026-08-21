<?php

namespace App\Services;

use App\Models\AdministrationProfile;
use App\Models\User;

class UserPermissionsService
{
    private function isSuperAdminProfile(?AdministrationProfile $profile): bool
    {
        if (!$profile || !is_string($profile->name)) {
            return false;
        }

        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', $profile->name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized === 'SUPER ADMIN';
    }

    /**
     * Permissions disponibles dans l'application (arbre complet).
     * Cle = ID permission, valeur = libelle affiché.
     */
    public const PERMISSION_TREE = [
        'dashboard'               => ['label' => 'Tableau de bord',        'children' => []],
        'templates-shared'        => ['label' => 'Templates partagés',      'children' => [
            'templates-shared.view' => 'Voir les templates partagés',
        ]],
        'courrier'                => ['label' => 'Gestion Courrier',       'children' => [
            'courrier.tableau-de-bord'  => 'Tableau de bord',
            'courrier.enregistrement'   => 'Enregistrement',
            'courrier.envoi'            => 'Envoi',
            'courrier.reception'        => 'Réception',
            'courrier.liste'            => 'Liste des courriers',
            'courrier.imputation'       => 'Imputation',
            'courrier.en-traitement'    => 'En traitement',
            'courrier.suivi-imputation' => 'Suivi des imputations',
            'courrier.traite'           => 'Courriers traités',
            'courrier.archives'         => 'Archives',
        ]],
        'documents'               => ['label' => 'Mes Documents',           'children' => [
            'documents.view'            => 'Voir les documents',
            'documents.upload'          => 'Uploader des fichiers',
            'documents.create-folder'   => 'Créer des dossiers',
            'documents.share'           => 'Partager des documents',
            'documents.edit-onlyoffice' => 'Éditer en ligne (OnlyOffice)',
            'documents.delete'          => 'Supprimer des documents',
        ]],
        'workflows'               => ['label' => 'Workflows',               'children' => [
            'workflows.view'     => 'Voir les workflows',
            'workflows.create'   => 'Créer un workflow',
            'workflows.validate' => 'Valider / approuver',
            'workflows.delete'   => 'Supprimer un workflow',
        ]],
        'signatures'              => ['label' => 'Signatures',              'children' => [
            'signatures.view'    => 'Voir les signatures',
            'signatures.request' => 'Demander une signature',
            'signatures.sign'    => 'Signer électroniquement',
            'signatures.reject'  => 'Rejeter une signature',
        ]],
        'reception'               => ['label' => 'Réception',               'children' => [
            'reception.view'    => 'Voir les courriers reçus',
            'reception.process' => 'Traiter les courriers reçus',
            'reception.archives' => 'Voir les archives de réception',
        ]],
        'act-requests'            => ['label' => 'Demandes d\'actes',       'children' => [
            'act-requests.view'    => 'Voir les demandes',
            'act-requests.process' => 'Traiter les demandes',
        ]],
        'meetings'                => ['label' => 'Réunions',                'children' => [
            'meetings.view'        => 'Voir les réunions',
            'meetings.create'      => 'Créer des réunions',
            'meetings.attendance'  => 'Gérer l\'émargement',
            'meetings.minutes'     => 'Rédiger les comptes rendus',
        ]],
        'administration'          => ['label' => 'Administration',          'children' => [
            'administration.templates'          => 'Templates de documents',
            'administration.emitters'           => 'Administrations émettrices',
            'administration.recipients'         => 'Administrations destinataires',
            'administration.sub-entities'       => 'Entités sous tutelle',
            'administration.direction-types'    => 'Types de direction',
            'administration.requested-acts'     => 'Actes demandés',
            'administration.routing'            => 'Règles de routage',
            'administration.onlyoffice'         => 'Serveur OnlyOffice',
            'administration.nni'                => 'Identification (NNI)',
            'administration.users'              => 'Gestion des utilisateurs',
            'administration.theming'            => 'Apparence',
            'administration.email-notifications'=> 'Notifications e-mail',
            'administration.signature-provider' => 'API Signature',
            'administration.courrier-archiving' => 'Archivage courrier',
            'administration.instructions'        => 'Instructions',
            'administration.user-profiles'      => 'Rôles & profils',
            'administration.antivirus'          => 'Journal Antivirus',
            'administration.ai-integration'     => 'Intelligence Artificielle (IA)',
            'administration.civil-status-types' => 'Types de dossiers État civil',
        ]],
        'qrcode'                  => ['label' => 'Vérification QR',        'children' => []],
        'civil-status'            => ['label' => 'Dossiers État civil',    'children' => [
            'civil-status.view'   => 'Voir les dossiers',
            'civil-status.manage' => 'Créer / traiter les dossiers',
        ]],
        'personnel'               => ['label' => 'Gestion du personnel',   'children' => [
            'personnel.dashboard' => 'Tableau de bord personnel',
            'personnel.employees' => 'Employés',
            'personnel.agent-space' => 'Espace agent',
            'personnel.leave' => 'Congés & permissions',
            'personnel.leave.validation' => 'Congés - Validation',
            'personnel.leave.parameters' => 'Congés - Paramètres',
            'personnel.leave.recent' => 'Congés - Demandes récentes',
            'personnel.training' => 'Formation',
            'personnel.career' => 'Carrière',
        ]],
    ];

    /**
     * Permissions minimales visibles pour un profil d'administration sans menu explicite.
     */
    private function defaultAdminMenuPermissions(): array
    {
        return [
            'dashboard',
            'courrier',
            'documents',
            'templates-shared',
            'workflows',
            'signatures',
            'reception',
            'act-requests',
            'meetings',
            'administration',
            'qrcode',
            'civil-status',
            'personnel',
        ];
    }

    /**
     * Résout les permissions d'un utilisateur Laravel.
     *
     * @return array{isElevated: bool, permissions: string[]}
     */
    public function resolve(User $user): array
    {
        $profile = null;
        if ($user->profile_id) {
            $profile = AdministrationProfile::find($user->profile_id);
        }

        // Exception métier: profil applicatif SUPER ADMIN = accès total.
        if ($this->isSuperAdminProfile($profile)) {
            return ['isElevated' => true, 'permissions' => []];
        }

        // ADMIN: forcer le menu standard applicatif, sans dépendre d'une configuration
        // manuelle des permissions de profil. Cela garantit que les modules métier clés
        // restent visibles pour tous les profils admin.
        if ($user->role === 'admin') {
            return ['isElevated' => false, 'permissions' => $this->defaultAdminMenuPermissions()];
        }

        // Profil applicatif associé (s'applique à tous les rôles système, non admin)
        if ($profile) {
            if (is_array($profile->permissions)) {
                $perms = $profile->permissions;
                $menuPerms = $perms['menuPermissions'] ?? [];
                if (!empty($menuPerms)) {
                    return ['isElevated' => false, 'permissions' => $menuPerms];
                }
            }

            // Certains profils admin existent mais ne portent aucune permission de menu.
            // Dans ce cas, on leur donne un accès de base aux blocs principaux pour garder
            // le menu cohérent avec les modules réellement présents dans l'application.
            if ($user->role === 'admin' || $this->isSuperAdminProfile($profile)) {
                return ['isElevated' => false, 'permissions' => $this->defaultAdminMenuPermissions()];
            }
        }

        // Fallback minimal pour les comptes non admin qui n'ont pas de profil configuré.
        return ['isElevated' => false, 'permissions' => ['dashboard']];
    }

    /**
     * Vérifie si un utilisateur peut accéder à une clé de menu/permission.
     */
    public function can(User $user, string $key): bool
    {
        $resolved = $this->resolve($user);
        if ($resolved['isElevated']) {
            return true;
        }
        $perms = $resolved['permissions'];
        if (in_array($key, $perms, true)) {
            return true;
        }

        // Un parent accordé donne accès à ses enfants (ex: "courrier" => "courrier.liste").
        if (str_contains($key, '.')) {
            $parent = explode('.', $key, 2)[0];
            if (in_array($parent, $perms, true)) {
                return true;
            }
        }

        // Un parent est accordé si au moins un enfant est présent
        foreach ($perms as $p) {
            if (str_starts_with($p, $key . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne l'ensemble des permissions sous forme de Set (tableau) pour la vue.
     */
    public function permissionsSet(User $user): array
    {
        $resolved = $this->resolve($user);
        return [
            'isElevated'  => $resolved['isElevated'],
            'permissions' => array_flip($resolved['permissions']), // clé => true pour isset() rapide
        ];
    }
}
