# SKZ Agents Workflow Map

## Agent Architecture Overview

```
                              +----------------------------+
                              |   WORKFLOW ORCHESTRATION   |
                              |          AGENT             |
                              |    (Central Coordinator)   |
                              +-------------+--------------+
                                           |
              +------------------+---------+---------+------------------+
              |                  |                   |                  |
              v                  v                   v                  v
+-------------+------+  +-------+--------+  +-------+--------+  +------+---------+
|   RESEARCH         |  |  MANUSCRIPT    |  |   PEER REVIEW  |  |   EDITORIAL    |
|   DISCOVERY        |  |  ANALYSIS      |  |  COORDINATION  |  |   DECISION     |
|   AGENT            |  |  AGENT         |  |  AGENT         |  |   AGENT        |
+--------------------+  +----------------+  +----------------+  +----------------+
| - Literature search|  | - Quality eval |  | - Reviewer     |  | - Triage       |
| - Trend analysis   |  | - Plagiarism   |  |   matching     |  | - Final        |
| - Gap analysis     |  | - Formatting   |  | - Workload     |  |   decision     |
| - INCI mining      |  | - Statistics   |  |   management   |  | - Review       |
| - Patent analysis  |  +----------------+  +----------------+  |   analysis     |
+--------------------+                                          +----------------+
              |                  |                   |                  |
              +------------------+---------+---------+------------------+
                                           |
              +------------------+---------+---------+------------------+
              |                  |                   |
              v                  v                   v
+-------------+------+  +-------+--------+  +-------+--------+
|   PUBLICATION      |  |    QUALITY     |  |   ANALYTICS    |
|   FORMATTING       |  |   ASSURANCE    |  |   & MONITORING |
|   AGENT            |  |   AGENT        |  |   AGENT        |
+--------------------+  +----------------+  +----------------+
| - Manuscript fmt   |  | - Validation   |  | - Performance  |
| - Metadata gen     |  | - Compliance   |  | - Anomaly      |
| - Multi-format     |  | - Issue track  |  |   detection    |
| - Typography       |  | - Standards    |  | - Reporting    |
+--------------------+  +----------------+  +----------------+
```

## Optimized Workflow Definitions

### 1. New Submission Workflow (Optimized)

**Total Estimated Time: 12 hours** (reduced from 24 hours with parallel execution)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NEW SUBMISSION WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Stage 1: PARALLEL ANALYSIS (max 2 hours)                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                         Manuscript Analysis                          │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │    │
│  │  │ Format Check │  │  Plagiarism  │  │  Statistics  │  PARALLEL    │    │
│  │  │  (30 min)    │  │   Detection  │  │   Review     │              │    │
│  │  │              │  │   (45 min)   │  │   (30 min)   │              │    │
│  │  └──────────────┘  └──────────────┘  └──────────────┘              │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 2: EDITORIAL TRIAGE (30 min)                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Editorial Decision Agent - Initial Assessment & Triage              │    │
│  │  • Desk rejection check                                              │    │
│  │  • Scope validation                                                  │    │
│  │  • Initial quality gate                                              │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 3: PARALLEL PREPARATION (max 2 hours)                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  ┌──────────────────────┐      ┌──────────────────────┐            │    │
│  │  │  Reviewer Matching   │      │   Research Context   │  PARALLEL  │    │
│  │  │  (Peer Review Agent) │      │  (Research Discovery)│            │    │
│  │  │  • Find experts      │      │  • Related works     │            │    │
│  │  │  • Check conflicts   │      │  • Trend alignment   │            │    │
│  │  │  • Rank candidates   │      │  • Gap analysis      │            │    │
│  │  └──────────────────────┘      └──────────────────────┘            │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2. Review Complete Workflow (Optimized)

**Total Estimated Time: 24 hours** (reduced from 48 hours)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        REVIEW COMPLETE WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Stage 1: PARALLEL QUALITY ASSESSMENT (max 2 hours)                          │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │    │
│  │  │ Scientific       │  │ Methodology      │  │ Statistical      │  │    │
│  │  │ Validity Check   │  │ Assessment       │  │ Rigor Analysis   │  │    │
│  │  │ (QA Agent)       │  │ (QA Agent)       │  │ (Manuscript)     │  │    │
│  │  └──────────────────┘  └──────────────────┘  └──────────────────┘  │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 2: REVIEW AGGREGATION (1 hour)                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Editorial Decision Agent                                            │    │
│  │  • Aggregate reviewer scores                                         │    │
│  │  • Weight by reviewer expertise                                      │    │
│  │  • Identify consensus/conflicts                                      │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 3: DECISION SYNTHESIS (2 hours)                                       │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Editorial Decision Agent                                            │    │
│  │  • Generate decision rationale                                       │    │
│  │  • Produce author feedback                                           │    │
│  │  • Determine: Accept / Minor / Major / Reject                        │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3. Accepted Manuscript Workflow (Optimized)

**Total Estimated Time: 36 hours** (reduced from 72 hours)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      ACCEPTED MANUSCRIPT WORKFLOW                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Stage 1: PARALLEL PRODUCTION PREP (max 4 hours)                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │    │
│  │  │ Manuscript       │  │ Metadata         │  │ Reference        │  │    │
│  │  │ Formatting       │  │ Generation       │  │ Verification     │  │    │
│  │  │ (Pub Format)     │  │ (Pub Format)     │  │ (Research Disc)  │  │    │
│  │  └──────────────────┘  └──────────────────┘  └──────────────────┘  │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 2: QUALITY ASSURANCE GATE (2 hours)                                   │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  Quality Assurance Agent                                             │    │
│  │  • Full QA review                                                    │    │
│  │  • Compliance validation                                             │    │
│  │  • Standards enforcement                                             │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                   │                                          │
│                                   ▼                                          │
│  Stage 3: PARALLEL OUTPUT GENERATION (max 3 hours)                           │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │    │
│  │  │   PDF Gen    │  │   HTML Gen   │  │   XML/JATS   │  PARALLEL    │    │
│  │  │              │  │              │  │   Export     │              │    │
│  │  └──────────────┘  └──────────────┘  └──────────────┘              │    │
│  │                                                                      │    │
│  │  ┌──────────────┐  ┌──────────────┐                                │    │
│  │  │  DOI/CrossRef│  │  Index       │  PARALLEL                      │    │
│  │  │  Registration│  │  Submission  │                                │    │
│  │  └──────────────┘  └──────────────┘                                │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Agent Capability Matrix

| Agent | Primary Capabilities | Secondary Capabilities | Parallel Safe |
|-------|---------------------|----------------------|---------------|
| **Research Discovery** | Literature search, Trend analysis, INCI mining, Patent analysis | Gap analysis, Context enrichment | Yes |
| **Manuscript Analysis** | Quality assessment, Plagiarism detection, Format validation | Statistical review, Enhancement suggestions | Yes |
| **Peer Review Coordination** | Reviewer matching, Assignment optimization | Workload balancing, Conflict detection | Yes |
| **Editorial Decision** | Decision making, Review synthesis | Rationale generation, Quality gating | No (sequential) |
| **Publication Formatting** | Manuscript formatting, Multi-format export | Typography, Layout optimization | Yes (per format) |
| **Quality Assurance** | Validation, Compliance checking | Issue tracking, Standards enforcement | Yes |
| **Analytics & Monitoring** | Performance tracking, Reporting | Anomaly detection, Bottleneck identification | Yes |

## Agent Communication Patterns

### Direct Communication (Point-to-Point)
```
[Agent A] ──── COMMAND ────> [Agent B]
[Agent B] ──── RESPONSE ───> [Agent A]
```

### Capability-Based Routing
```
[Orchestrator] ──── TASK(capability=X) ────> [Message Broker]
                                                    │
                    ┌───────────────────────────────┴───────────────────────────────┐
                    │                               │                               │
                    ▼                               ▼                               ▼
            [Agent with X]                  [Agent with X]                  [Agent with X]
             (selected)                      (available)                     (available)
```

### Broadcast Pattern
```
[Orchestrator] ──── BROADCAST ────> [All Agents]
```

### Fan-Out / Fan-In (Parallel Processing)
```
                              ┌────> [Agent 1] ────┐
[Orchestrator] ── FAN OUT ───>├────> [Agent 2] ────├── FAN IN ──> [Orchestrator]
                              └────> [Agent 3] ────┘
```

## Workflow Optimization Strategies

### 1. Parallel Task Execution
- **Before**: Sequential stages (24+ hours total)
- **After**: Parallel independent tasks (12 hours total)
- **Gain**: 50% reduction in processing time

### 2. Early Exit Gates
```
If (desk_rejection_criteria_met) {
    SKIP peer_review_stage
    EMIT rejection_notice
}
```

### 3. Predictive Pre-Processing
- Start reviewer search **during** manuscript analysis
- Begin metadata extraction **before** final acceptance
- Cache commonly needed research context

### 4. Adaptive Load Balancing
```
Agent Selection Score =
    (success_rate × 0.4) +
    (available_capacity × 0.3) +
    (avg_speed_factor × 0.3)
```

### 5. Circuit Breaker Pattern
```
if (consecutive_failures >= 5) {
    CIRCUIT_OPEN
    wait(recovery_timeout: 30s)
    allow_test_requests(3)
    if (all_succeed) CIRCUIT_CLOSED
}
```

## Performance Metrics

| Metric | Target | Current | Optimized |
|--------|--------|---------|-----------|
| New Submission Processing | < 12h | 24h | 12h |
| Review Complete Processing | < 24h | 48h | 24h |
| Accepted Manuscript Processing | < 36h | 72h | 36h |
| Agent Response Time | < 100ms | 2-4s | < 500ms |
| Message Delivery Rate | > 99% | 95% | 99.5% |
| Parallel Task Utilization | > 70% | 40% | 75% |

## Error Handling & Recovery

### Retry Strategy
```
RetryPolicy {
    max_attempts: 3
    backoff: exponential(base: 2s, max: 30s)
    retryable_errors: [TIMEOUT, NETWORK_ERROR, AGENT_BUSY]
    non_retryable: [VALIDATION_ERROR, AUTH_ERROR]
}
```

### Fallback Chain
```
Primary Agent ──fail──> Backup Agent ──fail──> Manual Queue
```

### State Persistence
- Workflow state saved after each stage completion
- Enables resume from last checkpoint on failure
- Audit trail for all agent decisions
