@extends('layouts.app')

@section('title', 'Détail du modèle')
@section('page-title', 'Détail du modèle')
@section('page-subtitle', $actTemplate->name)

@section('content')
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-500">Modèle</p>
                    <h2 class="mt-1 text-2xl font-bold text-gray-800">{{ $actTemplate->name }}</h2>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('act-templates.edit', $actTemplate) }}" class="rounded-xl border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Modifier</a>
                    <a href="{{ route('act-templates.generate-form', $actTemplate) }}" class="rounded-xl bg-[#173b9f] px-3 py-2 text-sm font-semibold text-white hover:opacity-95">Générer</a>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Type</p>
                    <p class="mt-2 text-sm font-semibold text-gray-800">{{ strtoupper($actTemplate->file_type) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Administration</p>
                    <p class="mt-2 text-sm font-semibold text-gray-800">{{ $actTemplate->administration?->name ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Variables</p>
                    <p class="mt-2 text-sm font-semibold text-gray-800">{{ $actTemplate->variables->count() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Contenu du modèle</h3>
            <pre class="whitespace-pre-wrap rounded-xl bg-gray-50 p-4 text-sm text-gray-700">{{ $actTemplate->content ?? 'Aucun contenu défini.' }}</pre>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Variables détectées</h3>

            @if($actTemplate->variables->isEmpty())
                <p class="text-sm text-gray-500">Aucune variable n’a été détectée pour ce modèle.</p>
            @else
                <div class="space-y-3">
                    @foreach($actTemplate->variables as $variable)
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $variable->label ?: $variable->key }}</p>
                                <p class="text-xs text-gray-500">{{ $variable->key }}</p>
                            </div>
                            <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-blue-700">
                                {{ $variable->field_type ?? 'text' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
