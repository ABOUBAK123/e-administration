@extends('layouts.app')
@section('title', 'Demandes d\'actes')
@section('page-title', 'Demandes d\'actes')
@section('page-subtitle', 'Demandes reçues, orientées par entité sous tutelle')
@section('content')

{{-- Filtres --}}
<div class="flex flex-wrap items-center gap-3 mb-6">
    <form method="GET" action="{{ route('act-requests.index') }}" class="flex gap-2 flex-1 max-w-md">
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Rechercher une demande…"
                class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#2453d6] bg-white">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-[#2453d6] text-white rounded-xl text-sm font-semibold hover:bg-[#1f47bb] transition">
            Chercher
        </button>
    </form>

    <div class="flex gap-2">
        @foreach([''=>'Tous','pending'=>'En attente','in_progress'=>'En cours','sent'=>'Envoyée','recu'=>'Reçu','treated'=>'Terminé'] as $val => $label)
        <a href="{{ route('act-requests.index', array_merge(request()->only('q'), ['status' => $val])) }}"
            class="px-3 py-2 rounded-xl text-xs font-semibold border transition
                {{ $status === $val ? 'bg-[#2453d6] text-white border-[#2453d6]' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>
</div>

@if($requests->isEmpty())
    <div class="flex flex-col items-center justify-center py-24 text-center">
        <div class="w-20 h-20 rounded-full bg-orange-50 flex items-center justify-center mb-5">
            <i class="fas fa-clipboard-list text-4xl text-orange-300"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">Aucune demande d'acte</h3>
        <p class="text-sm text-gray-400 max-w-sm">
            Les demandes d'actes reçues depuis le portail public apparaîtront ici.
        </p>
    </div>
@else
    @php
        $computeApplicantFields = function ($req) {
            $payloadMap = is_array($req->applicant_payload) ? $req->applicant_payload : [];
            $fieldDefinitions = is_array($req->requestedAct?->applicant_fields) ? $req->requestedAct->applicant_fields : [];
            $fields = [];
            $seenKeys = [];

            foreach ($fieldDefinitions as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fieldLabel = trim((string) ($field['label'] ?? ''));
                if ($fieldLabel === '') {
                    continue;
                }

                $fieldKey = (string) \Illuminate\Support\Str::of($fieldLabel)
                    ->ascii()
                    ->lower()
                    ->replace("'", '_')
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_');

                if ($fieldKey === '' || !array_key_exists($fieldKey, $payloadMap)) {
                    continue;
                }

                $fieldValue = $payloadMap[$fieldKey];
                if (is_array($fieldValue)) {
                    $fieldValue = implode(', ', array_filter(array_map(static fn ($item) => trim((string) $item), $fieldValue), static fn ($item) => $item !== ''));
                }

                $normalizedValue = trim((string) $fieldValue);
                if ($normalizedValue === '') {
                    continue;
                }

                $fields[$fieldLabel] = $normalizedValue;
                $seenKeys[] = $fieldKey;
            }

            foreach ($payloadMap as $fieldKey => $fieldValue) {
                if ($fieldKey === '_note' || in_array($fieldKey, $seenKeys, true)) {
                    continue;
                }

                if (is_array($fieldValue)) {
                    $fieldValue = implode(', ', array_filter(array_map(static fn ($item) => trim((string) $item), $fieldValue), static fn ($item) => $item !== ''));
                }

                $normalizedValue = trim((string) $fieldValue);
                if ($normalizedValue === '') {
                    continue;
                }

                $fields[ucwords(str_replace('_', ' ', (string) $fieldKey))] = $normalizedValue;
            }

            return $fields;
        };

        // Une colonne par champ distinct rencontré sur la page courante (ordre d'apparition),
        // pour couvrir tous les types d'actes affichés (chacun avec ses propres champs).
        $applicantFieldsByRequest = [];
        $allApplicantFieldLabels = [];
        foreach ($requests as $req) {
            $fields = $computeApplicantFields($req);
            $applicantFieldsByRequest[$req->id] = $fields;
            foreach (array_keys($fields) as $label) {
                if (!in_array($label, $allApplicantFieldLabels, true)) {
                    $allApplicantFieldLabels[] = $label;
                }
            }
        }
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-clipboard-list text-orange-500"></i> Demandes d'actes
            </h2>
            <span class="text-sm text-gray-500">{{ $requests->total() }} demande(s)</span>
        </div>

        @if(!empty($allApplicantFieldLabels))
        <p class="px-5 pt-3 text-[11px] text-gray-400 flex items-center gap-1.5">
            <i class="fas fa-arrows-left-right"></i> Faites défiler le tableau horizontalement pour voir tous les champs renseignés par les demandeurs.
        </p>
        @endif

        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">Demande</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">Acte</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">Destinataire</th>
                    @foreach($allApplicantFieldLabels as $fieldLabel)
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">{{ $fieldLabel }}</th>
                    @endforeach
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">Statut</th>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600 whitespace-nowrap">Reçue le</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($requests as $req)
                @php
                    $requestApplicantFields = $applicantFieldsByRequest[$req->id] ?? [];
                @endphp
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-file-invoice text-orange-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 truncate max-w-xs">{{ $req->applicant_full_name }}</p>
                                <p class="text-xs text-gray-400">{{ $req->applicant_email ?: 'Email non renseigne' }}</p>
                                <p class="text-[11px] text-blue-700 mt-0.5">N° {{ $req->tracking_number ?: '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-600 whitespace-nowrap">
                        <p class="font-medium text-gray-700">{{ $req->requested_document_name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Code admin: {{ $req->administration?->code ?: '—' }}
                            @if($req->direction_code)
                                · {{ $req->direction_code }}
                            @endif
                        </p>
                    </td>
                    <td class="px-5 py-4 text-gray-600 whitespace-nowrap">
                        <p class="text-xs text-gray-500">Code destinataire</p>
                        <p class="font-medium text-gray-700">{{ $req->recipientAdministration?->code ?: '—' }}</p>
                    </td>
                    @foreach($allApplicantFieldLabels as $fieldLabel)
                    <td class="px-5 py-4 text-gray-700 whitespace-nowrap">
                        {{ $requestApplicantFields[$fieldLabel] ?? '—' }}
                    </td>
                    @endforeach
                    <td class="px-5 py-4">
                        @php
                            $colors = [
                                'pending'     => 'bg-yellow-100 text-yellow-700',
                                'in_progress' => 'bg-blue-100 text-blue-700',
                                'sent'        => 'bg-indigo-100 text-indigo-700',
                                'recu'        => 'bg-purple-100 text-purple-700',
                                'treated'     => 'bg-green-100 text-green-700',
                                'rejected'    => 'bg-red-100 text-red-700',
                            ];
                            $labels = [
                                'pending'     => 'En attente',
                                'in_progress' => 'En cours',
                                'sent'        => 'Envoyée',
                                'recu'        => 'Reçu',
                                'treated'     => 'Terminé',
                                'rejected'    => 'Refusé',
                            ];
                            $cls   = $colors[$req->status]   ?? 'bg-gray-100 text-gray-600';
                            $label = $labels[$req->status]   ?? ucfirst($req->status);
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $cls }} whitespace-nowrap">
                            {{ $label }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-gray-500 text-xs whitespace-nowrap">{{ $req->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        @php
                            $attachments = is_array($req->attachments) ? $req->attachments : [];
                            $attachmentCount = count($attachments);
                            $zipAlreadyDownloaded = in_array($req->status, ['in_progress', 'sent', 'recu', 'treated'], true);
                            $zipBtnClasses = $zipAlreadyDownloaded
                                ? 'bg-green-50 hover:bg-green-100 text-green-700 border-green-200'
                                : 'bg-blue-50 hover:bg-blue-100 text-blue-700 border-blue-200';
                        @endphp
                        @if($attachmentCount > 0)
                            <form method="POST" action="{{ route('act-requests.attachments.zip', $req) }}" class="inline-block">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 border text-xs font-medium rounded-lg transition {{ $zipBtnClasses }}">
                                    <i class="fas fa-file-archive text-xs"></i>
                                    @if($zipAlreadyDownloaded)
                                        ZIP deja telecharge ({{ $attachmentCount }})
                                    @else
                                        ZIP ({{ $attachmentCount }})
                                    @endif
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400">Aucune PJ</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        @if($requests->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $requests->links() }}
        </div>
        @endif
    </div>
@endif

@endsection
