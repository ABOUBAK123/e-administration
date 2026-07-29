<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Models\AdministrationProfile;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Rate limiting : 5 tentatives par IP + email par minute
        $key = 'login|' . Str::lower($request->input('email')) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (!Auth::attempt($credentials, false)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        // Identifiants valides : déconnecter et exiger le code OTP envoyé par email
        $user = Auth::user();
        Auth::logout();

        $this->sendTwoFactorCode($user, $request);

        $request->session()->put('2fa:user_id', $user->id);
        $request->session()->put('2fa:remember', $request->boolean('remember'));

        $channelLabel = $request->session()->get('2fa:channel') === 'whatsapp' ? 'WhatsApp' : 'votre adresse email';
        return redirect()->route('2fa.show')
            ->with('success', 'Un code de vérification a été envoyé via ' . $channelLabel . '.');
    }

    public function showTwoFactorForm(Request $request)
    {
        if (!$request->session()->has('2fa:user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $userId = $request->session()->get('2fa:user_id');
        if (!$userId || !($user = User::find($userId))) {
            return redirect()->route('login')->withErrors(['email' => 'Session expirée, veuillez vous reconnecter.']);
        }

        // Rate limiting : 5 tentatives de code par utilisateur par minute
        $key = '2fa|' . $userId . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (
            !$user->two_factor_code ||
            !$user->two_factor_expires_at ||
            now()->greaterThan($user->two_factor_expires_at) ||
            !Hash::check($request->input('code'), $user->two_factor_code)
        ) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'code' => 'Code invalide ou expiré.',
            ]);
        }

        RateLimiter::clear($key);

        // Code valide : nettoyer et connecter l'utilisateur
        $user->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

        $remember = (bool) $request->session()->pull('2fa:remember', false);
        $request->session()->forget(['2fa:user_id', '2fa:channel']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resendTwoFactor(Request $request)
    {
        $userId = $request->session()->get('2fa:user_id');
        if (!$userId || !($user = User::find($userId))) {
            return redirect()->route('login');
        }

        // Rate limiting : 3 renvois par utilisateur toutes les 2 minutes
        $key = '2fa-resend|' . $userId;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['code' => 'Veuillez patienter avant de demander un nouveau code.']);
        }
        RateLimiter::hit($key, 120);

        $this->sendTwoFactorCode($user, $request);

        $channelLabel = $request->session()->get('2fa:channel') === 'whatsapp' ? 'WhatsApp' : 'votre adresse email';
        return back()->with('success', 'Un nouveau code a été envoyé via ' . $channelLabel . '.');
    }

    private function sendTwoFactorCode(User $user, Request $request): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $channel = $this->resolveOtpChannel($user);

        if ($channel === 'whatsapp' && $user->phone) {
            $message = config('app.name') . " - Votre code de vérification : {$code}\n"
                . "Ce code expire dans 10 minutes. Ne le partagez avec personne.";

            if (app(WhatsAppService::class)->sendMessage($user->phone, $message)) {
                $request->session()->put('2fa:channel', 'whatsapp');
                return;
            }
            // Échec WhatsApp : repli sur l'email
        }

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, $user->name));
        $request->session()->put('2fa:channel', 'email');
    }

    /**
     * Canal OTP de l'administration de l'utilisateur (otp_channel:{type}:{id}),
     * sinon paramètre global (otp_channel), sinon email.
     */
    private function resolveOtpChannel(User $user): string
    {
        $scopeKey = null;

        $assignment = UserDirectionAssignment::where('user_id', $user->id)->first();
        if ($assignment && $assignment->direction_scope_id) {
            $type = $assignment->direction_scope_type === 'recipient' ? 'recipient' : 'emitter';
            $scopeKey = 'otp_channel:' . $type . ':' . $assignment->direction_scope_id;
        } elseif ($user->profile_id) {
            $profile = AdministrationProfile::find($user->profile_id);
            if ($profile && $profile->administration_id) {
                $type = ($profile->effective_administration_type ?? 'emitter') === 'recipient' ? 'recipient' : 'emitter';
                $scopeKey = 'otp_channel:' . $type . ':' . $profile->administration_id;
            }
        }

        $channel = $scopeKey ? AppSetting::where('key', $scopeKey)->value('value') : null;

        return $channel ?: (AppSetting::where('key', 'otp_channel')->value('value') ?: 'email');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.')
            : back()->withErrors(['email' => 'Aucun compte trouvé avec cette adresse email.']);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $payload = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'user',
            'status'   => 'active',
            'locale'   => 'fr',
        ];

        try {
            $user = User::create($payload);
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unknown column') && str_contains($msg, 'locale')) {
                unset($payload['locale']);
                $user = User::create($payload);
            } else {
                throw $e;
            }
        }

        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function apiLogin(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        // Rate limiting API : 10 tentatives par IP par minute
        $key = 'api-login|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['message' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($key) . ' secondes.'], 429);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        RateLimiter::clear($key);
        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function apiLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
