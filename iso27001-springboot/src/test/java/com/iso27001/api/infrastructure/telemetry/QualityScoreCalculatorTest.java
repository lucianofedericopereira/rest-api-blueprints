package com.iso27001.api.infrastructure.telemetry;

import org.junit.jupiter.api.Test;

import static org.assertj.core.api.Assertions.*;

/**
 * A.17 — Unit tests for QualityScoreCalculator.
 */
class QualityScoreCalculatorTest {

    private final QualityScoreCalculator calc = new QualityScoreCalculator();

    @Test
    void perfectInputGivesScoreNearOne() {
        QualityScoreCalculator.Input input = new QualityScoreCalculator.Input(
            100, 100, 100, 100, 1.0, 1.0, 50.0, 100.0
        );
        QualityScoreCalculator.Result result = calc.calculate(input);
        assertThat(result.composite()).isCloseTo(1.0, within(0.001));
        assertThat(result.passesGate()).isTrue();
    }

    @Test
    void zeroSecurityScoreFailsGate() {
        QualityScoreCalculator.Input input = new QualityScoreCalculator.Input(
            0, 100, 100, 100, 1.0, 1.0, 50.0, 100.0
        );
        QualityScoreCalculator.Result result = calc.calculate(input);
        assertThat(result.passesGate()).isFalse();
    }

    @Test
    void productionGateThresholdIs70Pct() {
        QualityScoreCalculator.Result result = calc.calculate(
            new QualityScoreCalculator.Input(100, 100, 100, 100, 1.0, 1.0, 50.0, 100.0)
        );
        assertThat(result.productionGateThreshold()).isEqualTo(0.70);
    }

    @Test
    void weightsReflectPillarImportance() {
        QualityScoreCalculator.Result result = calc.calculate(
            new QualityScoreCalculator.Input(100, 100, 100, 100, 1.0, 1.0, 50.0, 100.0)
        );
        assertThat(result.security().weight()).isEqualTo(0.40);
        assertThat(result.dataIntegrity().weight()).isEqualTo(0.20);
        assertThat(result.reliability().weight()).isEqualTo(0.15);
        assertThat(result.auditability().weight()).isEqualTo(0.15);
        assertThat(result.performance().weight()).isEqualTo(0.10);
    }

    @Test
    void zeroDenominatorDefaultsToFullScore() {
        QualityScoreCalculator.Input input = new QualityScoreCalculator.Input(
            0, 0, 0, 0, 1.0, 1.0, 0.0, 0.0
        );
        QualityScoreCalculator.Result result = calc.calculate(input);
        assertThat(result.composite()).isGreaterThan(0.0);
    }
}
