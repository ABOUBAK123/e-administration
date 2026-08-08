<?php

namespace App\Services;

use App\Models\PersonnelEmployee;
use App\Models\PersonnelLeaveRequest;
use App\Models\PersonnelStaffingNeed;
use App\Models\PersonnelTrainingEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Calcule les indicateurs décisionnels du tableau de bord "Gestion du personnel" :
 * répartition par sexe/grade, congés et formations en cours à l'instant T,
 * prévisions de départs à la retraite, et suivi des besoins en personnel.
 *
 * Toutes les requêtes respectent le périmètre d'administration ($adminScope),
 * de la même manière que AdminController::applyPersonnelScope().
 */
class PersonnelAnalyticsService
{
    /** Âge légal de départ à la retraite utilisé pour les prévisions (par défaut). */
    public const DEFAULT_RETIREMENT_AGE = 60;

    /**
     * @param array{type: string, id: string}|null $adminScope
     */
    public function __construct(private readonly ?array $adminScope = null)
    {
    }

    /**
     * @return array{
     *   byGender: array<string, int>,
     *   byGrade: array<string, int>,
     *   onLeaveToday: int,
     *   inTrainingToday: int,
     *   leaveByType: array<string, int>,
     *   retirement: array{within1Year: int, within3Years: int, within5Years: int, upcoming: array<int, array<string, mixed>>},
     *   staffingNeeds: array{total: int, gap: int, urgent: int, items: \Illuminate\Support\Collection},
     * }
     */
    public function buildDashboard(int $retirementAge = self::DEFAULT_RETIREMENT_AGE): array
    {
        return [
            'byGender' => $this->byGender(),
            'byGrade' => $this->byGrade(),
            'onLeaveToday' => $this->onLeaveToday(),
            'inTrainingToday' => $this->inTrainingToday(),
            'leaveByType' => $this->leaveByType(),
            'retirement' => $this->retirementForecast($retirementAge),
            'staffingNeeds' => $this->staffingNeeds(),
        ];
    }

    /** @return array<string, int> */
    public function byGender(): array
    {
        if (!Schema::hasTable('personnel_employees')) {
            return [];
        }

        return $this->scopedEmployees()
            ->where('employment_status', 'active')
            ->selectRaw('COALESCE(NULLIF(gender, \'\'), \'non_renseigne\') as gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->toArray();
    }

    /** @return array<string, int> */
    public function byGrade(): array
    {
        if (!Schema::hasTable('personnel_employees')) {
            return [];
        }

        return $this->scopedEmployees()
            ->where('employment_status', 'active')
            ->selectRaw('COALESCE(NULLIF(job_title, \'\'), \'non_renseigne\') as job_title, COUNT(*) as total')
            ->groupBy('job_title')
            ->orderByDesc('total')
            ->pluck('total', 'job_title')
            ->toArray();
    }

    public function onLeaveToday(): int
    {
        if (!Schema::hasTable('personnel_leave_requests')) {
            return 0;
        }

        $today = Carbon::today()->toDateString();

        return $this->scopedLeaveRequests()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();
    }

    public function inTrainingToday(): int
    {
        if (!Schema::hasTable('personnel_training_enrollments')) {
            return 0;
        }

        $today = Carbon::today()->toDateString();

        return $this->scopedTrainingEnrollments()
            ->whereIn('status', ['in_progress', 'ongoing'])
            ->whereDate('planned_start_date', '<=', $today)
            ->whereDate('planned_end_date', '>=', $today)
            ->count();
    }

    /** @return array<string, int> */
    public function leaveByType(): array
    {
        if (!Schema::hasTable('personnel_leave_requests') || !Schema::hasTable('personnel_leave_types')) {
            return [];
        }

        $query = PersonnelLeaveRequest::query()
            ->join('personnel_leave_types', 'personnel_leave_types.id', '=', 'personnel_leave_requests.leave_type_id')
            ->where('personnel_leave_requests.status', 'approved');

        if ($this->adminScope) {
            $type = ($this->adminScope['type'] ?? null) === 'recipient' ? 'recipient' : 'emitter';
            $query->where('personnel_leave_requests.administration_type', $type)
                ->where('personnel_leave_requests.administration_id', $this->adminScope['id'] ?? null);
        }

        return $query
            ->selectRaw('personnel_leave_types.name as leave_type_name, COUNT(*) as total')
            ->groupBy('personnel_leave_types.name')
            ->orderByDesc('total')
            ->pluck('total', 'leave_type_name')
            ->toArray();
    }

    /**
     * @return array{within1Year: int, within3Years: int, within5Years: int, upcoming: array<int, array<string, mixed>>}
     */
    public function retirementForecast(int $retirementAge = self::DEFAULT_RETIREMENT_AGE): array
    {
        if (!Schema::hasTable('personnel_employees')) {
            return ['within1Year' => 0, 'within3Years' => 0, 'within5Years' => 0, 'upcoming' => []];
        }

        $now = Carbon::now();
        // Un employé atteint l'âge de la retraite quand sa date de naissance + $retirementAge ans est atteinte.
        // On cherche les employés dont la date anniversaire de la retraite tombe dans les 5 prochaines années
        // (ou est déjà dépassée alors qu'ils sont toujours actifs, ce qui est prioritaire/"urgent").
        // Borne haute : nés avant cette date => atteignent (ou ont atteint) l'âge de la retraite dans <= 5 ans.
        $maxBirthDate = $now->copy()->subYears($retirementAge - 5)->toDateString();
        // Borne basse (garde-fou) : exclut les enregistrements aberrants très en dehors de la fenêtre utile.
        $minBirthDate = $now->copy()->subYears($retirementAge + 15)->toDateString();

        $employees = $this->scopedEmployees()
            ->where('employment_status', 'active')
            ->whereNotNull('birth_date')
            ->whereBetween('birth_date', [$minBirthDate, $maxBirthDate])
            ->orderBy('birth_date')
            ->get(['id', 'first_name', 'last_name', 'job_title', 'birth_date']);

        $within1Year = 0;
        $within3Years = 0;
        $within5Years = 0;
        $upcoming = [];

        foreach ($employees as $employee) {
            $retirementDate = Carbon::parse($employee->birth_date)->addYears($retirementAge);
            if ($retirementDate->isPast()) {
                // Date de retraite déjà dépassée mais l'employé est toujours actif : à traiter en priorité.
                $retirementDate = $now->copy();
            }
            $yearsUntilRetirement = $now->diffInYears($retirementDate, false);

            if ($yearsUntilRetirement <= 1) {
                $within1Year++;
            }
            if ($yearsUntilRetirement <= 3) {
                $within3Years++;
            }
            if ($yearsUntilRetirement <= 5) {
                $within5Years++;
            }

            $upcoming[] = [
                'id' => $employee->id,
                'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'job_title' => $employee->job_title,
                'retirement_date' => $retirementDate->toDateString(),
            ];
        }

        return [
            'within1Year' => $within1Year,
            'within3Years' => $within3Years,
            'within5Years' => $within5Years,
            'upcoming' => array_slice($upcoming, 0, 20),
        ];
    }

    /**
     * @return array{total: int, gap: int, urgent: int, items: \Illuminate\Support\Collection}
     */
    public function staffingNeeds(): array
    {
        if (!Schema::hasTable('personnel_staffing_needs')) {
            return ['total' => 0, 'gap' => 0, 'urgent' => 0, 'items' => collect()];
        }

        $items = $this->scopedStaffingNeeds()
            ->where('status', 'open')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('target_date')
            ->get();

        $gap = $items->sum(fn (PersonnelStaffingNeed $need) => max(0, $need->required_count - $need->current_count));
        $urgent = $items->where('priority', 'urgent')->count();

        return [
            'total' => $items->count(),
            'gap' => $gap,
            'urgent' => $urgent,
            'items' => $items,
        ];
    }

    private function scopedEmployees()
    {
        return $this->applyScope(PersonnelEmployee::query());
    }

    private function scopedLeaveRequests()
    {
        return $this->applyScope(PersonnelLeaveRequest::query());
    }

    private function scopedTrainingEnrollments()
    {
        return $this->applyScope(PersonnelTrainingEnrollment::query());
    }

    private function scopedStaffingNeeds()
    {
        return $this->applyScope(PersonnelStaffingNeed::query());
    }

    private function applyScope($query)
    {
        if (!$this->adminScope) {
            return $query;
        }

        $type = ($this->adminScope['type'] ?? null) === 'recipient' ? 'recipient' : 'emitter';

        return $query
            ->where('administration_type', $type)
            ->where('administration_id', $this->adminScope['id'] ?? null);
    }
}
