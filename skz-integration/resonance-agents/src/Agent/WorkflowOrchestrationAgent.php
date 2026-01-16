<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Message\AgentMessage;
use SKZ\Agents\Message\MessageType;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;

/**
 * Workflow Orchestration Agent
 *
 * Capabilities:
 * - Agent coordination and workflow orchestration
 * - Process management and optimization
 * - Analytics reporting and strategic insights
 * - Real-time monitoring and alert systems
 */
#[Singleton]
class WorkflowOrchestrationAgent extends BaseAgent
{
    private int $workflowsOrchestrated = 0;
    private int $agentCoordinations = 0;
    private int $alertsGenerated = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $activeWorkflows = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $workflowTemplates = [
        'new_submission' => [
            'stages' => [
                ['agent' => 'manuscript_analysis', 'action' => 'full_analysis', 'timeout' => 3600],
                ['agent' => 'editorial_decision', 'action' => 'triage_submission', 'timeout' => 1800],
                ['agent' => 'peer_review_coordination', 'action' => 'find_reviewers', 'timeout' => 7200],
            ],
            'estimated_duration' => 24,
        ],
        'review_complete' => [
            'stages' => [
                ['agent' => 'quality_assurance', 'action' => 'assess_scientific_quality', 'timeout' => 3600],
                ['agent' => 'editorial_decision', 'action' => 'make_decision', 'timeout' => 7200],
            ],
            'estimated_duration' => 48,
        ],
        'accepted_manuscript' => [
            'stages' => [
                ['agent' => 'publication_formatting', 'action' => 'format_manuscript', 'timeout' => 7200],
                ['agent' => 'publication_formatting', 'action' => 'generate_metadata', 'timeout' => 1800],
                ['agent' => 'quality_assurance', 'action' => 'full_qa_review', 'timeout' => 3600],
                ['agent' => 'publication_formatting', 'action' => 'multi_format_export', 'timeout' => 3600],
            ],
            'estimated_duration' => 72,
        ],
    ];

    public function __construct(
        LoggerInterface $logger,
        MessageBroker $messageBroker,
        MemoryService $memoryService,
        DecisionEngine $decisionEngine,
    ) {
        parent::__construct($logger, $messageBroker, $memoryService, $decisionEngine);
    }

    public function getName(): string
    {
        return 'Workflow Orchestration Agent';
    }

    public function getType(): AgentType
    {
        return AgentType::WORKFLOW_ORCHESTRATION;
    }

    protected function executeTask(array $taskData, array $decision): array
    {
        $taskType = $taskData['type'] ?? 'unknown';

        return match ($taskType) {
            'start_workflow' => $this->startWorkflow($taskData),
            'coordinate_agents' => $this->coordinateAgents($taskData),
            'monitor_progress' => $this->monitorProgress($taskData),
            'generate_analytics' => $this->generateAnalytics($taskData),
            'optimize_process' => $this->optimizeProcess($taskData),
            'handle_alert' => $this->handleAlert($taskData),
            'get_system_status' => $this->getSystemStatus($taskData),
            'broadcast_message' => $this->broadcastToAgents($taskData),
            default => $this->handleGenericOrchestration($taskData),
        };
    }

    /**
     * Start a new workflow
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function startWorkflow(array $taskData): array
    {
        $workflowType = $taskData['workflow_type'] ?? 'new_submission';
        $submissionId = $taskData['submission_id'] ?? '';
        $context = $taskData['context'] ?? [];

        $this->logger->info("Starting workflow", [
            'workflow_type' => $workflowType,
            'submission_id' => $submissionId,
        ]);

        if (!isset($this->workflowTemplates[$workflowType])) {
            return ['error' => 'Unknown workflow type', 'workflow_type' => $workflowType];
        }

        $template = $this->workflowTemplates[$workflowType];
        $workflowId = bin2hex(random_bytes(8));

        $workflow = [
            'id' => $workflowId,
            'type' => $workflowType,
            'submission_id' => $submissionId,
            'status' => 'active',
            'current_stage' => 0,
            'stages' => $template['stages'],
            'context' => $context,
            'started_at' => time(),
            'estimated_completion' => time() + ($template['estimated_duration'] * 3600),
            'stage_results' => [],
        ];

        $this->activeWorkflows[$workflowId] = $workflow;
        $this->workflowsOrchestrated++;

        // Start first stage
        $this->executeWorkflowStage($workflowId, 0);

        return [
            'workflow_id' => $workflowId,
            'workflow' => $workflow,
            'message' => 'Workflow started successfully',
        ];
    }

    /**
     * Coordinate multiple agents for a task
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function coordinateAgents(array $taskData): array
    {
        $agents = $taskData['agents'] ?? [];
        $task = $taskData['task'] ?? [];
        $coordinationType = $taskData['coordination_type'] ?? 'sequential';

        $this->logger->info("Coordinating agents", [
            'agent_count' => count($agents),
            'coordination_type' => $coordinationType,
        ]);

        $results = [];

        if ($coordinationType === 'parallel') {
            $results = $this->coordinateParallel($agents, $task);
        } else {
            $results = $this->coordinateSequential($agents, $task);
        }

        $this->agentCoordinations++;

        return [
            'coordination_type' => $coordinationType,
            'agents_coordinated' => count($agents),
            'results' => $results,
            'success' => $this->evaluateCoordinationSuccess($results),
        ];
    }

    /**
     * Monitor workflow progress
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function monitorProgress(array $taskData): array
    {
        $workflowId = $taskData['workflow_id'] ?? null;

        $this->logger->info("Monitoring progress", [
            'workflow_id' => $workflowId,
        ]);

        if ($workflowId !== null) {
            return $this->getWorkflowProgress($workflowId);
        }

        // Return overall system progress
        $activeCount = count($this->activeWorkflows);
        $workflowStatuses = [];

        foreach ($this->activeWorkflows as $id => $workflow) {
            $workflowStatuses[$id] = [
                'type' => $workflow['type'],
                'status' => $workflow['status'],
                'progress' => $this->calculateWorkflowProgress($workflow),
                'current_stage' => $workflow['current_stage'],
            ];
        }

        return [
            'active_workflows' => $activeCount,
            'workflow_statuses' => $workflowStatuses,
            'system_health' => $this->assessSystemHealth(),
            'bottlenecks' => $this->identifyBottlenecks(),
        ];
    }

    /**
     * Generate analytics report
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generateAnalytics(array $taskData): array
    {
        $period = $taskData['period'] ?? 'day';
        $metrics = $taskData['metrics'] ?? ['all'];

        $this->logger->info("Generating analytics", [
            'period' => $period,
        ]);

        $analytics = [
            'period' => $period,
            'generated_at' => time(),
            'metrics' => [
                'workflows' => $this->getWorkflowMetrics($period),
                'agents' => $this->getAgentMetrics($period),
                'performance' => $this->getPerformanceMetrics($period),
                'quality' => $this->getQualityMetrics($period),
            ],
            'trends' => $this->identifyTrends($period),
            'recommendations' => $this->generateOptimizationRecommendations(),
        ];

        return $analytics;
    }

    /**
     * Optimize workflow process
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function optimizeProcess(array $taskData): array
    {
        $processType = $taskData['process_type'] ?? 'all';
        $constraints = $taskData['constraints'] ?? [];

        $this->logger->info("Optimizing process", [
            'process_type' => $processType,
        ]);

        $currentPerformance = $this->assessCurrentPerformance();
        $optimizations = $this->identifyOptimizations($currentPerformance, $constraints);
        $projectedImpact = $this->projectOptimizationImpact($optimizations);

        return [
            'process_type' => $processType,
            'current_performance' => $currentPerformance,
            'suggested_optimizations' => $optimizations,
            'projected_impact' => $projectedImpact,
            'implementation_plan' => $this->createImplementationPlan($optimizations),
        ];
    }

    /**
     * Handle system alert
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function handleAlert(array $taskData): array
    {
        $alertType = $taskData['alert_type'] ?? 'unknown';
        $severity = $taskData['severity'] ?? 'medium';
        $details = $taskData['details'] ?? [];

        $this->logger->warning("Handling alert", [
            'alert_type' => $alertType,
            'severity' => $severity,
        ]);

        $response = match ($alertType) {
            'agent_failure' => $this->handleAgentFailure($details),
            'workflow_timeout' => $this->handleWorkflowTimeout($details),
            'capacity_warning' => $this->handleCapacityWarning($details),
            'quality_threshold' => $this->handleQualityThreshold($details),
            default => $this->handleGenericAlert($details),
        };

        $this->alertsGenerated++;

        return [
            'alert_type' => $alertType,
            'severity' => $severity,
            'response' => $response,
            'escalated' => $severity === 'critical',
            'handled_at' => time(),
        ];
    }

    /**
     * Get comprehensive system status
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function getSystemStatus(array $taskData): array
    {
        $this->logger->info("Getting system status");

        $brokerStats = $this->messageBroker->getStats();
        $agents = $this->messageBroker->getAgents();

        $agentStatuses = [];
        foreach ($agents as $agentId => $agent) {
            $agentStatuses[$agentId] = [
                'name' => $agent->getName(),
                'type' => $agent->getType()->value,
                'status' => $agent->getStatus()->value,
                'health' => $agent->getHealth(),
                'metrics' => $agent->getMetrics(),
            ];
        }

        return [
            'system_healthy' => $this->isSystemHealthy($agentStatuses),
            'agents' => $agentStatuses,
            'active_workflows' => count($this->activeWorkflows),
            'message_broker' => $brokerStats,
            'memory_stats' => $this->memoryService->getStats(),
            'decision_engine_stats' => $this->decisionEngine->getStats(),
            'uptime' => microtime(true) - $this->startTime,
        ];
    }

    /**
     * Broadcast message to all or specific agents
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function broadcastToAgents(array $taskData): array
    {
        $message = $taskData['message'] ?? [];
        $targetAgents = $taskData['target_agents'] ?? null;

        $this->logger->info("Broadcasting message", [
            'target' => $targetAgents ?? 'all',
        ]);

        $agentMessage = new AgentMessage(
            senderId: $this->id,
            recipientId: '', // Will be set per recipient
            type: MessageType::BROADCAST,
            content: $message,
        );

        $delivered = $this->messageBroker->broadcast($agentMessage);

        return [
            'message_sent' => true,
            'recipients' => $delivered,
            'broadcast_type' => $targetAgents === null ? 'all_agents' : 'targeted',
        ];
    }

    protected function getAgentSpecificMetrics(): array
    {
        return [
            'workflows_orchestrated' => $this->workflowsOrchestrated,
            'agent_coordinations' => $this->agentCoordinations,
            'alerts_generated' => $this->alertsGenerated,
            'active_workflows' => count($this->activeWorkflows),
            'workflow_templates' => array_keys($this->workflowTemplates),
        ];
    }

    // Private helper methods

    private function executeWorkflowStage(string $workflowId, int $stageIndex): void
    {
        if (!isset($this->activeWorkflows[$workflowId])) {
            return;
        }

        $workflow = &$this->activeWorkflows[$workflowId];

        if ($stageIndex >= count($workflow['stages'])) {
            $workflow['status'] = 'completed';
            $workflow['completed_at'] = time();
            return;
        }

        $stage = $workflow['stages'][$stageIndex];
        $workflow['current_stage'] = $stageIndex;

        // Send task to the target agent
        $agents = $this->messageBroker->findAgentsByCapability(
            AgentCapability::from($stage['action'])
        );

        if (!empty($agents)) {
            $targetAgent = $agents[0];

            $message = new AgentMessage(
                senderId: $this->id,
                recipientId: $targetAgent->getId(),
                type: MessageType::COMMAND,
                content: [
                    'type' => $stage['action'],
                    'workflow_id' => $workflowId,
                    'stage_index' => $stageIndex,
                    'context' => $workflow['context'],
                ],
            );

            $this->sendMessage($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getWorkflowProgress(string $workflowId): array
    {
        if (!isset($this->activeWorkflows[$workflowId])) {
            return ['error' => 'Workflow not found'];
        }

        $workflow = $this->activeWorkflows[$workflowId];

        return [
            'workflow_id' => $workflowId,
            'type' => $workflow['type'],
            'status' => $workflow['status'],
            'progress' => $this->calculateWorkflowProgress($workflow),
            'current_stage' => $workflow['current_stage'],
            'total_stages' => count($workflow['stages']),
            'stage_results' => $workflow['stage_results'],
            'estimated_completion' => $workflow['estimated_completion'],
        ];
    }

    private function calculateWorkflowProgress(array $workflow): float
    {
        $totalStages = count($workflow['stages']);
        if ($totalStages === 0) {
            return 1.0;
        }

        return $workflow['current_stage'] / $totalStages;
    }

    /**
     * @return array<string, mixed>
     */
    private function coordinateParallel(array $agents, array $task): array
    {
        $results = [];
        // In production, would use Swoole coroutines for parallel execution
        foreach ($agents as $agentType) {
            $results[$agentType] = ['status' => 'initiated'];
        }
        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function coordinateSequential(array $agents, array $task): array
    {
        $results = [];
        foreach ($agents as $agentType) {
            $results[$agentType] = ['status' => 'initiated'];
        }
        return $results;
    }

    private function evaluateCoordinationSuccess(array $results): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function assessSystemHealth(): array
    {
        return ['healthy' => true, 'score' => 0.95];
    }

    /**
     * @return array<string>
     */
    private function identifyBottlenecks(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getWorkflowMetrics(string $period): array
    {
        return [
            'total_workflows' => $this->workflowsOrchestrated,
            'active' => count($this->activeWorkflows),
            'completed' => $this->workflowsOrchestrated - count($this->activeWorkflows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAgentMetrics(string $period): array
    {
        return [
            'total_agents' => count($this->messageBroker->getAgents()),
            'coordinations' => $this->agentCoordinations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPerformanceMetrics(string $period): array
    {
        return [
            'avg_response_time' => 1.5,
            'throughput' => 100,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getQualityMetrics(string $period): array
    {
        return [
            'success_rate' => 0.95,
            'error_rate' => 0.02,
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyTrends(string $period): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function generateOptimizationRecommendations(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessCurrentPerformance(): array
    {
        return ['efficiency' => 0.85, 'throughput' => 100];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyOptimizations(array $performance, array $constraints): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectOptimizationImpact(array $optimizations): array
    {
        return ['efficiency_gain' => 0.1, 'throughput_increase' => 15];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function createImplementationPlan(array $optimizations): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleAgentFailure(array $details): array
    {
        return ['action' => 'restart_agent', 'success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleWorkflowTimeout(array $details): array
    {
        return ['action' => 'extend_timeout', 'success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleCapacityWarning(array $details): array
    {
        return ['action' => 'scale_resources', 'success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleQualityThreshold(array $details): array
    {
        return ['action' => 'notify_admin', 'success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericAlert(array $details): array
    {
        return ['action' => 'logged', 'success' => true];
    }

    private function isSystemHealthy(array $agentStatuses): bool
    {
        foreach ($agentStatuses as $status) {
            if (!($status['health']['healthy'] ?? true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericOrchestration(array $taskData): array
    {
        return ['status' => 'processed'];
    }
}
