@extends('layouts.app')
@section('title', 'Calendrier des réunions')
@section('page-title', 'Calendrier')
@section('page-subtitle', 'Vue mensuelle par salle et par utilisateur')

@section('content')
@include('meetings._nav')

@php
    $workflowColors = [
        'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
        'in_validation' => 'bg-amber-100 text-amber-800 border-amber-200',
        'validated' => 'bg-blue-100 text-blue-800 border-blue-200',
        'published' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    ];
    $monthLabel = \Illuminate\Support\Str::ucfirst($monthStart->translatedFormat('F Y'));
@endphp

<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <div class="flex items-center gap-2">
        <a href="{{ route('meetings.calendar', ['year' => $prevMonth->year, 'month' => $prevMonth->month, 'room_id' => $roomId, 'mine' => $mineOnly ? 1 : 0]) }}"
           class="px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50">&larr;</a>
        <div class="px-3 py-2 rounded-lg text-sm font-bold text-gray-800 bg-gray-50 border border-gray-100 min-w-[10rem] text-center">
            {{ $monthLabel }}
        </div>
        <a href="{{ route('meetings.calendar', ['year' => $nextMonth->year, 'month' => $nextMonth->month, 'room_id' => $roomId, 'mine' => $mineOnly ? 1 : 0]) }}"
           class="px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50">&rarr;</a>
        <a href="{{ route('meetings.calendar') }}"
           class="px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-600 hover:bg-gray-50">Aujourd'hui</a>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <select name="room_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
                <option value="">Toutes les salles</option>
                @foreach($rooms as $room)
                    <option value="{{ $room->id }}" {{ $roomId === (string) $room->id ? 'selected' : '' }}>{{ $room->name }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1.5 text-sm text-gray-600 px-2">
                <input type="checkbox" name="mine" value="1" onchange="this.form.submit()" {{ $mineOnly ? 'checked' : '' }}
                       class="rounded border-gray-300 text-[#2453d6] focus:ring-[#2453d6]">
                Mes réunions
            </label>
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
        </form>
        <a href="{{ route('meetings.create', ['date' => now()->format('Y-m-d'), 'room_id' => $roomId]) }}"
           class="px-3 py-2 rounded-lg text-sm font-semibold bg-[#2453d6] text-white hover:bg-[#1f47bb] flex items-center gap-1.5">
            <i class="fas fa-plus"></i> Nouvelle réunion
        </a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
        @foreach(['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $dayLabel)
            <div class="px-2 py-2 text-xs font-semibold text-gray-600 text-center">{{ $dayLabel }}</div>
        @endforeach
    </div>
    <div class="grid grid-cols-7">
        @foreach($days as $day)
            @php
                $isWeekend = $day['date']->isWeekend();
            @endphp
            <div class="group relative min-h-[110px] border-b border-r border-gray-100 p-1.5 align-top
                        {{ $day['inMonth'] ? '' : 'bg-gray-50/60' }} {{ $isWeekend ? 'bg-gray-50/30' : '' }}">
                <a href="{{ route('meetings.create', ['date' => $day['date']->format('Y-m-d'), 'room_id' => $roomId]) }}"
                   title="Créer une réunion le {{ $day['date']->format('d/m/Y') }}"
                   class="absolute top-1 left-1 w-5 h-5 rounded-full bg-[#2453d6] text-white text-xs leading-5 text-center
                          opacity-0 group-hover:opacity-100 hover:bg-[#1f47bb] transition-opacity z-10">+</a>
                <div class="flex justify-end mb-1">
                    <span class="text-xs font-semibold w-6 h-6 flex items-center justify-center rounded-full
                                 {{ $day['isToday'] ? 'bg-[#2453d6] text-white' : ($day['inMonth'] ? 'text-gray-700' : 'text-gray-300') }}">
                        {{ $day['date']->day }}
                    </span>
                </div>
                <div class="space-y-1">
                    @foreach($day['meetings']->take(3) as $meeting)
                        @php
                            $colorClass = $workflowColors[$meeting->workflow_status ?? 'draft'] ?? $workflowColors['draft'];
                        @endphp
                        <a href="{{ route('meetings.show', $meeting) }}"
                           title="{{ $meeting->title }} - {{ $meeting->room?->name ?: 'Sans salle' }} - {{ $meeting->organizer?->name }}"
                           class="block text-[11px] leading-tight px-1.5 py-1 rounded border {{ $colorClass }} truncate hover:opacity-80">
                            <span class="font-semibold">{{ $meeting->starts_at->format('H:i') }}</span>
                            @if($meeting->is_virtual)<span title="Visioconférence">🔗</span>@endif
                            {{ $meeting->title }}
                        </a>
                    @endforeach
                    @if($day['meetings']->count() > 3)
                        <div class="text-[11px] text-gray-400 px-1.5">+{{ $day['meetings']->count() - 3 }} autre(s)</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="flex flex-wrap gap-3 mt-4 text-xs text-gray-600">
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gray-100 border border-gray-200 inline-block"></span> Brouillon</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-100 border border-amber-200 inline-block"></span> En validation</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-100 border border-blue-200 inline-block"></span> Validé</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200 inline-block"></span> Publié</span>
</div>
@endsection
