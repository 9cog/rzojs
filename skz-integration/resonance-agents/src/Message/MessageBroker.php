<?php

declare(strict_types=1);

namespace SKZ\Agents\Message;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Agent\AgentInterface;
use SKZ\Agents\Agent\AgentCapability;
use Swoole\Table;

/**
 * Message broker for inter-agent communication
 * Uses Swoole shared memory for high-performance message routing
 * Optimized with load balancing and priority-based routing
 */
#[Singleton]
class MessageBroker
{
    /**
     * @var array<string, AgentInterface>
     */
    private array $agents = [];

    /**
     * @var array<string, array<AgentMessage>>
     */
    private array $messageQueues = [];

    /**
     * @var array<string, array<string>>
     */
    private array $capabilityIndex = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $agentMetricsCache = [];

    /**
     * @var array<string, float>
     */
    private array $agentLoadScores = [];

    private int $messagesSent = 0;
    private int $messagesDelivered = 0;
    private int $messagesRouted = 0;
    private int $loadBalancedRoutes = 0;

    /**
     * Routing strategy weights for load balancing
     */
    private const ROUTING_WEIGHTS = [
        'availability' => 0.35,
        'performance' => 0.30,
        'queue_depth' => 0.20,
        'success_rate' => 0.15,
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Register an agent with the broker
     */
    public function registerAgent(AgentInterface $agent): void
    {
        $agentId = $agent->getId();

        $this->agents[$agentId] = $agent;
        $this->messageQueues[$agentId] = [];

        // Index agent capabilities for routing
        foreach ($agent->getCapabilities() as $capability) {
            $capName = $capability->value;
            if (!isset($this->capabilityIndex[$capName])) {
                $this->capabilityIndex[$capName] = [];
            }
            $this->capabilityIndex[$capName][] = $agentId;
        }

        $this->logger->info("Agent registered with broker", [
            'agent_id' => $agentId,
            'agent_type' => $agent->getType()->value,
            'capabilities' => array_map(fn($c) => $c->value, $agent->getCapabilities()),
        ]);
    }

    /**
     * Unregister an agent from the broker
     */
    public function unregisterAgent(string $agentId): void
    {
        if (!isset($this->agents[$agentId])) {
            return;
        }

        $agent = $this->agents[$agentId];

        // Remove from capability index
        foreach ($agent->getCapabilities() as $capability) {
            $capName = $capability->value;
            if (isset($this->capabilityIndex[$capName])) {
                $this->capabilityIndex[$capName] = array_filter(
                    $this->capabilityIndex[$capName],
                    fn($id) => $id !== $agentId
                );
            }
        }

        unset($this->agents[$agentId]);
        unset($this->messageQueues[$agentId]);

        $this->logger->info("Agent unregistered from broker", ['agent_id' => $agentId]);
    }

    /**
     * Send a message to a specific agent
     */
    public function send(AgentMessage $message): bool
    {
        $this->messagesSent++;

        $recipientId = $message->recipientId;

        if (!isset($this->agents[$recipientId])) {
            $this->logger->warning("Message recipient not found", [
                'recipient_id' => $recipientId,
                'message_id' => $message->id,
            ]);
            return false;
        }

        $this->agents[$recipientId]->receiveMessage($message);
        $this->messagesDelivered++;

        $this->logger->debug("Message delivered", [
            'message_id' => $message->id,
            'from' => $message->senderId,
            'to' => $recipientId,
        ]);

        return true;
    }

    /**
     * Broadcast a message to all agents
     */
    public function broadcast(AgentMessage $message): int
    {
        $delivered = 0;

        foreach ($this->agents as $agentId => $agent) {
            if ($agentId !== $message->senderId) {
                $broadcastMessage = new AgentMessage(
                    senderId: $message->senderId,
                    recipientId: $agentId,
                    type: $message->type,
                    content: $message->content,
                    correlationId: $message->correlationId,
                    metadata: $message->metadata,
                );

                if ($this->send($broadcastMessage)) {
                    $delivered++;
                }
            }
        }

        return $delivered;
    }

    /**
     * Route a message to agents with a specific capability
     */
    public function routeToCapability(AgentMessage $message, AgentCapability $capability): int
    {
        $capName = $capability->value;

        if (!isset($this->capabilityIndex[$capName])) {
            $this->logger->warning("No agents found with capability", [
                'capability' => $capName,
            ]);
            return 0;
        }

        $delivered = 0;

        foreach ($this->capabilityIndex[$capName] as $agentId) {
            if ($agentId !== $message->senderId) {
                $routedMessage = new AgentMessage(
                    senderId: $message->senderId,
                    recipientId: $agentId,
                    type: $message->type,
                    content: $message->content,
                    correlationId: $message->correlationId,
                    metadata: $message->metadata,
                );

                if ($this->send($routedMessage)) {
                    $delivered++;
                }
            }
        }

        return $delivered;
    }

    /**
     * Get an agent by ID
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
    public function getAgents(): array
    {
        return $this->agents;
    }

    /**
     * Find agents by capability
     *
     * @return array<AgentInterface>
     */
    public function findAgentsByCapability(AgentCapability $capability): array
    {
        $capName = $capability->value;
        $agents = [];

        if (isset($this->capabilityIndex[$capName])) {
            foreach ($this->capabilityIndex[$capName] as $agentId) {
                if (isset($this->agents[$agentId])) {
                    $agents[] = $this->agents[$agentId];
                }
            }
        }

        return $agents;
    }

    /**
     * Route message to the best available agent with a capability (load-balanced)
     *
     * @param AgentMessage $message
     * @param AgentCapability $capability
     * @return bool True if message was delivered
     */
    public function routeToOptimalAgent(AgentMessage $message, AgentCapability $capability): bool
    {
        $capName = $capability->value;

        if (!isset($this->capabilityIndex[$capName])) {
            $this->logger->warning("No agents found with capability", [
                'capability' => $capName,
            ]);
            return false;
        }

        $candidateIds = $this->capabilityIndex[$capName];
        $candidates = [];

        foreach ($candidateIds as $agentId) {
            if ($agentId !== $message->senderId && isset($this->agents[$agentId])) {
                $score = $this->calculateRoutingScore($agentId);
                $candidates[] = ['agent_id' => $agentId, 'score' => $score];
            }
        }

        if (empty($candidates)) {
            return false;
        }

        // Sort by score (highest first)
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestAgentId = $candidates[0]['agent_id'];
        $this->loadBalancedRoutes++;

        $routedMessage = new AgentMessage(
            senderId: $message->senderId,
            recipientId: $bestAgentId,
            type: $message->type,
            content: $message->content,
            correlationId: $message->correlationId,
            metadata: array_merge($message->metadata, ['routed_by' => 'load_balancer']),
        );

        return $this->send($routedMessage);
    }

    /**
     * Route message with priority consideration
     *
     * @param AgentMessage $message
     * @param AgentCapability $capability
     * @param int $priority 1-5 (5 = highest)
     * @return bool
     */
    public function routeWithPriority(AgentMessage $message, AgentCapability $capability, int $priority = 3): bool
    {
        $capName = $capability->value;

        if (!isset($this->capabilityIndex[$capName])) {
            return false;
        }

        // For high priority, find the fastest available agent
        if ($priority >= 4) {
            $bestAgent = $this->findFastestAvailableAgent($capName, $message->senderId);
            if ($bestAgent !== null) {
                $routedMessage = new AgentMessage(
                    senderId: $message->senderId,
                    recipientId: $bestAgent,
                    type: $message->type,
                    content: $message->content,
                    correlationId: $message->correlationId,
                    metadata: array_merge($message->metadata, ['priority' => $priority]),
                );
                return $this->send($routedMessage);
            }
        }

        // For normal priority, use standard load-balanced routing
        return $this->routeToOptimalAgent($message, $capability);
    }

    /**
     * Fan-out message to multiple agents in parallel
     *
     * @param AgentMessage $message
     * @param AgentCapability $capability
     * @param int $maxAgents Maximum number of agents to send to
     * @return array<string> Agent IDs that received the message
     */
    public function fanOut(AgentMessage $message, AgentCapability $capability, int $maxAgents = 3): array
    {
        $capName = $capability->value;
        $deliveredTo = [];

        if (!isset($this->capabilityIndex[$capName])) {
            return $deliveredTo;
        }

        $candidateIds = array_filter(
            $this->capabilityIndex[$capName],
            fn($id) => $id !== $message->senderId
        );

        // Score and sort candidates
        $scored = [];
        foreach ($candidateIds as $agentId) {
            if (isset($this->agents[$agentId])) {
                $scored[] = ['id' => $agentId, 'score' => $this->calculateRoutingScore($agentId)];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Send to top N agents
        $count = 0;
        foreach ($scored as $candidate) {
            if ($count >= $maxAgents) {
                break;
            }

            $fanOutMessage = new AgentMessage(
                senderId: $message->senderId,
                recipientId: $candidate['id'],
                type: $message->type,
                content: $message->content,
                correlationId: $message->correlationId,
                metadata: array_merge($message->metadata, ['fan_out' => true, 'fan_out_index' => $count]),
            );

            if ($this->send($fanOutMessage)) {
                $deliveredTo[] = $candidate['id'];
                $count++;
            }
        }

        return $deliveredTo;
    }

    /**
     * Calculate routing score for an agent
     *
     * @param string $agentId
     * @return float Score between 0.0 and 1.0
     */
    private function calculateRoutingScore(string $agentId): float
    {
        if (!isset($this->agents[$agentId])) {
            return 0.0;
        }

        $agent = $this->agents[$agentId];
        $health = $agent->getHealth();
        $metrics = $agent->getMetrics();

        // Availability score
        $availabilityScore = ($health['healthy'] ?? false) ? 1.0 : 0.0;

        // Performance score (inverse of avg processing time, normalized)
        $avgTime = $metrics['avg_processing_time'] ?? 1.0;
        $performanceScore = min(1.0, 1.0 / max(0.1, $avgTime));

        // Queue depth score (prefer agents with shorter queues)
        $queueSize = $metrics['queue_size'] ?? 0;
        $queueScore = max(0.0, 1.0 - ($queueSize / 20.0));

        // Success rate (if available)
        $successRate = $metrics['success_rate'] ?? 0.95;

        // Weighted combination
        $score =
            ($availabilityScore * self::ROUTING_WEIGHTS['availability']) +
            ($performanceScore * self::ROUTING_WEIGHTS['performance']) +
            ($queueScore * self::ROUTING_WEIGHTS['queue_depth']) +
            ($successRate * self::ROUTING_WEIGHTS['success_rate']);

        // Cache the score
        $this->agentLoadScores[$agentId] = $score;

        return $score;
    }

    /**
     * Find the fastest available agent for high-priority tasks
     *
     * @param string $capability
     * @param string $excludeAgentId
     * @return string|null
     */
    private function findFastestAvailableAgent(string $capability, string $excludeAgentId): ?string
    {
        if (!isset($this->capabilityIndex[$capability])) {
            return null;
        }

        $fastestAgent = null;
        $fastestTime = PHP_FLOAT_MAX;

        foreach ($this->capabilityIndex[$capability] as $agentId) {
            if ($agentId === $excludeAgentId || !isset($this->agents[$agentId])) {
                continue;
            }

            $agent = $this->agents[$agentId];
            $health = $agent->getHealth();

            if (!($health['healthy'] ?? false)) {
                continue;
            }

            $metrics = $agent->getMetrics();
            $queueSize = $metrics['queue_size'] ?? 0;
            $avgTime = $metrics['avg_processing_time'] ?? 1.0;

            // Estimated time = queue wait + processing time
            $estimatedTime = ($queueSize * $avgTime) + $avgTime;

            if ($estimatedTime < $fastestTime) {
                $fastestTime = $estimatedTime;
                $fastestAgent = $agentId;
            }
        }

        return $fastestAgent;
    }

    /**
     * Get capability distribution metrics
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCapabilityMetrics(): array
    {
        $metrics = [];

        foreach ($this->capabilityIndex as $capability => $agentIds) {
            $totalLoad = 0;
            $avgScore = 0;

            foreach ($agentIds as $agentId) {
                if (isset($this->agents[$agentId])) {
                    $agentMetrics = $this->agents[$agentId]->getMetrics();
                    $totalLoad += $agentMetrics['queue_size'] ?? 0;
                    $avgScore += $this->agentLoadScores[$agentId] ?? 0.5;
                }
            }

            $agentCount = count($agentIds);
            $metrics[$capability] = [
                'agent_count' => $agentCount,
                'total_queue_load' => $totalLoad,
                'avg_load_score' => $agentCount > 0 ? $avgScore / $agentCount : 0,
            ];
        }

        return $metrics;
    }

    /**
     * Get broker statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'registered_agents' => count($this->agents),
            'messages_sent' => $this->messagesSent,
            'messages_delivered' => $this->messagesDelivered,
            'messages_routed' => $this->messagesRouted,
            'load_balanced_routes' => $this->loadBalancedRoutes,
            'delivery_rate' => $this->messagesSent > 0
                ? $this->messagesDelivered / $this->messagesSent
                : 1.0,
            'capabilities_indexed' => count($this->capabilityIndex),
            'capability_metrics' => $this->getCapabilityMetrics(),
        ];
    }
}
