<?php

declare(strict_types=1);

/**
 * Agent Interaction Matrix Configuration
 *
 * Defines how agents communicate, dependencies, and workflow patterns
 * for optimized orchestration of the 7 autonomous agents.
 */

return [
    /**
     * Agent definitions with capabilities and performance characteristics
     */
    'agents' => [
        'research_discovery' => [
            'type' => 'research_discovery',
            'capabilities' => [
                'literature_search',
                'trend_identification',
                'research_gap_analysis',
                'inci_database_mining',
                'patent_analysis',
                'gather_context',
                'verify_references',
            ],
            'parallel_safe' => true,
            'max_concurrent_tasks' => 5,
            'avg_task_duration_seconds' => 180,
            'priority_boost_triggers' => ['high_impact', 'invited_submission'],
        ],
        'manuscript_analysis' => [
            'type' => 'manuscript_analysis',
            'capabilities' => [
                'quality_assessment',
                'plagiarism_detection',
                'formatting_validation',
                'statistical_review',
                'format_check',
                'statistical_rigor',
                'diff_analysis',
            ],
            'parallel_safe' => true,
            'max_concurrent_tasks' => 4,
            'avg_task_duration_seconds' => 240,
            'priority_boost_triggers' => ['revision_resubmission'],
        ],
        'peer_review_coordination' => [
            'type' => 'peer_review_coordination',
            'capabilities' => [
                'reviewer_matching',
                'review_tracking',
                'workload_management',
                'find_reviewers',
                'notify_reviewers',
                'conflict_detection',
            ],
            'parallel_safe' => true,
            'max_concurrent_tasks' => 3,
            'avg_task_duration_seconds' => 300,
            'priority_boost_triggers' => ['urgent_review', 'reviewer_shortage'],
        ],
        'editorial_decision' => [
            'type' => 'editorial_decision',
            'capabilities' => [
                'decision_making',
                'review_analysis',
                'rationale_generation',
                'triage_submission',
                'aggregate_reviews',
                'make_decision',
                'revision_decision',
            ],
            'parallel_safe' => false, // Decision tasks should be sequential
            'max_concurrent_tasks' => 2,
            'avg_task_duration_seconds' => 600,
            'priority_boost_triggers' => ['deadline_approaching'],
        ],
        'publication_formatting' => [
            'type' => 'publication_formatting',
            'capabilities' => [
                'manuscript_formatting',
                'format_manuscript',
                'generate_metadata',
                'export_pdf',
                'export_html',
                'export_jats_xml',
                'register_doi',
                'multi_format_export',
            ],
            'parallel_safe' => true, // Different format exports can run in parallel
            'max_concurrent_tasks' => 6,
            'avg_task_duration_seconds' => 120,
            'priority_boost_triggers' => ['issue_deadline'],
        ],
        'quality_assurance' => [
            'type' => 'quality_assurance',
            'capabilities' => [
                'quality_validation',
                'compliance_checking',
                'issue_tracking',
                'scientific_validity',
                'methodology_assessment',
                'full_qa_review',
                'verify_revisions',
            ],
            'parallel_safe' => true,
            'max_concurrent_tasks' => 4,
            'avg_task_duration_seconds' => 360,
            'priority_boost_triggers' => ['quality_concern'],
        ],
        'workflow_orchestration' => [
            'type' => 'workflow_orchestration',
            'capabilities' => [
                'agent_coordination',
                'workflow_management',
                'process_optimization',
                'analytics_reporting',
                'alert_handling',
            ],
            'parallel_safe' => false, // Orchestrator is singleton
            'max_concurrent_tasks' => 10,
            'avg_task_duration_seconds' => 30,
            'priority_boost_triggers' => [],
        ],
    ],

    /**
     * Agent interaction patterns - defines which agents communicate with which
     */
    'interaction_matrix' => [
        // Row = sender, Column = receiver, Value = interaction type
        'research_discovery' => [
            'manuscript_analysis' => 'context_enrichment',
            'editorial_decision' => 'research_insights',
            'workflow_orchestration' => 'status_updates',
        ],
        'manuscript_analysis' => [
            'editorial_decision' => 'quality_report',
            'quality_assurance' => 'analysis_handoff',
            'peer_review_coordination' => 'manuscript_summary',
            'workflow_orchestration' => 'status_updates',
        ],
        'peer_review_coordination' => [
            'editorial_decision' => 'review_collection',
            'manuscript_analysis' => 'revision_request',
            'workflow_orchestration' => 'status_updates',
        ],
        'editorial_decision' => [
            'peer_review_coordination' => 'assignment_directive',
            'publication_formatting' => 'acceptance_notice',
            'manuscript_analysis' => 'revision_request',
            'workflow_orchestration' => 'decision_notification',
        ],
        'publication_formatting' => [
            'quality_assurance' => 'format_review_request',
            'workflow_orchestration' => 'status_updates',
        ],
        'quality_assurance' => [
            'editorial_decision' => 'qa_report',
            'publication_formatting' => 'correction_request',
            'manuscript_analysis' => 'quality_feedback',
            'workflow_orchestration' => 'status_updates',
        ],
        'workflow_orchestration' => [
            'research_discovery' => 'task_dispatch',
            'manuscript_analysis' => 'task_dispatch',
            'peer_review_coordination' => 'task_dispatch',
            'editorial_decision' => 'task_dispatch',
            'publication_formatting' => 'task_dispatch',
            'quality_assurance' => 'task_dispatch',
        ],
    ],

    /**
     * Workflow stage definitions with parallel execution configuration
     */
    'workflow_stages' => [
        'new_submission' => [
            [
                'name' => 'parallel_analysis',
                'agents' => ['manuscript_analysis'],
                'tasks' => ['format_check', 'plagiarism_detection', 'statistical_review'],
                'execution' => 'parallel',
                'timeout_seconds' => 3600,
                'failure_policy' => 'continue_on_partial', // Continue if at least one succeeds
            ],
            [
                'name' => 'editorial_triage',
                'agents' => ['editorial_decision'],
                'tasks' => ['triage_submission'],
                'execution' => 'sequential',
                'timeout_seconds' => 1800,
                'early_exit_conditions' => ['desk_rejection'],
            ],
            [
                'name' => 'parallel_preparation',
                'agents' => ['peer_review_coordination', 'research_discovery'],
                'tasks' => ['find_reviewers', 'gather_context'],
                'execution' => 'parallel',
                'timeout_seconds' => 7200,
                'failure_policy' => 'fail_fast',
            ],
        ],
        'review_complete' => [
            [
                'name' => 'parallel_quality',
                'agents' => ['quality_assurance', 'manuscript_analysis'],
                'tasks' => ['scientific_validity', 'methodology_assessment', 'statistical_rigor'],
                'execution' => 'parallel',
                'timeout_seconds' => 3600,
            ],
            [
                'name' => 'review_aggregation',
                'agents' => ['editorial_decision'],
                'tasks' => ['aggregate_reviews'],
                'execution' => 'sequential',
                'timeout_seconds' => 3600,
            ],
            [
                'name' => 'decision_synthesis',
                'agents' => ['editorial_decision'],
                'tasks' => ['make_decision'],
                'execution' => 'sequential',
                'timeout_seconds' => 7200,
            ],
        ],
        'accepted_manuscript' => [
            [
                'name' => 'parallel_production',
                'agents' => ['publication_formatting', 'research_discovery'],
                'tasks' => ['format_manuscript', 'generate_metadata', 'verify_references'],
                'execution' => 'parallel',
                'timeout_seconds' => 7200,
            ],
            [
                'name' => 'qa_gate',
                'agents' => ['quality_assurance'],
                'tasks' => ['full_qa_review'],
                'execution' => 'sequential',
                'timeout_seconds' => 7200,
                'checkpoint' => true, // Must pass before proceeding
            ],
            [
                'name' => 'parallel_export',
                'agents' => ['publication_formatting'],
                'tasks' => ['export_pdf', 'export_html', 'export_jats_xml', 'register_doi'],
                'execution' => 'parallel',
                'timeout_seconds' => 3600,
            ],
        ],
    ],

    /**
     * Load balancing configuration
     */
    'load_balancing' => [
        'strategy' => 'weighted_round_robin',
        'weights' => [
            'availability' => 0.35,
            'performance' => 0.30,
            'queue_depth' => 0.20,
            'success_rate' => 0.15,
        ],
        'health_check_interval_seconds' => 30,
        'unhealthy_threshold' => 3, // Consecutive failures before marking unhealthy
        'recovery_threshold' => 2, // Consecutive successes to mark healthy again
    ],

    /**
     * Circuit breaker configuration
     */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'recovery_timeout_seconds' => 30,
        'half_open_requests' => 3,
        'monitored_errors' => ['timeout', 'connection_error', 'internal_error'],
    ],

    /**
     * Retry policy configuration
     */
    'retry_policy' => [
        'max_attempts' => 3,
        'base_delay_seconds' => 2,
        'max_delay_seconds' => 30,
        'backoff_multiplier' => 2.0,
        'retryable_errors' => ['timeout', 'network_error', 'agent_busy'],
        'non_retryable_errors' => ['validation_error', 'auth_error', 'rejected'],
    ],

    /**
     * Performance thresholds for alerting
     */
    'performance_thresholds' => [
        'max_queue_size' => 20,
        'max_processing_time_seconds' => 600,
        'min_success_rate' => 0.90,
        'max_response_time_p95_ms' => 500,
        'warning_utilization' => 0.80,
        'critical_utilization' => 0.95,
    ],
];
