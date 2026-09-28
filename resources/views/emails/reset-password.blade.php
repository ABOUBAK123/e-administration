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
                Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :
            </p>
            <div style="text-align:center; margin:24px 0;">
                <a href="{{ $resetUrl }}"
                   style="display:inline-block; background:#4f46e5; color:#ffffff; font-size:14px; font-weight:bold; padding:12px 28px; border-radius:8px; text-decoration:none;">
                    Réinitialiser mon mot de passe
                </a>
            </div>
            <p style="color:#6b7280; font-size:13px; margin:0 0 8px;">
                Ce lien expire dans <strong>60 minutes</strong>.
            </p>
            <p style="color:#6b7280; font-size:13px; margin:0;">
                Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.
            </p>
        </div>
        <div style="background:#f9fafb; padding:16px 28px; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
