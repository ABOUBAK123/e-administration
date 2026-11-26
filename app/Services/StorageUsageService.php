<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Espace disque occupé par les documents d'un utilisateur, comparé au quota choisi sur son compte.
 */
class StorageUsageService
{
    public const DEFAULT_QUOTA = '5 Go';

    private const UNITS = ['o' => 0, 'ko' => 1, 'mo' => 2, 'go' => 3, 'to' => 4];

    /**
     * Les fichiers sont mesurés sur le disque : documents dont l'utilisateur est propriétaire,
     * leurs versions et leurs copies signées, corbeille comprise (elle occupe toujours de la place).
     * Un même fichier n'est compté qu'une fois.
     *
     * @return array{used: int, trash: int, quota: int|null, percent: float|null, usedLabel: string, trashLabel: string, quotaLabel: string, freeLabel: string}
     */
    public function forUser(User $user): array
    {
        $documents = Document::withTrashed()
            ->where('owner_id', $user->id)
            ->toBase()
            ->get(['id', 'file_path', 'signed_file_path', 'final_file_path', 'deleted_at']);

        $paths = []; // [chemin => uniquement dans la corbeille]
        $trashed = [];
        $add = function ($path, bool $inTrash) use (&$paths): void {
            $relative = ltrim((string) preg_replace('#^/?storage/#', '', ltrim((string) $path, '/')), '/');
            if ($relative !== '') {
                $paths[$relative] = ($paths[$relative] ?? true) && $inTrash;
            }
        };

        foreach ($documents as $document) {
            $inTrash = $document->deleted_at !== null;
            $trashed[(string) $document->id] = $inTrash;
            foreach (['file_path', 'signed_file_path', 'final_file_path'] as $field) {
                $add($document->$field, $inTrash);
            }
        }

        foreach (array_chunk(array_keys($trashed), 500) as $ids) {
            DocumentVersion::query()->whereIn('document_id', $ids)->toBase()->get(['document_id', 'file_path'])
                ->each(fn ($version) => $add($version->file_path, $trashed[(string) $version->document_id]));
        }

        $disk = Storage::disk('public');
        $used = 0;
        $trash = 0;
        foreach ($paths as $relative => $inTrash) {
            $absolute = $disk->path($relative);
            if (!is_file($absolute)) {
                continue;
            }
            $size = (int) filesize($absolute);
            $used += $size;
            $trash += $inTrash ? $size : 0;
        }

        $quota = $this->quotaInBytes($user->quota);

        return [
            'used'       => $used,
            'trash'      => $trash,
            'quota'      => $quota,
            'percent'    => $quota === null ? null : round($used / $quota * 100, 1),
            'usedLabel'  => $this->format($used),
            'trashLabel' => $this->format($trash),
            'quotaLabel' => $quota === null ? 'Illimité' : $this->format($quota),
            'freeLabel'  => $quota === null ? 'Illimité' : $this->format(max(0, $quota - $used)),
        ];
    }

    /**
     * Refuse un import ou une création qui dépasserait le quota de l'utilisateur.
     *
     * @param int $incomingBytes taille du fichier importé ; 0 si elle n'est pas connue d'avance
     *                           (la création est alors refusée une fois le quota atteint)
     *
     * @throws ValidationException
     */
    public function assertCanStore(User $user, int $incomingBytes = 0, string $field = 'file'): void
    {
        if ($this->quotaInBytes($user->quota) === null) {
            return;
        }

        $usage = $this->forUser($user);
        $fits = $incomingBytes > 0
            ? $usage['used'] + $incomingBytes <= $usage['quota']
            : $usage['used'] < $usage['quota'];
        if ($fits) {
            return;
        }

        $message = $incomingBytes > 0
            ? sprintf('Quota de stockage dépassé : ce fichier pèse %s et il vous reste %s sur %s.', $this->format($incomingBytes), $usage['freeLabel'], $usage['quotaLabel'])
            : sprintf('Quota de stockage atteint : %s utilisés sur %s.', $usage['usedLabel'], $usage['quotaLabel']);

        throw ValidationException::withMessages([
            $field => $message . ' Videz la corbeille ou contactez votre administrateur.',
        ]);
    }

    /** Quota « 500 Mo », « 5 Go »… en octets ; null = illimité. Valeur absente ou illisible : quota par défaut. */
    public function quotaInBytes(?string $quota): ?int
    {
        $value = mb_strtolower(trim((string) $quota));
        if (str_starts_with($value, 'illimit')) {
            return null;
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*([kmgt]?)[ob]$/', $value, $m)) {
            $bytes = (int) round((float) str_replace(',', '.', $m[1]) * 1024 ** self::UNITS[$m[2] . 'o']);
            if ($bytes > 0) {
                return $bytes;
            }
        }

        return $this->quotaInBytes(self::DEFAULT_QUOTA);
    }

    private function format(int $bytes): string
    {
        $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $index = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return str_replace(',0', '', number_format($value, $index === 0 ? 0 : 1, ',', ' ')) . ' ' . $units[$index];
    }
}
