<?php

namespace Tests\Unit\Dedup;

use App\Support\Dedup\CalibrationExpansionRule;
use Tests\TestCase;

/** FR-144, FR-145: the stopping rule that keeps progressive calibration honest. */
class CalibrationExpansionRuleTest extends TestCase
{
    public function test_stop_pass_only_when_all_four_conditions_hold(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 50,
            precisionLowerBound: 0.92,
            precisionUpperBound: 0.97,
            recallLowerBound: 0.75,
            positiveClassFloor: 30,
        );

        $this->assertSame('stop_pass', $result);
    }

    public function test_boundary_values_at_exactly_the_gate_still_pass(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 30,
            precisionLowerBound: 0.90,
            precisionUpperBound: 0.95,
            recallLowerBound: 0.70,
            positiveClassFloor: 30,
        );

        $this->assertSame('stop_pass', $result);
    }

    public function test_expands_when_positives_are_below_the_floor_even_if_the_point_estimate_passes(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 20,
            precisionLowerBound: 0.95,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.85,
            positiveClassFloor: 30,
        );

        $this->assertSame('expand', $result);
    }

    public function test_expands_when_a_stratum_is_empty_even_if_the_point_estimate_passes(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: false,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 100,
            precisionLowerBound: 0.95,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.85,
            positiveClassFloor: 30,
        );

        $this->assertSame('expand', $result);
    }

    public function test_expands_when_inter_rater_agreement_is_not_acceptable(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: false,
            positiveClassCount: 100,
            precisionLowerBound: 0.95,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.85,
            positiveClassFloor: 30,
        );

        $this->assertSame('expand', $result);
    }

    public function test_expands_when_the_precision_lower_bound_misses_the_gate(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 100,
            precisionLowerBound: 0.85,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.85,
            positiveClassFloor: 30,
        );

        $this->assertSame('expand', $result);
    }

    public function test_expands_when_the_recall_lower_bound_misses_the_gate(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 100,
            precisionLowerBound: 0.95,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.65,
            positiveClassFloor: 30,
        );

        $this->assertSame('expand', $result);
    }

    public function test_stop_fail_when_the_precision_upper_bound_is_below_the_gate(): void
    {
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 100,
            precisionLowerBound: 0.75,
            precisionUpperBound: 0.89,
            recallLowerBound: 0.85,
            positiveClassFloor: 30,
        );

        $this->assertSame('stop_fail', $result);
    }

    public function test_stop_fail_takes_priority_even_when_every_other_condition_would_otherwise_pass(): void
    {
        // A decisive failure must not be masked by e.g. a thin stratum —
        // FR-145 says stop labelling immediately, not "expand and see".
        $rule = new CalibrationExpansionRule;

        $result = $rule->evaluate(
            allStrataAndQuotasNonEmpty: false,
            interRaterAgreementAcceptable: false,
            positiveClassCount: 5,
            precisionLowerBound: 0.60,
            precisionUpperBound: 0.89,
            recallLowerBound: 0.50,
            positiveClassFloor: 30,
        );

        $this->assertSame('stop_fail', $result);
    }

    public function test_reads_the_positive_class_floor_from_config_when_not_passed_explicitly(): void
    {
        config(['lab.dedup.eval_positive_class_floor' => 30]);

        $rule = new CalibrationExpansionRule;

        $belowConfigFloor = $rule->evaluate(
            allStrataAndQuotasNonEmpty: true,
            interRaterAgreementAcceptable: true,
            positiveClassCount: 29,
            precisionLowerBound: 0.95,
            precisionUpperBound: 0.99,
            recallLowerBound: 0.85,
        );

        $this->assertSame('expand', $belowConfigFloor);
    }
}
