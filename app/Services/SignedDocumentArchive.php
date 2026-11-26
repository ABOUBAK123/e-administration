<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Réponse « documents signés » de la plateforme de signature : un PDF pour un seul document,
 * une archive ZIP quand le workflow en contient plusieurs.
 *
 * La lecture du ZIP est faite en PHP pur pour ne pas dépendre de l'extension php-zip du serveur.
 */
class SignedDocumentArchive
{
    public static function isZip(string $content): bool
    {
        return str_starts_with($content, "PK\x03\x04");
    }

    public static function isPdf(string $content): bool
    {
        return str_contains(substr($content, 0, 1024), '%PDF-');
    }

    /**
     * PDF contenus dans l'archive, dans l'ordre de l'archive : [nom => contenu].
     * Retourne un tableau vide si l'archive est illisible.
     *
     * @return array<string, string>
     */
    public static function pdfEntries(string $zip): array
    {
        // Fin du répertoire central (22 octets + commentaire éventuel).
        $end = strrpos($zip, "PK\x05\x06");
        if ($end === false || strlen($zip) < $end + 22) {
            return [];
        }
        $eocd = unpack('vcount/Vsize/Voffset', substr($zip, $end + 10, 10));

        $entries = [];
        $pos = $eocd['offset'];
        for ($i = 0; $i < $eocd['count']; $i++) {
            if (substr($zip, $pos, 4) !== "PK\x01\x02") {
                return [];
            }
            // Les tailles du répertoire central restent justes même avec des « data descriptors ».
            $h = unpack('vflags/vmethod/Vtime/Vcrc/Vcsize/Vsize/vnameLen/vextraLen/vcommentLen', substr($zip, $pos + 8, 26));
            $local = unpack('V', substr($zip, $pos + 42, 4))[1];
            $name = substr($zip, $pos + 46, $h['nameLen']);
            $pos += 46 + $h['nameLen'] + $h['extraLen'] + $h['commentLen'];

            if (!str_ends_with(strtolower($name), '.pdf') || substr($zip, $local, 4) !== "PK\x03\x04") {
                continue;
            }
            $l = unpack('vnameLen/vextraLen', substr($zip, $local + 26, 4));
            $data = substr($zip, $local + 30 + $l['nameLen'] + $l['extraLen'], $h['csize']);
            $content = match ($h['method']) {
                0 => $data,
                8 => @gzinflate($data),
                default => false,
            };

            if (is_string($content) && crc32($content) === $h['crc']) {
                $entries[$name] = $content;
            }
        }

        return $entries;
    }

    /**
     * PDF correspondant à un document, reconnu à son ID dans le nom du fichier, sinon à sa position
     * de dépôt. Jamais le PDF d'un autre document par défaut.
     *
     * Les anciens dépôts n'utilisaient que les 8 premiers caractères de l'ID, communs à des documents
     * créés à quelques secondes d'intervalle : le titre ou la position les départagent alors.
     *
     * @param array<string, string> $entries
     */
    public static function pdfForDocument(array $entries, string $documentId, mixed $depositIndex = null, string $title = ''): ?string
    {
        $id = str_replace('-', '', $documentId);
        if ($id === '') {
            return null;
        }
        $matching = fn (string $needle) => array_filter(
            $entries, fn ($name) => stripos((string) $name, $needle) !== false, ARRAY_FILTER_USE_KEY
        );

        $found = $matching($id);
        if (count($found) !== 1) {
            $found = $matching(substr($id, 0, 8));
        }
        if (count($found) > 1) {
            $slug = Str::slug(pathinfo($title, PATHINFO_FILENAME));
            $found = $slug === '' ? [] : array_filter(
                $found,
                fn ($name) => strcasecmp(basename((string) $name, '.pdf'), $slug . '-' . substr($id, 0, 8)) === 0,
                ARRAY_FILTER_USE_KEY
            );
        }
        if (count($found) === 1) {
            return reset($found);
        }

        if (count($entries) === 1) {
            return reset($entries);
        }

        $values = array_values($entries);

        return is_int($depositIndex) ? ($values[$depositIndex] ?? null) : null;
    }
}
