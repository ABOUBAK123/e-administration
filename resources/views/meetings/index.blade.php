@extends('layouts.app')
@section('title', 'Réunions')
@section('page-title', 'Réunions')
@section('page-subtitle', 'Planification et suivi des réunions')

@section('content')
@include('meetings._nav')
<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Rechercher une réunion..."
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
        <button class="px-3 py-2 rounded-lg text-sm font-semibold bg-[#2453d6] text-white hover:bg-[#1f47bb]">Chercher</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Titre</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Salle</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Date</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Organisateur</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Statut</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Validation</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($meetings as $meeting)
            @php
                $statusLabels = [
                    'planned' => 'Planifiée',
                    'in_progress' => 'En cours',
                    'closed' => 'Terminée',
                    'cancelled' => 'Annulée',
                ];
                $workflowLabels = [
                    'draft' => 'Brouillon',
                ];
            @endphp
            <tr class="border-b border-gray-100">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $meeting->title }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $meeting->room?->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $meeting->starts_at?->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $meeting->organizer?->name }}</td>
                <td class="px-4 py-3 {{ $meeting->status === 'cancelled' ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $statusLabels[$meeting->status] ?? $meeting->status }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $workflowLabels[$meeting->workflow_status ?? 'draft'] ?? ($meeting->workflow_status ?? 'draft') }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('meetings.show', $meeting) }}" class="text-[#2453d6] font-semibold hover:underline">Ouvrir</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-10 text-center text-gray-400">Aucune réunion trouvée.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($meetings->hasPages())
<div class="mt-4">{{ $meetings->links() }}</div>
@endif
@endsection
