<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Émargement - {{ $meeting->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body.qr-attendance-body {
            margin: 0;
            background: #f0fdf4;
            min-height: 100vh;
            padding: 24px 16px;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }
        .qr-card {
            max-width: 42rem;
            margin: 0 auto;
            background: #f0fdf4;
            border: 2px solid #fb923c;
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            padding: 24px;
        }
        .qr-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 8px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid #fed7aa;
        }
        .qr-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .qr-title {
            margin: 0;
            text-align: center;
            font-size: 1.25rem;
            line-height: 1.4;
            font-weight: 700;
        }
        .qr-subtitle {
            margin: 4px 0 0;
            text-align: center;
            color: #6b7280;
            font-size: 0.95rem;
        }
        .qr-alert {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        .qr-alert-success {
            background: #dcfce7;
            color: #14532d;
            border: 2px solid #16a34a;
            border-radius: 16px;
            padding: 20px 18px;
            text-align: center;
        }
        .qr-alert-success .qr-success-icon {
            font-size: 3rem;
            line-height: 1;
            display: block;
            margin-bottom: 10px;
        }
        .qr-alert-success .qr-success-title {
            font-size: 1.35rem;
            font-weight: 800;
            display: block;
            margin-bottom: 6px;
            color: #14532d;
        }
        .qr-alert-success .qr-success-body {
            font-size: 1rem;
            font-weight: 500;
            color: #166534;
        }
        .qr-alert-error {
            background: #fef2f2;
            color: #b91c1c;
        }
        .qr-form {
            margin-top: 16px;
            display: grid;
            gap: 16px;
        }
        .qr-field {
            display: grid;
            gap: 6px;
        }
        .qr-field label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #374151;
        }
        .qr-field input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 14px 14px;
            font-size: 16px;
            background: #fff;
            color: #111827;
        }
        .qr-field input:focus {
            outline: none;
            border-color: #2453d6;
            box-shadow: 0 0 0 3px rgba(36, 83, 214, 0.15);
        }
        .qr-grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .qr-signature-box {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            overflow: hidden;
        }
        .qr-signature-canvas {
            display: block;
            width: 100%;
            height: 220px;
            background:
                linear-gradient(to bottom, transparent 31px, #eef2f7 32px),
                #ffffff;
            background-size: 100% 32px;
            touch-action: none;
            cursor: crosshair;
        }
        .qr-signature-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .qr-signature-hint {
            font-size: 0.8rem;
            color: #6b7280;
            line-height: 1.4;
        }
        .qr-signature-clear {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #374151;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }
        .qr-signature-clear:hover {
            background: #f3f4f6;
        }
        .qr-submit {
            width: 100%;
            border: 0;
            border-radius: 16px;
            padding: 16px;
            background: #2453d6;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .12s ease, background-color .12s ease;
        }
        .qr-submit:hover {
            background: #1f47bb;
        }
        .qr-submit:active {
            transform: scale(0.98);
        }
        .qr-badge {
            display: block;
            margin-top: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #166534;
        }
        .qr-hidden {
            display: none;
        }
        .qr-correction-banner {
            background: #fffbeb;
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.92rem;
            color: #92400e;
            font-weight: 500;
        }
        .qr-correction-banner .qr-correction-icon {
            font-size: 1.4rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .qr-submit-correct {
            width: 100%;
            border: 0;
            border-radius: 16px;
            padding: 16px;
            background: #d97706;
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform .12s ease, background-color .12s ease;
        }
        .qr-submit-correct:hover {
            background: #b45309;
        }
        .qr-submit-correct:active {
            transform: scale(0.98);
        }
        .qr-field input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }
        @media (min-width: 640px) {
            .qr-grid-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 639px) {
            body.qr-attendance-body {
                padding: 12px;
            }
            .qr-card {
                padding: 16px;
                border-radius: 18px;
            }
            .qr-logo {
                width: 68px;
                height: 68px;
            }
            .qr-title {
                font-size: 1.05rem;
            }
            .qr-subtitle {
                font-size: 0.88rem;
            }
            .qr-signature-canvas {
                height: 180px;
            }
            .qr-signature-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="bg-green-50 min-h-screen py-6 px-4 qr-attendance-body">
<div class="max-w-lg mx-auto bg-green-50 rounded-2xl shadow-lg border-2 border-orange-400 p-6 qr-card">

    {{-- En-tête centré : logo + entité sous tutelle --}}
    <div class="flex flex-col items-center text-center pb-4 border-b border-orange-200 mb-4 gap-2 qr-header">
        @if(!empty($branding['logo_url']))
            <img src="{{ $branding['logo_url'] }}" alt="Logo administration"
                 class="h-20 w-20 object-contain rounded-xl bg-white border border-gray-200 p-1 shadow-sm qr-logo">
        @endif
        @if(!empty($branding['tutelle_entity_name']))
            <div class="text-base font-bold text-gray-800 leading-tight">{{ $branding['tutelle_entity_name'] }}</div>
        @endif
        @if(!empty($branding['tutelle_entity_code']))
            <div class="text-sm font-bold text-gray-900 leading-tight">{{ $branding['tutelle_entity_code'] }}</div>
        @endif
        <div class="text-xs uppercase tracking-widest text-gray-400 font-medium mt-1">Formulaire d'émargement</div>
    </div>

    <h1 class="text-lg font-bold text-gray-800 text-center qr-title">{{ $meeting->title }}</h1>
    <p class="text-sm text-gray-500 text-center mt-1 qr-subtitle">{{ $meeting->starts_at?->format('d/m/Y H:i') }}</p>

    @if(session('success'))
    <div class="mt-4 qr-alert qr-alert-success">
        <span class="qr-success-icon">✅</span>
        <span class="qr-success-title">Inscription enregistrée !</span>
        <span class="qr-success-body">{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="mt-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm qr-alert qr-alert-error">{{ session('error') }}</div>
    @endif
    <div id="lookup-error" class="mt-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm qr-alert qr-alert-error hidden qr-hidden"></div>
    <div id="correction-banner" class="mt-4 qr-correction-banner hidden qr-hidden">
        <span class="qr-correction-icon">✏️</span>
        <div>
            <strong>Vous êtes déjà inscrit.</strong><br>
            Vous pouvez corriger vos informations ci-dessous pendant la durée de la réunion.
        </div>
    </div>

    <form id="attendance-form" method="POST" action="{{ route('meetings.qr.sign', $meeting->qr_token) }}" class="mt-4 space-y-4 qr-form">
        @csrf
        <div class="qr-field">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Matricule ou email</label>
            <input id="field-identifier" name="identifier" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base"
                   placeholder="Votre matricule ou votre adresse e-mail" autocomplete="off">
            <span id="autofill-badge" class="hidden text-xs text-green-700 font-medium mt-1 block qr-badge qr-hidden">✓ Informations pré-remplies : il ne reste qu'à signer.</span>
        </div>
        <div class="qr-field">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Nom complet (si externe)</label>
            <input id="field-full_name" name="full_name" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base" placeholder="Nom et prénom">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 qr-grid-2">
            <div class="qr-field">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                <input id="field-email" type="email" name="email" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base">
            </div>
            <div class="qr-field">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Téléphone</label>
                <input id="field-phone" name="phone" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base">
            </div>
        </div>
        <div class="qr-field">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Fonction</label>
            <input id="field-job_title" name="job_title" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base">
        </div>
        <div class="qr-field">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Organisation</label>
            <input id="field-organization" name="organization" class="w-full border border-gray-300 rounded-lg px-3 py-3 text-base">
        </div>
        <div class="qr-field">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Signature</label>
            <div class="qr-signature-box">
                <canvas id="signature-pad" class="qr-signature-canvas"></canvas>
                <div class="qr-signature-actions">
                    <div class="qr-signature-hint">Signez avec le doigt, le stylet ou la souris dans la zone ci-dessus.</div>
                    <button type="button" id="signature-clear" class="qr-signature-clear">Effacer</button>
                </div>
            </div>
            <input type="hidden" name="signature" id="signature-data">
        </div>
        <button id="submit-btn" class="w-full px-3 py-4 rounded-xl bg-[#2453d6] text-white text-base font-semibold hover:bg-[#1f47bb] active:scale-95 transition-transform qr-submit">Signer ma présence</button>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('attendance-form');
    const lookupUrl = '{{ route("meetings.qr.lookup", $meeting->qr_token) }}';
    const signUrl   = '{{ route("meetings.qr.sign", $meeting->qr_token) }}';
    const correctUrl = '{{ route("meetings.qr.correct", $meeting->qr_token) }}';
    const identifierInput = document.getElementById('field-identifier');
    const badge = document.getElementById('autofill-badge');
    const lookupError = document.getElementById('lookup-error');
    const correctionBanner = document.getElementById('correction-banner');
    const submitBtn = document.getElementById('submit-btn');
    const fields = ['full_name', 'email', 'phone', 'job_title', 'organization'];
    const signatureCanvas = document.getElementById('signature-pad');
    const signatureClear = document.getElementById('signature-clear');
    const signatureData = document.getElementById('signature-data');
    const signatureContext = signatureCanvas.getContext('2d');
    let debounceTimer = null;
    let isDrawing = false;
    let hasSignature = false;
    let correctionMode = false;

    function resizeSignatureCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = signatureCanvas.getBoundingClientRect();
        signatureCanvas.width = Math.floor(rect.width * ratio);
        signatureCanvas.height = Math.floor(rect.height * ratio);
        signatureContext.setTransform(1, 0, 0, 1, 0, 0);
        signatureContext.scale(ratio, ratio);
        signatureContext.lineCap = 'round';
        signatureContext.lineJoin = 'round';
        signatureContext.lineWidth = 2;
        signatureContext.strokeStyle = '#111827';
        hasSignature = false;
        signatureData.value = '';
    }

    function pointFromEvent(event) {
        const rect = signatureCanvas.getBoundingClientRect();
        if (event.touches && event.touches[0]) {
            return { x: event.touches[0].clientX - rect.left, y: event.touches[0].clientY - rect.top };
        }
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    function startDrawing(event) {
        isDrawing = true;
        const point = pointFromEvent(event);
        signatureContext.beginPath();
        signatureContext.moveTo(point.x, point.y);
        event.preventDefault();
    }

    function draw(event) {
        if (!isDrawing) return;
        const point = pointFromEvent(event);
        signatureContext.lineTo(point.x, point.y);
        signatureContext.stroke();
        hasSignature = true;
        event.preventDefault();
    }

    function stopDrawing() { isDrawing = false; }

    function clearSignature() {
        signatureContext.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
        signatureContext.beginPath();
        hasSignature = false;
        signatureData.value = '';
    }

    function showLookupError(message) {
        if (!lookupError) return;
        lookupError.textContent = message;
        lookupError.classList.remove('hidden', 'qr-hidden');
    }

    function hideLookupError() {
        if (!lookupError) return;
        lookupError.textContent = '';
        lookupError.classList.add('hidden', 'qr-hidden');
    }

    function showCorrectionBanner() {
        if (correctionBanner) correctionBanner.classList.remove('hidden', 'qr-hidden');
    }

    function hideCorrectionBanner() {
        if (correctionBanner) correctionBanner.classList.add('hidden', 'qr-hidden');
    }

    function enterCorrectionMode() {
        correctionMode = true;
        form.action = correctUrl;
        submitBtn.textContent = 'Corriger mes informations';
        submitBtn.classList.remove('qr-submit');
        submitBtn.classList.add('qr-submit-correct');
        hideLookupError();
        showCorrectionBanner();
    }

    function exitCorrectionMode() {
        correctionMode = false;
        form.action = signUrl;
        submitBtn.textContent = 'Signer ma présence';
        submitBtn.classList.remove('qr-submit-correct');
        submitBtn.classList.add('qr-submit');
        hideCorrectionBanner();
    }

    // Seuls les champs remplis automatiquement sont effacés : la saisie manuelle est conservée.
    let autofilled = [];

    function fill(data) {
        clear();
        fields.forEach(function (key) {
            const el = document.getElementById('field-' + key);
            if (el && data[key]) {
                el.value = data[key];
                autofilled.push(el);
            }
        });
        badge.classList.remove('hidden', 'qr-hidden');
    }

    function clear() {
        autofilled.forEach(function (el) { el.value = ''; });
        autofilled = [];
        badge.classList.add('hidden', 'qr-hidden');
    }

    identifierInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        badge.classList.add('hidden', 'qr-hidden');
        hideLookupError();
        hideCorrectionBanner();
        exitCorrectionMode();
        clear();
        const val = this.value.trim();
        if (val.length < 3) return;

        debounceTimer = setTimeout(function () {
            fetch(lookupUrl + '?identifier=' + encodeURIComponent(val), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    // La saisie a pu changer pendant la recherche.
                    if (identifierInput.value.trim() !== val || !data || !Object.keys(data).length) return;
                    fill(data);
                    if (data.already_registered) {
                        if (data.can_edit) {
                            enterCorrectionMode();
                        } else {
                            showLookupError(data.message || 'Vous êtes déjà inscrit à cette réunion. La période de modification est terminée.');
                        }
                    }
                })
                .catch(function () {});
        }, 400);
    });

    resizeSignatureCanvas();
    window.addEventListener('resize', resizeSignatureCanvas);

    signatureCanvas.addEventListener('mousedown', startDrawing);
    signatureCanvas.addEventListener('mousemove', draw);
    signatureCanvas.addEventListener('mouseup', stopDrawing);
    signatureCanvas.addEventListener('mouseleave', stopDrawing);
    signatureCanvas.addEventListener('touchstart', startDrawing, { passive: false });
    signatureCanvas.addEventListener('touchmove', draw, { passive: false });
    signatureCanvas.addEventListener('touchend', stopDrawing);
    signatureCanvas.addEventListener('touchcancel', stopDrawing);
    signatureClear.addEventListener('click', clearSignature);

    form.addEventListener('submit', function (event) {
        if (!correctionMode && lookupError && !lookupError.classList.contains('hidden')) {
            event.preventDefault();
            return;
        }
        if (hasSignature) {
            signatureData.value = signatureCanvas.toDataURL('image/png');
        }
    });
})();
</script>
</body>
</html>
