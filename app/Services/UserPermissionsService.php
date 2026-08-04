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
            'administration.users'              => 'Gestion des utilisateurs',
            'administration.theming'            => 'Apparence',
            'administration.email-notifications'=> 'Notifications e-mail',
            'administration.signature-provider' => 'API Signature',
            'administration.courrier-archiving' => 'Archivage courrier',
            'administration.instructions'        => 'Instructions',
            'administration.user-profiles'      => 'Rôles & profils',
            'administration.antivirus'          => 'Journal Antivirus',
        ]],
        'qrcode'                  => ['label' => 'Vérification QR',        'children' => []],
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

        // Super-admin = rôle système admin SANS profil applicatif → accès total à tout
        // Un admin AVEC un profil applicatif est limité aux onglets cochés dans ce profil
        if ($user->role === 'admin' && !$user->profile_id) {
            return ['isElevated' => true, 'permissions' => []];
        }

        // Profil applicatif associé (s'applique à tous les rôles système, y compris admin)
        if ($profile) {
            if ($profile && is_array($profile->permissions)) {
                $perms = $profile->permissions;
                $menuPerms = $perms['menuPermissions'] ?? [];
                if (!empty($menuPerms)) {
                    return ['isElevated' => false, 'permissions' => $menuPerms];
                }
            }
        }

        // Fallback minimal
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
