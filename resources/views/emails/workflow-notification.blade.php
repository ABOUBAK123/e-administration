@php
    $tones = [
        'info'    => ['bg' => '#4f46e5', 'soft' => '#eef2ff', 'text' => '#3730a3', 'label' => 'Notification'],
        'success' => ['bg' => '#16a34a', 'soft' => '#f0fdf4', 'text' => '#166534', 'label' => 'Terminé'],
        'danger'  => ['bg' => '#dc2626', 'soft' => '#fef2f2', 'text' => '#991b1b', 'label' => 'Rejeté'],
        'warning' => ['bg' => '#d97706', 'soft' => '#fffbeb', 'text' => '#92400e', 'label' => 'Action requise'],
    ];
    $t = $tones[$tone] ?? $tones['info'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: Arial, Helvetica, sans-serif;">
    <div style="max-width:580px; margin:32px auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

        <div style="background:#4f46e5; padding:20px 28px;">
            <h1 style="margin:0; color:#ffffff; font-size:18px;">{{ config('app.name') }}</h1>
            <p style="margin:4px 0 0; color:#c7d2fe; font-size:12px;">Suivi des workflows</p>
        </div>

        <div style="padding:28px;">
            <span style="display:inline-block; background:{{ $t['soft'] }}; color:{{ $t['text'] }}; font-size:11px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em; padding:4px 10px; border-radius:999px; margin:0 0 12px;">
                {{ $t['label'] }}
            </span>
            <h2 style="margin:0 0 16px; color:#111827; font-size:20px; line-height:1.3;">{{ $headline }}</h2>

            <p style="color:#374151; font-size:14px; margin:0 0 12px;">Bonjour {{ $recipientName }},</p>

            @foreach ($paragraphs as $p)
                <p style="color:#374151; font-size:14px; line-height:1.6; margin:0 0 14px;">{!! nl2br(e($p)) !!}</p>
            @endforeach

            @if (count($details) > 0)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:6px 0 20px;">
                    @foreach ($details as $label => $value)
                        <tr>
                            <td style="padding:9px 16px; color:#6b7280; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.04em; width:38%; vertical-align:top; {{ !$loop->first ? 'border-top:1px solid #e5e7eb;' : '' }}">{{ $label }}</td>
                            <td style="padding:9px 16px; color:#111827; font-size:14px; vertical-align:top; {{ !$loop->first ? 'border-top:1px solid #e5e7eb;' : '' }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($buttonUrl && $buttonLabel)
                <div style="text-align:center; margin:24px 0;">
                    <a href="{{ $buttonUrl }}"
                       style="display:inline-block; background:{{ $t['bg'] }}; color:#ffffff; font-size:14px; font-weight:bold; padding:12px 30px; border-radius:8px; text-decoration:none;">
                        {{ $buttonLabel }}
                    </a>
                </div>
            @endif

            @if (count($links) > 0)
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px 18px; margin:0 0 20px;">
                    <p style="margin:0 0 8px; color:#166534; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.05em;">
                        {{ count($links) > 1 ? 'Documents signés à télécharger' : 'Document signé à télécharger' }}
                    </p>
                    @foreach ($links as $link)
                        <p style="margin:0; padding:6px 0; font-size:14px; {{ !$loop->first ? 'border-top:1px solid #bbf7d0;' : '' }}">
                            <a href="{{ $link['url'] }}" style="color:#15803d; font-weight:bold; text-decoration:underline;">{{ $link['label'] }}</a>
                        </p>
                    @endforeach
                    <p style="margin:8px 0 0; color:#6b7280; font-size:12px;">Ces liens sécurisés restent valables 7 jours.</p>
                </div>
            @endif

            @if ($note)
                <p style="color:#6b7280; font-size:13px; line-height:1.6; margin:0 0 12px;">{{ $note }}</p>
            @endif

            <p style="color:#374151; font-size:14px; margin:22px 0 0;">
                Cordialement,<br>
                L'équipe {{ config('app.name') }}
            </p>
        </div>

        <div style="background:#f9fafb; padding:16px 28px; border-top:1px solid #e5e7eb;">
            <p style="color:#9ca3af; font-size:12px; margin:0;">
                Cet e-mail a été envoyé automatiquement, merci de ne pas y répondre. Si vous n'êtes pas concerné par ce message, vous pouvez l'ignorer.
            </p>
        </div>
    </div>
</body>
</html>
