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
     * Choix du formulaire de recherche : administrations autorisées et leurs entités sous tutelle.
     *
     * @return array<int, array{id: string, name: string, entities: array<int, array{code: string, name: string}>}>
     */
    public function options(?array $scope = null): array
    {
        $names = $this->administrationNames($scope['id'] ?? null);

        $subEntities = SubEntity::query()
            ->whereIn('scope_id', array_keys($names))
            ->orderBy('name')
            ->get(['scope_id', 'name', 'code'])
            ->groupBy('scope_id');

        $options = [];
        foreach ($names as $id => $name) {
            $options[] = [
                'id'       => (string) $id,
                'name'     => (string) $name,
                'entities' => $subEntities->get($id, collect())
                    ->filter(fn ($e) => trim((string) $e->code) !== '')
                    ->map(fn ($e) => ['code' => (string) $e->code, 'name' => (string) $e->name])
                    ->values()
                    ->all(),
            ];
        }

        return $options;
    }

    /**
     * @param array|null  $scope      périmètre de l'administrateur connecté (null = toutes les administrations)
     * @param string|null $adminId    administration recherchée (ignorée si hors périmètre)
     * @param string|null $entityCode entité sous tutelle recherchée (nécessite une administration)
     * @return array{administrations: array<int, array>, totals: array<string, int>}
     */
    public function build(?array $scope = null, ?Carbon $from = null, ?Carbon $to = null, ?string $adminId = null, ?string $entityCode = null): array
    {
        $scopeId = $scope['id'] ?? null;
        if (!$scopeId && $adminId) {
            $scopeId = IssuingAdministration::query()->whereKey($adminId)->exists() ? $adminId : null;
        }
        $entityCode = $scopeId && $adminId ? mb_strtoupper(trim((string) $entityCode)) : '';
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
        $names = $this->administrationNames($scopeId);

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

            // Recherche sur une entité : seuls ses chiffres sont affichés et totalisés.
            if ($entityCode !== '') {
                $entities = array_values(array_filter($entities, fn ($e) => mb_strtoupper(trim((string) $e['code'])) === $entityCode));
                $total = self::ZERO;
                foreach ($entities as $e) {
                    $total = $this->sum($total, $e);
                }
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

    /** @return array<string, string> [id => nom] */
    private function administrationNames(?string $onlyId): array
    {
        $names = IssuingAdministration::query()
            ->when($onlyId, fn ($q) => $q->whereKey($onlyId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        // Périmètre « destinataire » : l'administration n'est pas dans la table des émettrices.
        if ($onlyId && !isset($names[$onlyId])) {
            $names[$onlyId] = RecipientAdministration::query()->whereKey($onlyId)->value('name') ?? 'Mon administration';
        }

        return $names;
    }

    private function sum(array $a, array $b): array
    {
        foreach (array_keys(self::ZERO) as $key) {
            $a[$key] += $b[$key];
        }
        return $a;
    }
}
