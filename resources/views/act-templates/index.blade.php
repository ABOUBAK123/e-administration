@extends('layouts.app')

@section('title', 'Modèles d’actes')
@section('page-title', 'Modèles d’actes')
@section('page-subtitle', 'Gérer les modèles de génération et les variables associées')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Modèles disponibles</h2>
                <p class="text-sm text-gray-500">Créer, modifier et générer les actes selon les variables définies.</p>
            </div>
            <a href="{{ route('act-templates.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#173b9f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:opacity-95">
                <i class="fas fa-plus"></i>
                Nouveau modèle
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if($templates->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-2xl text-blue-600">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Aucun modèle d’acte</h3>
                <p class="mt-2 text-sm text-gray-500">Créez un premier modèle pour activer la génération automatique d’actes.</p>
            </div>
        @else
            <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($templates as $template)
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                                    <i class="fas fa-file-word"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-gray-800">{{ $template->name }}</h3>
                                    <p class="text-xs text-gray-500 uppercase">{{ $template->file_type }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4 text-sm text-gray-600">
                            <p><strong>Administration :</strong> {{ $template->administration?->name ?? '—' }}</p>
                            <p><strong>Variables :</strong> {{ $template->variables->count() }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('act-templates.show', $template) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                            <a href="{{ route('act-templates.edit', $template) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-pen"></i> Modifier
                            </a>
                            <a href="{{ route('act-templates.generate-form', $template) }}" class="inline-flex items-center gap-2 rounded-lg bg-[#173b9f] px-3 py-2 text-xs font-semibold text-white hover:opacity-95">
                                <i class="fas fa-wand-magic-sparkles"></i> Générer
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
