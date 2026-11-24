<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de signature</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:560px; margin:32px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
        <div style="background:#4f46e5; padding:20px 28px;">
            <h1 style="margin:0; color:#ffffff; font-size:18px;">{{ config('app.name') }}</h1>
            <p style="margin:4px 0 0; color:#c7d2fe; font-size:12px;">Signature électronique</p>
        </div>

        <div style="padding:28px;">
            <p style="color:#374151; font-size:14px; margin:0 0 12px;">Bonjour {{ $recipientName }},</p>
            <p style="color:#374151; font-size:14px; line-height:1.6; margin:0 0 20px;">
                <strong>{{ $creatorName }}</strong> vous invite à signer électroniquement
                {{ count($documentTitles) > 1 ? 'les documents' : 'le document' }}
                du dossier <strong>« {{ $workflowName }} »</strong>.
            </p>

            @if (count($documentTitles) > 0)
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px 20px; margin:0 0 24px;">
                    <p style="margin:0 0 10px; color:#6b7280; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em;">
                        {{ count($documentTitles) > 1 ? 'Documents à signer (' . count($documentTitles) . ')' : 'Document à signer' }}
                    </p>
                    @foreach ($documentTitles as $title)
                        <p style="margin:0; padding:6px 0; color:#111827; font-size:14px; {{ !$loop->first ? 'border-top:1px solid #e5e7eb;' : '' }}">{{ $title }}</p>
                    @endforeach
                </div>
            @endif

            <div style="text-align:center; margin:0 0 24px;">
                <a href="{{ $signatureUrl }}"
                   style="display:inline-block; background:#4f46e5; color:#ffffff; font-size:14px; font-weight:bold; padding:12px 32px; border-radius:8px; text-decoration:none;">
                    {{ count($documentTitles) > 1 ? 'Signer les documents' : 'Signer le document' }}
                </a>
            </div>

            <p style="color:#6b7280; font-size:13px; line-height:1.6; margin:0 0 8px;">
                Ce lien vous conduit directement sur la plateforme de signature sécurisée. Vous pourrez y consulter {{ count($documentTitles) > 1 ? 'les documents' : 'le document' }}, puis {{ count($documentTitles) > 1 ? 'les signer' : 'le signer' }} ; un code de vérification peut vous être demandé pour confirmer votre identité.
            </p>
            <p style="color:#6b7280; font-size:13px; line-height:1.6; margin:0 0 6px;">
                Si le bouton ne fonctionne pas, copiez ce lien dans la barre d'adresse de votre navigateur :
            </p>
            <p style="color:#4f46e5; font-size:12px; word-break:break-all; margin:0;">{{ $signatureUrl }}</p>

            <p style="color:#374151; font-size:14px; margin:24px 0 0;">
                Cordialement,<br>
                L'équipe {{ config('app.name') }}
            </p>
        </div>

        <div style="background:#f9fafb; padding:16px 28px; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">
                Cet e-mail a été envoyé automatiquement, merci de ne pas y répondre. Si vous n'êtes pas concerné par cette demande, vous pouvez l'ignorer.
            </p>
        </div>
    </div>
</body>
</html>
