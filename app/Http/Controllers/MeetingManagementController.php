<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRoom;
use App\Models\User;
use App\Services\MeetingMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modification, report et annulation d'une réunion ou de la suite d'une série.
 */
class MeetingManagementController extends MeetingController
{
    /** Champs repris tels quels ; seuls ceux que l'utilisateur change sont reportés sur la série. */
    private const SIMPLE_FIELDS = [
        'title', 'meeting_type', 'meeting_room_id', 'minutes_writer_id', 'validator_id', 'is_virtual', 'meeting_link',
        'agenda', 'priority', 'confidentiality', 'diffusion_email_subject', 'diffusion_email_body',
        'diffusion_ack_required', 'reminder_hours_before',
    ];

    public function edit(Meeting $meeting)
    {
        if ($refusal = $this->refuseManagement($meeting)) {
            return $refusal;
        }

        $meeting->load(['participants.user', 'room']);
        $scope = $this->meetingScope($meeting);
        $rooms = MeetingRoom::where('administration_id', $scope['administration_id'])
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('status', 'active')->where('maintenance_status', 'operational'))
                ->orWhere('id', $meeting->meeting_room_id))
            ->orderBy('name')
            ->get();
        $users = $this->selectableUsers($scope);
        $following = $this->targets($meeting, 'following')->count() - 1;
        $droppedNames = $meeting->participants
            ->filter(fn (MeetingParticipant $p) => $p->user_id && !$users->contains('id', $p->user_id))
            ->map(fn (MeetingParticipant $p) => $p->full_name ?: $p->user?->name)
            ->values();

        return view('meetings.create', compact('meeting', 'rooms', 'users', 'following', 'droppedNames'));
    }

    public function update(Request $request, Meeting $meeting)
    {
        if ($refusal = $this->refuseManagement($meeting)) {
            return $refusal;
        }

        $rules = $this->meetingRules() + [
            'apply_to' => 'nullable|in:one,following',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'string',
        ];
        unset($rules['recurrence_type'], $rules['recurrence_until']);
        $validated = $request->validate($rules);

        $scope = $this->meetingScope($meeting);
        [$externals, $error] = $this->checkPeople($validated, $scope);
        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        $targets = $this->targets($meeting, (string) ($validated['apply_to'] ?? 'one'));
        $base = $meeting->load('participants.user');

        // Ce que l'utilisateur a réellement changé sur la réunion ouverte.
        $input = [
            'is_virtual' => (bool) ($validated['is_virtual'] ?? false),
            'diffusion_ack_required' => (bool) ($validated['diffusion_ack_required'] ?? false),
            'reminder_hours_before' => isset($validated['reminder_hours_before']) ? (int) $validated['reminder_hours_before'] : $base->reminder_hours_before,
        ];
        $changes = [];
        foreach (self::SIMPLE_FIELDS as $field) {
            $value = array_key_exists($field, $input) ? $input[$field] : ($validated[$field] ?? null);
            $current = $field === 'reminder_hours_before' ? $base->reminderHours() : $base->{$field};
            if ($this->comparable($value) !== $this->comparable($current)) {
                $changes[$field] = $value;
            }
        }

        $newStart = now()->parse($validated['starts_at'])->startOfMinute();
        $newEnd = now()->parse($validated['ends_at'])->startOfMinute();
        $moved = $newStart->format('YmdHi') !== $base->starts_at->format('YmdHi') || $newEnd->format('YmdHi') !== $base->ends_at->format('YmdHi');
        $shift = $newStart->getTimestamp() - $base->starts_at->copy()->startOfMinute()->getTimestamp();
        $duration = $newEnd->getTimestamp() - $newStart->getTimestamp();
        $newDeadline = !empty($validated['processing_deadline']) ? now()->parse($validated['processing_deadline']) : null;
        $deadlineChanged = ($newDeadline?->format('YmdHi')) !== ($base->processing_deadline?->format('YmdHi'));

        // Participants ajoutés, retirés ou mis à jour par rapport à la réunion ouverte.
        $wanted = $this->wantedParticipants($validated, $externals);
        $existing = $base->participants->keyBy(fn ($p) => MeetingMailer::participantKey($p) ?? 'p:' . $p->id);
        $added = array_diff_key($wanted, $existing->all());
        $removedKeys = array_values(array_filter(array_keys(array_diff_key($existing->all(), $wanted)), fn ($key) => !str_starts_with($key, 'p:')));
        $updated = array_filter(array_intersect_key($wanted, $existing->all()), fn (array $data, string $key) => $data['is_external']
            && ($data['full_name'] !== $existing[$key]->full_name || $data['organization'] !== $existing[$key]->organization), ARRAY_FILTER_USE_BOTH);

        // Nouvelles dates de chaque réunion concernée.
        $plan = [];
        foreach ($targets as $target) {
            $start = $moved ? $target->starts_at->copy()->addSeconds($shift) : $target->starts_at->copy();
            $end = $moved ? $start->copy()->addSeconds($duration) : $target->ends_at->copy();
            if ($moved && $start->lte(now())) {
                return back()->withInput()->with('error', 'La nouvelle date doit être à venir (' . $start->format('d/m/Y H:i') . ').');
            }
            $writer = (string) ($changes['minutes_writer_id'] ?? $target->minutes_writer_id);
            $validatorId = (string) ($changes['validator_id'] ?? $target->validator_id);
            if ($writer === $validatorId) {
                return back()->withInput()->with('error', 'Le validateur doit être différent du rédacteur.');
            }

            $keys = $target->participants()->get()->map(fn ($p) => MeetingMailer::participantKey($p))->filter()->all();
            $keys = array_unique(array_merge(array_diff($keys, $removedKeys), array_keys($added)));
            $userIds = array_values(array_unique(array_filter(array_merge(
                [(string) $target->organizer_id, $writer, $validatorId],
                array_map(fn ($key) => str_starts_with($key, 'u:') ? substr($key, 2) : null, $keys),
            ))));
            $plan[(string) $target->id] = [$start, $end, (string) ($changes['meeting_room_id'] ?? $target->meeting_room_id), $userIds];
        }

        $peopleChanged = $added !== [] || isset($changes['minutes_writer_id']) || isset($changes['validator_id']);
        if ($moved || $peopleChanged || isset($changes['meeting_room_id'])) {
            $slots = array_values($plan);
            foreach ($slots as $i => $slot) {
                if ($i > 0 && $slot[0]->lt($slots[$i - 1][1])) {
                    return back()->withInput()->with('error', 'La durée de la réunion dépasse l\'intervalle entre deux dates de la série.');
                }
            }
            $conflict = $this->findScheduleConflict('', $slots, [], array_keys($plan), 'Aucune modification enregistrée. ');
            if ($conflict !== null) {
                return back()->withInput()->with('error', $conflict);
            }
        }

        [$attachments, $templatePath] = $this->storeUploads($request);
        $toRemove = (array) ($validated['remove_attachments'] ?? []);
        $mailer = app(MeetingMailer::class);
        // Les personnes retirées sont relevées avant leur suppression.
        $removedRecipients = array_intersect_key($mailer->recipients($targets), array_flip($removedKeys));
        $templateCopies = [];

        try {
            DB::transaction(function () use ($targets, $plan, $changes, $moved, $shift, $newStart, $newDeadline, $deadlineChanged, $attachments, $toRemove, $templatePath, $added, $removedKeys, $updated, &$templateCopies) {
                foreach ($targets->values() as $index => $target) {
                    [$start, $end] = $plan[(string) $target->id];
                    $data = $changes;

                    if ($moved) {
                        $data += [
                            'starts_at' => $start, 'ends_at' => $end, 'estimated_duration_minutes' => $start->diffInMinutes($end),
                            'qr_valid_from' => $start, 'qr_valid_until' => $end, 'reminder_sent_at' => null,
                            // Une réunion close sans avoir eu lieu redevient planifiée.
                            'status' => 'planned',
                        ];
                    }
                    if ($deadlineChanged) {
                        $data['processing_deadline'] = $newDeadline ? $start->copy()->addSeconds($newDeadline->getTimestamp() - $newStart->getTimestamp()) : null;
                        $data['deadline_alert_sent_at'] = null;
                    } elseif ($moved && $target->processing_deadline) {
                        $data['processing_deadline'] = $target->processing_deadline->copy()->addSeconds($shift);
                        $data['deadline_alert_sent_at'] = null;
                    }
                    if ($attachments || $toRemove) {
                        $data['attachments'] = array_merge(array_values(array_filter(
                            (array) $target->attachments,
                            fn ($file) => !in_array($file['path'] ?? '', $toRemove, true),
                        )), $attachments);
                    }
                    if ($templatePath) {
                        $path = $templatePath;
                        if ($index > 0) {
                            $source = Str::after($templatePath, '/storage/');
                            $copy = 'meetings/templates/' . Str::uuid() . '/' . basename($source);
                            if (!Storage::disk('public')->copy($source, $copy)) {
                                throw new \RuntimeException('Copie du modèle de compte rendu impossible.');
                            }
                            $templateCopies[] = $copy;
                            $path = '/storage/' . $copy;
                        }
                        $data += ['minutes_template' => $path, 'template_variables' => null, 'template_sealed_path' => null];
                    }

                    $target->forceFill($data)->save();

                    $current = $target->participants()->get()->keyBy(fn ($p) => MeetingMailer::participantKey($p) ?? 'p:' . $p->id);
                    foreach ($removedKeys as $key) {
                        $current->get($key)?->delete();
                    }
                    foreach ($added as $key => $participant) {
                        if (!$current->has($key)) {
                            MeetingParticipant::create(['meeting_id' => $target->id] + $participant);
                        }
                    }
                    foreach ($updated as $key => $participant) {
                        $current->get($key)?->update(['full_name' => $participant['full_name'], 'organization' => $participant['organization']]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($templateCopies);
            throw $e;
        }

        foreach ($targets as $target) {
            $this->appendMinutesVersion($target->refresh(), $moved ? 'Réunion reportée au ' . $target->starts_at->format('d/m/Y H:i') : 'Réunion modifiée');
        }

        $message = count($targets) === 1 ? 'Réunion mise à jour.' : count($targets) . ' réunions mises à jour.';
        $errors = [];
        if ($request->boolean('send_invitations', true)) {
            $recipients = $mailer->recipients($targets);
            $results = [$mailer->invite(array_intersect_key($recipients, $added))];
            if ($moved || array_intersect_key($changes, array_flip(['meeting_room_id', 'is_virtual', 'meeting_link']))) {
                $results[] = $mailer->notifyChange(array_diff_key($recipients, $added));
            }
            $results[] = $mailer->notifyRemoval($removedRecipients);
            $sent = array_sum(array_column($results, 'sent'));
            if ($sent > 0) {
                $message .= ' ' . $sent . ' e-mail(s) envoyé(s) aux participants.';
            }
            $errors = array_unique(array_filter(array_column($results, 'error')));
        }

        $response = redirect()->route('meetings.show', $meeting)->with('success', $message);

        return $errors ? $response->with('error', 'E-mails non envoyés en totalité : ' . implode(' ', $errors)) : $response;
    }

    public function cancel(Request $request, Meeting $meeting)
    {
        if ($refusal = $this->refuseManagement($meeting)) {
            return $refusal;
        }

        $validated = $request->validate([
            'apply_to' => 'nullable|in:one,following',
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);
        $reason = $validated['cancellation_reason'] ?? null;
        $targets = $this->targets($meeting, (string) ($validated['apply_to'] ?? 'one'));

        DB::transaction(function () use ($targets, $reason) {
            foreach ($targets as $target) {
                $target->forceFill([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => Auth::id(), 'cancellation_reason' => $reason,
                ])->save();
                $this->appendMinutesVersion($target, 'Réunion annulée');
            }
        });

        $message = count($targets) === 1 ? 'Réunion annulée.' : count($targets) . ' réunions annulées.';
        $error = null;
        if ($request->boolean('send_invitations', true)) {
            $mailer = app(MeetingMailer::class);
            $result = $mailer->notifyCancellation($mailer->recipients($targets), $reason);
            if ($result['sent'] > 0) {
                $message .= ' ' . $result['sent'] . ' participant(s) informé(s) par e-mail.';
            }
            $error = $result['error'];
        }

        $response = redirect()->route('meetings.show', $meeting)->with('success', $message);

        return $error ? $response->with('error', 'Participants non informés en totalité : ' . $error) : $response;
    }

    /** Renvoie l'invitation aux participants de cette réunion. */
    public function invitations(Meeting $meeting)
    {
        $this->guardPermission('meetings.create');
        $this->abortIfMeetingOutsideScope($meeting);
        abort_unless($this->canManage($meeting), 403);

        if ($meeting->isCancelled() || $meeting->ends_at?->isPast()) {
            return back()->with('error', 'Aucune invitation à envoyer : la réunion est annulée ou terminée.');
        }

        $mailer = app(MeetingMailer::class);
        $recipients = $mailer->recipients([$meeting]);
        if ($recipients === []) {
            return back()->with('error', 'Aucun participant avec une adresse e-mail valide.');
        }
        $result = $mailer->invite($recipients);
        if ($result['sent'] === 0) {
            return back()->with('error', 'Invitations non envoyées : ' . $result['error']);
        }
        $response = back()->with('success', 'Invitation envoyée à ' . $result['sent'] . ' participant(s).');

        return $result['error'] ? $response->with('error', $result['error']) : $response;
    }

    private function refuseManagement(Meeting $meeting)
    {
        $this->guardPermission('meetings.create');
        $this->abortIfMeetingOutsideScope($meeting);
        abort_unless($this->canManage($meeting), 403, 'Seul l\'organisateur peut modifier ou annuler cette réunion.');

        return $meeting->isChangeable() ? null : redirect()->route('meetings.show', $meeting)
            ->with('error', 'Cette réunion ne peut plus être modifiée : elle est annulée, des présences sont enregistrées ou le compte rendu est en cours de validation.');
    }

    /** La réunion ouverte, ou elle et les suivantes de la série qui sont encore modifiables. */
    private function targets(Meeting $meeting, string $applyTo): Collection
    {
        if ($applyTo !== 'following' || !$meeting->recurrence_series_id) {
            return collect([$meeting]);
        }

        return Meeting::where('recurrence_series_id', $meeting->recurrence_series_id)
            ->where('starts_at', '>=', $meeting->starts_at)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Meeting $m) => $m->is($meeting) || $m->isChangeable())
            ->map(fn (Meeting $m) => $m->is($meeting) ? $meeting : $m)
            ->values();
    }

    private function meetingScope(Meeting $meeting): array
    {
        return [
            'administration_id' => (string) $meeting->issuing_administration_id,
            'sub_entity_code' => strtoupper((string) $meeting->sub_entity_code),
        ];
    }

    private function comparable(mixed $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : str_replace("\r\n", "\n", trim((string) $value));
    }
}
