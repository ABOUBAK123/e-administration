<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paiement de la demande</title>
    @php
        $useVite = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
    @endphp
    @if($useVite)
        @vite(['resources/css/app.css'])
    @else
        <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    @endif
</head>
<body class="min-h-screen bg-gradient-to-br from-cyan-50 via-sky-50 to-indigo-100 text-slate-800">
    <main class="max-w-2xl mx-auto px-4 py-8 md:px-6 space-y-5">
        <section class="rounded-2xl border border-cyan-100 bg-gradient-to-r from-[#059669] via-[#0d9488] to-[#0891b2] shadow-lg p-6 text-white">
            <p class="text-emerald-100 text-xs mb-1">Paiement requis</p>
            <h1 class="text-2xl font-bold">{{ $requestedAct->document_name }}</h1>
            <p class="text-sm text-emerald-50 mt-2">
                Montant à payer :
                <span class="font-bold">{{ number_format((float) $requestedAct->amount, 0, ',', ' ') }} FCFA</span>
            </p>
        </section>

        <div class="bg-white/95 backdrop-blur rounded-2xl border border-emerald-100 shadow-sm p-6 space-y-4">
            <p class="text-xs text-gray-500">
                Numéro de traitement : <strong>{{ $submission->tracking_number }}</strong>
            </p>

            @if($configs->isEmpty())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Aucun moyen de paiement mobile money n'est actuellement disponible pour cette administration.
                    Merci de contacter l'administration concernée, ou de réessayer plus tard.
                </div>
            @else
                <div id="mm-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>

                {{-- Étape 1 : formulaire de paiement --}}
                <form id="mm-payment-form" method="POST"
                    action="{{ route('public.act-requests.payment.initiate', $submission->tracking_token) }}"
                    class="space-y-4 {{ $pendingTransaction ? 'hidden' : '' }}">
                    @csrf
                    @if($configs->count() > 1)
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Moyen de paiement</label>
                        <div class="space-y-2">
                            @foreach($configs as $cfg)
                            <label class="flex items-center gap-2 border border-gray-200 rounded-xl px-4 py-3 text-sm cursor-pointer hover:border-emerald-300">
                                <input type="radio" name="provider_config_id" value="{{ $cfg->id }}" {{ $loop->first ? 'checked' : '' }} class="text-emerald-600 focus:ring-emerald-400">
                                {{ $cfg->provider_label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <input type="hidden" name="provider_config_id" value="{{ $configs->first()->id }}">
                    <p class="text-sm text-gray-600">Moyen de paiement : <strong>{{ $configs->first()->provider_label }}</strong></p>
                    @endif

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Numéro Mobile Money</label>
                        <input type="tel" name="phone" required placeholder="Ex: 07 XX XX XX XX"
                            value="{{ $submission->applicant_phone }}"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">
                        <p class="text-[11px] text-gray-500 mt-1">Vous recevrez une demande de confirmation (USSD) sur ce numéro.</p>
                    </div>

                    <button type="submit" id="mm-pay-btn"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-3 rounded-xl transition">
                        Payer {{ number_format((float) $requestedAct->amount, 0, ',', ' ') }} FCFA
                    </button>
                </form>

                {{-- Étape 2 : attente de confirmation --}}
                <div id="mm-waiting" class="{{ $pendingTransaction ? '' : 'hidden' }} space-y-3 text-center py-6">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                        <i class="fas fa-spinner fa-spin text-emerald-600 text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">Confirmation en attente</p>
                    <p class="text-xs text-gray-500">
                        Une demande a été envoyée sur votre téléphone. Validez-la (code USSD) pour finaliser votre demande.
                    </p>
                </div>

                {{-- Étape 3 : succès --}}
                <div id="mm-success" class="hidden space-y-3 text-center py-6">
                    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                        <i class="fas fa-check text-emerald-600 text-xl"></i>
                    </div>
                    <p class="text-sm font-semibold text-gray-800">Paiement confirmé</p>
                    <p class="text-xs text-gray-500">Votre demande est maintenant prise en compte. Redirection...</p>
                </div>
            @endif
        </div>

        <a href="{{ route('public.act-requests.track', $submission->tracking_token) }}"
           class="block text-center text-xs font-semibold text-emerald-700 hover:underline">
            Suivre ma demande
        </a>
    </main>

<script>
const MM_STATUS_URL_BASE = @json(url('/api/mobile-money/status'));
const MM_TRACK_URL = @json(route('public.act-requests.track', $submission->tracking_token));
const MM_PENDING_TRANSACTION_ID = @json($pendingTransaction->id ?? null);

(function () {
    var form = document.getElementById('mm-payment-form');
    var waiting = document.getElementById('mm-waiting');
    var success = document.getElementById('mm-success');
    var errorBox = document.getElementById('mm-error');
    var payBtn = document.getElementById('mm-pay-btn');
    var pollTimer = null;

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('hidden');
    }

    function startPolling(transactionId) {
        var statusUrl = MM_STATUS_URL_BASE + '/' + transactionId;
        pollTimer = setInterval(function () {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'successful') {
                        clearInterval(pollTimer);
                        waiting.classList.add('hidden');
                        success.classList.remove('hidden');
                        setTimeout(function () {
                            window.location.href = MM_TRACK_URL;
                        }, 2000);
                    } else if (data.status === 'failed') {
                        clearInterval(pollTimer);
                        waiting.classList.add('hidden');
                        form.classList.remove('hidden');
                        showError(data.reason || 'Le paiement a échoué ou a été annulé. Vous pouvez réessayer.');
                    }
                })
                .catch(function () { /* silencieux, on continue de sonder */ });
        }, 3000);
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errorBox.classList.add('hidden');
            payBtn.disabled = true;
            payBtn.textContent = 'Envoi en cours...';

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    if (!res.data.ok) {
                        payBtn.disabled = false;
                        payBtn.textContent = 'Réessayer';
                        showError(res.data.message || 'Une erreur est survenue.');
                        return;
                    }
                    form.classList.add('hidden');
                    waiting.classList.remove('hidden');
                    startPolling(res.data.transaction_id);
                })
                .catch(function () {
                    payBtn.disabled = false;
                    payBtn.textContent = 'Réessayer';
                    showError('Erreur réseau. Veuillez réessayer.');
                });
        });
    }

    if (MM_PENDING_TRANSACTION_ID) {
        startPolling(MM_PENDING_TRANSACTION_ID);
    }
})();
</script>
</body>
</html>
