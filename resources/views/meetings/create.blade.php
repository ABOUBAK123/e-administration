@extends('layouts.app')
@section('title', isset($meeting) ? 'Modifier la réunion' : 'Nouvelle réunion')
@section('page-title', isset($meeting) ? 'Modifier la réunion' : 'Nouvelle réunion')
@section('page-subtitle', isset($meeting) ? $meeting->title : 'Planifier une réunion')

@section('content')
@include('meetings._nav')
@php
    // En modification, les champs reprennent la réunion ; après un refus, la saisie.
    $editing = isset($meeting) ? $meeting : null;
    $value = fn (string $field, $default = null) => old($field, $editing ? $editing->{$field} : $default);
    $dateValue = fn (string $field, $default = null) => old($field, $editing ? $editing->{$field}?->format('Y-m-d\TH:i') : $default);
    $checked = fn (string $field, bool $default) => session()->hasOldInput() ? (bool) old($field) : $default;
    $selectedParticipants = array_map('strval', (array) old('participants', session()->hasOldInput() || !$editing ? [] : $editing->participants->pluck('user_id')->filter()->all()));
    $externalRows = array_values((array) old('externals', session()->hasOldInput() || !$editing ? [] : $editing->participants
        ->filter(fn ($p) => !$p->user_id && $p->email)
        ->map(fn ($p) => ['full_name' => $p->full_name, 'email' => $p->email, 'organization' => $p->organization])->values()->all()));
@endphp
<form method="POST" action="{{ $editing ? route('meetings.update', $editing) : route('meetings.store') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if($editing)
        @method('PUT')
        @if($editing->recurrence_series_id && ($following ?? 0) > 0)
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 space-y-1">
            <p class="font-semibold">Cette réunion fait partie d'une série.</p>
            <label class="flex items-center gap-2"><input type="radio" name="apply_to" value="one" {{ old('apply_to', 'one') === 'one' ? 'checked' : '' }}> Modifier cette réunion seulement</label>
            <label class="flex items-center gap-2"><input type="radio" name="apply_to" value="following" {{ old('apply_to') === 'following' ? 'checked' : '' }}> Appliquer aussi aux {{ $following }} réunion(s) suivante(s)</label>
            <p class="text-xs">Seuls les champs que vous changez sont reportés ; un changement de date décale chaque réunion du même écart.</p>
        </div>
        @endif
        @if(($droppedNames ?? collect())->isNotEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Compte désactivé, retiré à l'enregistrement : {{ $droppedNames->implode(', ') }}.</div>
        @endif
    @endif

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Titre</label>
            <input type="text" name="title" value="{{ $value('title') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Type</label>
            <select name="meeting_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="ordinary" {{ $value('meeting_type') === 'ordinary' ? 'selected' : '' }}>Réunion ordinaire</option>
                <option value="extraordinary" {{ $value('meeting_type') === 'extraordinary' ? 'selected' : '' }}>Réunion extraordinaire</option>
                <option value="management_committee" {{ $value('meeting_type') === 'management_committee' ? 'selected' : '' }}>Comité de direction</option>
                <option value="project" {{ $value('meeting_type') === 'project' ? 'selected' : '' }}>Réunion de projet</option>
                <option value="technical" {{ $value('meeting_type') === 'technical' ? 'selected' : '' }}>Réunion technique</option>
                <option value="other" {{ $value('meeting_type') === 'other' ? 'selected' : '' }}>Autre</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Salle</label>
            <select name="meeting_room_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($rooms as $room)
                <option value="{{ $room->id }}" {{ (string) $value('meeting_room_id', $prefillRoomId ?? '') === (string) $room->id ? 'selected' : '' }}>{{ $room->name }} ({{ $room->location }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Début</label>
            <input type="datetime-local" name="starts_at" value="{{ $dateValue('starts_at', $prefillStartsAt ?? '') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Fin</label>
            <input type="datetime-local" name="ends_at" value="{{ $dateValue('ends_at', $prefillEndsAt ?? '') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Délai fixé (traitement)</label>
            <input type="datetime-local" name="processing_deadline" value="{{ $dateValue('processing_deadline') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Rédacteur du compte rendu</label>
            <select name="minutes_writer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ (string) $value('minutes_writer_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Validateur du compte rendu</label>
            <select name="validator_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ (string) $value('validator_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            @error('validator_id')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Priorité</label>
            <select name="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="low" {{ $value('priority', 'normal') === 'low' ? 'selected' : '' }}>Faible</option>
                <option value="normal" {{ $value('priority', 'normal') === 'normal' ? 'selected' : '' }}>Normale</option>
                <option value="high" {{ $value('priority', 'normal') === 'high' ? 'selected' : '' }}>Élevée</option>
                <option value="urgent" {{ $value('priority', 'normal') === 'urgent' ? 'selected' : '' }}>Urgente</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Confidentialité</label>
            <select name="confidentiality" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="public" {{ $value('confidentiality', 'internal') === 'public' ? 'selected' : '' }}>Publique</option>
                <option value="internal" {{ $value('confidentiality', 'internal') === 'internal' ? 'selected' : '' }}>Interne</option>
                <option value="confidential" {{ $value('confidentiality', 'internal') === 'confidential' ? 'selected' : '' }}>Confidentielle</option>
            </select>
        </div>

        @if(!$editing)
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Récurrence</label>
            <select name="recurrence_type" id="recurrence_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="none" {{ old('recurrence_type') === 'none' ? 'selected' : '' }}>Aucune</option>
                <option value="daily" {{ old('recurrence_type') === 'daily' ? 'selected' : '' }}>Quotidienne</option>
                <option value="weekly" {{ old('recurrence_type') === 'weekly' ? 'selected' : '' }}>Hebdomadaire</option>
                <option value="monthly" {{ old('recurrence_type') === 'monthly' ? 'selected' : '' }}>Mensuelle</option>
                <option value="yearly" {{ old('recurrence_type') === 'yearly' ? 'selected' : '' }}>Annuelle</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Fin de récurrence</label>
            <input type="date" name="recurrence_until" id="recurrence_until" value="{{ old('recurrence_until') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm disabled:bg-gray-100">
            <p class="text-xs text-gray-400 mt-1">Une réunion est créée pour chaque date, sur un an au plus (cinq ans en récurrence annuelle).</p>
        </div>
        @endif

        <div class="md:col-span-2 border border-gray-100 rounded-lg p-3 bg-gray-50/50">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-2">
                <input type="checkbox" id="is_virtual" name="is_virtual" value="1" {{ $checked('is_virtual', (bool) $editing?->is_virtual) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#2453d6] focus:ring-[#2453d6]"
                       onchange="document.getElementById('meeting_link_wrap').classList.toggle('hidden', !this.checked)">
                Réunion virtuelle / hybride (visioconférence)
            </label>
            <div id="meeting_link_wrap" class="{{ $checked('is_virtual', (bool) $editing?->is_virtual) ? '' : 'hidden' }}">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Lien de visioconférence (Teams, Zoom, Meet...)</label>
                <input type="url" name="meeting_link" value="{{ $value('meeting_link') }}" placeholder="https://teams.microsoft.com/..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @error('meeting_link')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Ordre du jour</label>
            <textarea name="agenda" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $value('agenda') }}</textarea>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Modèle de compte rendu (fichier Word)</label>
            <div id="tpl-dropzone"
                 class="border-2 border-dashed border-gray-300 rounded-xl px-4 py-6 text-center cursor-pointer hover:border-[#2453d6] hover:bg-blue-50 transition"
                 onclick="document.getElementById('minutes_template_file').click()">
                <i class="fas fa-file-word text-3xl text-blue-400 mb-2 block"></i>
                <p class="text-sm text-gray-600">Cliquer ici ou <strong>déposer un fichier .docx</strong></p>
                <p class="text-xs text-gray-400 mt-1">Formats acceptés : .doc, .docx — max 20 Mo</p>
                <p id="tpl-filename" class="text-xs text-emerald-600 font-semibold mt-2 hidden"></p>
            </div>
            <input type="file" id="minutes_template_file" name="minutes_template_file"
                   accept=".doc,.docx" class="hidden"
                   onchange="
                     const fn = this.files[0]?.name;
                     const lbl = document.getElementById('tpl-filename');
                     if (fn) { lbl.textContent = '✔ ' + fn; lbl.classList.remove('hidden'); }
                   ">
            @if($editing?->minutes_template)
            <p class="text-xs text-gray-500 mt-1">Modèle actuel : {{ basename($editing->minutes_template) }}. Un nouveau fichier le remplace.</p>
            @endif
            @error('minutes_template_file')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Participants internes</label>
            <select name="participants[]" id="participants-select" multiple class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm h-40">
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ in_array((string) $u->id, $selectedParticipants, true) ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Cliquez sur un nom pour l'ajouter ; cliquez à nouveau, ou sur la croix ci-dessous, pour le retirer.</p>
            <div id="participants-summary" class="mt-2 hidden">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <span id="participants-count" class="text-xs font-semibold text-gray-700"></span>
                    <button type="button" id="participants-clear" class="text-xs font-semibold text-red-600 hover:underline">Tout retirer</button>
                </div>
                <div id="participant-chips" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Participants externes</label>
            <div id="external-rows" class="space-y-2">
                @foreach($externalRows as $i => $row)
                <div class="external-row flex flex-wrap gap-2">
                    <input type="text" name="externals[{{ $i }}][full_name]" value="{{ $row['full_name'] ?? '' }}" placeholder="Nom et prénom" style="min-width: 12rem" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <input type="email" name="externals[{{ $i }}][email]" value="{{ $row['email'] ?? '' }}" placeholder="Adresse e-mail" style="min-width: 12rem" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <input type="text" name="externals[{{ $i }}][organization]" value="{{ $row['organization'] ?? '' }}" placeholder="Organisation" style="min-width: 12rem" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <button type="button" class="external-remove px-3 py-2 rounded-lg border border-gray-300 text-gray-500 text-sm hover:bg-gray-100" title="Retirer">&times;</button>
                </div>
                @endforeach
            </div>
            <button type="button" id="external-add" class="mt-2 px-3 py-1.5 rounded-lg border border-[#2453d6] text-[#2453d6] text-xs font-semibold hover:bg-blue-50">
                <i class="fas fa-user-plus mr-1"></i> Ajouter un participant externe
            </button>
        </div>

        <div class="md:col-span-2">
            <label for="reminder_hours_before" class="block text-xs font-semibold text-gray-700 mb-1">Rappel aux participants (e-mail)</label>
            <select id="reminder_hours_before" name="reminder_hours_before" class="w-full md:w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach(\App\Models\Meeting::reminderOptions() as $hours => $label)
                <option value="{{ $hours }}" {{ (int) old('reminder_hours_before', $editing ? $editing->reminderHours() : config('meetings.reminder_hours_before_meeting', 24)) === $hours ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Un seul e-mail est envoyé aux participants sélectionnés, au délai choisi avant le début de la réunion.</p>
            @error('reminder_hours_before')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Documents joints</label>
            @foreach((array) ($editing?->attachments ?? []) as $file)
            <label class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                <input type="checkbox" name="remove_attachments[]" value="{{ $file['path'] ?? '' }}"> Retirer « {{ $file['name'] ?? 'Document' }} »
            </label>
            @endforeach
            <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Diffusion automatique (email)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Objet personnalisable</label>
                    <input type="text" name="diffusion_email_subject" value="{{ $value('diffusion_email_subject') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex: Compte rendu - Réunion hebdomadaire">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Corps du message personnalisable</label>
                    <textarea name="diffusion_email_body" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Variables: {meeting_title}, {meeting_date}, {meeting_room}">{{ $value('diffusion_email_body') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="diffusion_ack_required" value="1" {{ $checked('diffusion_ack_required', (bool) $editing?->diffusion_ack_required) ? 'checked' : '' }}>
                        Demander un accusé de réception du compte rendu (lien de confirmation dans l'e-mail)
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 mr-auto">
            <input type="hidden" name="send_invitations" value="0">
            <input type="checkbox" name="send_invitations" value="1" {{ $checked('send_invitations', true) ? 'checked' : '' }}>
            {{ $editing ? 'Informer les participants par e-mail (nouvelle date, salle ou lien, ajouts et retraits)' : 'Envoyer l\'invitation par e-mail aux participants' }}
        </label>
        <a href="{{ $editing ? route('meetings.show', $editing) : route('meetings.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Annuler</a>
        <button class="px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb]">{{ $editing ? 'Enregistrer les modifications' : 'Créer la réunion' }}</button>
    </div>
</form>

<script>
(function () {
    const rows = document.getElementById('external-rows');
    let next = rows.children.length;
    document.getElementById('external-add').addEventListener('click', function () {
        const i = next++;
        const row = document.createElement('div');
        row.className = 'external-row flex flex-wrap gap-2';
        row.innerHTML = [['full_name', 'text', 'Nom et prénom'], ['email', 'email', 'Adresse e-mail'], ['organization', 'text', 'Organisation']]
            .map(([field, kind, label]) => `<input type="${kind}" name="externals[${i}][${field}]" placeholder="${label}" style="min-width: 12rem" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">`).join('')
            + '<button type="button" class="external-remove px-3 py-2 rounded-lg border border-gray-300 text-gray-500 text-sm hover:bg-gray-100" title="Retirer">&times;</button>';
        rows.appendChild(row);
        row.querySelector('input').focus();
    });
    rows.addEventListener('click', function (event) {
        if (event.target.closest('.external-remove')) event.target.closest('.external-row').remove();
    });
})();
</script>

<script>
(function () {
    // Participants internes : un clic ajoute ou retire, la liste des retenus permet d'enlever chacun.
    const select = document.getElementById('participants-select');
    const summary = document.getElementById('participants-summary');
    const chips = document.getElementById('participant-chips');
    const count = document.getElementById('participants-count');

    function render() {
        const chosen = [...select.options].filter(option => option.selected);
        summary.classList.toggle('hidden', chosen.length === 0);
        count.textContent = chosen.length + ' participant(s) sélectionné(s)';
        chips.replaceChildren(...chosen.map(option => {
            const chip = document.createElement('span');
            chip.className = 'inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold';
            const name = document.createElement('span');
            name.textContent = option.text;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.title = 'Retirer';
            remove.setAttribute('aria-label', 'Retirer ' + option.text);
            remove.addEventListener('click', () => { option.selected = false; render(); });
            chip.append(name, remove);
            return chip;
        }));
    }

    // Sans cela, un simple clic remplace toute la sélection (il faudrait maintenir Ctrl).
    select.addEventListener('mousedown', function (event) {
        if (event.target.tagName !== 'OPTION' || event.shiftKey) return;
        event.preventDefault();
        const position = select.scrollTop;
        event.target.selected = !event.target.selected;
        select.focus();
        select.scrollTop = position;
        render();
    });
    select.addEventListener('change', render);
    document.getElementById('participants-clear').addEventListener('click', function () {
        [...select.options].forEach(option => { option.selected = false; });
        render();
    });
    render();
})();
</script>

<script>
(function () {
    // La date de fin n'a de sens que pour une réunion récurrente.
    const type = document.getElementById('recurrence_type');
    const until = document.getElementById('recurrence_until');
    if (!type || !until) return;
    const sync = () => { until.disabled = type.value === 'none'; until.required = type.value !== 'none'; };
    type.addEventListener('change', sync);
    sync();
})();
</script>

<script>
(function () {
    const zone  = document.getElementById('tpl-dropzone');
    const input = document.getElementById('minutes_template_file');
    const lbl   = document.getElementById('tpl-filename');

    if (!zone || !input) return;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('border-[#2453d6]', 'bg-blue-50'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('border-[#2453d6]', 'bg-blue-50'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('border-[#2453d6]', 'bg-blue-50');
        const file = e.dataTransfer?.files?.[0];
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        lbl.textContent = '✔ ' + file.name;
        lbl.classList.remove('hidden');
    });
}());
</script>
@endsection
