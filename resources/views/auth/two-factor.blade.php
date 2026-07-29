@extends('layouts.auth')
@section('title', 'Vérification en deux étapes')
@section('content')
<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">Vérification en deux étapes</h2>
    <p class="text-sm text-gray-500 mt-2">
        Un code de vérification à 6 chiffres a été envoyé
        @if(session('2fa:channel') === 'whatsapp')
            sur votre <span class="font-semibold text-green-600"><i class="fab fa-whatsapp"></i> WhatsApp</span>.
        @else
            à votre <span class="font-semibold text-indigo-600">adresse email</span>.
        @endif
        Il expire dans 10 minutes.
    </p>
</div>

@if(session('success'))
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 text-sm">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 text-red-600 rounded-lg p-3 text-sm">
    {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('2fa.verify') }}" class="space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Code de vérification</label>
        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
               autocomplete="one-time-code" placeholder="______"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-center text-2xl tracking-[0.5em] font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <button type="submit"
            class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-medium hover:bg-indigo-700 transition">
        Vérifier
    </button>
</form>

<form method="POST" action="{{ route('2fa.resend') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm text-indigo-600 hover:underline">
        Renvoyer le code
    </button>
</form>

<div class="mt-4 text-center">
    <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:underline">Retour à la connexion</a>
</div>
@endsection
