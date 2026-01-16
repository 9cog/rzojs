<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\AgentMessage;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;
use Swoole\Coroutine;

/**
 * Base implementation for all autonomous agents
 */
abstract class BaseAgent implements AgentInterface
{
    protected string $id;
    protected AgentStatus $status = AgentStatus::INITIALIZING;
    protected float $startTime;
    protected int $tasksProcessed = 0;
    protected int $messagesReceived = 0;
    protected int $messagesSent = 0;
    protected float $totalProcessingTime = 0.0;

    /**
     * @var array<string, mixed>
     */
    protected array $metrics = [];

    /**
     * @var array<AgentMessage>
     */
    protected array $messageQueue = [];

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly MessageBroker $messageBroker,
        protected readonly MemoryService $memoryService,
        protected readonly DecisionEngine $decisionEngine,
    ) {
        $this->id = $this->generateId();
        $this->startTime = microtime(true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    abstract public function getName(): string;

    abstract public function getType(): AgentType;

    public function getStatus(): AgentStatus
    {
        return $this->status;
    }

    public function getCapabilities(): array
    {
        return $this->getType()->getCapabilities();
    }

    public function hasCapability(AgentCapability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    public function initialize(): void
    {
        $this->logger->info("Initializing agent", [
            'agent_id' => $this->id,
            'agent_type' => $this->getType()->value,
            'agent_name' => $this->getName(),
        ]);

        $this->status = AgentStatus::INITIALIZING;

        // Register with message broker
        $this->messageBroker->registerAgent($this);

        // Load persistent memory
        $this->loadMemory();

        // Perform agent-specific initialization
        $this->onInitialize();

        $this->status = AgentStatus::IDLE;

        $this->logger->info("Agent initialized successfully", [
            'agent_id' => $this->id,
        ]);
    }

    public function start(): void
    {
        $this->logger->info("Starting agent", ['agent_id' => $this->id]);

        $this->status = AgentStatus::ACTIVE;

        // Start the processing coroutine
        Coroutine::create(function () {
            $this->processingLoop();
        });

        $this->onStart();
    }

    public function stop(): void
    {
        $this->logger->info("Stopping agent", ['agent_id' => $this->id]);

        $this->status = AgentStatus::SHUTDOWN;

        // Persist memory before shutdown
        $this->saveMemory();

        // Unregister from message broker
        $this->messageBroker->unregisterAgent($this->id);

        $this->onStop();

        $this->logger->info("Agent stopped", ['agent_id' => $this->id]);
    }

    public function processTask(array $taskData): array
    {
        $startTime = microtime(true);

        $this->status = AgentStatus::BUSY;
        $this->tasksProcessed++;

        $this->logger->debug("Processing task", [
            'agent_id' => $this->id,
            'task_type' => $taskData['type'] ?? 'unknown',
        ]);

        try {
            // Use decision engine for complex tasks
            $decision = $this->decisionEngine->analyze($taskData, $this->getCapabilities());

            // Execute the task with the decision context
            $result = $this->executeTask($taskData, $decision);

            // Learn from the experience
            $this->learnFromExperience($taskData, $result, true);

            $processingTime = microtime(true) - $startTime;
            $this->totalProcessingTime += $processingTime;

            $this->status = AgentStatus::IDLE;

            return [
                'success' => true,
                'result' => $result,
                'processing_time' => $processingTime,
                'agent_id' => $this->id,
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Task processing failed", [
                'agent_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            $this->learnFromExperience($taskData, [], false);

            $this->status = AgentStatus::IDLE;

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'agent_id' => $this->id,
            ];
        }
    }

    public function sendMessage(AgentMessage $message): void
    {
        $this->messagesSent++;

        $this->logger->debug("Sending message", [
            'from' => $this->id,
            'to' => $message->recipientId,
            'type' => $message->type->value,
        ]);

        $this->messageBroker->send($message);
    }

    public function receiveMessage(AgentMessage $message): void
    {
        $this->messagesReceived++;

        $this->logger->debug("Received message", [
            'agent_id' => $this->id,
            'from' => $message->senderId,
            'type' => $message->type->value,
        ]);

        $this->messageQueue[] = $message;
    }

    public function getMetrics(): array
    {
        $uptime = microtime(true) - $this->startTime;
        $avgProcessingTime = $this->tasksProcessed > 0
            ? $this->totalProcessingTime / $this->tasksProcessed
            : 0;

        return [
            'agent_id' => $this->id,
            'agent_type' => $this->getType()->value,
            'status' => $this->status->value,
            'uptime_seconds' => $uptime,
            'tasks_processed' => $this->tasksProcessed,
            'messages_sent' => $this->messagesSent,
            'messages_received' => $this->messagesReceived,
            'avg_processing_time' => $avgProcessingTime,
            'queue_size' => count($this->messageQueue),
            ...$this->getAgentSpecificMetrics(),
        ];
    }

    public function getHealth(): array
    {
        return [
            'healthy' => $this->status->isOperational(),
            'status' => $this->status->value,
            'can_process_tasks' => $this->status->canProcessTasks(),
            'memory_usage' => memory_get_usage(true),
            'queue_size' => count($this->messageQueue),
        ];
    }

    /**
     * Execute the actual task - implemented by concrete agents
     *
     * @param array<string, mixed> $taskData
     * @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    abstract protected function executeTask(array $taskData, array $decision): array;

    /**
     * Get agent-specific metrics
     *
     * @return array<string, mixed>
     */
    protected function getAgentSpecificMetrics(): array
    {
        return [];
    }

    /**
     * Called during initialization
     */
    protected function onInitialize(): void
    {
        // Override in subclasses
    }

    /**
     * Called when agent starts
     */
    protected function onStart(): void
    {
        // Override in subclasses
    }

    /**
     * Called when agent stops
     */
    protected function onStop(): void
    {
        // Override in subclasses
    }

    /**
     * Main processing loop for handling queued messages
     */
    protected function processingLoop(): void
    {
        while ($this->status !== AgentStatus::SHUTDOWN) {
            if (!empty($this->messageQueue)) {
                $message = array_shift($this->messageQueue);
                $this->handleMessage($message);
            }

            // Yield to other coroutines
            Coroutine::sleep(0.01);
        }
    }

    /**
     * Handle an incoming message
     */
    protected function handleMessage(AgentMessage $message): void
    {
        $response = $this->processTask([
            'type' => 'message',
            'message_type' => $message->type->value,
            'content' => $message->content,
            'sender_id' => $message->senderId,
            'correlation_id' => $message->correlationId,
        ]);

        // Send response if needed
        if ($message->requiresResponse()) {
            $this->sendMessage(new AgentMessage(
                senderId: $this->id,
                recipientId: $message->senderId,
                type: \SKZ\Agents\Message\MessageType::RESPONSE,
                content: $response,
                correlationId: $message->correlationId,
            ));
        }
    }

    /**
     * Load agent's persistent memory
     */
    protected function loadMemory(): void
    {
        $memory = $this->memoryService->retrieve($this->id, 'context', 10);

        foreach ($memory as $item) {
            $this->metrics['loaded_memories'][] = $item;
        }
    }

    /**
     * Save agent's memory for persistence
     */
    protected function saveMemory(): void
    {
        $this->memoryService->store(
            $this->id,
            'context',
            [
                'metrics' => $this->getMetrics(),
                'timestamp' => time(),
            ],
            0.8,
            ['shutdown', 'metrics']
        );
    }

    /**
     * Learn from task execution experience
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    protected function learnFromExperience(array $input, array $output, bool $success): void
    {
        $this->memoryService->store(
            $this->id,
            'experience',
            [
                'input' => $input,
                'output' => $output,
                'success' => $success,
                'timestamp' => time(),
            ],
            $success ? 0.9 : 0.5,
            ['learning', $success ? 'success' : 'failure']
        );
    }

    /**
     * Generate a unique agent ID
     */
    protected function generateId(): string
    {
        return sprintf(
            '%s_%s_%s',
            $this->getType()->value,
            bin2hex(random_bytes(4)),
            time()
        );
    }
}
