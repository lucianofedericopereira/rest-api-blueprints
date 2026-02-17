# Telemetry checklist

Goals:
- Helps debug real incidents  
- Supports fraud detection and financial integrity  
- Keeps SREs sane  
- Gives product teams visibility into business health  
- Prevents silent failures  
- Surfaces issues before customers feel them  

---

## **1. Logging Essentials**
- [ ] Every request has a correlation ID propagated end‑to‑end  
- [ ] Logs include request path, method, status code, latency, and caller identity  
- [ ] Sensitive data is never logged (PAN, CVV, tokens, passwords)  
- [ ] Errors include stack traces in non‑production environments  
- [ ] Production logs include error codes, not stack traces  
- [ ] Log levels are consistent (INFO for business events, WARN for anomalies, ERROR for failures)  
- [ ] Log volume is monitored to detect spikes or drops  

---

## **2. Metrics That Actually Matter**
- [ ] P95 and P99 latency are tracked for each endpoint  
- [ ] Error rate is tracked separately for 4xx and 5xx  
- [ ] Retries, timeouts, and circuit‑breaker events are counted  
- [ ] Queue depth and processing lag are monitored (if async flows exist)  
- [ ] Database query latency and error rate are tracked  
- [ ] External API dependency latency and failures are tracked  
- [ ] Rate‑limit events are logged and counted  

---

## **3. Tracing That Helps Debug Real Failures**
- [ ] Distributed tracing is enabled across all microservices  
- [ ] Traces include DB calls, external API calls, and internal hops  
- [ ] Slow traces are sampled at a higher rate  
- [ ] Traces include correlation IDs and user/session identifiers  
- [ ] Tracing is integrated with logs and metrics for pivoting  

---

## **4. Alerts That Don’t Wake People Up for Nothing**
- [ ] Alerts fire on sustained P99 latency degradation  
- [ ] Alerts fire on elevated 5xx error rates  
- [ ] Alerts fire on authentication/authorization anomalies  
- [ ] Alerts fire on dependency failures (DB, cache, external APIs)  
- [ ] Alerts fire on missing telemetry (e.g., no logs for 5 minutes)  
- [ ] Alerts include actionable runbooks  
- [ ] Alerts are routed to the right team, not a generic mailbox  

---

## **5. Telemetry for Fraud & Abuse Detection**
- [ ] Suspicious request patterns are logged (velocity, repeated failures)  
- [ ] Geo‑location anomalies are captured  
- [ ] Device fingerprint mismatches are logged  
- [ ] High‑value transactions are tagged for deeper monitoring  
- [ ] Telemetry integrates with fraud‑detection systems  

---

## **6. Operational Health Signals**
- [ ] CPU, memory, and disk usage are monitored with thresholds  
- [ ] Container restarts and crash loops are tracked  
- [ ] Deployment events are correlated with telemetry spikes  
- [ ] Autoscaling events are logged and monitored  
- [ ] TLS certificate expiration is monitored  
- [ ] DNS resolution failures are tracked  

---

## **7. Telemetry Hygiene**
- [ ] Telemetry pipelines are monitored for lag and ingestion failures  
- [ ] Log retention is tuned (not too short, not too expensive)  
- [ ] Metrics cardinality is controlled (no unbounded labels)  
- [ ] Telemetry schemas are versioned and documented  
- [ ] Telemetry is tested in staging before production rollout  

---

## **8. Business‑Level Telemetry**
- [ ] Payment success rate is tracked  
- [ ] Decline reasons are categorized and counted  
- [ ] Refund and chargeback events are logged  
- [ ] Transaction processing time is monitored  
- [ ] Partner API performance is tracked separately  

---
