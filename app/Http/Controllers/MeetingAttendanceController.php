<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\IssuingAdministration;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingParticipant;
use App\Models\PersonnelEmployee;
use App\Models\RecipientAdministration;
use App\Models\SubEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeetingAttendanceController extends Controller
{
    public function dashboard(Meeting $meeting)
    {
        $this->abortIfMeetingOutsideScope($meeting);

        $meeting->load(['participants.user', 'attendances', 'organizer.directionAssignments']);
        $branding = $this->resolveOrganizerBranding($meeting);

        return view('meetings.dashboard', compact('meeting', 'branding'));
    }

    public function showByToken(string $token)
    {
        $meeting = Meeting::with('organizer.directionAssignments')
            ->where('qr_token', $token)
            ->firstOrFail();

        $branding = $this->resolveOrganizerBranding($meeting);

        if (!$this->isQrWindowValid($meeting)) {
            return view('meetings.attendance-expired', [
                'meeting' => $meeting,
                'branding' => $branding,
            ]);
        }

        return view('meetings.attendance', [
            'meeting' => $meeting,
            'branding' => $branding,
        ]);
    }

    public function signByToken(Request $request, string $token)
    {
        $meeting = Meeting::where('qr_token', $token)->firstOrFail();

        if (!$this->isQrWindowValid($meeting)) {
            return back()->with('error', 'Le QR Code n\'est plus valide pour cette réunion.');
        }

        $validated = $request->validate([
            'identifier' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'signature' => 'nullable|string',
        ]);

        $identifier = trim($validated['identifier']);

        $participant = $this->findParticipant($meeting, $identifier);
        $profile = $participant ? $this->participantProfile($meeting, $participant) : [];

        $resolvedEmail = trim((string) (($profile['email'] ?? null) ?: ($validated['email'] ?? '')));

        $existingAttendance = $this->findAttendance($meeting, $identifier, $participant, $resolvedEmail);

        if ($existingAttendance) {
            $registeredName = trim((string) ($existingAttendance->full_name ?? ''));
            $registeredTime = $existingAttendance->signed_at?->format('H:i');
            $message = $registeredName !== ''
                ? 'Vous êtes déjà inscrit à cette réunion : ' . $registeredName
                : 'Vous êtes déjà inscrit à cette réunion';

            if ($registeredTime) {
                $message .= ' à ' . $registeredTime;
            }

            $message .= '.';

            return back()
                ->withInput()
                ->with('error', $message);
        }

        $name = ($profile['full_name'] ?? null) ?: ($validated['full_name'] ?? null);

        if (!$name) {
            return back()->withInput()->with('error', 'Nom obligatoire pour un participant externe.');
        }

        $signaturePath = null;
        if (!empty($validated['signature']) && str_starts_with($validated['signature'], 'data:image/')) {
            $raw = explode(',', $validated['signature'], 2)[1] ?? '';
            $binary = base64_decode($raw, true);
            if ($binary !== false) {
                $fileName = 'meetings/signatures/' . Str::uuid() . '.png';
                \Storage::disk('public')->put($fileName, $binary);
                $signaturePath = '/storage/' . $fileName;
            }
        }

        MeetingAttendance::create([
            'meeting_id' => $meeting->id,
            'meeting_participant_id' => $participant?->id,
            'identifier' => $identifier,
            'full_name' => $name,
            'email' => $resolvedEmail !== '' ? $resolvedEmail : null,
            'phone' => ($validated['phone'] ?? null) ?: ($profile['phone'] ?? null),
            'job_title' => ($validated['job_title'] ?? null) ?: ($profile['job_title'] ?? null),
            'organization' => ($validated['organization'] ?? null) ?: ($profile['organization'] ?? null),
            'attendance_status' => 'present',
            'signed_at' => now(),
            'signature_path' => $signaturePath,
        ]);

        return back()->with('success', 'Inscription avec succès.');
    }

    public function lookupByToken(Request $request, string $token)
    {
        $meeting = Meeting::where('qr_token', $token)->firstOrFail();

        $identifier = trim((string) $request->input('identifier', ''));
        if ($identifier === '' || !$this->isQrWindowValid($meeting)) {
            return response()->json(null);
        }

        $participant = $this->findParticipant($meeting, $identifier);
        $profile = $participant ? $this->participantProfile($meeting, $participant) : null;

        // Déjà émargé, avec ce même identifiant ou avec l'autre (e-mail / matricule).
        $attendance = $this->findAttendance($meeting, $identifier, $participant, $profile['email'] ?? null);

        if ($attendance) {
            $registeredName = trim((string) ($attendance->full_name ?? ''));
            $registeredTime = $attendance->signed_at?->format('H:i');
            $message = $registeredName !== ''
                ? 'Vous êtes déjà inscrit à cette réunion : ' . $registeredName
                : 'Vous êtes déjà inscrit à cette réunion';

            if ($registeredTime) {
                $message .= ' à ' . $registeredTime;
            }

            $message .= '.';

            return response()->json([
                'already_registered' => true,
                'can_edit'     => $this->isQrWindowValid($meeting),
                'message'      => $message,
                'full_name'    => $attendance->full_name,
                'email'        => $attendance->email,
                'phone'        => $attendance->phone,
                'job_title'    => $attendance->job_title,
                'organization' => $attendance->organization,
            ]);
        }

        return response()->json($profile ? ['already_registered' => false] + $profile : null);
    }

    public function correctByToken(Request $request, string $token)
    {
        $meeting = Meeting::where('qr_token', $token)->firstOrFail();

        if (!$this->isQrWindowValid($meeting)) {
            return back()->withInput()->with('error', 'La période de modification est terminée pour cette réunion.');
        }

        $validated = $request->validate([
            'identifier'   => 'required|string|max:255',
            'full_name'    => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'job_title'    => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'signature'    => 'nullable|string',
        ]);

        $identifier = trim($validated['identifier']);
        $participant = $this->findParticipant($meeting, $identifier);
        $email = $participant ? $this->participantProfile($meeting, $participant)['email'] : null;

        $attendance = $this->findAttendance($meeting, $identifier, $participant, $email);

        if (!$attendance) {
            return back()->withInput()->with('error', 'Aucune inscription trouvée pour cet identifiant.');
        }

        $signaturePath = $attendance->signature_path;
        if (!empty($validated['signature']) && str_starts_with($validated['signature'], 'data:image/')) {
            $raw = explode(',', $validated['signature'], 2)[1] ?? '';
            $binary = base64_decode($raw, true);
            if ($binary !== false) {
                if ($signaturePath && str_starts_with($signaturePath, '/storage/')) {
                    Storage::disk('public')->delete(ltrim(substr($signaturePath, strlen('/storage/')), '/'));
                }
                $fileName = 'meetings/signatures/' . Str::uuid() . '.png';
                Storage::disk('public')->put($fileName, $binary);
                $signaturePath = '/storage/' . $fileName;
            }
        }

        $attendance->update([
            'full_name'      => ($validated['full_name'] ?? null) ?: $attendance->full_name,
            'email'          => ($validated['email'] ?? null) ?: $attendance->email,
            'phone'          => array_key_exists('phone', $validated) ? ($validated['phone'] ?? null) : $attendance->phone,
            'job_title'      => array_key_exists('job_title', $validated) ? ($validated['job_title'] ?? null) : $attendance->job_title,
            'organization'   => array_key_exists('organization', $validated) ? ($validated['organization'] ?? null) : $attendance->organization,
            'signature_path' => $signaturePath,
        ]);

        return back()->with('success', 'Vos informations ont été mises à jour avec succès.');
    }

    /** Participant inscrit à la réunion, désigné par son e-mail ou son matricule (saisie exacte). */
    private function findParticipant(Meeting $meeting, string $identifier): ?MeetingParticipant
    {
        $needle = mb_strtolower(trim($identifier), 'UTF-8');
        if ($needle === '') {
            return null;
        }

        $participants = MeetingParticipant::where('meeting_id', $meeting->id)->with('user')->get();

        if (str_contains($needle, '@')) {
            return $participants->first(fn (MeetingParticipant $p) => in_array($needle, $this->participantEmails($p), true));
        }

        $employees = PersonnelEmployee::query()
            ->whereRaw('LOWER(employee_number) = ?', [$needle])
            ->get(['linked_user_id', 'email']);

        $matches = $participants->filter(fn (MeetingParticipant $p) => $employees->contains(
            fn (PersonnelEmployee $e) => ($e->linked_user_id && (string) $e->linked_user_id === (string) $p->user_id)
                || ($e->email && in_array(mb_strtolower(trim($e->email), 'UTF-8'), $this->participantEmails($p), true))
        ));

        // Un matricule désignant deux personnes différentes ne pré-remplit rien.
        return $matches->unique(fn ($p) => (string) ($p->user_id ?: $p->id))->count() === 1 ? $matches->first() : null;
    }

    /** @return list<string> adresses du participant, en minuscules */
    private function participantEmails(MeetingParticipant $participant): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($email) => mb_strtolower(trim((string) $email), 'UTF-8'),
            [$participant->email, $participant->user?->email],
        ))));
    }

    /**
     * Informations à pré-remplir : inscription, compte, dossier du personnel,
     * puis dernier émargement de la personne pour ce qui manque.
     *
     * @return array{full_name: ?string, email: ?string, phone: ?string, job_title: ?string, organization: ?string}
     */
    private function participantProfile(Meeting $meeting, MeetingParticipant $participant): array
    {
        $user = $participant->user;
        $emails = $this->participantEmails($participant);

        $employees = PersonnelEmployee::query()
            ->where(function ($query) use ($participant, $emails) {
                $query->whereRaw('1 = 0');
                if ($participant->user_id) {
                    $query->orWhere('linked_user_id', $participant->user_id);
                }
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->get();
        // Le dossier lié au compte, dans l'administration de la réunion, est prioritaire.
        $employee = $employees->sortByDesc(fn (PersonnelEmployee $e) => ($participant->user_id && (string) $e->linked_user_id === (string) $participant->user_id ? 2 : 0)
            + ((string) $e->administration_id === (string) $meeting->issuing_administration_id ? 1 : 0))->first();

        $previous = $emails === [] ? collect() : MeetingAttendance::query()
            ->where(function ($query) use ($emails) {
                foreach ($emails as $email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->latest('signed_at')
            ->limit(10)
            ->get(['phone', 'job_title', 'organization']);
        $last = fn (string $field) => $previous->pluck($field)->first(fn ($value) => trim((string) $value) !== '');

        // Un agent invité sans dossier appartient à l'administration qui organise la réunion.
        $administrationId = $employee?->administration_id ?: ($user && !$participant->is_external ? $meeting->issuing_administration_id : null);
        $organization = $administrationId
            ? (IssuingAdministration::find($administrationId)?->name ?? RecipientAdministration::find($administrationId)?->name)
            : null;

        return [
            'full_name'    => $participant->full_name ?: $user?->name ?: $employee?->full_name ?: null,
            'email'        => $participant->email ?: $user?->email ?: $employee?->email ?: null,
            'phone'        => $user?->phone ?: $employee?->phone ?: $last('phone'),
            'job_title'    => $employee?->job_title ?: $last('job_title'),
            'organization' => $participant->organization ?: $organization ?: $last('organization'),
        ];
    }

    /** Émargement existant : même identifiant, même e-mail ou même participant. */
    private function findAttendance(Meeting $meeting, string $identifier, ?MeetingParticipant $participant, ?string $email): ?MeetingAttendance
    {
        $needles = array_unique(array_filter(array_map(
            fn ($value) => mb_strtolower(trim((string) $value), 'UTF-8'),
            [$identifier, $email],
        )));

        return MeetingAttendance::query()
            ->where('meeting_id', $meeting->id)
            ->where(function ($query) use ($needles, $participant) {
                foreach ($needles as $needle) {
                    $query->orWhereRaw('LOWER(identifier) = ?', [$needle])->orWhereRaw('LOWER(email) = ?', [$needle]);
                }
                if ($participant) {
                    $query->orWhere('meeting_participant_id', $participant->id);
                }
            })
            ->orderBy('signed_at')
            ->first();
    }

    public function downloadAttendance(Request $request, Meeting $meeting)
    {
        $this->abortIfMeetingOutsideScope($meeting);

        $format      = in_array($request->input('format'), ['pdf', 'csv']) ? $request->input('format') : 'csv';
        $orientation = in_array($request->input('orientation'), ['portrait', 'landscape']) ? $request->input('orientation') : 'portrait';

        $attendances = $meeting->attendances()->latest('signed_at')->get();
        $baseName    = 'presence_' . Str::slug($meeting->title) . '_' . ($meeting->starts_at?->format('Ymd') ?? date('Ymd'));

        if ($format === 'pdf') {
            return $this->attendancePdf($meeting, $orientation)->download($baseName . '.pdf');
        }

        // CSV
        $filename = $baseName . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($meeting, $attendances) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Réunion', $meeting->title], ';');
            fputcsv($handle, ['Date', $meeting->starts_at?->format('d/m/Y H:i')], ';');
            fputcsv($handle, ['Salle', $meeting->room?->name . ' (' . $meeting->room?->location . ')'], ';');
            fputcsv($handle, ['Organisateur', $meeting->organizer?->name], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Nom complet', 'Identifiant', 'Email', 'Téléphone', 'Fonction', 'Organisation', 'Heure de signature'], ';');

            foreach ($attendances as $a) {
                $displayIdentifier = filter_var($a->identifier, FILTER_VALIDATE_EMAIL) ? '' : $a->identifier;

                fputcsv($handle, [
                    $a->full_name,
                    $displayIdentifier,
                    $a->email,
                    $a->phone,
                    $a->job_title,
                    $a->organization,
                    $a->signed_at?->format('d/m/Y H:i:s'),
                ], ';');
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** Liste de présence signée (logo, émargements), pour le téléchargement et l'envoi par e-mail. */
    public function attendancePdf(Meeting $meeting, string $orientation = 'portrait'): \Barryvdh\DomPDF\PDF
    {
        $meeting->load(['participants', 'room', 'organizer']);
        $attendances = $meeting->attendances()->latest('signed_at')->get();
        $branding = $this->resolveOrganizerBranding($meeting);
        $branding['logo_pdf_src'] = $this->resolvePdfLogoSource($branding['logo_url'] ?? null);
        $signatureSources = [];

        foreach ($attendances as $attendance) {
            $signatureSources[(string) $attendance->id] = $this->resolvePdfImageSource($attendance->signature_path);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.attendance_pdf', [
            'meeting'     => $meeting,
            'attendances' => $attendances,
            'branding'    => $branding,
            'signatureSources' => $signatureSources,
        ]);

        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isFontSubsettingEnabled', true);
        $pdf->setPaper('a4', $orientation);

        return $pdf;
    }

    private function resolvePdfLogoSource(?string $logoUrl): ?string
    {
        return $this->resolvePdfImageSource($logoUrl);
    }

    private function resolvePdfImageSource(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        if (str_starts_with($imageUrl, 'data:image/')) {
            return $imageUrl;
        }

        // Common case: logo served from /storage/... symlinked to storage/app/public
        if (str_starts_with($imageUrl, '/storage/')) {
            $relativePath = ltrim(substr($imageUrl, strlen('/storage/')), '/');
            if (Storage::disk('public')->exists($relativePath)) {
                $content = Storage::disk('public')->get($relativePath);
                $mime = Storage::disk('public')->mimeType($relativePath) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        // Fallback for public relative paths
        if (str_starts_with($imageUrl, '/')) {
            $publicFile = public_path(ltrim($imageUrl, '/'));
            if (is_file($publicFile)) {
                $content = file_get_contents($publicFile);
                if ($content !== false) {
                    $mime = mime_content_type($publicFile) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode($content);
                }
            }
        }

        // Keep original URL as final fallback (works when remote loading is available)
        return $imageUrl;
    }

    private function isQrWindowValid(Meeting $meeting): bool
    {
        if ($meeting->isCancelled()) {
            return false;
        }

        if (!$meeting->qr_valid_from || !$meeting->qr_valid_until) {
            return true;
        }

        $now = now();
        return $now->between($meeting->qr_valid_from, $meeting->qr_valid_until);
    }

    private function resolveOrganizerBranding(Meeting $meeting): array
    {
        $logoUrl = null;
        $tutelleEntityName = null;
        $tutelleEntityCode = null;

        // ── 1. Résoudre l'administration via issuing_administration_id (priorité directe)
        //       ou via l'assignment de l'organisateur (fallback)
        $issuingAdmin = null;

        if (!empty($meeting->sub_entity_code)) {
            $tutelleEntityCode = strtoupper((string) $meeting->sub_entity_code);
        }

        if (!empty($meeting->issuing_administration_id)) {
            $issuingAdmin = IssuingAdministration::find($meeting->issuing_administration_id);
        }

        if (!$issuingAdmin) {
            $assignment = optional($meeting->organizer)->directionAssignments
                ?->sortByDesc('created_at')
                ?->first();

            if ($assignment) {
                $issuingAdmin = IssuingAdministration::find($assignment->direction_scope_id);

                if (!empty($assignment->direction_label)) {
                    $tutelleEntityName = (string) $assignment->direction_label;
                }

                if (!$tutelleEntityCode && !empty($assignment->sub_entity_code)) {
                    $tutelleEntityCode = strtoupper((string) $assignment->sub_entity_code);
                }

                if (!$tutelleEntityName && !empty($assignment->sub_entity_code)) {
                    $subEntity = SubEntity::query()
                        ->where('scope_id', $assignment->direction_scope_id)
                        ->where('code', $assignment->sub_entity_code)
                        ->first();
                    $tutelleEntityName = $subEntity?->name ?? (string) $assignment->sub_entity_code;
                }
            }
        }

        if ($issuingAdmin && !$tutelleEntityName) {
            $tutelleEntityName = (string) $issuingAdmin->name;
        }

        if (!$issuingAdmin) {
            return [
                'logo_url' => null,
                'tutelle_entity_name' => $tutelleEntityName,
                'tutelle_entity_code' => $tutelleEntityCode,
            ];
        }

        // ── 2. Stratégie 1 : logo theming via AppSetting (même mécanisme que le sidebar)
        //       clé : theme_{emitter|recipient}_{adminId}_logo
        foreach (['emitter', 'recipient'] as $type) {
            $themKey  = 'theme_' . $type . '_' . $issuingAdmin->id . '_logo';
            $themPath = AppSetting::where('key', $themKey)->value('value');
            if ($themPath && Storage::disk('public')->exists($themPath)) {
                $logoUrl = asset('storage/' . $themPath);
                break;
            }
        }

        // ── 3. Stratégie 2 : champ logo direct ou metadata['logoPath'] (même que le sidebar)
        if (!$logoUrl) {
            $rawLogoField = $issuingAdmin->logo ?? null;
            if (!$rawLogoField && isset($issuingAdmin->metadata['logoPath'])) {
                $rawLogoField = $issuingAdmin->metadata['logoPath'];
            }

            if ($rawLogoField) {
                $rawLogo = (string) $rawLogoField;

                if (str_starts_with($rawLogo, 'http://') || str_starts_with($rawLogo, 'https://') || str_starts_with($rawLogo, 'data:image/')) {
                    $logoUrl = $rawLogo;
                } else {
                    $normalized = ltrim($rawLogo, '/');

                    if (str_starts_with($normalized, 'storage/')) {
                        $normalized = ltrim(substr($normalized, strlen('storage/')), '/');
                    }
                    if (str_starts_with($normalized, 'public/')) {
                        $normalized = ltrim(substr($normalized, strlen('public/')), '/');
                    }

                    if (Storage::disk('public')->exists($normalized)) {
                        $logoUrl = url('/storage/' . $normalized);
                    } elseif (str_starts_with($rawLogo, '/')) {
                        $logoUrl = url($rawLogo);
                    } elseif (is_file(public_path($normalized))) {
                        $logoUrl = url('/' . $normalized);
                    } elseif (str_starts_with($normalized, 'images/')) {
                        if (is_file(public_path($normalized))) {
                            $logoUrl = asset($normalized);
                        }
                    } else {
                        $fallback = 'images/logos/' . basename($normalized);
                        if (is_file(public_path($fallback))) {
                            $logoUrl = asset($fallback);
                        }
                    }
                }
            }
        }

        return [
            'logo_url'            => $logoUrl,
            'tutelle_entity_name' => $tutelleEntityName,
            'tutelle_entity_code' => $tutelleEntityCode,
        ];
    }

    private function abortIfMeetingOutsideScope(Meeting $meeting): void
    {
        $scope = $this->resolveCurrentUserScope();

        if ($scope === null) {
            abort_unless((string) $meeting->organizer_id === (string) Auth::id(), 403);
            return;
        }

        abort_unless(
            (string) ($meeting->issuing_administration_id ?? '') === $scope['administration_id']
            && strtoupper((string) ($meeting->sub_entity_code ?? '')) === $scope['sub_entity_code'],
            403
        );
    }

    private function resolveCurrentUserScope(): ?array
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $assignment = DB::table('user_direction_assignments')
            ->where('user_id', (string) $user->id)
            ->orderByDesc('created_at')
            ->first();

        if (!$assignment || empty($assignment->direction_scope_id)) {
            return null;
        }

        return [
            'administration_id' => (string) $assignment->direction_scope_id,
            'sub_entity_code' => strtoupper(trim((string) ($assignment->sub_entity_code ?? ''))),
        ];
    }
}
