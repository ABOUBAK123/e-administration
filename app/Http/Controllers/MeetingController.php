<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingMinutesVersion;
use App\Models\MeetingParticipant;
use App\Models\MeetingRoom;
use App\Models\User;
use Carbon\Carbon;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Services\ClamAvScanner;
use App\Traits\GuardsPermissions;
use Illuminate\Support\Str;

class MeetingController extends Controller
{
    use GuardsPermissions;

    public function index(Request $request)
    {
        $this->guardPermission('meetings.view');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $q = trim((string) $request->get('q', ''));
        $scope = $this->resolveCurrentUserScope();

        $meetings = Meeting::with(['room', 'organizer', 'minutesWriter', 'validator', 'validatedByUser'])
            ->when($scope !== null, function ($query) use ($scope) {
                $query->where('issuing_administration_id', $scope['administration_id'])
                    ->where('sub_entity_code', $scope['sub_entity_code']);
            }, function ($query) {
                // Fallback de securite: sans scope, l'utilisateur ne voit que ses reunions.
                $query->where('organizer_id', Auth::id());
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%");
            })
            ->latest('starts_at')
            ->paginate(20)
            ->appends(['q' => $q]);

        return view('meetings.index', compact('meetings', 'q'));
    }

    public function calendar(Request $request)
    {
        $this->guardPermission('meetings.view');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $scope = $this->resolveCurrentUserScope();

        $year = (int) ($request->get('year') ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);
        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }

        $roomId = trim((string) $request->get('room_id', ''));
        $mineOnly = (bool) $request->boolean('mine');

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $meetings = Meeting::with(['room', 'organizer'])
            ->when($scope !== null, function ($query) use ($scope) {
                $query->where('issuing_administration_id', $scope['administration_id'])
                    ->where('sub_entity_code', $scope['sub_entity_code']);
            }, function ($query) {
                // Fallback de securite: sans scope, l'utilisateur ne voit que ses reunions.
                $query->where('organizer_id', Auth::id());
            })
            ->when($roomId !== '', function ($query) use ($roomId) {
                $query->where('meeting_room_id', $roomId);
            })
            ->when($mineOnly, function ($query) {
                $query->where('organizer_id', Auth::id());
            })
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$gridStart, $gridEnd])
            ->orderBy('starts_at')
            ->get();

        $meetingsByDay = $meetings->groupBy(fn ($m) => $m->starts_at->format('Y-m-d'));

        $days = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $cursor->copy(),
                'inMonth' => $cursor->month === $month,
                'isToday' => $cursor->isToday(),
                'meetings' => $meetingsByDay->get($key, collect()),
            ];
            $cursor->addDay();
        }

        $rooms = $scope !== null
            ? MeetingRoom::where('administration_id', $scope['administration_id'])->orderBy('name')->get()
            : collect();

        $prevMonth = $monthStart->copy()->subMonth();
        $nextMonth = $monthStart->copy()->addMonth();

        return view('meetings.calendar', compact(
            'days',
            'month',
            'year',
            'monthStart',
            'rooms',
            'roomId',
            'mineOnly',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function create()
    {
        $this->guardPermission('meetings.create');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $scope = $this->resolveCurrentUserScope();
        if ($scope === null) {
            return redirect()->route('meetings.index')
                ->with('error', 'Votre compte n\'est rattaché à aucune entité sous tutelle.');
        }

        $rooms = MeetingRoom::where('status', 'active')
            ->where('maintenance_status', 'operational')
            ->where('administration_id', $scope['administration_id'])
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->whereExists(function ($query) use ($scope) {
                $query->select(DB::raw(1))
                    ->from('user_direction_assignments as uda')
                    ->whereColumn('uda.user_id', 'users.id')
                    ->where('uda.direction_scope_id', $scope['administration_id'])
                    ->whereRaw("UPPER(COALESCE(uda.sub_entity_code, '')) = ?", [$scope['sub_entity_code']]);
            })
            ->orderBy('users.name')
            ->get();

        return view('meetings.create', compact('rooms', 'users'));
    }

    public function store(Request $request)
    {
        $this->guardPermission('meetings.create');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $scope = $this->resolveCurrentUserScope();
        if ($scope === null) {
            return back()->withInput()->with('error', 'Impossible de créer une réunion sans entité sous tutelle.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_type' => 'required|in:ordinary,extraordinary,management_committee,project,technical,other',
            'meeting_room_id' => 'required|uuid|exists:meeting_rooms,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'processing_deadline' => 'nullable|date|after_or_equal:starts_at',
            'minutes_writer_id' => 'required|uuid|exists:users,id',
            'validator_id' => 'required|uuid|exists:users,id',
            'agenda' => 'nullable|string',
            'minutes_template_file' => 'nullable|file|mimes:doc,docx|max:20480',
            'priority' => 'required|in:low,normal,high,urgent',
            'confidentiality' => 'required|in:public,internal,confidential',
            'diffusion_email_subject' => 'nullable|string|max:255',
            'diffusion_email_body' => 'nullable|string|max:5000',
            'diffusion_ack_required' => 'nullable|boolean',
            'recurrence_type' => 'nullable|in:none,daily,weekly,monthly,yearly',
            'recurrence_until' => 'nullable|date|after_or_equal:today',
            'participants' => 'nullable|array',
            'participants.*' => 'uuid|exists:users,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480',
        ]);

        $allowedUserIds = $this->resolveScopeUserIds($scope);
        if (!in_array((string) $validated['minutes_writer_id'], $allowedUserIds, true)) {
            return back()->withInput()->with('error', 'Le rédacteur doit appartenir à la même entité sous tutelle.');
        }
        if (!in_array((string) $validated['validator_id'], $allowedUserIds, true)) {
            return back()->withInput()->with('error', 'Le validateur doit appartenir à la même entité sous tutelle.');
        }
        if ((string) $validated['validator_id'] === (string) $validated['minutes_writer_id']) {
            return back()->withInput()->with('error', 'Le validateur doit être différent du rédacteur.');
        }

        $requestedParticipants = array_map('strval', (array) ($validated['participants'] ?? []));
        $forbiddenParticipants = array_diff($requestedParticipants, $allowedUserIds);
        if (!empty($forbiddenParticipants)) {
            return back()->withInput()->with('error', 'Tous les participants doivent appartenir à la même entité sous tutelle.');
        }

        $startsAt = now()->parse($validated['starts_at']);
        $endsAt = now()->parse($validated['ends_at']);

        $overlapExists = Meeting::where('meeting_room_id', $validated['meeting_room_id'])
            ->whereIn('status', ['planned', 'in_progress'])
            ->where(function ($query) use ($startsAt, $endsAt) {
                $query->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($overlapExists) {
            return back()->withInput()->with('error', 'La salle est déjà réservée sur ce créneau horaire.');
        }

        $attachments = [];
        foreach ((array) $request->file('attachments', []) as $file) {
            ClamAvScanner::scan($file, 'reunions');
            $path = $file->store('meetings/attachments', 'public');
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => '/storage/' . $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $minutesTemplatePath = null;
        if ($request->hasFile('minutes_template_file')) {
            $tplFile = $request->file('minutes_template_file');
            ClamAvScanner::scan($tplFile, 'reunions');
            $tplStorePath = $tplFile->storeAs(
                'meetings/templates/' . Str::uuid(),
                $tplFile->getClientOriginalName(),
                'public'
            );
            $minutesTemplatePath = '/storage/' . $tplStorePath;
        }

        $meeting = Meeting::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'meeting_type' => $validated['meeting_type'],
            'meeting_room_id' => $validated['meeting_room_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'processing_deadline' => !empty($validated['processing_deadline']) ? now()->parse($validated['processing_deadline']) : null,
            'estimated_duration_minutes' => $startsAt->diffInMinutes($endsAt),
            'organizer_id' => Auth::id(),
            'minutes_writer_id' => $validated['minutes_writer_id'],
            'validator_id' => $validated['validator_id'],
            'issuing_administration_id' => $scope['administration_id'],
            'sub_entity_code' => $scope['sub_entity_code'],
            'agenda' => $validated['agenda'] ?? null,
            'minutes_template' => $minutesTemplatePath,
            'minutes_content' => null,
            'attachments' => $attachments,
            'priority' => $validated['priority'],
            'confidentiality' => $validated['confidentiality'],
            'status' => 'planned',
            'workflow_status' => 'draft',
            'review_requested' => false,
            'validation_requested_at' => null,
            'validated_by' => null,
            'validated_at' => null,
            'diffusion_email_subject' => $validated['diffusion_email_subject'] ?? null,
            'diffusion_email_body' => $validated['diffusion_email_body'] ?? null,
            'diffusion_ack_required' => (bool) ($validated['diffusion_ack_required'] ?? false),
            'recurrence_type' => $validated['recurrence_type'] ?? 'none',
            'recurrence_until' => $validated['recurrence_until'] ?? null,
            'recurrence_exceptions' => [],
            'qr_token' => (string) Str::uuid(),
            'qr_valid_from' => $startsAt,
            'qr_valid_until' => $endsAt,
        ]);

        foreach ((array) ($validated['participants'] ?? []) as $userId) {
            $participantUser = User::find($userId);
            if (!$participantUser) {
                continue;
            }

            MeetingParticipant::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $participantUser->id],
                [
                    'email' => $participantUser->email,
                    'full_name' => $participantUser->name,
                    'participant_role' => 'participant',
                    'is_external' => false,
                    'invitation_status' => 'sent',
                ]
            );
        }

        return redirect()->route('meetings.show', $meeting)->with('success', 'Réunion créée avec succès.');
    }

    public function show(Meeting $meeting)
    {
        $this->guardPermission('meetings.view');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $this->abortIfMeetingOutsideScope($meeting);

        $meeting->load(['room', 'organizer', 'minutesWriter', 'validator', 'validatedByUser', 'participants.user', 'attendances', 'minutesVersions.creator']);

        $qrUrl = route('meetings.qr.show', ['token' => $meeting->qr_token]);

        $qrImageDataUri = null;
        try {
            $qrResult = Builder::create()
                ->writer(new PngWriter())
                ->data($qrUrl)
                ->size(360)
                ->margin(8)
                ->build();

            $qrImageDataUri = 'data:image/png;base64,' . base64_encode($qrResult->getString());
        } catch (\Throwable $e) {
            // En cas d'erreur du moteur QR, on garde au minimum le lien scannable/copiable.
            $qrImageDataUri = null;
        }

        $currentUserId = (string) Auth::id();
        $isWriter    = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
        $isValidator = (string) ($meeting->validator_id    ?? '') === $currentUserId;
        $isOrganizer = (string) ($meeting->organizer_id   ?? '') === $currentUserId;

        return view('meetings.show', compact('meeting', 'qrUrl', 'qrImageDataUri', 'isWriter', 'isValidator', 'isOrganizer'));
    }

    public function updateMinutes(Request $request, Meeting $meeting)
    {
        $this->guardPermission('meetings.minutes');
        $this->abortIfMeetingOutsideScope($meeting);

        $currentUserId = (string) Auth::id();
        $isWriter = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
        $isValidator = (string) ($meeting->validator_id ?? '') === $currentUserId;
        if (!$isWriter && !$isValidator) {
            return back()->with('error', 'Seul le rédacteur ou le validateur peut modifier le compte rendu.');
        }

        $validated = $request->validate([
            'minutes_content' => 'required|string',
            'minutes_template' => 'nullable|string',
            'note' => 'nullable|string|max:255',
        ]);

        $meeting->minutes_content = $validated['minutes_content'];
        if (array_key_exists('minutes_template', $validated)) {
            $meeting->minutes_template = $validated['minutes_template'];
        }
        $meeting->save();

        $this->appendMinutesVersion($meeting, $validated['note'] ?? 'Mise à jour du compte rendu');

        return redirect()->route('meetings.show', $meeting)->with('success', 'Compte rendu mis à jour.');
    }

    public function workflow(Request $request, Meeting $meeting)
    {
        $this->guardPermission('meetings.create');
        $this->abortIfMeetingOutsideScope($meeting);

        $validated = $request->validate([
            'action' => 'required|in:submit_validation,validate,publish,request_review,sign_writer',
            'review_comment' => 'nullable|string|max:2000',
            'signature' => 'nullable|string',
        ]);

        $action = (string) $validated['action'];

        if ($action === 'submit_validation') {
            if ($meeting->workflow_status !== 'draft') {
                return back()->with('error', 'Cette transition n\'est pas autorisée depuis le statut actuel.');
            }

            if ((string) $meeting->minutes_writer_id !== (string) Auth::id()) {
                return back()->with('error', 'Seul le rédacteur peut envoyer le compte rendu en validation.');
            }

            if (empty($meeting->validator_id)) {
                return back()->with('error', 'Aucun validateur n\'est défini pour cette réunion.');
            }

            $meeting->workflow_status = 'in_validation';
            $meeting->review_requested = false;
            $meeting->review_comment = null;
            $meeting->validation_requested_at = now();
            $meeting->validated_by = null;
            $meeting->validated_at = null;
            $meeting->save();
            $this->appendMinutesVersion($meeting, 'Passage en validation');
            $this->sendValidationRequestEmail($meeting);

            return back()->with('success', 'Compte rendu envoyé en validation et notification email transmise au validateur.');
        }

        if ($action === 'validate') {
            if ($meeting->workflow_status !== 'in_validation') {
                return back()->with('error', 'Le compte rendu doit être en validation.');
            }

            if ((string) $meeting->validator_id !== (string) Auth::id()) {
                return back()->with('error', 'Seul le validateur désigné peut valider ce compte rendu.');
            }

            $meeting->workflow_status = 'validated';
            $meeting->review_requested = false;
            $meeting->review_comment = null;
            $meeting->validated_by = Auth::id();
            $meeting->validated_at = now();
            $meeting->save();
            $this->appendMinutesVersion($meeting, 'Compte rendu validé par ' . (Auth::user()?->name ?? 'Validateur'));

            return back()->with('success', 'Compte rendu validé.');
        }

        if ($action === 'request_review') {
            if ((string) $meeting->validator_id !== (string) Auth::id()) {
                return back()->with('error', 'Seul le validateur désigné peut demander une relecture.');
            }

            if ($meeting->workflow_status !== 'in_validation') {
                return back()->with('error', 'La relecture ne peut être demandée que pendant la validation.');
            }

            $meeting->workflow_status = 'draft';
            $meeting->review_requested = true;
            $meeting->review_comment = (string) ($validated['review_comment'] ?? 'Relecture demandée');
            $meeting->validated_by = null;
            $meeting->validated_at = null;
            $meeting->save();
            $this->appendMinutesVersion($meeting, 'Demande de relecture');

            return back()->with('success', 'Demande de relecture enregistrée.');
        }

        if ($action === 'sign_writer') {
            if ((string) $meeting->minutes_writer_id !== (string) Auth::id()) {
                return back()->with('error', 'Seul le rédacteur peut signer le compte rendu.');
            }

            if (!empty($validated['signature']) && str_starts_with($validated['signature'], 'data:image/')) {
                $raw = explode(',', $validated['signature'], 2)[1] ?? '';
                $binary = base64_decode($raw, true);
                if ($binary !== false) {
                    $fileName = 'meetings/minutes-signatures/' . Str::uuid() . '.png';
                    Storage::disk('public')->put($fileName, $binary);
                    $meeting->writer_signature_path = '/storage/' . $fileName;
                }
            }

            $meeting->writer_signed_at = now();
            $meeting->save();
            $this->appendMinutesVersion($meeting, 'Signature électronique du rédacteur');

            return back()->with('success', 'Signature enregistrée.');
        }

        // publish
        if ($meeting->workflow_status !== 'validated') {
            return back()->with('error', 'Le compte rendu doit être validé avant publication.');
        }

        if (empty($meeting->writer_signed_at)) {
            return back()->with('error', 'Le rédacteur doit signer avant publication.');
        }

        $meeting->workflow_status = 'published';
        $meeting->published_at = now();
        $meeting->save();

        $this->appendMinutesVersion($meeting, 'Compte rendu publié');
        $this->sendDiffusionEmails($meeting);

        return back()->with('success', 'Compte rendu publié et diffusé automatiquement par email.');
    }

    public function reporting(Request $request)
    {
        $this->guardPermission('meetings.view');
        if (!$this->isMeetingsModuleReady()) {
            return redirect()->route('dashboard')
                ->with('error', 'Le module Reunions n\'est pas encore initialise sur ce serveur. Lancez les migrations.');
        }

        $scope = $this->resolveCurrentUserScope();
        $year = (int) ($request->get('year') ?: now()->year);

        $meetingsQuery = Meeting::query()
            ->when($scope !== null, function ($query) use ($scope) {
                $query->where('issuing_administration_id', $scope['administration_id'])
                    ->where('sub_entity_code', $scope['sub_entity_code']);
            }, function ($query) {
                $query->where('organizer_id', Auth::id());
            })
            ->whereYear('starts_at', $year);

        $meetings = $meetingsQuery->with(['room'])->get();
        $meetingIds = $meetings->pluck('id');

        $participantsByMeeting = MeetingParticipant::query()
            ->whereIn('meeting_id', $meetingIds)
            ->selectRaw('meeting_id, COUNT(*) as total')
            ->groupBy('meeting_id')
            ->pluck('total', 'meeting_id');

        $attendancesByMeeting = MeetingAttendance::query()
            ->whereIn('meeting_id', $meetingIds)
            ->selectRaw('meeting_id, COUNT(*) as total')
            ->groupBy('meeting_id')
            ->pluck('total', 'meeting_id');

        $totalMeetings = $meetings->count();
        $participationRates = $meetings->map(function ($m) use ($participantsByMeeting, $attendancesByMeeting) {
            $p = (int) ($participantsByMeeting[$m->id] ?? 0);
            $a = (int) ($attendancesByMeeting[$m->id] ?? 0);
            if ($p <= 0) {
                return 0;
            }
            return min(100, round(($a / $p) * 100, 2));
        });
        $avgParticipation = $participationRates->count() > 0 ? round($participationRates->avg(), 2) : 0;

        $byType = $meetings->groupBy('meeting_type')->map->count();
        $byRoom = $meetings->groupBy(fn ($m) => (string) ($m->room?->name ?: 'Sans salle'))->map->count();
        $byMonth = $meetings->groupBy(fn ($m) => $m->starts_at?->format('Y-m') ?: 'N/A')->map->count();

        $userStats = User::query()
            ->whereIn('id', $meetings->pluck('organizer_id')->unique())
            ->get(['id', 'name'])
            ->map(function ($user) use ($meetings) {
                $organized = $meetings->where('organizer_id', $user->id);
                $count = $organized->count();
                $durationMinutes = $organized->sum('estimated_duration_minutes');

                return [
                    'name' => $user->name,
                    'organized_count' => $count,
                    'participation_rate' => $count > 0 ? 100 : 0,
                    'time_minutes' => (int) $durationMinutes,
                ];
            });

        return view('meetings.reporting', compact(
            'year',
            'totalMeetings',
            'avgParticipation',
            'byType',
            'byRoom',
            'byMonth',
            'userStats'
        ));
    }

    public function exportCsv(Request $request)
    {
        $this->guardPermission('meetings.view');
        $scope = $this->resolveCurrentUserScope();
        $type = (string) $request->get('type', 'meetings');
        if (!in_array($type, ['meetings', 'attendances', 'minutes'], true)) {
            $type = 'meetings';
        }

        $filename = 'meetings_' . $type . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($scope, $type) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");

            if ($type === 'meetings') {
                fputcsv($out, ['Titre', 'Type', 'Date debut', 'Date fin', 'Salle', 'Statut', 'Workflow'], ';');
                Meeting::query()
                    ->with('room')
                    ->when($scope !== null, function ($q) use ($scope) {
                        $q->where('issuing_administration_id', $scope['administration_id'])
                          ->where('sub_entity_code', $scope['sub_entity_code']);
                    }, function ($q) {
                        $q->where('organizer_id', Auth::id());
                    })
                    ->latest('starts_at')
                    ->chunk(200, function ($meetings) use ($out) {
                        foreach ($meetings as $m) {
                            fputcsv($out, [
                                $m->title,
                                $m->meeting_type,
                                optional($m->starts_at)->format('d/m/Y H:i'),
                                optional($m->ends_at)->format('d/m/Y H:i'),
                                $m->room?->name,
                                $m->status,
                                $m->workflow_status,
                            ], ';');
                        }
                    });
            } elseif ($type === 'attendances') {
                fputcsv($out, ['Reunion', 'Nom', 'Email', 'Telephone', 'Heure signature'], ';');
                MeetingAttendance::query()
                    ->whereIn('meeting_id', Meeting::query()
                        ->when($scope !== null, function ($q) use ($scope) {
                            $q->where('issuing_administration_id', $scope['administration_id'])
                                ->where('sub_entity_code', $scope['sub_entity_code']);
                        }, function ($q) {
                            $q->where('organizer_id', Auth::id());
                        })
                        ->pluck('id'))
                    ->with('meeting:id,title')
                    ->latest('signed_at')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row->meeting?->title,
                                $row->full_name,
                                $row->email,
                                $row->phone,
                                optional($row->signed_at)->format('d/m/Y H:i:s'),
                            ], ';');
                        }
                    });
            } else {
                fputcsv($out, ['Reunion', 'Workflow', 'Date publication', 'Version courante'], ';');
                Meeting::query()
                    ->when($scope !== null, function ($q) use ($scope) {
                        $q->where('issuing_administration_id', $scope['administration_id'])
                            ->where('sub_entity_code', $scope['sub_entity_code']);
                    }, function ($q) {
                        $q->where('organizer_id', Auth::id());
                    })
                    ->withCount('minutesVersions')
                    ->latest('starts_at')
                    ->chunk(200, function ($rows) use ($out) {
                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row->title,
                                $row->workflow_status,
                                optional($row->published_at)->format('d/m/Y H:i'),
                                (int) $row->minutes_versions_count,
                            ], ';');
                        }
                    });
            }

            fclose($out);
        }, 200, $headers);
    }

    public function exportSummaryPdf(Request $request)
    {
        $this->guardPermission('meetings.view');
        $scope = $this->resolveCurrentUserScope();
        $mode = in_array($request->get('mode'), ['monthly', 'annual'], true) ? $request->get('mode') : 'monthly';
        $year = (int) ($request->get('year') ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);

        $query = Meeting::query()
            ->when($scope !== null, function ($q) use ($scope) {
                $q->where('issuing_administration_id', $scope['administration_id'])
                    ->where('sub_entity_code', $scope['sub_entity_code']);
            }, function ($q) {
                $q->where('organizer_id', Auth::id());
            })
            ->whereYear('starts_at', $year);

        if ($mode === 'monthly') {
            $query->whereMonth('starts_at', $month);
        }

        $meetings = $query->with(['room', 'organizer'])->orderBy('starts_at')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.summary_pdf', [
            'meetings' => $meetings,
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('synthese_reunions_' . $mode . '_' . now()->format('Ymd_His') . '.pdf');
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

    private function isMeetingsModuleReady(): bool
    {
        return Schema::hasTable('meetings')
            && Schema::hasTable('meeting_rooms')
            && Schema::hasTable('meeting_participants')
            && Schema::hasTable('meeting_attendances')
            && Schema::hasTable('user_direction_assignments');
    }

    private function resolveScopeUserIds(array $scope): array
    {
        return DB::table('user_direction_assignments')
            ->where('direction_scope_id', $scope['administration_id'])
            ->whereRaw("UPPER(COALESCE(sub_entity_code, '')) = ?", [$scope['sub_entity_code']])
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function appendMinutesVersion(Meeting $meeting, string $note): void
    {
        $lastVersion = MeetingMinutesVersion::query()
            ->where('meeting_id', $meeting->id)
            ->max('version_no');

        MeetingMinutesVersion::create([
            'meeting_id' => $meeting->id,
            'version_no' => ((int) $lastVersion) + 1,
            'content' => (string) ($meeting->minutes_content ?? ''),
            'created_by' => Auth::id(),
            'note' => $note,
            'workflow_status' => (string) ($meeting->workflow_status ?? 'draft'),
        ]);
    }

    private function sendDiffusionEmails(Meeting $meeting): void
    {
        $meeting->loadMissing(['participants.user', 'attendances', 'room']);

        $emails = collect();
        foreach ($meeting->participants as $participant) {
            $email = trim((string) ($participant->email ?: $participant->user?->email ?: ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails->push($email);
            }
        }
        foreach ($meeting->attendances as $attendance) {
            $email = trim((string) ($attendance->email ?: ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails->push($email);
            }
        }

        $emails = $emails->unique()->values();
        if ($emails->isEmpty()) {
            return;
        }

        $subject = trim((string) ($meeting->diffusion_email_subject ?: 'Compte rendu de réunion - ' . $meeting->title));
        $bodyTemplate = (string) ($meeting->diffusion_email_body ?: "Bonjour,\n\nVeuillez trouver ci-joint le compte rendu de la réunion {meeting_title}.\nDate: {meeting_date}\nSalle: {meeting_room}\n\nCordialement.");
        $body = strtr($bodyTemplate, [
            '{meeting_title}' => (string) $meeting->title,
            '{meeting_date}' => (string) optional($meeting->starts_at)->format('d/m/Y H:i'),
            '{meeting_room}' => (string) ($meeting->room?->name ?: 'N/A'),
        ]);
        if ($meeting->diffusion_ack_required) {
            $body .= "\n\nMerci de répondre à cet e-mail pour accusé de réception.";
        }

        $minutesPdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.minutes_pdf', ['meeting' => $meeting])->output();
        $attendancePdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('meetings.attendance_list_pdf', [
            'meeting' => $meeting,
            'attendances' => $meeting->attendances,
        ])->output();

        Mail::raw($body, function ($message) use ($emails, $subject, $minutesPdf, $attendancePdf, $meeting) {
            $to = $emails->first();
            $bcc = $emails->slice(1)->all();

            $message->to($to)->subject($subject);
            if (!empty($bcc)) {
                $message->bcc($bcc);
            }

            $message->attachData($minutesPdf, 'compte_rendu_' . Str::slug($meeting->title) . '.pdf', [
                'mime' => 'application/pdf',
            ]);
            $message->attachData($attendancePdf, 'liste_presence_' . Str::slug($meeting->title) . '.pdf', [
                'mime' => 'application/pdf',
            ]);

            foreach ((array) ($meeting->attachments ?? []) as $attachment) {
                $publicPath = (string) ($attachment['path'] ?? '');
                if (!str_starts_with($publicPath, '/storage/')) {
                    continue;
                }

                $relativePath = ltrim(substr($publicPath, strlen('/storage/')), '/');
                if (!Storage::disk('public')->exists($relativePath)) {
                    continue;
                }

                $message->attachData(
                    Storage::disk('public')->get($relativePath),
                    (string) ($attachment['name'] ?? basename($relativePath)),
                    ['mime' => (string) ($attachment['mime'] ?? Storage::disk('public')->mimeType($relativePath) ?? 'application/octet-stream')]
                );
            }
        });
    }

    private function sendValidationRequestEmail(Meeting $meeting): void
    {
        $meeting->loadMissing(['validator', 'minutesWriter']);

        $validatorEmail = trim((string) ($meeting->validator?->email ?? ''));
        if ($validatorEmail === '' || !filter_var($validatorEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $showUrl = route('meetings.show', $meeting);
        $writerName = (string) ($meeting->minutesWriter?->name ?: 'Redacteur');
        $subject = 'Validation requise - Compte rendu reunion: ' . $meeting->title;
        $body = "Bonjour,\n\n"
            . "Vous avez ete designe comme validateur pour le compte rendu de la reunion suivante:\n"
            . "- Titre: {$meeting->title}\n"
            . "- Date: " . (string) optional($meeting->starts_at)->format('d/m/Y H:i') . "\n"
            . "- Redacteur: {$writerName}\n\n"
            . "Veuillez ouvrir la reunion pour corriger/valider le compte rendu:\n"
            . "{$showUrl}\n\n"
            . "Cordialement.";

        Mail::raw($body, function ($message) use ($validatorEmail, $subject) {
            $message->to($validatorEmail)->subject($subject);
        });
    }

    // -------------------------------------------------------------------------
    // Modèle de compte rendu – accès OnlyOffice
    // -------------------------------------------------------------------------

    /**
     * Sert le fichier template via URL signée (accessible par OnlyOffice sans session).
     */
    public function templateFile(Request $request, Meeting $meeting)
    {
        $expires  = (int) $request->query('expires');
        $access   = (string) $request->query('access');
        $expected = hash_hmac('sha256', 'tpl|' . $meeting->id . '|' . $expires, (string) config('app.key'));

        if (!hash_equals($expected, $access) || now()->timestamp > $expires) {
            abort(403, 'Token expiré ou invalide.');
        }

        $templatePath = (string) ($meeting->minutes_template ?? '');
        if (!$templatePath || !str_starts_with($templatePath, '/storage/')) {
            abort(404, 'Aucun modèle de compte rendu pour cette réunion.');
        }

        $relative = ltrim(str_replace('/storage/', '', $templatePath), '/');
        if (!Storage::disk('public')->exists($relative)) {
            abort(404, 'Fichier modèle introuvable sur le disque.');
        }

        return response()->file(Storage::disk('public')->path($relative), [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="' . basename($relative) . '"',
        ]);
    }

    /**
     * Génère la configuration OnlyOffice pour ouvrir le modèle dans l'éditeur.
     */
    public function templateOoConfig(Request $request, Meeting $meeting)
    {
        $this->abortIfMeetingOutsideScope($meeting);

        $currentUserId = (string) Auth::id();
        $isWriter = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
        $isValidator = (string) ($meeting->validator_id ?? '') === $currentUserId;
        if (!$isWriter && !$isValidator) {
            return response()->json(['error' => 'Seul le rédacteur ou le validateur peut ouvrir ce modèle dans OnlyOffice.'], 403);
        }

        $templatePath = (string) ($meeting->minutes_template ?? '');
        if (!$templatePath || !str_starts_with($templatePath, '/storage/')) {
            return response()->json(['error' => 'Aucun modèle de compte rendu uploadé pour cette réunion.'], 404);
        }

        $relative = ltrim(str_replace('/storage/', '', $templatePath), '/');
        if (!Storage::disk('public')->exists($relative)) {
            return response()->json(['error' => 'Fichier modèle introuvable sur le disque.'], 404);
        }

        $onlyofficeUrl = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';
        $appPublicUrl  = AppSetting::where('key', 'app_public_url')->value('value') ?: '';

        if (!$onlyofficeUrl) {
            return response()->json(['error' => 'OnlyOffice non configuré. Contactez l\'administrateur.'], 400);
        }

        $expires    = now()->addHours(8)->timestamp;
        $access     = hash_hmac('sha256', 'tpl|' . $meeting->id . '|' . $expires, (string) config('app.key'));
        $signedPath = route('meetings.template.file', [
            'meeting' => $meeting->id,
            'expires' => $expires,
            'access'  => $access,
        ], false);

        $docUrl = $appPublicUrl
            ? rtrim($appPublicUrl, '/') . $signedPath
            : url($signedPath);

        $callbackAccess = hash_hmac('sha256', 'tplcb|' . $meeting->id, (string) config('app.key'));
        $callbackBase   = $appPublicUrl ? rtrim($appPublicUrl, '/') : rtrim((string) config('app.url'), '/');
        $callbackUrl    = $callbackBase . '/api/oo-callback/meeting-template/' . $meeting->id . '?access=' . $callbackAccess;

        $fileExt = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'docx');

        $payload = [
            'document' => [
                'fileType'    => $fileExt,
                'key'         => 'meeting-tpl-' . $meeting->id . '-' . ($meeting->updated_at?->timestamp ?? time()),
                'title'       => 'Modèle – ' . $meeting->title,
                'url'         => $docUrl,
                'permissions' => ['edit' => true, 'download' => true, 'print' => true],
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'mode'        => 'edit',
                'lang'        => 'fr',
                'callbackUrl' => $callbackUrl,
                'user'        => ['id' => 'u-' . Auth::id(), 'name' => Auth::user()?->name ?? 'Utilisateur'],
                'customization' => [
                    'autosave'      => true,
                    'compactHeader' => true,
                ],
            ],
        ];

        return response()->json([
            'onlyofficeUrl' => rtrim($onlyofficeUrl, '/'),
            'config'        => $payload,
        ]);
    }

    /**
     * Callback OnlyOffice pour sauvegarder le modèle après édition.
     */
    public function templateOoCallback(Request $request, Meeting $meeting)
    {
        $access   = (string) $request->query('access');
        $expected = hash_hmac('sha256', 'tplcb|' . $meeting->id, (string) config('app.key'));

        if (!hash_equals($expected, $access)) {
            return response()->json(['error' => 1]);
        }

        $body   = $request->all();
        $status = (int) ($body['status'] ?? 0);

        // Status 2 = prêt à sauvegarder, 6 = forcer la sauvegarde
        if (in_array($status, [2, 6], true)) {
            $downloadUrl = $body['url'] ?? null;
            if ($downloadUrl) {
                $ctx     = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                $content = file_get_contents($downloadUrl, false, $ctx);
                if ($content !== false) {
                    $templatePath = (string) ($meeting->minutes_template ?? '');
                    if ($templatePath && str_starts_with($templatePath, '/storage/')) {
                        $relative = ltrim(str_replace('/storage/', '', $templatePath), '/');
                        Storage::disk('public')->put($relative, $content);
                        // Le template source a été modifié: invalider l'analyse/scellé précédent.
                        $meeting->template_sealed_path = null;
                        $meeting->template_variables = null;
                        $meeting->save();
                    }
                }
            }
        }

        return response()->json(['error' => 0]);
    }

        // -------------------------------------------------------------------------
        // Analyse du modèle DOCX : détection des variables {{ var }} et scellement
        // des zones de signature @@@  (10 cm × 8 cm, centrées, encadrées).
        // -------------------------------------------------------------------------

        /**
         * Analyse le DOCX uploadé : extrait les variables {{ var }}, scelle les
         * zones @@@ (table centrée 10×8 cm encadrée), sauvegarde le DOCX modifié
         * et stocke la liste des variables en base.
         */
        public function analyzeTemplate(Request $request, Meeting $meeting)
        {
            $this->abortIfMeetingOutsideScope($meeting);

            $currentUserId = (string) Auth::id();
            $isWriter    = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
            $isValidator = (string) ($meeting->validator_id    ?? '') === $currentUserId;
            $isOrganizer = (string) ($meeting->organizer_id   ?? '') === $currentUserId;
            if (!$isWriter && !$isValidator && !$isOrganizer) {
                return response()->json(['error' => 'Accès refusé.'], 403);
            }

            $templatePath = (string) ($meeting->minutes_template ?? '');
            if (!$templatePath || !str_starts_with($templatePath, '/storage/')) {
                return response()->json(['error' => 'Aucun modèle DOCX uploadé pour cette réunion.'], 404);
            }

            $relative = ltrim(str_replace('/storage/', '', $templatePath), '/');
            if (!Storage::disk('public')->exists($relative)) {
                return response()->json(['error' => 'Fichier modèle introuvable sur le disque.'], 404);
            }

            $docxPath = Storage::disk('public')->path($relative);

            // --- Lire le DOCX comme ZIP et extraire word/document.xml -------------
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) !== true) {
                return response()->json(['error' => 'Impossible d\'ouvrir le fichier DOCX.'], 500);
            }
            $xmlContent = $zip->getFromName('word/document.xml');
            if ($xmlContent === false) {
                $zip->close();
                return response()->json(['error' => 'Structure DOCX invalide (word/document.xml manquant).'], 500);
            }
            $zip->close();

            // --- Détecter les variables {{ nom_variable }} -----------------------
            $textForScan = $this->extractDocxText($xmlContent);
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u', $textForScan, $varMatches);
            $variables = array_values(array_unique($varMatches[1] ?? []));

            // --- Sceller les zones @@@ -------------------------------------------
            $signatureCount = substr_count($textForScan, '@@@');
            $sealedXml      = $this->sealSignatureZones($xmlContent);

            // --- Écrire le DOCX scellé (copie du fichier original) ---------------
            $dir            = dirname($relative);
            $baseName       = pathinfo($relative, PATHINFO_FILENAME);
            $sealedRelative = $dir . '/' . $baseName . '_sealed.docx';
            $sealedAbsPath  = Storage::disk('public')->path($sealedRelative);

            copy($docxPath, $sealedAbsPath);

            $zipOut = new \ZipArchive();
            if ($zipOut->open($sealedAbsPath) !== true) {
                return response()->json(['error' => 'Impossible de créer le fichier DOCX scellé.'], 500);
            }
            $zipOut->deleteName('word/document.xml');
            $zipOut->addFromString('word/document.xml', $sealedXml);
            $zipOut->close();

            // --- Persister en base -----------------------------------------------
            $meeting->update([
                'template_variables'   => $variables,
                'template_sealed_path' => '/storage/' . $sealedRelative,
            ]);

            return response()->json([
                'variables'      => $variables,
                'sealedPath'     => '/storage/' . $sealedRelative,
                'signatureZones' => $signatureCount,
            ]);
        }

        /**
         * Remplace chaque paragraphe contenant @@@ par une table Word centrée
         * 10 cm (largeur) × 8 cm (hauteur), encadrée, fond bleu pâle.
         */
        private function sealSignatureZones(string $xml): string
        {
            // Table OOXML : 1 ligne / 1 colonne — 10 cm = 5670 twips, 8 cm = 4536 twips
            $signatureTableXml =
                '<w:tbl>' .
                '<w:tblPr>' .
                '<w:jc w:val="center"/>' .
                '<w:tblW w:w="5670" w:type="dxa"/>' .
                '<w:tblBorders>' .
                '<w:top    w:val="single" w:sz="12" w:space="0" w:color="2453D6"/>' .
                '<w:left   w:val="single" w:sz="12" w:space="0" w:color="2453D6"/>' .
                '<w:bottom w:val="single" w:sz="12" w:space="0" w:color="2453D6"/>' .
                '<w:right  w:val="single" w:sz="12" w:space="0" w:color="2453D6"/>' .
                '<w:insideH w:val="none"/><w:insideV w:val="none"/>' .
                '</w:tblBorders>' .
                '<w:tblCellMar>' .
                '<w:top w:w="120" w:type="dxa"/><w:left w:w="120" w:type="dxa"/>' .
                '<w:bottom w:w="120" w:type="dxa"/><w:right w:w="120" w:type="dxa"/>' .
                '</w:tblCellMar>' .
                '</w:tblPr>' .
                '<w:tr>' .
                '<w:trPr><w:trHeight w:val="4536" w:hRule="exact"/></w:trPr>' .
                '<w:tc>' .
                '<w:tcPr>' .
                '<w:tcW w:w="5670" w:type="dxa"/>' .
                '<w:vAlign w:val="center"/>' .
                '<w:shd w:val="clear" w:color="auto" w:fill="EEF2FF"/>' .
                '</w:tcPr>' .
                '<w:p>' .
                '<w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="0"/></w:pPr>' .
                '<w:r>' .
                '<w:rPr>' .
                '<w:rFonts w:ascii="Arial" w:hAnsi="Arial"/>' .
                '<w:sz w:val="24"/><w:szCs w:val="24"/>' .
                '<w:color w:val="2453D6"/><w:b/>' .
                '</w:rPr>' .
                '<w:t>&#9312; Zone de signature</w:t>' .
                '</w:r>' .
                '</w:p>' .
                '</w:tc>' .
                '</w:tr>' .
                '</w:tbl>' .
                '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="0" w:after="0"/></w:pPr></w:p>';

            // Trouver chaque <w:p>…</w:p> dont le texte contient @@@ et le remplacer
            $sealed = preg_replace_callback(
                '/<w:p[ >][\s\S]*?<\/w:p>/',  // pas de /U : *? doit rester lazy
                static function (array $m) use ($signatureTableXml): string {
                    if (str_contains(strip_tags($m[0]), '@@@')) {
                        return $signatureTableXml;
                    }
                    return $m[0];
                },
                $xml
            );

            return $sealed ?? $xml;
        }

        /**
         * Génère un DOCX final à partir du modèle scellé en remplaçant les
         * variables {{ nom }} par les valeurs soumises via le formulaire.
         * Retourne le fichier en téléchargement direct.
         */
        public function generateFromTemplate(Request $request, Meeting $meeting)
        {
            $this->abortIfMeetingOutsideScope($meeting);

            $currentUserId = (string) Auth::id();
            $isWriter    = (string) ($meeting->minutes_writer_id ?? '') === $currentUserId;
            $isValidator = (string) ($meeting->validator_id    ?? '') === $currentUserId;
            $isOrganizer = (string) ($meeting->organizer_id   ?? '') === $currentUserId;
            if (!$isWriter && !$isValidator && !$isOrganizer) {
                abort(403);
            }

            // Toujours partir du template source courant pour éviter qu'un scellé
            // ancien supprime encore du contenu après modification du modèle.
            $templatePath = (string) ($meeting->minutes_template ?? '');

            if (!$templatePath || !str_starts_with($templatePath, '/storage/')) {
                abort(404, 'Aucun modèle disponible pour cette réunion.');
            }

            $relative = ltrim(str_replace('/storage/', '', $templatePath), '/');
            if (!Storage::disk('public')->exists($relative)) {
                abort(404, 'Fichier modèle introuvable.');
            }

            $docxPath = Storage::disk('public')->path($relative);

            $zip = new \ZipArchive();
            if ($zip->open($docxPath) !== true) {
                abort(500, 'Impossible d\'ouvrir le fichier DOCX.');
            }
            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xmlContent === false) {
                abort(500, 'Structure DOCX invalide.');
            }

            // --- Sceller puis substituer les variables ---------------------------
            $xmlContent = $this->sealSignatureZones($xmlContent);
            $values = (array) $request->input('variables', []);
            $xmlContent = $this->replaceTemplateVariablesInDocxXml($xmlContent, $values);

            // --- Créer le DOCX final ---------------------------------------------
            $tmpFile = tempnam(sys_get_temp_dir(), 'mtg_doc_') . '.docx';
            copy($docxPath, $tmpFile);

            $zipOut = new \ZipArchive();
            if ($zipOut->open($tmpFile) === true) {
                $zipOut->deleteName('word/document.xml');
                $zipOut->addFromString('word/document.xml', $xmlContent);
                $zipOut->close();
            }

            $fileName = 'compte_rendu_' . Str::slug($meeting->title) . '_' . now()->format('Ymd') . '.docx';

            return response()->download($tmpFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);
        }

        /**
         * Extrait le texte DOCX en concaténant les noeuds w:t par paragraphe.
         * Cela rend visibles les placeholders même s'ils sont split sur plusieurs runs.
         */
        private function extractDocxText(string $xml): string
        {
            $dom = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            if (!$loaded) {
                return strip_tags($xml);
            }

            $xp = new \DOMXPath($dom);
            $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $out = [];
            $paragraphs = $xp->query('//w:p');
            foreach ($paragraphs as $p) {
                $buf = '';
                foreach ($xp->query('.//w:t', $p) as $t) {
                    $buf .= (string) $t->nodeValue;
                }
                $out[] = $buf;
            }

            return implode("\n", $out);
        }

        /**
         * Remplace les placeholders {{ var }} en travaillant au niveau texte de
         * chaque paragraphe DOCX, pour gérer les placeholders split en plusieurs runs.
         * Utilise exclusivement la manipulation de chaîne/regex pour éviter que
         * DOMDocument::saveXML() ne corrompe ou ne supprime des paragraphes Word.
         */
        private function replaceTemplateVariablesInDocxXml(string $xml, array $values): string
        {
            // Normalise les clés : espaces/tirets/underscores → underscore, minuscules
            $normalizedValues = [];
            foreach ($values as $k => $v) {
                $key = strtolower(preg_replace('/[\s\-_]+/', '_', trim((string) $k)));
                if ($key !== '') {
                    $normalizedValues[$key] = (string) $v;
                }
            }

            if (empty($normalizedValues)) {
                return $xml;
            }

            // Traite chaque <w:p>…</w:p> individuellement sans re-sérialiser tout le DOM
            $result = preg_replace_callback(
                '/<w:p[ >][\s\S]*?<\/w:p>/',  // pas de /U : *? doit rester lazy
                static function (array $m) use ($normalizedValues): string {
                    $paraXml = $m[0];

                    // Concatène le contenu de tous les <w:t> du paragraphe
                    if (!preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $paraXml, $tMatches)) {
                        return $paraXml;
                    }

                    $fullText = implode('', $tMatches[1]);

                    if (!str_contains($fullText, '{{')) {
                        return $paraXml;
                    }

                    // Remplace {{ var }} — accepte espaces, underscores et tirets dans le nom
                    $replaced = preg_replace_callback(
                        '/\{\{\s*([a-zA-Z0-9_\s\-]+?)\s*\}\}/u',
                        static function (array $mm) use ($normalizedValues): string {
                            $raw  = trim((string) ($mm[1] ?? ''));
                            // Normalisation identique aux clés
                            $key  = strtolower(preg_replace('/[\s\-_]+/', '_', $raw));
                            if (array_key_exists($key, $normalizedValues)) {
                                return $normalizedValues[$key];
                            }
                            // Correspondance floue : ignore tous les séparateurs
                            $flat = preg_replace('/[\s\-_]+/', '', strtolower($raw));
                            foreach ($normalizedValues as $nk => $nv) {
                                if (preg_replace('/[\s\-_]+/', '', $nk) === $flat) {
                                    return $nv;
                                }
                            }
                            return $mm[0]; // aucune valeur → laisser intact
                        },
                        $fullText
                    );

                    if ($replaced === null || $replaced === $fullText) {
                        return $paraXml;
                    }

                    // Réécrit dans le XML : premier <w:t> reçoit le texte complet,
                    // les suivants sont vidés — la structure OOXML reste intacte.
                    $firstDone = false;
                    $newPara = preg_replace_callback(
                        '/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s',
                        static function (array $tm) use ($replaced, &$firstDone): string {
                            if (!$firstDone) {
                                $firstDone = true;
                                preg_match('/^<w:t([^>]*)>/', $tm[0], $tagM);
                                $attrs = $tagM[1] ?? '';
                                // Ajouter xml:space="preserve" si le texte a des espaces aux bords
                                if (!str_contains($attrs, 'xml:space') &&
                                    (str_starts_with($replaced, ' ') || str_ends_with($replaced, ' '))) {
                                    $attrs .= ' xml:space="preserve"';
                                }
                                $safe = htmlspecialchars($replaced, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                                return "<w:t{$attrs}>{$safe}</w:t>";
                            }
                            return '<w:t/>';
                        },
                        $paraXml
                    );

                    return $newPara ?? $paraXml;
                },
                $xml
            );

            return $result ?? $xml;
        }
}
