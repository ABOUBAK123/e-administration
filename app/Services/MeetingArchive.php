<?php

namespace App\Services;

use App\Http\Controllers\MeetingAttendanceController;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Archivage dans la GED du compte rendu validé et de la liste de présence d'une réunion.
 * Les documents appartiennent à l'organisateur ; le rédacteur et le validateur les ont en lecture.
 */
class MeetingArchive
{
    public const FOLDER = 'Archives réunions';

    /** Compte rendu validé et émargement terminé : la liste de présence ne changera plus. */
    public function isDue(Meeting $meeting): bool
    {
        $end = collect([$meeting->ends_at, $meeting->qr_valid_until])->filter()->max();

        return !$meeting->archived_at
            && !$meeting->isCancelled()
            && $meeting->isMinutesLocked()
            && $end !== null
            && $end->lte(now());
    }

    /**
     * @return bool false si la réunion est déjà archivée
     *
     * @throws \RuntimeException message destiné à l'utilisateur ; l'archivage sera retenté
     */
    public function archive(Meeting $meeting): bool
    {
        // Réservation : la validation et la tâche planifiée ne doivent pas archiver deux fois.
        // Sans passer par le modèle, pour ne pas changer updated_at (clé de session OnlyOffice).
        $claimed = Meeting::whereKey($meeting->id)->whereNull('archived_at')->toBase()
            ->update(['archived_at' => now(), 'archive_attempted_at' => now()]);
        if (!$claimed) {
            return false;
        }

        $paths = [];
        try {
            $owner = $this->owner($meeting);
            $minutes = app(MeetingMinutesPdf::class)->content($meeting);
            $attendance = app(MeetingAttendanceController::class)->attendancePdf($meeting, 'landscape')->output();

            try {
                app(StorageUsageService::class)->assertCanStore($owner, strlen($minutes) + strlen($attendance));
            } catch (ValidationException $e) {
                throw new \RuntimeException($e->validator->errors()->first() . ' (compte de ' . $owner->name . ')');
            }

            DB::transaction(function () use ($meeting, $owner, $minutes, $attendance, &$paths) {
                $suffix = $meeting->title . ' - ' . $meeting->starts_at?->format('d-m-Y');
                $minutesDocument = $this->store($meeting, $owner, 'Compte rendu - ' . $suffix, $minutes, $paths);
                $attendanceDocument = $this->store($meeting, $owner, 'Liste de présence - ' . $suffix, $attendance, $paths);

                $readers = User::whereIn('id', array_filter([$meeting->minutes_writer_id, $meeting->validator_id, $meeting->organizer_id]))
                    ->where('status', 'active')
                    ->whereKeyNot($owner->id)
                    ->get();

                $this->ensureFolder($owner);
                foreach ($readers as $reader) {
                    // Chacun a besoin du dossier pour y voir les documents partagés.
                    $this->ensureFolder($reader);
                    foreach ([$minutesDocument, $attendanceDocument] as $document) {
                        DocumentShare::create([
                            'document_id' => $document->id,
                            'shared_by' => $owner->id,
                            'mode' => 'internal',
                            'permission' => 'lecture',
                            'has_delay' => false,
                            'recipient_name' => 'user:' . $reader->id,
                            'recipient_email' => $reader->email,
                        ]);
                    }
                }

                Meeting::whereKey($meeting->id)->toBase()->update([
                    'archived_minutes_document_id' => $minutesDocument->id,
                    'archived_attendance_document_id' => $attendanceDocument->id,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            Meeting::whereKey($meeting->id)->toBase()->update(['archived_at' => null]);
            Log::error('Archivage GED de la réunion impossible', ['meeting_id' => (string) $meeting->id, 'error' => $e->getMessage()]);

            throw new \RuntimeException($e instanceof \RuntimeException ? $e->getMessage() : 'erreur inattendue lors de l\'archivage.', 0, $e);
        }

        $meeting->setAttribute('archived_at', now());
        $meeting->syncOriginalAttribute('archived_at');

        return true;
    }

    private function owner(Meeting $meeting): User
    {
        foreach ([$meeting->organizer_id, $meeting->validator_id, $meeting->minutes_writer_id] as $id) {
            $user = $id ? User::whereKey($id)->where('status', 'active')->first() : null;
            if ($user) {
                return $user;
            }
        }

        throw new \RuntimeException('ni l\'organisateur, ni le validateur, ni le rédacteur n\'a de compte actif.');
    }

    /** @param list<string> $paths fichiers écrits, à supprimer en cas d'échec */
    private function store(Meeting $meeting, User $owner, string $title, string $content, array &$paths): Document
    {
        $path = 'documents/' . Str::uuid() . '.pdf';
        if (!Storage::disk('public')->put($path, $content)) {
            throw new \RuntimeException('le fichier n\'a pas pu être enregistré dans la GED.');
        }
        $paths[] = $path;

        $document = Document::create([
            'title' => Str::limit($title, 490),
            'description' => 'Dossier: ' . self::FOLDER,
            'file_path' => '/storage/' . $path,
            'final_file_path' => '/storage/' . $path,
            'file_size' => strlen($content),
            'mime_type' => 'application/pdf',
            'status' => 'archived',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'issuing_administration_id' => $meeting->issuing_administration_id,
            'sub_entity_code' => $meeting->sub_entity_code,
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'file_path' => $document->file_path,
            'creator_id' => $owner->id,
            'change_log' => 'Archivage de la réunion',
        ]);

        return $document;
    }

    private function ensureFolder(User $user): void
    {
        Document::firstOrCreate(
            ['owner_id' => $user->id, 'description' => '[folder]', 'title' => self::FOLDER],
            ['file_path' => '', 'final_file_path' => '', 'mime_type' => 'application/x-folder', 'status' => 'draft', 'created_by' => $user->id],
        );
    }
}
