<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyMobileMoneyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.mobile_money.webhook_secret');

        if (empty($secret)) {
            Log::warning('VerifyMobileMoneyWebhook: MOBILE_MONEY_WEBHOOK_SECRET non configuré — webhook accepté sans vérification.', [
                'ip' => $request->ip(),
            ]);
            return $next($request);
        }

        $token = (string) $request->query('token', '');

        if ($token === '' || !hash_equals($secret, $token)) {
            Log::warning('VerifyMobileMoneyWebhook: token invalide ou absent — requête rejetée.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
