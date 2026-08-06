@extends('layouts.app')
@section('title', 'Nouvelle réunion')
@section('page-title', 'Nouvelle réunion')
@section('page-subtitle', 'Planifier une réunion')

@section('content')
@include('meetings._nav')
<form method="POST" action="{{ route('meetings.store') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Titre</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Type</label>
            <select name="meeting_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="ordinary">Réunion ordinaire</option>
                <option value="extraordinary">Réunion extraordinaire</option>
                <option value="management_committee">Comité de direction</option>
                <option value="project">Réunion de projet</option>
                <option value="technical">Réunion technique</option>
                <option value="other">Autre</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Salle</label>
            <select name="meeting_room_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($rooms as $room)
                <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->location }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Début</label>
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Fin</label>
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Délai fixé (traitement)</label>
            <input type="datetime-local" name="processing_deadline" value="{{ old('processing_deadline') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Rédacteur du compte rendu</label>
            <select name="minutes_writer_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Validateur du compte rendu</label>
            <select name="validator_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sélectionner</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" {{ old('validator_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            @error('validator_id')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Priorité</label>
            <select name="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="low">Faible</option>
                <option value="normal" selected>Normale</option>
                <option value="high">Élevée</option>
                <option value="urgent">Urgente</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Confidentialité</label>
            <select name="confidentiality" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="public">Publique</option>
                <option value="internal" selected>Interne</option>
                <option value="confidential">Confidentielle</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Récurrence</label>
            <select name="recurrence_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="none">Aucune</option>
                <option value="daily">Quotidienne</option>
                <option value="weekly">Hebdomadaire</option>
                <option value="monthly">Mensuelle</option>
                <option value="yearly">Annuelle</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Fin de récurrence</label>
            <input type="date" name="recurrence_until" value="{{ old('recurrence_until') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2 border border-gray-100 rounded-lg p-3 bg-gray-50/50">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-2">
                <input type="checkbox" id="is_virtual" name="is_virtual" value="1" {{ old('is_virtual') ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#2453d6] focus:ring-[#2453d6]"
                       onchange="document.getElementById('meeting_link_wrap').classList.toggle('hidden', !this.checked)">
                Réunion virtuelle / hybride (visioconférence)
            </label>
            <div id="meeting_link_wrap" class="{{ old('is_virtual') ? '' : 'hidden' }}">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Lien de visioconférence (Teams, Zoom, Meet...)</label>
                <input type="url" name="meeting_link" value="{{ old('meeting_link') }}" placeholder="https://teams.microsoft.com/..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @error('meeting_link')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Ordre du jour</label>
            <textarea name="agenda" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('agenda') }}</textarea>
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
            @error('minutes_template_file')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Participants internes</label>
            <select name="participants[]" multiple class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm h-40">
                @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Maintenez Ctrl (ou Cmd) pour sélectionner plusieurs utilisateurs.</p>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Documents joints</label>
            <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-2 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-bold text-gray-800 mb-3">Diffusion automatique (email)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Objet personnalisable</label>
                    <input type="text" name="diffusion_email_subject" value="{{ old('diffusion_email_subject') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex: Compte rendu - Réunion hebdomadaire">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Corps du message personnalisable</label>
                    <textarea name="diffusion_email_body" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Variables: {meeting_title}, {meeting_date}, {meeting_room}">{{ old('diffusion_email_body') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="diffusion_ack_required" value="1" {{ old('diffusion_ack_required') ? 'checked' : '' }}>
                        Accusé de réception optionnel
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('meetings.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">Annuler</a>
        <button class="px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold hover:bg-[#1f47bb]">Créer la réunion</button>
    </div>
</form>

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
