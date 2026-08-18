@extends('layouts.app')

@section('title', 'Nouveau modèle d’acte')
@section('page-title', 'Nouveau modèle d’acte')
@section('page-subtitle', 'Définir un modèle de document et ses variables')

@section('content')
    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <form action="{{ route('act-templates.store') }}" method="POST" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nom du modèle</label>
                    <input id="name" name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100" required>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="file_type" class="mb-1 block text-sm font-medium text-gray-700">Type de fichier</label>
                    <select id="file_type" name="file_type" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100" required>
                        @foreach(['docx','xlsx','pptx','pdf'] as $type)
                            <option value="{{ $type }}" {{ old('file_type', 'docx') === $type ? 'selected' : '' }}>{{ strtoupper($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="administration_id" class="mb-1 block text-sm font-medium text-gray-700">Administration</label>
                    <select id="administration_id" name="administration_id" class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">— Non spécifique —</option>
                        @foreach($administrations as $administration)
                            <option value="{{ $administration->id }}" {{ old('administration_id') == $administration->id ? 'selected' : '' }}>{{ $administration->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="content" class="mb-1 block text-sm font-medium text-gray-700">Contenu du modèle</label>
                    <textarea id="content" name="content" rows="12" placeholder="Exemple : Bonjour @{{ nom }}, ..." class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('content') }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Utilisez le format <code>{{ '{' }}{ variable }}</code> pour déclarer les champs à remplir.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('act-templates.index') }}" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="rounded-xl bg-[#173b9f] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
