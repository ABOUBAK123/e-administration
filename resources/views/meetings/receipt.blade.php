<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Accusé de réception</title>
    <style>
        body { margin: 0; min-height: 100vh; background: #f3f4f6; padding: 24px 16px; font-family: Arial, Helvetica, sans-serif; color: #1f2937; }
        .card { max-width: 36rem; margin: 0 auto; background: #fff; border-radius: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12); padding: 24px; text-align: center; }
        h1 { margin: 0 0 12px; font-size: 1.25rem; line-height: 1.4; }
        p { margin: 10px 0 0; font-size: 0.95rem; color: #4b5563; line-height: 1.6; overflow-wrap: anywhere; }
        button { margin-top: 20px; width: 100%; border: 0; border-radius: 12px; background: #2453d6; color: #fff; font-size: 1rem; font-weight: 700; padding: 14px; cursor: pointer; }
        button:disabled { opacity: .6; }
        .done { margin-top: 18px; border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; border-radius: 12px; padding: 12px; font-weight: 700; }
    </style>
</head>
<body>
<div class="card">
    <h1>Accusé de réception</h1>
    <p>Compte rendu de la réunion <strong>{{ $meeting->title }}</strong>@if($meeting->starts_at) du {{ $meeting->starts_at->format('d/m/Y à H:i') }}@endif.</p>
    <p>Destinataire : <strong>{{ $receipt->name ?: $receipt->email }}</strong> ({{ $receipt->email }})</p>

    @if($receipt->acknowledged_at)
    <div class="done">Réception confirmée le {{ $receipt->acknowledged_at->format('d/m/Y à H:i') }}. Merci.</div>
    @else
    <form method="POST" action="{{ route('meetings.receipt.confirm', ['token' => $receipt->token]) }}" onsubmit="this.querySelector('button').disabled = true;">
        @csrf
        <button type="submit">J'accuse réception</button>
    </form>
    <p>En cliquant, vous confirmez avoir reçu le compte rendu et la liste de présence envoyés par e-mail.</p>
    @endif
</div>
</body>
</html>
