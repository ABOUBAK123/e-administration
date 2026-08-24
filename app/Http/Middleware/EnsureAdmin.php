<?php

namespace App\Http\Middleware;

use App\Models\AdministrationProfile;
use App\Services\UserPermissionsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * @param string|null $permission Optional menuPermissions key the route requires
     *   (e.g. 'administration.recipients'). When given, access is granted only if the
     *   user's resolved permissions (UserPermissionsService::can) cover that key —
     *   matching the same check already used to hide/show the corresponding menu entry.
     *   When omitted, falls back to the coarse "has at least one permission" entry check.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        if (!auth()->check()) {
            abort(403, 'Accès non autorisé.');
        }

        $user = auth()->user();

        // Super-admin ou admin scopé : accès direct
        if ($user->role === 'admin') {
            return $next($request);
        }

        if ($permission !== null) {
            if (app(UserPermissionsService::class)->can($user, $permission)) {
                return $next($request);
            }
            abort(403, 'Accès refusé.');
        }

        // Utilisateurs avec profil ayant des permissions de menu : accès autorisé (scopé par profil)
        if ($user->profile_id) {
            $profile = AdministrationProfile::find($user->profile_id);
            if ($profile) {
                $perms = $profile->permissions['menuPermissions'] ?? [];
                if (!empty($perms)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'Accès réservé aux administrateurs.');
    }
}
