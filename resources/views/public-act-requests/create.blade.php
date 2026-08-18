<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouvelle demande d'acte</title>
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
    <main class="max-w-5xl mx-auto px-4 py-8 md:px-6 space-y-5">
        <section class="rounded-2xl border border-cyan-100 bg-gradient-to-r from-[#0ea5e9] via-[#2563eb] to-[#4f46e5] shadow-lg p-6 text-white">
            <p class="text-blue-100 text-xs mb-1">Étape 3 sur 3</p>
            <h1 class="text-2xl font-bold">Formulaire de demande</h1>
            <p class="text-sm text-blue-50 mt-2">{{ $requestedAct->document_name }} · {{ $administration->name }}</p>
        </section>

        <a href="{{ route('public.act-requests.by-admin', $administration->id) }}"
           class="inline-flex items-center text-xs font-semibold text-blue-700 hover:underline">
            ← Retour à la liste des actes
        </a>

        <div class="bg-white/95 backdrop-blur rounded-2xl border border-emerald-100 shadow-sm p-6">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-base font-semibold text-gray-800">
                    3. Formulaire de demande – {{ $requestedAct->document_name }}
                </h2>
            </div>

            @if(session('success'))
                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                    @if(session('tracking_number'))
                        <div class="mt-2 text-xs text-emerald-800">
                            <p><strong>Numero de traitement:</strong> {{ session('tracking_number') }}</p>
                            @if(session('tracking_url'))
                                <p class="mt-1">
                                    <a href="{{ session('tracking_url') }}" class="font-semibold underline hover:no-underline">
                                        Suivre ma demande en ligne
                                    </a>
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @if($errors->any())
                <div class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="mt-4 space-y-4" method="POST" enctype="multipart/form-data"
                  action="{{ route('public.act-requests.store', [$administration->id, $requestedAct->id]) }}">
                @csrf

                {{-- Secteur de compétence + Administration réceptrice --}}
                @php
                    $sectorLabels = [
                        'fiscalite'      => 'Fiscalité & Finances',
                        'social'         => 'Protection Sociale',
                        'travail'        => 'Travail & Emploi',
                        'urbanisme'      => 'Urbanisme & Logement',
                        'education'      => 'Éducation & Formation',
                        'sante'          => 'Santé',
                        'justice'        => 'Justice',
                        'environnement'  => 'Environnement',
                        'commerce'       => 'Commerce & Industrie',
                        'banques'        => 'Banques',
                        'securite'       => 'Sécurité',
                        'administration' => 'Administration',
                        'agriculture'    => 'Agriculture',
                        'autre'          => 'Autre',
                    ];
                    $selectedRecipientId = (string) old('recipient_administration_id', '');
                    $selectedSector = (string) old('recipient_sector', '');
                    if ($selectedSector === '' && $selectedRecipientId !== '') {
                        $selectedRecipient = $recipients->firstWhere('id', $selectedRecipientId);
                        $selectedRecipientMeta = is_array($selectedRecipient?->metadata) ? $selectedRecipient->metadata : [];
                        $selectedSector = (string) ($selectedRecipientMeta['sector'] ?? '');
                    }
                @endphp
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 space-y-3">
                    <p class="text-xs font-semibold text-blue-800">Administration réceptrice de votre demande</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Secteur de compétence</label>
                            <select id="pub-sector-select" name="recipient_sector"
                                    onchange="pubFilterRecipients(this.value)"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="">— Tous les secteurs —</option>
                                @foreach($sectorLabels as $sv => $sl)
                                    <option value="{{ $sv }}" {{ $selectedSector === $sv ? 'selected' : '' }}>{{ $sl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Administration réceptrice *</label>
                            <select name="recipient_administration_id" id="pub-recip-select" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="">— Sélectionner —</option>
                                @foreach($recipients as $recip)
                                    @php $recipMeta = is_array($recip->metadata) ? $recip->metadata : []; @endphp
                                    <option value="{{ $recip->id }}"
                                            data-sector="{{ (string) ($recipMeta['sector'] ?? '') }}"
                                            {{ $selectedRecipientId === $recip->id ? 'selected' : '' }}>
                                        {{ $recip->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Motif de la demande --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Motif de la demande *</label>
                    <textarea name="motif" rows="3" required
                              placeholder="Expliquez brièvement le motif de votre demande..."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 min-h-[88px]">{{ old('motif') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Nom complet *</label>
                        <input type="text" name="applicant_full_name" value="{{ old('applicant_full_name') }}" required
                               placeholder="Votre nom et prénom"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="applicant_email" value="{{ old('applicant_email') }}" required
                               placeholder="votre.email@exemple.com"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                        <input type="text" name="applicant_phone" value="{{ old('applicant_phone') }}"
                               placeholder="Votre téléphone"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                </div>

                @php
                    $fields = is_array($requestedAct->applicant_fields) ? $requestedAct->applicant_fields : [];
                @endphp
                @if(!empty($fields))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($fields as $field)
                            @php
                                $label = trim((string)($field['label'] ?? ''));
                                $type  = strtolower(trim((string)($field['inputType'] ?? 'text')));
                                $key   = \Illuminate\Support\Str::of($label)->ascii()->lower()->replace("'", '_')->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
                                $rawOptions = $field['options'] ?? $field['values'] ?? [];
                                if (!is_array($rawOptions)) {
                                    $rawOptions = preg_split('/\r\n|\n|,/', (string) $rawOptions) ?: [];
                                }
                                $options = array_values(array_unique(array_filter(array_map(static fn($option) => trim((string) $option), $rawOptions), static fn($option) => $option !== '')));
                            @endphp
                            @if($label !== '' && $key !== '')
                                <div class="{{ in_array($type, ['textarea','select'], true) && $type === 'textarea' ? 'md:col-span-2' : '' }}">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ $label }} *</label>
                                    @if($type === 'textarea')
                                        <textarea name="extra[{{ $key }}]" rows="3" required
                                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 min-h-[88px]">{{ old('extra.' . $key) }}</textarea>
                                    @elseif($type === 'list')
                                        <select name="extra[{{ $key }}]" required
                                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                            <option value="">Sélectionner une valeur</option>
                                            @foreach($options as $option)
                                                <option value="{{ $option }}" {{ old('extra.' . $key) === $option ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php $htmlType = match($type) { 'date'=>'date','number'=>'number','email'=>'email','phone'=>'tel', default=>'text' }; @endphp
                                        <input type="{{ $htmlType }}" name="extra[{{ $key }}]" value="{{ old('extra.' . $key) }}" required
                                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Note --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
                    <textarea name="note" rows="3"
                              placeholder="Informations complémentaires"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 min-h-[88px]">{{ old('note') }}</textarea>
                </div>

                @php
                    $requiredDocs = is_array($requestedAct->required_documents) ? $requestedAct->required_documents : [];
                @endphp
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Fichiers à joindre</label>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-[1.1fr_1fr_auto] gap-2 items-start">
                            <div id="attachment-picker-slot">
                                <input id="attachment-file-picker" type="file" accept="application/pdf,.pdf"
                                       class="block w-full text-xs border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            </div>
                            <select id="attachment-label-picker"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="">Choisir le nom du fichier</option>
                                @foreach($requiredDocs as $doc)
                                    @php $docLabel = trim((string) $doc); @endphp
                                    @if($docLabel !== '')
                                        <option value="{{ $docLabel }}">{{ $docLabel }}</option>
                                    @endif
                                @endforeach
                                <option value="Pièce complémentaire">Pièce complémentaire</option>
                            </select>
                            <button type="button" id="join-attachment-btn"
                                    class="rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold transition">
                                Joindre
                            </button>
                        </div>
                        <div id="attachment-inline-error" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></div>
                        <div id="extra-attachments-list" class="space-y-2"></div>
                        <p class="text-[11px] text-gray-500">Choisissez un fichier PDF, sélectionnez son nom dans la liste, puis cliquez sur <strong>Joindre</strong>. Le fichier ajouté s'affiche juste en dessous.</p>
                    </div>
                    @error('attachment_labels.*')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                    @error('attachments_files')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-[11px] text-gray-500 mt-1">PDF uniquement · 50 Mo max par fichier.</p>
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="rounded-lg bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 text-sm font-semibold transition">
                        Envoyer la demande
                    </button>
                </div>
            </form>
        </div>
    </main>
    <script>
        (function () {
            var addBtn = document.getElementById('join-attachment-btn');
            var list = document.getElementById('extra-attachments-list');
            var fileSlot = document.getElementById('attachment-picker-slot');
            var labelPicker = document.getElementById('attachment-label-picker');
            var errorBox = document.getElementById('attachment-inline-error');
            var requiredLabels = @json(array_values(array_filter(array_map(static fn ($doc) => trim((string) $doc), $requiredDocs))));
            if (!addBtn || !list || !fileSlot || !labelPicker || !errorBox) return;

            function buildFilePicker() {
                var input = document.createElement('input');
                input.type = 'file';
                input.accept = 'application/pdf,.pdf';
                input.className = 'block w-full text-xs border border-gray-300 rounded-lg px-3 py-2 bg-white';
                return input;
            }

            function showError(message) {
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
            }

            function clearError() {
                errorBox.textContent = '';
                errorBox.classList.add('hidden');
            }

            function updateLabelPickerState() {
                var usedLabels = new Set();
                list.querySelectorAll('input[name="attachment_labels[]"]').forEach(function (input) {
                    if (input.value) {
                        usedLabels.add(input.value);
                    }
                });

                Array.prototype.forEach.call(labelPicker.options, function (option) {
                    if (!option.value || requiredLabels.indexOf(option.value) === -1) {
                        option.disabled = false;
                        return;
                    }

                    option.disabled = usedLabels.has(option.value);
                });

                if (labelPicker.selectedOptions.length && labelPicker.selectedOptions[0].disabled) {
                    labelPicker.value = '';
                }
            }

            addBtn.addEventListener('click', function () {
                var currentPicker = fileSlot.querySelector('input[type="file"]');
                if (!currentPicker || !currentPicker.files || currentPicker.files.length === 0) {
                    showError('Choisissez d\'abord un fichier à téléverser.');
                    return;
                }

                if (!labelPicker.value) {
                    showError('Choisissez le nom du fichier dans la liste.');
                    return;
                }

                clearError();

                currentPicker.name = 'attachments_files[]';
                currentPicker.className = 'hidden';

                var hiddenLabel = document.createElement('input');
                hiddenLabel.type = 'hidden';
                hiddenLabel.name = 'attachment_labels[]';
                hiddenLabel.value = labelPicker.value;

                var row = document.createElement('div');
                row.className = 'rounded-lg border border-gray-200 bg-white px-3 py-3 extra-attachment-row';
                row.innerHTML =
                    '<div class="flex items-start justify-between gap-3">' +
                    '<div class="min-w-0">' +
                    '<p class="text-sm font-semibold text-gray-800 break-words"></p>' +
                    '<p class="text-xs text-gray-500 mt-1 break-all"></p>' +
                    '</div>' +
                    '<button type="button" class="remove-extra-attachment text-xs px-2 py-1 rounded bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">Supprimer</button>' +
                    '</div>';

                row.querySelector('p.text-sm').textContent = labelPicker.value;
                row.querySelector('p.text-xs').textContent = currentPicker.files[0].name;
                row.appendChild(currentPicker);
                row.appendChild(hiddenLabel);
                list.appendChild(row);

                fileSlot.innerHTML = '';
                fileSlot.appendChild(buildFilePicker());
                labelPicker.value = '';
                updateLabelPickerState();
            });

            list.addEventListener('click', function (e) {
                var target = e.target;
                if (!(target instanceof HTMLElement)) return;
                if (!target.classList.contains('remove-extra-attachment')) return;
                var row = target.closest('.extra-attachment-row');
                if (row) {
                    row.remove();
                    updateLabelPickerState();
                }
            });

            updateLabelPickerState();
        })();

        // Filtre administration réceptrice par secteur
        function pubFilterRecipients(sector) {
            var sel = document.getElementById('pub-recip-select');
            var prev = sel.value;
            Array.prototype.forEach.call(sel.options, function(opt) {
                if (!opt.dataset.sector && opt.value === '') { return; } // keep placeholder
                var show = !sector || opt.dataset.sector === sector || opt.value === '';
                opt.style.display = show ? '' : 'none';
            });
            if (prev && sel.querySelector('option[value="'+prev+'"]') &&
                sel.querySelector('option[value="'+prev+'"]').style.display === 'none') {
                sel.value = '';
            }
        }

        var sectorInit = document.getElementById('pub-sector-select');
        if (sectorInit) {
            pubFilterRecipients(sectorInit.value || '');
        }
    </script>
</body>
</html>
