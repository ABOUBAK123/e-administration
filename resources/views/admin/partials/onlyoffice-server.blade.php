{{-- Serveur OnlyOffice propre à chaque administration --}}
@php
    $ooType = $adminScope ? $adminScope['type'] : (request('oo_admin_type') === 'recipient' ? 'recipient' : 'emitter');
    $ooAdminId = (string) ($adminScope ? $adminScope['id'] : request('oo_admin_id', ''));
    $ooChoices = $ooType === 'recipient' ? $recipients : $emitters;
    $ooAdmin = $ooAdminId !== '' ? $ooChoices->firstWhere('id', $ooAdminId) : null;
    $ooConfig = $ooServerConfigs->first(fn ($c) => $c->administration_id === $ooAdminId && $c->administration_type === $ooType);
    $ooHasSecret = $ooConfig && (string) $ooConfig->secret !== '';
    $ooActive = (bool) old('is_active', $ooConfig ? $ooConfig->is_active : true);
@endphp

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4" id="oo-admin-server">
  <div>
    <h3 class="text-lg font-bold text-gray-900 mb-1">Serveur OnlyOffice par administration</h3>
    <p class="text-sm text-gray-500">
      Pour répartir la charge, chaque administration peut avoir son propre serveur. Ses documents, modèles et réunions
      s'ouvrent alors sur ce serveur ; sans configuration active, le serveur global est utilisé.
    </p>
  </div>

  @if(session('oo_success'))
  <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">{{ session('oo_success') }}</div>
  @endif
  @if($errors->has('server_url') || $errors->has('secret'))
  <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm">
    {{ $errors->first('server_url') ?: $errors->first('secret') }}
  </div>
  @endif

  @if($adminScope)
  <p class="text-sm text-gray-700">Administration : <strong>{{ $ooAdmin?->name ?? $ooAdminId }}</strong></p>
  @else
  <form method="GET" action="{{ route('admin.index') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <input type="hidden" name="tab" value="onlyoffice">
    <select name="oo_admin_type" onchange="this.form.oo_admin_id.value=''; this.form.submit()"
      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
      <option value="emitter" @selected($ooType === 'emitter')>Administration émettrice</option>
      <option value="recipient" @selected($ooType === 'recipient')>Administration destinataire</option>
    </select>
    <select name="oo_admin_id" onchange="this.form.submit()"
      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
      <option value="">Sélectionner une administration</option>
      @foreach($ooChoices as $choice)
      <option value="{{ $choice->id }}" @selected($ooAdminId === (string) $choice->id)>{{ $choice->name }}</option>
      @endforeach
    </select>
  </form>
  @endif

  @if($ooAdmin)
  <form method="POST" action="{{ route('admin.onlyoffice-server.save') }}" id="oo-admin-form" class="space-y-4">
    @csrf
    <input type="hidden" name="oo_admin_type" value="{{ $ooType }}">
    <input type="hidden" name="oo_admin_id" value="{{ $ooAdminId }}">

    <p class="text-xs font-semibold {{ $ooConfig && $ooConfig->is_active ? 'text-emerald-700' : 'text-gray-500' }}">
      {{ $ooConfig && $ooConfig->is_active ? 'Cette administration utilise son propre serveur.' : 'Cette administration utilise le serveur global.' }}
    </p>

    <label class="flex items-center gap-2 text-sm text-gray-700">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" @checked($ooActive) class="h-4 w-4 rounded border-gray-300">
      Utiliser ce serveur pour cette administration
    </label>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Adresse du ONLYOFFICE Docs</label>
      <input type="url" name="server_url" required value="{{ old('server_url', $ooConfig?->server_url) }}"
        placeholder="https://<documentserver>"
        class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Clé secrète (JWT)</label>
      <input type="password" name="secret" autocomplete="new-password" @required(!$ooHasSecret)
        placeholder="{{ $ooHasSecret ? 'Clé enregistrée — laisser vide pour la conserver' : 'Saisir la clé secrète du serveur' }}"
        class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6]">
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
      <input type="hidden" name="disable_cert" value="0">
      <input type="checkbox" name="disable_cert" value="1" @checked(old('disable_cert', $ooConfig?->disable_cert)) class="h-4 w-4 rounded border-gray-300">
      Désactiver la vérification du certificat <span class="text-gray-400 text-xs">(non sûr)</span>
    </label>

    <div class="flex flex-wrap items-center gap-3">
      <button type="submit" class="px-5 py-2.5 bg-[#2453d6] hover:bg-[#1f47bb] text-white text-sm font-semibold rounded-lg">Enregistrer ce serveur</button>
      <button type="button" id="oo-admin-test" class="px-4 py-2.5 bg-blue-50 border border-blue-200 text-blue-700 text-sm font-semibold rounded-lg hover:bg-blue-100">Tester la connexion</button>
      @if($ooConfig)
      <button type="submit" form="oo-admin-delete" class="px-4 py-2.5 text-red-600 text-sm font-semibold rounded-lg hover:bg-red-50">Supprimer</button>
      @endif
      <span id="oo-admin-test-result" class="text-sm font-semibold"></span>
    </div>
  </form>

  @if($ooConfig)
  <form method="POST" action="{{ route('admin.onlyoffice-server.delete') }}" id="oo-admin-delete"
    onsubmit="return confirm('Supprimer ce serveur ? Cette administration utilisera de nouveau le serveur global.')">
    @csrf @method('DELETE')
    <input type="hidden" name="oo_admin_type" value="{{ $ooType }}">
    <input type="hidden" name="oo_admin_id" value="{{ $ooAdminId }}">
  </form>
  @endif

  <script>
  document.getElementById('oo-admin-test').addEventListener('click', async function () {
    const form = document.getElementById('oo-admin-form');
    const result = document.getElementById('oo-admin-test-result');
    this.disabled = true;
    result.style.color = '#6b7280';
    result.textContent = 'Test en cours…';
    try {
      const response = await fetch(@json(route('admin.onlyoffice-server.test')), {
        method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' },
      });
      const data = await response.json();
      result.style.color = data.ok ? '#047857' : '#dc2626';
      result.textContent = data.message || 'Test impossible.';
    } catch (error) {
      result.style.color = '#dc2626';
      result.textContent = 'Test impossible : ' + error.message;
    }
    this.disabled = false;
  });
  </script>
  @endif

  @if(!$adminScope && $ooServerConfigs->isNotEmpty())
  <div class="border-t border-gray-100 pt-4">
    <p class="text-sm font-semibold text-gray-700 mb-2">Administrations avec un serveur configuré ({{ $ooServerConfigs->count() }})</p>
    <ul class="space-y-2 text-sm">
      @foreach($ooServerConfigs as $row)
      <li class="flex flex-wrap items-center gap-2">
        <a class="font-semibold text-[#2453d6] hover:underline"
          href="{{ route('admin.index', ['tab' => 'onlyoffice', 'oo_admin_type' => $row->administration_type, 'oo_admin_id' => $row->administration_id]) }}">
          {{ ($row->administration_type === 'recipient' ? $recipients : $emitters)->firstWhere('id', $row->administration_id)?->name ?? $row->administration_id }}
        </a>
        <span class="text-gray-500" style="word-break:break-all;">{{ $row->server_url }}</span>
        @unless($row->is_active)<span class="text-xs text-gray-400">(désactivé)</span>@endunless
      </li>
      @endforeach
    </ul>
  </div>
  @endif
</div>
