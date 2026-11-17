<?php

namespace App\Http\Controllers;

use App\Models\ActRequestSubmission;
use App\Models\AppSetting;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\IssuingAdministration;
use App\Models\MobileMoneyProviderConfig;
use App\Models\MobileMoneyTransaction;
use App\Models\RecipientAdministration;
use App\Models\RequestedAct;
use App\Models\SubEntity;
use App\Models\User;
use App\Services\Payments\MobileMoneyGatewayFactory;
use App\Services\Templates\TemplateGenerationCoreService;
use App\Services\ClamAvScanner;
use App\Services\NniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicActRequestController extends Controller
{
    private function generateTrackingNumber(): string
    {
        $prefix = 'DACT-' . now()->format('Ym') . '-';

        for ($i = 0; $i < 25; $i++) {
            $candidate = $prefix . random_int(100000, 999999);
            $exists = ActRequestSubmission::query()
                ->where('tracking_number', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return $prefix . strtoupper(Str::random(8));
    }

    private function generateTrackingToken(): string
    {
        for ($i = 0; $i < 25; $i++) {
            $candidate = strtolower(Str::random(48));
            $exists = ActRequestSubmission::query()
                ->where('tracking_token', $candidate)
                ->exists();

            if (!$exists) {
                return $candidate;
            }
        }

        return strtolower((string) Str::uuid());
    }

    private function buildDocKey(string $label): string
    {
        return (string) Str::of($label)
            ->ascii()
            ->lower()
            ->replace("'", '_')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function buildApplicantFieldOptions(array $field): array
    {
        $options = $field['options'] ?? $field['values'] ?? [];
        if (!is_array($options)) {
            $options = preg_split('/\r\n|\n|,/', (string) $options) ?: [];
        }

        $cleanOptions = [];
        foreach ($options as $option) {
            $value = trim((string) $option);
            if ($value !== '') {
                $cleanOptions[] = $value;
            }
        }

        return array_values(array_unique($cleanOptions));
    }

    private function buildApplicantFieldKey(array $field): string
    {
        $label = trim((string) ($field['label'] ?? ''));
        if ($label === '') {
            return '';
        }

        return (string) Str::of($label)
            ->ascii()
            ->lower()
            ->replace("'", '_')
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function normalizeUniqueKeyValue(?string $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $ascii = Str::of($raw)->ascii()->lower()->toString();
        $normalized = preg_replace('/[^a-z0-9]+/', '', $ascii) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveUniqueKeyValue(RequestedAct $requestedAct, Request $request, array $extraPayload): ?string
    {
        $configuredField = trim((string) ($requestedAct->unique_key_field ?? ''));
        if ($configuredField === '') {
            return null;
        }

        $configuredSlug = $this->buildDocKey($configuredField);

        $candidates = [
            $configuredField,
            $configuredSlug,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $extraValue = $extraPayload[$candidate] ?? null;
            if (is_string($extraValue) && trim($extraValue) !== '') {
                return $this->normalizeUniqueKeyValue($extraValue);
            }

            $requestValue = $request->input($candidate);
            if (is_string($requestValue) && trim($requestValue) !== '') {
                return $this->normalizeUniqueKeyValue($requestValue);
            }
        }

        $basePayload = [
            'applicant_full_name' => (string) $request->input('applicant_full_name', ''),
            'applicant_email' => (string) $request->input('applicant_email', ''),
            'applicant_phone' => (string) $request->input('applicant_phone', ''),
        ];

        if (array_key_exists($configuredSlug, $basePayload)) {
            return $this->normalizeUniqueKeyValue((string) $basePayload[$configuredSlug]);
        }

        return null;
    }

    private function resolveDirectionCode(RequestedAct $requestedAct, string $administrationId, ?string $recipientAdministrationId): ?string
    {
        $candidates = [];

        $actDirectionCode = trim((string) ($requestedAct->direction_code ?? ''));
        if ($actDirectionCode !== '') {
            $candidates[] = $actDirectionCode;
        }

        $directionMapRaw = AppSetting::query()->where('key', 'act_request_direction_map')->value('value');
        $directionMap = [];
        if (is_string($directionMapRaw) && trim($directionMapRaw) !== '') {
            try {
                $directionMap = json_decode($directionMapRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $directionMap = [];
            }
        }

        if (!empty($directionMap)) {
            $actMap = (array) ($directionMap['requested_act'] ?? []);
            $recipientMap = (array) ($directionMap['recipient'] ?? []);
            $recipientByEmitterMap = (array) ($directionMap['recipient_by_emitter'] ?? []);

            $mappedByAct = trim((string) ($actMap[(string) $requestedAct->id] ?? ''));
            if ($mappedByAct !== '') {
                $candidates[] = $mappedByAct;
            }

            if ($recipientAdministrationId !== null && $recipientAdministrationId !== '') {
                $mappedByRecipient = trim((string) ($recipientMap[$recipientAdministrationId] ?? ''));
                if ($mappedByRecipient !== '') {
                    $candidates[] = $mappedByRecipient;
                }

                $mappedByEmitterAndRecipient = trim((string) (($recipientByEmitterMap[$administrationId] ?? [])[$recipientAdministrationId] ?? ''));
                if ($mappedByEmitterAndRecipient !== '') {
                    $candidates[] = $mappedByEmitterAndRecipient;
                }
            }
        }

        if ($recipientAdministrationId !== null && $recipientAdministrationId !== '') {
            $recipient = RecipientAdministration::query()->find($recipientAdministrationId);
            if ($recipient) {
                $metadata = is_array($recipient->metadata) ? $recipient->metadata : [];

                $directMetadataKeys = [
                    'direction_code',
                    'sub_entity_code',
                    'default_direction_code',
                    'default_sub_entity_code',
                ];

                foreach ($directMetadataKeys as $key) {
                    $value = trim((string) ($metadata[$key] ?? ''));
                    if ($value !== '') {
                        $candidates[] = $value;
                    }
                }

                $metaByEmitter = (array) (($metadata['direction_by_emitter'] ?? $metadata['direction_map'] ?? []));
                $emitterMapping = $metaByEmitter[$administrationId] ?? null;

                if (is_array($emitterMapping)) {
                    $mapped = trim((string) ($emitterMapping['direction_code'] ?? $emitterMapping['sub_entity_code'] ?? ''));
                    if ($mapped !== '') {
                        $candidates[] = $mapped;
                    }
                } elseif (is_string($emitterMapping)) {
                    $mapped = trim($emitterMapping);
                    if ($mapped !== '') {
                        $candidates[] = $mapped;
                    }
                }
            }
        }

        $seen = [];
        foreach ($candidates as $candidate) {
            $normalized = trim((string) $candidate);
            if ($normalized === '') {
                continue;
            }

            $dedupeKey = strtoupper($normalized);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $matchedCode = SubEntity::query()
                ->where('scope_type', 'emitter')
                ->where('scope_id', $administrationId)
                ->where('is_active', true)
                ->whereRaw('UPPER(code) = ?', [$dedupeKey])
                ->value('code');

            if (is_string($matchedCode) && trim($matchedCode) !== '') {
                return trim($matchedCode);
            }
        }

        return null;
    }

    private function sharedTemplateUserIds(string $templateId): array
    {
        $shareMapRaw = AppSetting::where('key', 'template_share_map')->value('value');
        $shareMap = [];
        if ($shareMapRaw) {
            try {
                $shareMap = json_decode((string) $shareMapRaw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $shareMap = [];
            }
        }

        $raw = (array) ($shareMap[$templateId] ?? []);

        return array_values(array_unique(array_map('strval', array_filter($raw, fn ($id) => trim((string) $id) !== ''))));
    }

    private function buildTemplateValues(ActRequestSubmission $submission): array
    {
        $payload = is_array($submission->applicant_payload) ? $submission->applicant_payload : [];

        return array_merge($payload, [
            'applicant_full_name' => (string) ($submission->applicant_full_name ?? ''),
            'applicant_email' => (string) ($submission->applicant_email ?? ''),
            'applicant_phone' => (string) ($submission->applicant_phone ?? ''),
            'tracking_number' => (string) ($submission->tracking_number ?? ''),
            'requested_document_name' => (string) ($submission->requested_document_name ?? ''),
            'date_du_jour' => now()->locale('fr')->isoFormat('D MMMM YYYY'),
        ]);
    }

    private function applyTemplateValues(DocumentTemplate $template, array $values, TemplateGenerationCoreService $core): string
    {
        $content = (string) ($template->content ?? '');

        if ($content === '') {
            return '';
        }

        $template->loadMissing('variables');
        $contentVarMap = $core->extractContentVariables($content);
        $replacements = $core->buildReplacementMap($template, $contentVarMap, []);

        foreach ($replacements as $slug => $original) {
            $lookupKeys = [$slug, $core->slugify((string) $original)];
            $replacementValue = '';
            foreach ($lookupKeys as $lookupKey) {
                if ($lookupKey !== '' && array_key_exists($lookupKey, $values)) {
                    $replacementValue = (string) ($values[$lookupKey] ?? '');
                    break;
                }
            }

            $patterns = [
                '/\{\{\s*' . preg_quote((string) $original, '/') . '\s*\}\}/u',
                '/\[' . preg_quote((string) $original, '/') . '\]/u',
            ];

            if ($slug !== (string) $original) {
                $patterns[] = '/\{\{\s*' . preg_quote((string) $slug, '/') . '\s*\}\}/u';
                $patterns[] = '/\[' . preg_quote((string) $slug, '/') . '\]/u';
            }

            foreach ($patterns as $pattern) {
                $content = preg_replace($pattern, $replacementValue, $content) ?? $content;
            }
        }

        return $content;
    }

    private function generateDocumentForSubmission(RequestedAct $requestedAct, ActRequestSubmission $submission): ?Document
    {
        if (!(bool) ($requestedAct->auto_generate_enabled ?? false)) {
            return null;
        }

        $templateId = (string) ($requestedAct->auto_template_id ?? '');
        if ($templateId === '') {
            return null;
        }

        $template = DocumentTemplate::query()->with('variables')->find($templateId);
        if (!$template) {
            return null;
        }

        $sharedUserIds = $this->sharedTemplateUserIds((string) $template->id);
        if (empty($sharedUserIds)) {
            return null;
        }

        $usersById = User::query()
            ->whereIn('id', $sharedUserIds)
            ->get(['id', 'email'])
            ->keyBy('id');

        $eligibleUserIds = array_values(array_filter($sharedUserIds, fn ($id) => $usersById->has($id)));
        if (empty($eligibleUserIds)) {
            return null;
        }

        $ownerUserId = (string) $eligibleUserIds[0];
        $core = app(TemplateGenerationCoreService::class);
        $values = $this->buildTemplateValues($submission);
        $rendered = $this->applyTemplateValues($template, $values, $core);

        if (trim(strip_tags($rendered)) === '') {
            $rendered = '<h2>Demande d\'acte</h2>'
                . '<p><strong>Document:</strong> ' . e((string) ($submission->requested_document_name ?? '')) . '</p>'
                . '<p><strong>Demandeur:</strong> ' . e((string) ($submission->applicant_full_name ?? '')) . '</p>'
                . '<p><strong>Email:</strong> ' . e((string) ($submission->applicant_email ?? '')) . '</p>'
                . '<p><strong>Téléphone:</strong> ' . e((string) ($submission->applicant_phone ?? '')) . '</p>'
                . '<p><strong>N° suivi:</strong> ' . e((string) ($submission->tracking_number ?? '')) . '</p>';
        }

        if (!str_contains(strtolower($rendered), '<html')) {
            $rendered = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Acte auto-généré</title>'
                . '<style>body{font-family:Arial,sans-serif;margin:24px;color:#111827;line-height:1.5;}h2{margin:0 0 12px;}p{margin:6px 0;}</style>'
                . '</head><body>' . $rendered . '</body></html>';
        }

        $numbering = $core->reserveDocumentNumber($template, $ownerUserId);
        $docNumber = $numbering['document_number'] ?? null;
        $subEntityCode = $numbering['sub_entity_code'] ?? null;
        $issuingAdministrationId = $numbering['issuing_administration_id'] ?? null;

        $storedFile = 'documents/act-auto-' . $submission->id . '-' . now()->format('YmdHis') . '.html';
        Storage::disk('public')->put($storedFile, $rendered);
        $publicPath = '/storage/' . $storedFile;

        $document = Document::create([
            'id' => (string) Str::uuid(),
            'title' => 'Acte auto-généré - ' . (string) ($submission->requested_document_name ?? 'Document'),
            'description' => 'Auto-généré depuis demande publique #' . (string) ($submission->tracking_number ?? $submission->id),
            'file_path' => $publicPath,
            'final_file_path' => $publicPath,
            'file_size' => strlen($rendered),
            'mime_type' => 'text/html',
            'status' => 'active',
            'owner_id' => $ownerUserId,
            'created_by' => $ownerUserId,
            'document_number' => $docNumber,
            'sub_entity_code' => $subEntityCode,
            'issuing_administration_id' => $issuingAdministrationId,
        ]);

        DocumentVersion::create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'version' => 1,
            'file_path' => $publicPath,
            'creator_id' => $ownerUserId,
            'change_log' => 'Auto-génération depuis demande publique',
        ]);

        foreach ($eligibleUserIds as $userId) {
            $recipientEmail = (string) ($usersById->get($userId)->email ?? '');

            DocumentShare::query()->firstOrCreate(
                [
                    'document_id' => $document->id,
                    'mode' => 'internal',
                    'recipient_name' => 'user:' . $userId,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'shared_by' => $ownerUserId,
                    'recipient_email' => $recipientEmail !== '' ? $recipientEmail : null,
                    'permission' => 'modification',
                    'has_delay' => false,
                ]
            );
        }

        return $document;
    }

    public function index()
    {
        $administrations = IssuingAdministration::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('public-act-requests.index', [
            'administrations' => $administrations,
            'selectedAdministration' => null,
            'acts' => collect(),
            'groupedActs' => collect(),
            'subEntityByCode' => collect(),
        ]);
    }

    public function searchByTrackingNumber(Request $request)
    {
        $request->validate([
            'tracking_number' => 'required|string|max:60',
        ]);

        $trackingNumber = strtoupper(trim((string) $request->input('tracking_number', '')));

        $submission = ActRequestSubmission::query()
            ->whereRaw('UPPER(tracking_number) = ?', [$trackingNumber])
            ->first();

        if (!$submission || empty($submission->tracking_token)) {
            return redirect()
                ->route('public.act-requests.index')
                ->withInput()
                ->with('tracking_error', 'Numero de traitement introuvable. Verifiez puis reessayez.');
        }

        return redirect()->route('public.act-requests.track', $submission->tracking_token);
    }

    public function showActsByAdministration(string $administration_id)
    {
        $administrations = IssuingAdministration::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedAdministration = IssuingAdministration::query()
            ->where('is_active', true)
            ->findOrFail($administration_id);

        $acts = RequestedAct::query()
            ->where('administration_id', $selectedAdministration->id)
            ->where('is_active', true)
            ->orderBy('direction_code')
            ->orderBy('document_name')
            ->get();

        $subEntityByCode = SubEntity::query()
            ->where('scope_type', 'emitter')
            ->where('scope_id', $selectedAdministration->id)
            ->where('is_active', true)
            ->get(['code', 'name'])
            ->mapWithKeys(function ($se) {
                return [(string) $se->code => (string) $se->name];
            });

        $groupedActs = $acts->groupBy(function ($act) use ($subEntityByCode) {
            $code = trim((string) ($act->direction_code ?? ''));
            if ($code === '') {
                return 'Sans entite sous tutelle';
            }
            $label = (string) ($subEntityByCode[$code] ?? 'Entite inconnue');
            return $code . ' - ' . $label;
        });

        return view('public-act-requests.index', compact('administrations', 'selectedAdministration', 'acts', 'groupedActs', 'subEntityByCode'));
    }

    public function create(string $administration_id, string $requested_act_id)
    {
        $administration = IssuingAdministration::query()
            ->where('is_active', true)
            ->findOrFail($administration_id);

        $requestedAct = RequestedAct::query()
            ->where('administration_id', $administration->id)
            ->where('is_active', true)
            ->findOrFail($requested_act_id);

        $directions = SubEntity::query()
            ->where('scope_type', 'emitter')
            ->where('scope_id', $administration->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        $recipients = RecipientAdministration::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'metadata']);

        $nniExample = trim((string) (AppSetting::where('key', 'nni_display_example')->value('value') ?? ''));

        return view('public-act-requests.create', compact('administration', 'requestedAct', 'directions', 'recipients', 'nniExample'));
    }

    /**
     * Fournisseurs mobile money actifs applicables à cette administration : d'abord la
     * config propre à l'administration émettrice, sinon la config globale (administration_id NULL).
     */
    private function activeMobileMoneyConfigsFor(IssuingAdministration $administration)
    {
        $scoped = MobileMoneyProviderConfig::query()
            ->where('administration_id', $administration->id)
            ->where('administration_type', 'emitter')
            ->where('is_active', true)
            ->get();

        if ($scoped->isNotEmpty()) {
            return $scoped;
        }

        return MobileMoneyProviderConfig::query()
            ->whereNull('administration_id')
            ->where('is_active', true)
            ->get();
    }

    public function store(Request $request, string $administration_id, string $requested_act_id)
    {
        $administration = IssuingAdministration::query()
            ->where('is_active', true)
            ->findOrFail($administration_id);

        $requestedAct = RequestedAct::query()
            ->where('administration_id', $administration->id)
            ->where('is_active', true)
            ->findOrFail($requested_act_id);

        $request->validate([
            'applicant_full_name'        => 'required|string|max:255',
            'applicant_email'            => 'required|email|max:255',
            'applicant_phone'            => 'nullable|string|max:50',
            'nni'                        => 'required|string|max:40',
            'recipient_administration_id'=> 'nullable|exists:recipient_administrations,id',
            'motif'                      => 'nullable|string|max:2000',
            'note'                       => 'nullable|string|max:2000',
            'extra'                      => 'nullable|array',
            'attachments_files'          => 'nullable|array',
            'attachments_files.*'        => 'nullable|file|max:51200',
            'attachment_labels'          => 'nullable|array',
            'attachment_labels.*'        => 'nullable|string|max:255',
        ], [
            'attachments_files.*.uploaded' => 'Echec du televersement du fichier. Verifiez la taille (max 50 Mo par fichier) puis reessayez.',
            'attachments_files.*.max' => 'Chaque fichier ne doit pas depasser 50 Mo.',
            'attachments_files.*.file' => 'Le fichier joint est invalide.',
        ]);

        $nniProcessed = NniService::process($request->input('nni'));
        if (!$nniProcessed) {
            return back()
                ->withErrors(['nni' => "Le NNI saisi n'est pas au format attendu."])
                ->withInput();
        }

        $extraPayload = [];
        $requestedFields = is_array($requestedAct->applicant_fields) ? $requestedAct->applicant_fields : [];
        $incomingExtra = $request->input('extra', []);

        foreach ($requestedFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = $this->buildApplicantFieldKey($field);
            if ($key === '') {
                continue;
            }

            $value = (string) ($incomingExtra[$key] ?? '');
            $type = strtolower(trim((string) ($field['inputType'] ?? 'text')));

            if ($type === 'list') {
                $allowedOptions = $this->buildApplicantFieldOptions($field);
                if (empty($allowedOptions)) {
                    return back()->withErrors(['extra.' . $key => 'La liste de choix est vide pour ce champ.'])->withInput();
                }

                if ($value === '' || !in_array($value, $allowedOptions, true)) {
                    return back()->withErrors(['extra.' . $key => 'Veuillez sélectionner une valeur valide dans la liste proposée.'])->withInput();
                }
            }

            $extraPayload[$key] = $value;
        }

        $attachments = [];

        $requiredDocs = is_array($requestedAct->required_documents) ? $requestedAct->required_documents : [];
        $extraFiles = (array) $request->file('attachments_files', []);
        $attachmentLabels = (array) $request->input('attachment_labels', []);
        $requiredLookup = [];
        foreach ($requiredDocs as $docLabel) {
            $docLabel = trim((string) $docLabel);
            if ($docLabel !== '') {
                $requiredLookup[$docLabel] = false;
            }
        }

        foreach ($extraFiles as $idx => $file) {
            if (!$file) {
                continue;
            }

            $selectedLabel = trim((string) ($attachmentLabels[$idx] ?? ''));
            if ($selectedLabel === '') {
                return back()
                    ->withErrors(['attachment_labels.' . $idx => 'Veuillez choisir le nom du fichier dans la liste.'])
                    ->withInput();
            }

            ClamAvScanner::scan($file, 'actes-publics');
            $path = $file->store('act-requests', 'public');
            $isRequiredDocument = array_key_exists($selectedLabel, $requiredLookup);
            if ($isRequiredDocument) {
                $requiredLookup[$selectedLabel] = true;
            }

            $attachments[] = [
                'type' => $isRequiredDocument ? 'required_document' : 'additional',
                'required_label' => $isRequiredDocument ? $selectedLabel : null,
                'uploaded_name' => $selectedLabel,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        foreach ($requiredLookup as $docLabel => $isPresent) {
            if ($isPresent) {
                continue;
            }

            return back()
                ->withErrors(['attachments_files' => 'Le fichier "' . $docLabel . '" est obligatoire.'])
                ->withInput();
        }

        $recipientAdministrationId = trim((string) ($request->input('recipient_administration_id') ?: $requestedAct->recipient_administration_id ?: ''));
        $directionCode = $this->resolveDirectionCode(
            $requestedAct,
            (string) $administration->id,
            $recipientAdministrationId !== '' ? $recipientAdministrationId : null
        );
        $trackingNumber = $this->generateTrackingNumber();
        $trackingToken = $this->generateTrackingToken();
        $uniqueKeyValue = $this->resolveUniqueKeyValue($requestedAct, $request, $extraPayload);

        $submission = ActRequestSubmission::create([
            'tracking_number'             => $trackingNumber,
            'tracking_token'              => $trackingToken,
            'unique_key_value'            => $uniqueKeyValue,
            'requested_act_id'            => $requestedAct->id,
            'emitter_administration_id'   => $administration->id,
            'direction_code'              => $directionCode,
            'recipient_administration_id' => $recipientAdministrationId !== '' ? $recipientAdministrationId : null,
            'motif'                       => trim((string) $request->input('motif', '')),
            'requested_document_name'     => (string) $requestedAct->document_name,
            'applicant_full_name'         => (string) $request->input('applicant_full_name'),
            'applicant_email'             => (string) $request->input('applicant_email', ''),
            'applicant_phone'             => (string) $request->input('applicant_phone', ''),
            'nni_hash'                    => $nniProcessed['hash'],
            'nni_masked'                  => $nniProcessed['masked'],
            'applicant_payload'           => array_merge($extraPayload, [
                '_note' => trim((string) $request->input('note', '')),
            ]),
            'attachments'                 => $attachments,
            'status'                      => $requestedAct->is_paid ? 'awaiting_payment' : 'pending',
        ]);

        if ($requestedAct->is_paid) {
            return redirect()
                ->route('public.act-requests.payment', $submission->tracking_token);
        }

        $isAutoGenerationEnabled = (bool) ($requestedAct->auto_generate_enabled ?? false);
        if ($isAutoGenerationEnabled && $uniqueKeyValue) {
            $hasPreviousSubmission = ActRequestSubmission::query()
                ->where('requested_act_id', $requestedAct->id)
                ->where('unique_key_value', $uniqueKeyValue)
                ->where('id', '!=', $submission->id)
                ->exists();

            if ($hasPreviousSubmission) {
                $generatedDocument = $this->generateDocumentForSubmission($requestedAct, $submission);

                if ($generatedDocument) {
                    $submission->update([
                        'auto_generated_document_id' => $generatedDocument->id,
                        'auto_generated_at' => now(),
                        'status' => 'in_progress',
                    ]);
                }
            }
        }

        return redirect()
            ->route('public.act-requests.create', [$administration->id, $requestedAct->id])
            ->with('success', 'Votre demande a ete enregistree avec succes.')
            ->with('tracking_number', $submission->tracking_number)
            ->with('tracking_url', route('public.act-requests.track', $submission->tracking_token));
    }

    public function payment(string $tracking_token)
    {
        $submission = ActRequestSubmission::query()
            ->with('requestedAct.administration')
            ->where('tracking_token', $tracking_token)
            ->firstOrFail();

        $requestedAct = $submission->requestedAct;
        abort_unless($requestedAct && $requestedAct->is_paid, 404);

        if (!in_array($submission->status, ['awaiting_payment', 'payment_failed'], true)) {
            // Déjà payée (ou en traitement) : rien à faire ici, direction le suivi.
            return redirect()->route('public.act-requests.track', $tracking_token);
        }

        $configs = $this->activeMobileMoneyConfigsFor($requestedAct->administration);
        $pendingTransaction = MobileMoneyTransaction::where('act_request_submission_id', $submission->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return view('public-act-requests.payment', compact('submission', 'requestedAct', 'configs', 'pendingTransaction'));
    }

    public function initiatePayment(Request $request, string $tracking_token)
    {
        $submission = ActRequestSubmission::query()
            ->with('requestedAct')
            ->where('tracking_token', $tracking_token)
            ->firstOrFail();

        $requestedAct = $submission->requestedAct;
        abort_unless($requestedAct && $requestedAct->is_paid, 404);

        if (!in_array($submission->status, ['awaiting_payment', 'payment_failed'], true)) {
            return response()->json(['ok' => false, 'message' => 'Cette demande n\'est plus en attente de paiement.'], 409);
        }

        $data = $request->validate([
            'provider_config_id' => 'required|uuid|exists:mobile_money_provider_configs,id',
            'phone' => 'required|string|max:30',
        ]);

        $config = MobileMoneyProviderConfig::where('id', $data['provider_config_id'])
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json(['ok' => false, 'message' => 'Fournisseur de paiement invalide ou inactif.'], 422);
        }

        $externalId = (string) Str::uuid();

        $transaction = MobileMoneyTransaction::create([
            'act_request_submission_id' => $submission->id,
            'mobile_money_provider_config_id' => $config->id,
            'provider' => $config->provider,
            'external_id' => $externalId,
            'phone_number' => $data['phone'],
            'amount' => $requestedAct->amount,
            'currency' => $config->currency ?: 'XOF',
            'status' => 'pending',
        ]);

        try {
            $gateway = MobileMoneyGatewayFactory::make($config->provider);
            $gateway->initiate(
                $config,
                $externalId,
                $data['phone'],
                (float) $requestedAct->amount,
                'Paiement ' . $requestedAct->document_name . ' - ' . $submission->tracking_number
            );
        } catch (\Throwable $e) {
            Log::error('PublicActRequestController::initiatePayment - échec du déclenchement', [
                'transaction_id' => (string) $transaction->id,
                'provider' => $config->provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => "Impossible de contacter le fournisseur de paiement pour l'instant. Veuillez réessayer dans un instant.",
                'transaction_id' => $transaction->id,
            ], 502);
        }

        return response()->json(['ok' => true, 'transaction_id' => $transaction->id]);
    }

    public function track(string $tracking_token)
    {
        $submission = ActRequestSubmission::query()
            ->with(['administration'])
            ->where('tracking_token', $tracking_token)
            ->firstOrFail();

        return view('public-act-requests.track', compact('submission'));
    }
}
