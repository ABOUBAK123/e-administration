@extends('layouts.app')

@section('title', 'Génération d’acte')
@section('page-title', 'Génération d’acte')
@section('page-subtitle', $template->name)

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Variables du modèle</h3>

            <form action="{{ route('act-templates.generate', $template) }}" method="POST" class="space-y-4">
                @csrf

                @foreach($variables as $variable)
                    <div>
                        <label for="values_{{ $variable->key }}" class="mb-1 block text-sm font-medium text-gray-700">
                            {{ $variable->label ?: $variable->key }}
                            @if((bool) $variable->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <input
                            id="values_{{ $variable->key }}"
                            name="values[{{ $variable->key }}]"
                            value="{{ old('values.' . $variable->key, $submittedValues[$variable->key] ?? '') }}"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-[#173b9f] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            placeholder="{{ $variable->placeholder ?: $variable->label ?: $variable->key }}"
                            @if((bool) $variable->required) required @endif
                        >
                    </div>
                @endforeach

                @if($variables->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-500">
                        Ce modèle ne contient pas encore de variables. Ajoutez des champs dans le contenu du modèle avec la syntaxe <code>{{ '{' }}{ variable }</code>.
                    </div>
                @endif

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-xl bg-[#173b9f] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
                        Générer le contenu
                    </button>
                    <a href="{{ route('act-templates.index') }}" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Retour
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Aperçu du document</h3>
            <div class="min-h-[320px] rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-7 text-gray-700 whitespace-pre-wrap">
                @if($rendered !== null)
                    {{ $rendered }}
                @else
                    Le contenu généré s’affichera ici après validation des champs.
                @endif
            </div>
        </div>
    </div>
@endsection
