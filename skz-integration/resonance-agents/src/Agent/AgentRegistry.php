<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;

/**
 * Registry for managing all autonomous agents
 */
#[Singleton]
class AgentRegistry
{
    /**
     * @var array<string, AgentInterface>
     */
    private array $agents = [];

    /**
     * @var array<string, array<string>>
     */
    private array $typeIndex = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Register an agent
     */
    public function register(AgentInterface $agent): void
    {
        $agentId = $agent->getId();
        $type = $agent->getType()->value;

        $this->agents[$agentId] = $agent;

        if (!isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = [];
        }
        $this->typeIndex[$type][] = $agentId;

        $this->logger->info("Agent registered", [
            'agent_id' => $agentId,
            'type' => $type,
            'name' => $agent->getName(),
        ]);
    }

    /**
     * Unregister an agent
     */
    public function unregister(string $agentId): void
    {
        if (!isset($this->agents[$agentId])) {
            return;
        }

        $agent = $this->agents[$agentId];
        $type = $agent->getType()->value;

        // Remove from type index
        if (isset($this->typeIndex[$type])) {
            $this->typeIndex[$type] = array_filter(
                $this->typeIndex[$type],
                fn($id) => $id !== $agentId
            );
        }

        unset($this->agents[$agentId]);

        $this->logger->info("Agent unregistered", ['agent_id' => $agentId]);
    }

    /**
     * Get agent by ID
     */
    public function getAgent(string $agentId): ?AgentInterface
    {
        return $this->agents[$agentId] ?? null;
    }

    /**
     * Get all registered agents
     *
     * @return array<string, AgentInterface>
     */
    public function getAllAgents(): array
    {
        return $this->agents;
    }

    /**
     * Get agents by type
     *
     * @return array<AgentInterface>
     */
    public function getAgentsByType(AgentType $type): array
    {
        $agentIds = $this->typeIndex[$type->value] ?? [];
        $agents = [];

        foreach ($agentIds as $id) {
            if (isset($this->agents[$id])) {
                $agents[] = $this->agents[$id];
            }
        }

        return $agents;
    }

    /**
     * Get first agent of a specific type
     */
    public function getAgentByType(AgentType $type): ?AgentInterface
    {
        $agents = $this->getAgentsByType($type);
        return $agents[0] ?? null;
    }

    /**
     * Get agents by capability
     *
     * @return array<AgentInterface>
     */
    public function getAgentsByCapability(AgentCapability $capability): array
    {
        $agents = [];

        foreach ($this->agents as $agent) {
            if ($agent->hasCapability($capability)) {
                $agents[] = $agent;
            }
        }

        return $agents;
    }

    /**
     * Initialize all agents
     */
    public function initializeAll(): void
    {
        foreach ($this->agents as $agent) {
            $agent->initialize();
        }

        $this->logger->info("All agents initialized", [
            'count' => count($this->agents),
        ]);
    }

    /**
     * Start all agents
     */
    public function startAll(): void
    {
        foreach ($this->agents as $agent) {
            $agent->start();
        }

        $this->logger->info("All agents started", [
            'count' => count($this->agents),
        ]);
    }

    /**
     * Stop all agents
     */
    public function stopAll(): void
    {
        foreach ($this->agents as $agent) {
            $agent->stop();
        }

        $this->logger->info("All agents stopped");
    }

    /**
     * Get comprehensive system status
     *
     * @return array<string, mixed>
     */
    public function getSystemStatus(): array
    {
        $agentStatuses = [];
        $healthyCount = 0;
        $totalTasks = 0;

        foreach ($this->agents as $id => $agent) {
            $health = $agent->getHealth();
            $metrics = $agent->getMetrics();

            $agentStatuses[$id] = [
                'name' => $agent->getName(),
                'type' => $agent->getType()->value,
                'status' => $agent->getStatus()->value,
                'healthy' => $health['healthy'],
                'tasks_processed' => $metrics['tasks_processed'] ?? 0,
            ];

            if ($health['healthy']) {
                $healthyCount++;
            }
            $totalTasks += $metrics['tasks_processed'] ?? 0;
        }

        return [
            'total_agents' => count($this->agents),
            'healthy_agents' => $healthyCount,
            'system_healthy' => $healthyCount === count($this->agents),
            'total_tasks_processed' => $totalTasks,
            'agents' => $agentStatuses,
            'types_registered' => array_keys($this->typeIndex),
            'timestamp' => time(),
        ];
    }
}
