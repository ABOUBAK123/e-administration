<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuids;

    protected $fillable = [
        'name', 'full_name', 'email', 'password', 'avatar',
        'role', 'status', 'quota', 'bio', 'profile_id', 'locale', 'phone', 'two_factor_enabled',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_code'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_expires_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
        ];
    }

    /**
     * Résout l'administration (type + id) à laquelle cet utilisateur appartient,
     * avec la même priorité que AdminController::resolveAdminScope() : d'abord son
     * UserDirectionAssignment, puis à défaut l'administration de son profil applicatif.
     * Retourne null pour un super-admin / utilisateur sans rattachement (pas de scope).
     *
     * @return array{type: string, id: string}|null
     */
    private function resolveAdministrationScope(): ?array
    {
        $assignment = UserDirectionAssignment::where('user_id', $this->id)->first();
        if ($assignment && $assignment->direction_scope_id) {
            return [
                'type' => $assignment->direction_scope_type,
                'id' => $assignment->direction_scope_id,
            ];
        }

        $profile = $this->profile_id ? AdministrationProfile::find($this->profile_id) : null;
        if ($profile && $profile->administration_id) {
            return [
                'type' => $profile->effective_administration_type ?? 'emitter',
                'id' => $profile->administration_id,
            ];
        }

        return null;
    }

    /** Configure le mailer par défaut sur la config SMTP de l'administration de cet utilisateur, si elle existe. */
    private function applyAdministrationSmtpConfiguration(): void
    {
        $scope = $this->resolveAdministrationScope();
        if (!$scope) {
            return;
        }

        $smtp = AdministrationSmtpSetting::forAdministration($scope['id'], $scope['type']);
        if (!$smtp || !$smtp->mail_host || !$smtp->mail_from_address) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp->mail_host,
            'mail.mailers.smtp.port' => $smtp->mail_port ?? 587,
            'mail.mailers.smtp.username' => $smtp->mail_username,
            'mail.mailers.smtp.password' => $smtp->mail_password,
            'mail.mailers.smtp.encryption' => $smtp->mail_encryption ?: null,
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => $smtp->mail_from_address,
            'mail.from.name' => $smtp->mail_from_name ?? config('app.name'),
        ]);
    }

    /**
     * L'app n'utilise pas de route "password.reset" au sens Laravel par défaut
     * (formulaire/gestion custom dans AuthController) — on envoie donc l'email
     * nous-mêmes plutôt que de laisser la notification par défaut construire son
     * URL, ce qui provoquait une RouteNotFoundException (500) faute de cette route.
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        try {
            // Utilise la config SMTP de l'administration de l'utilisateur quand elle
            // existe ; sinon conserve le mailer global par défaut (.env) sans y toucher.
            $this->applyAdministrationSmtpConfiguration();

            \Illuminate\Support\Facades\Mail::to($this->email)->send(
                new \App\Mail\ResetPasswordMail($resetUrl, $this->name ?? $this->email)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Password reset email delivery failed.', [
                'user_id' => (string) $this->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function documents() { return $this->hasMany(Document::class, 'owner_id'); }
    public function workflows() { return $this->hasMany(Workflow::class, 'created_by'); }
    public function signatures() { return $this->hasMany(Signature::class, 'signer_id'); }
    public function notifications() { return $this->hasMany(Notification::class, 'recipient_id'); }
    public function directionAssignments() { return $this->hasMany(UserDirectionAssignment::class); }
    public function profile() { return $this->belongsTo(AdministrationProfile::class, 'profile_id'); }
}
