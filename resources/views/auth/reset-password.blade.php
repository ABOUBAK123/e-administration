@extends('layouts.auth')
@section('title', __('auth.reset_password'))
@section('content')
<div class="mb-2">
    <a href="{{ route('login') }}" class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-indigo-600 transition">
        <i class="fa-solid fa-arrow-left text-xs"></i> {{ __('buttons.back') }}
    </a>
</div>
<h2 class="text-xl font-bold text-gray-800 mb-2">{{ __('auth.reset_password') }}</h2>
<p class="text-sm text-gray-500 mb-6">{{ __('auth.choose_new_password') }}</p>

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 text-red-600 rounded-lg p-3 text-sm">
    {{ $errors->first() }}
</div>
@endif

<form method="POST" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.email') }}</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus
               placeholder="your@email.com"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.new_password') }}</label>
        <input type="password" name="password" required minlength="8"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.confirm_password') }}</label>
        <input type="password" name="password_confirmation" required minlength="8"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
    <button type="submit"
            class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-medium hover:bg-indigo-700 transition">
        {{ __('auth.reset_password_button') }}
    </button>
</form>
@endsection
