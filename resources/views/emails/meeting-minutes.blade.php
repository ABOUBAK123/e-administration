@php
    $accent = $isReminder ? '#b45309' : '#2453d6';
    $soft = $isReminder ? '#fffbeb' : '#eff6ff';
    $border = $isReminder ? '#fde68a' : '#bfdbfe';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; text-align:left;">
                {{-- En-tête --}}
                <tr>
                    <td style="padding:20px 28px; border-bottom:4px solid {{ $accent }};">
                        @if($logo)
                            <img src="{{ $message->embedData($logo['data'], $logo['name'], $logo['mime']) }}" alt="{{ $senderName }}" height="52" style="display:block; height:52px; max-width:220px; margin:0 0 10px;">
                        @endif
                        <div style="color:#111827; font-size:17px; font-weight:bold; line-height:1.3;">{{ $senderName }}</div>
                        <div style="color:#6b7280; font-size:12px; margin-top:2px;">Gestion des réunions</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <span style="display:inline-block; background:{{ $soft }}; color:{{ $accent }}; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em; padding:5px 10px; border-radius:999px;">
                            {{ $isReminder ? 'Rappel' : 'Compte rendu validé' }}
                        </span>
                        <h1 style="margin:12px 0 18px; color:#111827; font-size:20px; line-height:1.35;">{{ $meetingTitle }}</h1>

                        @if($isReminder)
                            <p style="margin:0 0 16px; padding:12px 16px; background:{{ $soft }}; border:1px solid {{ $border }}; border-radius:8px; color:#92400e; font-size:14px; line-height:1.6;">
                                Sauf erreur de notre part, nous n'avons pas encore reçu votre accusé de réception. Vous trouverez de nouveau les documents en pièces jointes.
                            </p>
                        @endif

                        @if($customBody !== null)
                            <p style="color:#374151; font-size:14px; line-height:1.7; margin:0 0 18px;">{!! nl2br(e($customBody)) !!}</p>
                        @else
                            <p style="color:#374151; font-size:14px; margin:0 0 12px;">{{ $recipientName ? 'Bonjour ' . $recipientName . ',' : 'Bonjour,' }}</p>
                            <p style="color:#374151; font-size:14px; line-height:1.7; margin:0 0 18px;">
                                Nous vous prions de bien vouloir trouver ci-joint le compte rendu validé ainsi que la liste de présence de la réunion mentionnée ci-dessous.
                            </p>
                        @endif

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:0 0 20px;">
                            @foreach($details as $label => $value)
                                <tr>
                                    <td style="padding:10px 16px; width:34%; vertical-align:top; color:#6b7280; font-size:12px; font-weight:bold; {{ !$loop->first ? 'border-top:1px solid #e5e7eb;' : '' }}">{{ $label }}</td>
                                    <td style="padding:10px 16px; vertical-align:top; color:#111827; font-size:14px; {{ !$loop->first ? 'border-top:1px solid #e5e7eb;' : '' }}">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>

                        <p style="margin:0 0 6px; color:#111827; font-size:13px; font-weight:bold;">Pièces jointes ({{ count($attachmentNames) }})</p>
                        <ul style="margin:0 0 20px; padding-left:20px; color:#374151; font-size:13px; line-height:1.8;">
                            @foreach($attachmentNames as $name)
                                <li style="word-break:break-all;">{{ $name }}</li>
                            @endforeach
                        </ul>

                        @if($receiptUrl)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $soft }}; border:1px solid {{ $border }}; border-radius:8px; margin:0 0 20px;">
                                <tr>
                                    <td align="center" style="padding:20px 18px;">
                                        <p style="margin:0 0 14px; color:#111827; font-size:14px; line-height:1.6;">Merci de confirmer la bonne réception de ces documents.</p>
                                        <a href="{{ $receiptUrl }}" style="display:inline-block; background:{{ $accent }}; color:#ffffff; font-size:15px; font-weight:bold; padding:13px 32px; border-radius:8px; text-decoration:none;">J'accuse réception</a>
                                        <p style="margin:14px 0 0; color:#6b7280; font-size:12px; line-height:1.6;">
                                            Ce lien vous est personnel, merci de ne pas le transférer.<br>
                                            Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>
                                            <a href="{{ $receiptUrl }}" style="color:{{ $accent }}; word-break:break-all;">{{ $receiptUrl }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        @if($customBody === null)
                            <p style="color:#374151; font-size:14px; line-height:1.7; margin:0;">
                                Veuillez agréer l'expression de nos salutations distinguées.<br><br>
                                <strong>{{ $senderName }}</strong>
                            </p>
                        @endif
                    </td>
                </tr>

                {{-- Pied de page --}}
                <tr>
                    <td style="background:#f9fafb; border-top:1px solid #e5e7eb; border-radius:0 0 12px 12px; padding:16px 28px; color:#6b7280; font-size:12px; line-height:1.6;">
                        @if($contact)
                            Pour toute question, contactez l'organisateur de la réunion : {{ $contact }}.<br>
                        @endif
                        Message envoyé automatiquement par {{ config('app.name') }}. Les informations et pièces jointes sont destinées à leurs seuls destinataires.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
