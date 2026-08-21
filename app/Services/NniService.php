<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Str;

/**
 * Centralise la normalisation, la validation, le hachage et le masquage du NNI
 * (identifiant unique demandeur). Le NNI en clair n'est jamais persisté : seuls
 * un hash (clé de rapprochement automatique) et une version masquée (affichage)
 * sont conservés.
 */
class NniService
{
    /** Format ivoirien par défaut : à ajuster depuis Administration > Identification (NNI). */
    private const DEFAULT_PATTERN = '/^[0-9]{10,15}$/';
    private const DEFAULT_MASK_VISIBLE = 4;

    public static function normalize(string $raw): string
    {
        $ascii = Str::of($raw)->ascii()->upper()->toString();
        return preg_replace('/[^A-Z0-9]/', '', $ascii) ?: '';
    }

    public static function validationPattern(): string
    {
        $pattern = trim((string) (AppSetting::where('key', 'nni_validation_regex')->value('value') ?? ''));
        if ($pattern === '') {
            return self::DEFAULT_PATTERN;
        }

        // Le motif est enregistré sans délimiteurs par l'admin ; on les ajoute ici.
        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern . '/';
        }

        return $pattern;
    }

    public static function isValid(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        $pattern = self::validationPattern();
        $result = @preg_match($pattern, $normalized);

        // Motif invalide configuré par l'admin : repli sur le format par défaut.
        if ($result === false) {
            return (bool) preg_match(self::DEFAULT_PATTERN, $normalized);
        }

        return $result === 1;
    }

    /** Empreinte déterministe utilisée uniquement pour le rapprochement automatique (jamais réversible). */
    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized . '|' . config('app.key'));
    }

    public static function mask(string $normalized): string
    {
        $visible = (int) (AppSetting::where('key', 'nni_mask_visible_chars')->value('value') ?? self::DEFAULT_MASK_VISIBLE);
        $visible = max(0, min($visible, mb_strlen($normalized)));

        $hiddenLength = mb_strlen($normalized) - $visible;
        $suffix = $visible > 0 ? mb_substr($normalized, -$visible) : '';

        return str_repeat('•', max($hiddenLength, 1)) . $suffix;
    }

    /**
     * Calcule hash + masque à partir d'une saisie brute. Retourne null si vide/invalide.
     *
     * @return array{normalized: string, hash: string, masked: string}|null
     */
    public static function process(?string $raw): ?array
    {
        $normalized = self::normalize((string) ($raw ?? ''));
        if (!self::isValid($normalized)) {
            return null;
        }

        return [
            'normalized' => $normalized,
            'hash' => self::hash($normalized),
            'masked' => self::mask($normalized),
        ];
    }
}
