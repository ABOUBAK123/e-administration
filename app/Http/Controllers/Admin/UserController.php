<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:191',
            'email'                => 'required|email|unique:users,email',
            'password'             => 'required|string|min:8',
            'phone'                => 'nullable|string|max:20',
            'role'                 => 'required|in:admin,user,signer,manager',
            'administration_type'  => 'required|in:emitter,recipient',
            'administration_id'    => 'required|uuid',
        ]);

        $selectedAdminType = $data['administration_type'];
        $selectedAdminId = $data['administration_id'];
        $selectedProfileId = null;

        // Chercher un profil correspondant
        $fallbackProfile = \App\Models\AdministrationProfile::query()
            ->where('administration_id', $selectedAdminId)
            ->where('administration_type', $selectedAdminType)
            ->orderBy('name')
            ->first();

        if ($fallbackProfile) {
            $selectedProfileId = $fallbackProfile->id;
        } else {
            Log::warning('UserController@store: No profile found for administration', [
                'administration_id' => $selectedAdminId,
                'administration_type' => $selectedAdminType,
            ]);
            return back()
                ->withInput()
                ->withErrors(['administration_id' => 'Aucun profil trouvé pour cette administration. Veuillez d\'abord créer un profil.']);
        }

        $payload = [
            'name'       => $data['name'],
            'full_name'  => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'phone'      => $data['phone'] ?? null,
            'role'       => $data['role'],
            'profile_id' => $selectedProfileId,
            'status'     => 'active',
            'locale'     => 'fr',
        ];

        try {
            $user = User::create($payload);

            // Créer aussi une assignation de direction pour que l'administration soit directement accessible
            \App\Models\UserDirectionAssignment::create([
                'user_id' => $user->id,
                'direction_scope_type' => $selectedAdminType,
                'direction_scope_id' => $selectedAdminId,
                'sub_entity_code' => null,
                'direction_label' => $fallbackProfile->name ?? null,
            ]);

            Log::info('User created successfully with profile and direction', [
                'user_id' => $user->id,
                'email' => $user->email,
                'profile_id' => $selectedProfileId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin UserController@store failed', [
                'email' => $data['email'] ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $msg = strtolower($e->getMessage());
            if ($e instanceof QueryException && str_contains($msg, 'unknown column') && str_contains($msg, 'locale')) {
                unset($payload['locale']);
                User::create($payload);
                return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
            }

            return back()
                ->withInput()
                ->withErrors(['email' => 'Échec de création utilisateur: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:191',
            'phone'  => 'nullable|string|max:20',
            'role'   => 'required|in:admin,user,signer,manager',
            'status' => 'required|in:active,inactive,suspended',
        ]);
        $user->update($data);
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success', 'Utilisateur supprimé.');
    }
}
