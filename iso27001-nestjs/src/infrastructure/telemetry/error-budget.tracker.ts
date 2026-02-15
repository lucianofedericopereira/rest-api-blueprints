/**
 * A.17: Error Budget Tracker — SLA enforcement.
 *
 * Tracks availability against a configured error budget. When the budget is
 * exhausted the system should freeze risky deployments and trigger alerts.
 *
 * SLA mapping:
 *   99.9%  → 43.8 min downtime budget per month
 *   99.95% → 21.9 min downtime budget per month
 *   99.99% →  4.4 min downtime budget per month
 */
export interface ErrorBudgetSnapshot {
  slaTarget: number;
  totalRequests: number;
  failedRequests: number;
  clientErrors: number;
  observedAvailability: number;
  budgetConsumedPct: number;
  budgetExhausted: boolean;
}

export class ErrorBudgetTracker {
  private total = 0;
  private failed = 0;   // 5xx — consumes budget
  private clientErr = 0; // 4xx — tracked separately

  constructor(private readonly slaTarget: number = 0.999) {
    if (slaTarget <= 0 || slaTarget >= 1) {
      throw new Error('slaTarget must be between 0 and 1 exclusive');
    }
  }

  record(statusCode: number): void {
    this.total += 1;
    if (statusCode >= 500) {
      this.failed += 1;
    } else if (statusCode >= 400) {
      this.clientErr += 1;
    }
  }

  snapshot(): ErrorBudgetSnapshot {
    if (this.total === 0) {
      return {
        slaTarget: this.slaTarget,
        totalRequests: 0,
        failedRequests: 0,
        clientErrors: 0,
        observedAvailability: 1.0,
        budgetConsumedPct: 0.0,
        budgetExhausted: false,
      };
    }
    const availability = (this.total - this.failed) / this.total;
    const allowedErrorRate = 1.0 - this.slaTarget;
    const actualErrorRate = this.failed / this.total;
    const budgetConsumedPct = allowedErrorRate === 0
      ? (this.failed > 0 ? 100.0 : 0.0)
      : Math.min((actualErrorRate / allowedErrorRate) * 100.0, 100.0);

    const rounded = Math.round(budgetConsumedPct * 100) / 100;
    return {
      slaTarget: this.slaTarget,
      totalRequests: this.total,
      failedRequests: this.failed,
      clientErrors: this.clientErr,
      observedAvailability: Math.round(availability * 1_000_000) / 1_000_000,
      budgetConsumedPct: rounded,
      budgetExhausted: rounded >= 100.0,
    };
  }

  reset(): void {
    this.total = 0;
    this.failed = 0;
    this.clientErr = 0;
  }
}

/** Module-level singleton — shared across middleware */
export const errorBudget = new ErrorBudgetTracker(0.999);
