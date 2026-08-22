<?php

namespace Tests\Feature;

use App\Filament\Pages\LabHealth;
use App\Support\Health\CheckResult;
use App\Support\Health\HealthCheck;
use App\Support\Health\HealthMatrix;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class HealthSurfacesTest extends TestCase
{
    public function test_command_prints_expectations_and_exits_nonzero_for_a_skipped_check(): void
    {
        $this->app->instance(HealthMatrix::class, new HealthMatrix([
            $this->fixedCheck(CheckResult::PASS),
            $this->fixedCheck(CheckResult::SKIPPED, 2),
        ]));

        $exit = Artisan::call('lab:health');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('must_succeed', $output);
        $this->assertStringContainsString('SKIPPED', $output);
    }

    public function test_panel_runs_nothing_on_load_then_renders_the_matrix_on_demand(): void
    {
        $this->app->instance(HealthMatrix::class, new HealthMatrix([
            $this->fixedCheck(CheckResult::PASS),
        ]));

        Livewire::test(LabHealth::class)
            ->assertSet('hasRun', false)
            ->assertSet('results', [])
            ->call('runHealth')
            ->assertSet('hasRun', true)
            ->assertSet('results.0.outcome', 'pass')
            ->assertSee('Check 1');
    }

    private function fixedCheck(string $outcome, int $number = 1): HealthCheck
    {
        return new class($outcome, $number) implements HealthCheck
        {
            public function __construct(
                private readonly string $fixedOutcome,
                private readonly int $checkNumber,
            ) {}

            public function number(): int
            {
                return $this->checkNumber;
            }

            public function name(): string
            {
                return "Check {$this->checkNumber}";
            }

            public function target(): string
            {
                return "target-{$this->checkNumber}";
            }

            public function expectation(): string
            {
                return CheckResult::MUST_SUCCEED;
            }

            public function run(): CheckResult
            {
                return new CheckResult(
                    $this->number(),
                    $this->name(),
                    $this->target(),
                    $this->expectation(),
                    $this->fixedOutcome,
                    'detail',
                );
            }
        };
    }
}
