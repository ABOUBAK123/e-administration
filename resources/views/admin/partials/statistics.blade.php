@php
    $statsData = $statistics ?? ['administrations' => [], 'totals' => ['signed' => 0, 'arrive' => 0, 'depart' => 0]];
    $statsOptions = $statsData['options'] ?? [];
    // Une seule administration autorisée : elle est présélectionnée.
    $statsAdmin = count($statsOptions) === 1 ? $statsOptions[0]['id'] : (string) request()->query('stats_admin', '');
    $statsEntity = (string) request()->query('stats_entity', '');
    $statsEntities = collect($statsOptions)->firstWhere('id', $statsAdmin)['entities'] ?? [];
@endphp

{{-- Formulaire de recherche --}}
<form method="GET" action="{{ route('admin.index') }}" class="mb-5 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <input type="hidden" name="tab" value="statistics">
    <h3 class="text-sm font-bold text-gray-800 mb-3"><i class="fas fa-magnifying-glass text-[#2453d6] mr-2"></i>Rechercher des statistiques</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="text-xs font-semibold text-gray-600">Administration émettrice
            <select name="stats_admin" id="stats-admin" class="block w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                @if(count($statsOptions) !== 1)
                    <option value="">Toutes les administrations</option>
                @endif
                @foreach($statsOptions as $option)
                    <option value="{{ $option['id'] }}" @selected($statsAdmin === $option['id'])>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-600">Entité sous tutelle
            <select name="stats_entity" id="stats-entity" @disabled($statsAdmin === '')
                    class="block w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100 disabled:text-gray-400">
                <option value="">Toutes les entités</option>
                @foreach($statsEntities as $entity)
                    <option value="{{ $entity['code'] }}" @selected($statsEntity === $entity['code'])>{{ $entity['name'] }} ({{ $entity['code'] }})</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-600">Du
            <input type="date" name="stats_from" value="{{ request()->query('stats_from') }}" class="block w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </label>
        <label class="text-xs font-semibold text-gray-600">Au
            <input type="date" name="stats_to" value="{{ request()->query('stats_to') }}" class="block w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </label>
    </div>
    <div class="flex flex-wrap justify-end gap-2 mt-4">
        <a href="{{ route('admin.index', ['tab' => 'statistics']) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-600">Réinitialiser</a>
        <button type="submit" class="px-5 py-2 rounded-lg bg-[#2453d6] text-white text-sm font-semibold"><i class="fas fa-search mr-1"></i> Rechercher</button>
    </div>
</form>

<script>
(function () {
    const options = @json($statsOptions);
    const admin = document.getElementById('stats-admin');
    const entity = document.getElementById('stats-entity');
    if (!admin || !entity) return;

    // La liste des entités suit l'administration choisie.
    admin.addEventListener('change', function () {
        const selected = options.find(o => o.id === admin.value);
        entity.innerHTML = '';
        entity.add(new Option('Toutes les entités', ''));
        (selected ? selected.entities : []).forEach(e => entity.add(new Option(e.name + ' (' + e.code + ')', e.code)));
        entity.disabled = !selected;
    });
})();
</script>

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
