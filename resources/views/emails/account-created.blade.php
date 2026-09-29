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
            <p style="color:#374151; font-size:14px; margin:0 0 12px;">Bonjour {{ $displayName }},</p>
            <p style="color:#374151; font-size:14px; margin:0 0 20px;">
                Votre compte utilisateur sur la plateforme <strong>{{ config('app.name') }}</strong> vient d'être créé avec succès.
            </p>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; margin:0 0 20px;">
                <p style="margin:0 0 4px; color:#6b7280; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em;">
                    Informations de connexion
                </p>
                <p style="margin:8px 0 0; color:#374151; font-size:14px;">
                    <span style="color:#6b7280;">Identifiant (e-mail) :</span><br>
                    <strong>{{ $email }}</strong>
                </p>
            </div>

            <div style="text-align:center; margin:24px 0;">
                <a href="{{ $appUrl }}"
                   style="display:inline-block; background:#4f46e5; color:#ffffff; font-size:14px; font-weight:bold; padding:12px 28px; border-radius:8px; text-decoration:none;">
                    Accéder à la plateforme
                </a>
            </div>

            <p style="color:#6b7280; font-size:13px; margin:0 0 8px;">
                Pour des raisons de sécurité, votre mot de passe ne figure pas dans cet e-mail. Veuillez contacter l'administrateur de la plateforme pour l'obtenir, ou utiliser la fonction « Mot de passe oublié » sur la page de connexion.
            </p>
            <p style="color:#374151; font-size:14px; margin:20px 0 0;">
                Cordialement,<br>
                L'équipe {{ config('app.name') }}
            </p>
        </div>
        <div style="background:#f9fafb; padding:16px 28px; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
