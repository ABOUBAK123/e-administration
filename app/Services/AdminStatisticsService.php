<?php

namespace App\Services;

use App\Models\Courrier;
use App\Models\Document;
use App\Models\IssuingAdministration;
use App\Models\RecipientAdministration;
use App\Models\SubEntity;
use Carbon\Carbon;

/**
 * Statistiques de l'onglet Administration : documents signés, courriers arrivés et départ,
 * par administration émettrice et par entité sous tutelle.
 */
class AdminStatisticsService
{
    private const ZERO = ['signed' => 0, 'arrive' => 0, 'depart' => 0];

    /**
     * @param array|null $scope périmètre de l'administrateur connecté (null = toutes les administrations)
     * @return array{administrations: array<int, array>, totals: array<string, int>}
     */
    public function build(?array $scope = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $scopeId = $scope['id'] ?? null;
        $counts = []; // [administration_id][CODE] => compteurs ('' = non renseigné)

        $add = function ($adminId, $code, string $key, int $total) use (&$counts): void {
            $adminId = (string) $adminId;
            $code = mb_strtoupper(trim((string) $code));
            $counts[$adminId][$code] ??= self::ZERO;
            $counts[$adminId][$code][$key] += $total;
        };

        // Documents signés (date de référence : date de signature).
        Document::query()
            ->where(fn ($q) => $q->whereNotNull('signed_at')->orWhere('status', 'signed'))
            ->when($scopeId, fn ($q) => $q->where('issuing_administration_id', $scopeId))
            ->when($from, fn ($q) => $q->whereRaw('COALESCE(signed_at, updated_at) >= ?', [$from->copy()->startOfDay()]))
            ->when($to, fn ($q) => $q->whereRaw('COALESCE(signed_at, updated_at) <= ?', [$to->copy()->endOfDay()]))
            ->selectRaw('issuing_administration_id, sub_entity_code, COUNT(*) as total')
            ->groupBy('issuing_administration_id', 'sub_entity_code')
            ->toBase()
            ->get()
            ->each(fn ($r) => $add($r->issuing_administration_id, $r->sub_entity_code, 'signed', (int) $r->total));

        // Courriers arrivés et départ (date de référence : date d'enregistrement).
        Courrier::query()
            ->whereIn('type', ['arrive', 'depart'])
            ->when($scopeId, fn ($q) => $q->where('administration_id', $scopeId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->selectRaw('administration_id, sub_entity_code, type, COUNT(*) as total')
            ->groupBy('administration_id', 'sub_entity_code', 'type')
            ->toBase()
            ->get()
            ->each(fn ($r) => $add($r->administration_id, $r->sub_entity_code, $r->type, (int) $r->total));

        // Administrations émettrices (toutes, même sans activité).
        $names = IssuingAdministration::query()
            ->when($scopeId, fn ($q) => $q->whereKey($scopeId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        // Périmètre « destinataire » : l'administration n'est pas dans la table des émettrices.
        if ($scopeId && !isset($names[$scopeId])) {
            $names[$scopeId] = RecipientAdministration::query()->whereKey($scopeId)->value('name') ?? 'Mon administration';
        }

        $subEntities = SubEntity::query()
            ->whereIn('scope_id', array_keys($names))
            ->orderBy('name')
            ->get(['scope_id', 'name', 'code'])
            ->groupBy('scope_id');

        $administrations = [];
        foreach ($names as $adminId => $name) {
            $adminCounts = $counts[(string) $adminId] ?? [];
            unset($counts[(string) $adminId]);

            $total = self::ZERO;
            foreach ($adminCounts as $c) {
                $total = $this->sum($total, $c);
            }

            $entities = [];
            foreach ($subEntities->get($adminId, collect()) as $entity) {
                $code = mb_strtoupper(trim((string) $entity->code));
                $entities[] = ['name' => $entity->name, 'code' => $entity->code] + ($adminCounts[$code] ?? self::ZERO);
                unset($adminCounts[$code]);
            }

            // Reliquat (entité absente ou code inconnu) pour que le détail corresponde au total.
            $rest = self::ZERO;
            foreach ($adminCounts as $c) {
                $rest = $this->sum($rest, $c);
            }
            if (array_sum($rest) > 0) {
                $entities[] = ['name' => 'Sans entité rattachée', 'code' => null] + $rest;
            }

            $administrations[] = ['name' => $name, 'entities' => $entities] + $total;
        }

        // Éléments sans administration émettrice connue (vue globale uniquement).
        if (!$scopeId) {
            $orphans = self::ZERO;
            foreach ($counts as $adminCounts) {
                foreach ($adminCounts as $c) {
                    $orphans = $this->sum($orphans, $c);
                }
            }
            if (array_sum($orphans) > 0) {
                $administrations[] = ['name' => 'Sans administration émettrice', 'entities' => []] + $orphans;
            }
        }

        $totals = self::ZERO;
        foreach ($administrations as $a) {
            $totals = $this->sum($totals, $a);
        }

        return ['administrations' => $administrations, 'totals' => $totals];
    }

    private function sum(array $a, array $b): array
    {
        foreach (array_keys(self::ZERO) as $key) {
            $a[$key] += $b[$key];
        }
        return $a;
    }
}
