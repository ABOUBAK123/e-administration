<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification du document — E-administration</title>
    @php
        $useVite = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
        // Nom fixe sur la page ouverte par le QR code, indépendant du réglage « nom de l'application ».
        $appName = 'E-administration';
        $logoPath = \App\Models\AppSetting::where('key', 'header_logo')->value('value');
        $brandColor = \App\Models\AppSetting::where('key', 'menu_color')->value('value') ?? '#1e40af';
    @endphp
    @if($useVite)
        @vite(['resources/css/app.css'])
    @else
        <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    @endif
    <link rel="stylesheet" href="/vendor/fontawesome/css/all.min.css">
    <style>
        body { background: #f1f5f9; min-height: 100vh; font-family: system-ui, -apple-system, sans-serif; }
        .brand-bar { background: {{ $brandColor }}; }
        .btn-signed { background: #059669; }
        .btn-signed:hover { background: #047857; }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    {{-- Barre de marque --}}
    <div class="brand-bar py-3 px-4 sm:px-8 flex items-center gap-3 shadow">
        <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
            @if($logoPath)
                <img src="{{ asset('storage/' . $logoPath) }}" alt="{{ $appName }}" class="w-full h-full object-contain rounded-xl p-0.5">
            @else
                <i class="fas fa-file-signature text-white text-base"></i>
            @endif
        </div>
        <div>
            <div class="text-white font-bold text-sm leading-tight">{{ $appName }}</div>
            <div class="text-white/70 text-xs">Vérification de document</div>
        </div>
    </div>

    {{-- Contenu principal --}}
    <main class="flex-1 flex flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg space-y-5">

            @if($isSigned)
            {{-- ════════════════════════════════════════════
                 DOCUMENT SIGNÉ
                 ════════════════════════════════════════════ --}}

            {{-- Badge authentifié --}}
            <div class="flex flex-col items-center text-center gap-3">
                <div class="w-20 h-20 rounded-full bg-emerald-100 border-4 border-emerald-400 flex items-center justify-center shadow-lg">
                    <i class="fas fa-shield-alt text-3xl text-emerald-600"></i>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-emerald-700">Document authentique</h1>
                    <p class="text-sm text-gray-500 mt-1">Ce document a été signé électroniquement et sa signature est vérifiée.</p>
                </div>
            </div>

            {{-- Carte d'information du document --}}
            <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                <div class="bg-emerald-50 px-5 py-3 flex items-center gap-2 border-b border-emerald-100">
                    <i class="fas fa-file-signature text-emerald-600"></i>
                    <span class="text-sm font-bold text-emerald-800">Informations du document signé</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @if($document->document_number)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-hashtag text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Numéro de document</div>
                            <div class="text-sm font-mono font-bold text-blue-700">{{ $document->document_number }}</div>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-file-alt text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Titre du document</div>
                            <div class="text-sm font-semibold text-gray-800 truncate">{{ $document->title }}</div>
                        </div>
                    </div>

                    @if($document->issuingAdministration)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-building text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Administration émettrice</div>
                            <div class="text-sm font-medium text-gray-800">{{ $document->issuingAdministration->name }}</div>
                        </div>
                    </div>
                    @endif

                    @if($document->owner)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-user text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Généré par</div>
                            <div class="text-sm font-medium text-gray-800">{{ $document->owner->name }}</div>
                        </div>
                    </div>
                    @endif

                    @if($document->created_at)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="far fa-calendar text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Date de génération</div>
                            <div class="text-sm text-gray-700">{{ optional($document->created_at)->format('d/m/Y à H:i') }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Signature info --}}
                <div class="bg-emerald-50 border-t border-emerald-200 divide-y divide-emerald-100">
                    @if(count($signers))
                    <div class="flex gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-pen-nib text-emerald-500 text-xs"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs text-emerald-600 font-semibold">{{ count($signers) > 1 ? 'Signataires (' . count($signers) . ')' : 'Signataire' }}</div>
                            <ul class="space-y-2 mt-1">
                                @foreach($signers as $signer)
                                <li>
                                    <div class="text-sm font-bold text-emerald-800">{{ $signer['name'] }}</div>
                                    @if($signer['signed_at'] && count($signers) > 1)
                                    <div class="text-xs text-gray-500">Signé le {{ $signer['signed_at']->format('d/m/Y à H:i') }}</div>
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    @endif
                    @if($document->signed_at)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="far fa-clock text-emerald-500 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-emerald-600 font-semibold">Date de signature</div>
                            <div class="text-sm font-bold text-emerald-800">{{ $document->signed_at instanceof \Carbon\Carbon ? $document->signed_at->format('d/m/Y à H:i') : \Carbon\Carbon::parse($document->signed_at)->format('d/m/Y à H:i') }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Bouton téléchargement version signée --}}
            <a href="{{ $downloadUrl }}"
               class="btn-signed w-full flex items-center justify-center gap-3 px-6 py-4 text-white font-bold rounded-2xl shadow-lg transition text-base">
                <i class="fas fa-file-pdf text-lg"></i>
                Télécharger la version PDF signée
            </a>

            {{-- Mention légale --}}
            <p class="text-center text-xs text-gray-400 px-4">
                <i class="fas fa-lock mr-1"></i>
                Document certifié par signature électronique. La version téléchargée est la version officielle signée.
            </p>

            @else
            {{-- ════════════════════════════════════════════
                 DOCUMENT NON ENCORE SIGNÉ
                 ════════════════════════════════════════════ --}}

            <div class="flex flex-col items-center text-center gap-3">
                <div class="w-20 h-20 rounded-full bg-amber-100 border-4 border-amber-400 flex items-center justify-center shadow-lg">
                    <i class="fas fa-file-alt text-3xl text-amber-600"></i>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-amber-700">Document authentique</h1>
                    <p class="text-sm text-gray-500 mt-1">Ce document est issu du système e-administration, mais n'a pas encore été signé électroniquement.</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                <div class="bg-amber-50 px-5 py-3 flex items-center gap-2 border-b border-amber-100">
                    <i class="fas fa-info-circle text-amber-600"></i>
                    <span class="text-sm font-bold text-amber-800">Informations du document</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @if($document->document_number)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-hashtag text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Numéro de document</div>
                            <div class="text-sm font-mono font-bold text-blue-700">{{ $document->document_number }}</div>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-file-alt text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Titre</div>
                            <div class="text-sm font-semibold text-gray-800 truncate">{{ $document->title }}</div>
                        </div>
                    </div>

                    @if($document->issuingAdministration)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-building text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Administration</div>
                            <div class="text-sm font-medium text-gray-800">{{ $document->issuingAdministration->name }}</div>
                        </div>
                    </div>
                    @endif

                    @if($document->created_at)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 flex-shrink-0 text-center"><i class="far fa-calendar text-gray-400 text-xs"></i></div>
                        <div class="min-w-0">
                            <div class="text-xs text-gray-500">Généré le</div>
                            <div class="text-sm text-gray-700">{{ optional($document->created_at)->format('d/m/Y à H:i') }}</div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 flex items-start gap-3">
                <i class="fas fa-hourglass-half text-amber-500 mt-0.5 flex-shrink-0"></i>
                <div class="text-sm text-amber-800">
                    Ce document est en attente de signature électronique. Une fois signé sur la plateforme de signature,
                    ce QR code permettra de télécharger la version PDF officielle et signée.
                </div>
            </div>

            {{-- Télécharger la version actuelle (non signée) --}}
            <a href="{{ $downloadUrl }}"
               class="w-full flex items-center justify-center gap-3 px-6 py-3.5 bg-slate-600 hover:bg-slate-700 text-white font-semibold rounded-2xl shadow transition text-sm">
                <i class="fas fa-download"></i>
                Télécharger la version actuelle (non signée)
            </a>
            @endif

        </div>
    </main>

    {{-- Footer --}}
    <footer class="text-center py-4 text-xs text-gray-400 border-t border-gray-200 bg-white">
        {{ $appName }} — Système de vérification de documents &nbsp;·&nbsp; Token : <span class="font-mono">{{ substr($token, 0, 8) }}…</span>
    </footer>

</body>
</html>
