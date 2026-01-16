<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use SKZ\Agents\Message\AgentMessage;

/**
 * Interface for all autonomous agents in the SKZ framework
 */
interface AgentInterface
{
    /**
     * Get the unique identifier of the agent
     */
    public function getId(): string;

    /**
     * Get the agent's name
     */
    public function getName(): string;

    /**
     * Get the agent type
     */
    public function getType(): AgentType;

    /**
     * Get the current status of the agent
     */
    public function getStatus(): AgentStatus;

    /**
     * Get the capabilities of this agent
     *
     * @return array<AgentCapability>
     */
    public function getCapabilities(): array;

    /**
     * Check if the agent has a specific capability
     */
    public function hasCapability(AgentCapability $capability): bool;

    /**
     * Initialize the agent
     */
    public function initialize(): void;

    /**
     * Start the agent's processing loop
     */
    public function start(): void;

    /**
     * Stop the agent gracefully
     */
    public function stop(): void;

    /**
     * Process a task asynchronously
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function processTask(array $taskData): array;

    /**
     * Send a message to another agent
     */
    public function sendMessage(AgentMessage $message): void;

    /**
     * Receive and process a message from another agent
     */
    public function receiveMessage(AgentMessage $message): void;

    /**
     * Get the agent's performance metrics
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * Get agent health status
     *
     * @return array<string, mixed>
     */
    public function getHealth(): array;
}
