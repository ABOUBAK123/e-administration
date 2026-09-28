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
