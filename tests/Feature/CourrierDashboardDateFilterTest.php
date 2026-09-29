<?php

namespace Tests\Feature;

use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierDashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createCourrier(string $createdAt, array $overrides = []): Courrier
    {
        $courrier = Courrier::create(array_merge([
            'numero' => 'C-' . uniqid(),
            'type' => 'arrive',
            'objet' => 'Test',
            'statut' => 'en_attente',
            'sub_entity_code' => 'GEN',
            'date_emission' => now()->toDateString(),
        ], $overrides));

        // created_at est auto-géré par Eloquent ; on le force ensuite pour le test.
        $courrier->created_at = $createdAt;
        $courrier->save();

        return $courrier;
    }

    public function test_date_range_filter_only_counts_courriers_within_the_interval(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);

        $this->createCourrier('2026-01-05 10:00:00'); // avant l'intervalle
        $this->createCourrier('2026-01-15 10:00:00'); // dans l'intervalle
        $this->createCourrier('2026-01-20 10:00:00'); // dans l'intervalle
        $this->createCourrier('2026-02-01 10:00:00'); // après l'intervalle

        $response = $this->actingAs($admin)->get(route('courrier.tableau-de-bord', [
            'date_debut' => '2026-01-10',
            'date_fin' => '2026-01-25',
        ]));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total'] === 2;
        });
        $response->assertViewHas('dateDebut', '2026-01-10');
        $response->assertViewHas('dateFin', '2026-01-25');
    }

    public function test_date_range_takes_priority_over_periode_when_both_are_present(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);

        // "periode=7" exclurait ce courrier (trop ancien) si l'intervalle de dates
        // n'avait pas la priorité.
        $this->createCourrier(now()->subDays(60)->toDateTimeString());

        $response = $this->actingAs($admin)->get(route('courrier.tableau-de-bord', [
            'periode' => '7',
            'date_debut' => now()->subDays(90)->toDateString(),
            'date_fin' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
    }

    public function test_invalid_dates_are_ignored_and_fall_back_to_periode(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $this->createCourrier(now()->subDays(5)->toDateTimeString());

        $response = $this->actingAs($admin)->get(route('courrier.tableau-de-bord', [
            'date_debut' => 'not-a-date',
        ]));

        $response->assertOk();
        $response->assertViewHas('dateDebut', null);
    }

    public function test_dashboard_without_date_filter_still_uses_periode_as_before(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $this->createCourrier(now()->subDays(5)->toDateTimeString());
        $this->createCourrier(now()->subDays(60)->toDateTimeString());

        $response = $this->actingAs($admin)->get(route('courrier.tableau-de-bord', ['periode' => '30']));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
    }
}
