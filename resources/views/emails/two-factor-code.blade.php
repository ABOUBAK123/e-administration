<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:520px; margin:32px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
        <div style="background:#4f46e5; padding:20px 28px;">
            <h1 style="margin:0; color:#ffffff; font-size:18px;">{{ config('app.name') }}</h1>
        </div>
        <div style="padding:28px;">
            <p style="color:#374151; font-size:14px; margin:0 0 12px;">Bonjour {{ $userName }},</p>
            <p style="color:#374151; font-size:14px; margin:0 0 20px;">
                Voici votre code de vérification pour vous connecter :
            </p>
            <div style="text-align:center; margin:24px 0;">
                <span style="display:inline-block; background:#eef2ff; color:#4f46e5; font-size:32px; font-weight:bold; letter-spacing:8px; padding:14px 28px; border-radius:8px;">{{ $code }}</span>
            </div>
            <p style="color:#6b7280; font-size:13px; margin:0 0 8px;">
                Ce code expire dans <strong>10 minutes</strong>.
            </p>
            <p style="color:#6b7280; font-size:13px; margin:0;">
                Si vous n'êtes pas à l'origine de cette tentative de connexion, ignorez cet email et changez votre mot de passe.
            </p>
        </div>
        <div style="background:#f9fafb; padding:16px 28px; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
