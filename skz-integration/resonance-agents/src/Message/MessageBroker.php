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

    private int $messagesSent = 0;
    private int $messagesDelivered = 0;

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
            'delivery_rate' => $this->messagesSent > 0
                ? $this->messagesDelivered / $this->messagesSent
                : 1.0,
            'capabilities_indexed' => count($this->capabilityIndex),
        ];
    }
}
