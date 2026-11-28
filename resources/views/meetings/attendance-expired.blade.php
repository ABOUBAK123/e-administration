<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR expiré</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body.qr-expired-body {
            margin: 0;
            min-height: 100vh;
            background: #f3f4f6;
            padding: 24px 16px;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }
        .qr-expired-card {
            max-width: 42rem;
            margin: 0 auto;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            padding: 24px;
            text-align: center;
        }
        .qr-expired-title {
            margin: 0;
            font-size: 1.3rem;
            line-height: 1.4;
            font-weight: 700;
        }
        .qr-expired-text {
            margin: 10px 0 0;
            font-size: 0.98rem;
            color: #4b5563;
            line-height: 1.6;
        }
        @media (max-width: 639px) {
            body.qr-expired-body {
                padding: 12px;
            }
            .qr-expired-card {
                padding: 18px;
                border-radius: 18px;
            }
            .qr-expired-title {
                font-size: 1.08rem;
            }
            .qr-expired-text {
                font-size: 0.92rem;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-8 px-4 qr-expired-body">
<div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-6 text-center qr-expired-card">
    <h1 class="text-xl font-bold text-gray-800 qr-expired-title">QR Code non valide</h1>
    @if($meeting->isCancelled())
    <p class="text-sm text-gray-600 mt-2 qr-expired-text">La réunion <strong>{{ $meeting->title }}</strong> a été annulée.</p>
    @else
    <p class="text-sm text-gray-600 mt-2 qr-expired-text">La période d'émargement est expirée pour la réunion: <strong>{{ $meeting->title }}</strong>.</p>
    @endif
</div>
</body>
</html>
