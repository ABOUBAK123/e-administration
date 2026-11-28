<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 14px; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 10px; white-space: pre-line; }
    </style>
</head>
<body>
    <h1>Compte rendu de réunion</h1>
    <div class="meta">
        Réunion: {{ $meeting->title }}<br>
        Date: {{ $meeting->starts_at?->format('d/m/Y H:i') }}<br>
        Salle: {{ $meeting->room?->name ?: 'N/A' }}
        @if($meeting->validated_at)
        <br>Validé le {{ $meeting->validated_at->format('d/m/Y H:i') }}{{ $meeting->validatedByUser ? ' par ' . $meeting->validatedByUser->name : '' }}
        @endif
    </div>

    <div class="box">{{ $meeting->minutes_content ?: 'Aucun contenu de compte rendu.' }}</div>
</body>
</html>
