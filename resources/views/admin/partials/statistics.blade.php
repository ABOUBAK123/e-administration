@php
    $statsData = $statistics ?? ['administrations' => [], 'totals' => ['signed' => 0, 'arrive' => 0, 'depart' => 0]];
@endphp

{{-- Filtre par période --}}
<form method="GET" action="{{ route('admin.index') }}" class="mb-5 bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex flex-wrap items-end gap-3">
    <input type="hidden" name="tab" value="statistics">
    <label class="text-xs font-semibold text-gray-600">Du
        <input type="date" name="stats_from" value="{{ request('stats_from') }}" class="block mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </label>
    <label class="text-xs font-semibold text-gray-600">Au
        <input type="date" name="stats_to" value="{{ request('stats_to') }}" class="block mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </label>
    <button type="submit" class="px-4 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold">Filtrer</button>
    @if(request('stats_from') || request('stats_to'))
        <a href="{{ route('admin.index', ['tab' => 'statistics']) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-600">Réinitialiser</a>
    @endif
</form>

{{-- Totaux --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
    @foreach([
        ['fas fa-file-signature', $statsData['totals']['signed'], 'Documents signés', '#16a34a'],
        ['fas fa-inbox',          $statsData['totals']['arrive'], 'Courriers arrivés', '#2563eb'],
        ['fas fa-paper-plane',    $statsData['totals']['depart'], 'Courriers départ',  '#ea580c'],
    ] as [$icon, $count, $label, $color])
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <i class="{{ $icon }} text-xl" style="color:{{ $color }}"></i>
            <span class="text-sm font-semibold text-gray-600">{{ $label }}</span>
        </div>
        <span class="text-2xl font-black text-gray-800">{{ number_format($count, 0, ',', ' ') }}</span>
    </div>
    @endforeach
</div>

{{-- Détail par administration émettrice et entité sous tutelle --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-x-auto">
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Administration émettrice / Entité sous tutelle</th>
                <th class="px-4 py-3 text-right">Documents signés</th>
                <th class="px-4 py-3 text-right">Courriers arrivés</th>
                <th class="px-4 py-3 text-right">Courriers départ</th>
            </tr>
        </thead>
        @forelse($statsData['administrations'] as $administration)
        <tbody class="border-t border-gray-200">
            <tr class="bg-blue-50/60 font-bold text-gray-800">
                <td class="px-4 py-3"><i class="fas fa-building text-blue-500 mr-2"></i>{{ $administration['name'] }}</td>
                <td class="px-4 py-3 text-right">{{ number_format($administration['signed'], 0, ',', ' ') }}</td>
                <td class="px-4 py-3 text-right">{{ number_format($administration['arrive'], 0, ',', ' ') }}</td>
                <td class="px-4 py-3 text-right">{{ number_format($administration['depart'], 0, ',', ' ') }}</td>
            </tr>
            @foreach($administration['entities'] as $entity)
            <tr class="border-t border-gray-100 text-gray-600">
                <td class="px-4 py-2.5 pl-10">
                    {{ $entity['name'] }}
                    @if($entity['code'])<span class="ml-1 text-xs text-gray-400">({{ $entity['code'] }})</span>@endif
                </td>
                <td class="px-4 py-2.5 text-right">{{ number_format($entity['signed'], 0, ',', ' ') }}</td>
                <td class="px-4 py-2.5 text-right">{{ number_format($entity['arrive'], 0, ',', ' ') }}</td>
                <td class="px-4 py-2.5 text-right">{{ number_format($entity['depart'], 0, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400 italic">Aucune administration à afficher.</td></tr>
        </tbody>
        @endforelse
    </table>
</div>
