<?php

declare(strict_types=1);

namespace SKZ\Agents\WebSocket;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Agent\AgentRegistry;
use SKZ\Agents\Message\AgentMessage;
use SKZ\Agents\Message\MessageType;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

/**
 * WebSocket handler for real-time agent communication
 */
#[Singleton]
class AgentWebSocketHandler
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $connections = [];

    /**
     * @var array<string, array<int>>
     */
    private array $subscriptions = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AgentRegistry $agentRegistry,
    ) {
    }

    /**
     * Handle new WebSocket connection
     */
    public function onOpen(Server $server, \Swoole\Http\Request $request): void
    {
        $fd = $request->fd;

        $this->connections[$fd] = [
            'fd' => $fd,
            'connected_at' => time(),
            'subscriptions' => [],
            'user_id' => $request->get['user_id'] ?? null,
        ];

        $this->logger->info("WebSocket connection opened", ['fd' => $fd]);

        // Send welcome message
        $this->send($server, $fd, [
            'type' => 'connected',
            'message' => 'Connected to SKZ Agent WebSocket',
            'available_agents' => $this->getAvailableAgents(),
        ]);
    }

    /**
     * Handle incoming WebSocket message
     */
    public function onMessage(Server $server, Frame $frame): void
    {
        $fd = $frame->fd;
        $data = json_decode($frame->data, true);

        if ($data === null) {
            $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Invalid JSON',
            ]);
            return;
        }

        $action = $data['action'] ?? '';

        $this->logger->debug("WebSocket message received", [
            'fd' => $fd,
            'action' => $action,
        ]);

        match ($action) {
            'subscribe' => $this->handleSubscribe($server, $fd, $data),
            'unsubscribe' => $this->handleUnsubscribe($server, $fd, $data),
            'send_to_agent' => $this->handleSendToAgent($server, $fd, $data),
            'get_agent_status' => $this->handleGetAgentStatus($server, $fd, $data),
            'get_system_status' => $this->handleGetSystemStatus($server, $fd),
            'execute_task' => $this->handleExecuteTask($server, $fd, $data),
            default => $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Unknown action: ' . $action,
            ]),
        };
    }

    /**
     * Handle WebSocket connection close
     */
    public function onClose(Server $server, int $fd): void
    {
        // Remove subscriptions
        foreach ($this->subscriptions as $topic => $fds) {
            $this->subscriptions[$topic] = array_filter($fds, fn($f) => $f !== $fd);
        }

        unset($this->connections[$fd]);

        $this->logger->info("WebSocket connection closed", ['fd' => $fd]);
    }

    /**
     * Broadcast message to all subscribers of a topic
     *
     * @param array<string, mixed> $message
     */
    public function broadcast(Server $server, string $topic, array $message): void
    {
        $fds = $this->subscriptions[$topic] ?? [];

        foreach ($fds as $fd) {
            if ($server->isEstablished($fd)) {
                $this->send($server, $fd, array_merge($message, ['topic' => $topic]));
            }
        }
    }

    /**
     * Broadcast agent event to subscribers
     *
     * @param array<string, mixed> $event
     */
    public function broadcastAgentEvent(Server $server, string $agentId, array $event): void
    {
        $this->broadcast($server, "agent:{$agentId}", $event);
        $this->broadcast($server, 'agents:all', array_merge($event, ['agent_id' => $agentId]));
    }

    private function handleSubscribe(Server $server, int $fd, array $data): void
    {
        $topic = $data['topic'] ?? '';

        if (empty($topic)) {
            $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Topic required for subscription',
            ]);
            return;
        }

        if (!isset($this->subscriptions[$topic])) {
            $this->subscriptions[$topic] = [];
        }

        if (!in_array($fd, $this->subscriptions[$topic])) {
            $this->subscriptions[$topic][] = $fd;
            $this->connections[$fd]['subscriptions'][] = $topic;
        }

        $this->send($server, $fd, [
            'type' => 'subscribed',
            'topic' => $topic,
        ]);
    }

    private function handleUnsubscribe(Server $server, int $fd, array $data): void
    {
        $topic = $data['topic'] ?? '';

        if (isset($this->subscriptions[$topic])) {
            $this->subscriptions[$topic] = array_filter(
                $this->subscriptions[$topic],
                fn($f) => $f !== $fd
            );
        }

        $this->send($server, $fd, [
            'type' => 'unsubscribed',
            'topic' => $topic,
        ]);
    }

    private function handleSendToAgent(Server $server, int $fd, array $data): void
    {
        $agentId = $data['agent_id'] ?? '';
        $message = $data['message'] ?? [];

        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Agent not found',
            ]);
            return;
        }

        $agentMessage = new AgentMessage(
            senderId: 'websocket_client_' . $fd,
            recipientId: $agentId,
            type: MessageType::from($message['type'] ?? 'query'),
            content: $message['content'] ?? [],
        );

        $agent->receiveMessage($agentMessage);

        $this->send($server, $fd, [
            'type' => 'message_sent',
            'agent_id' => $agentId,
            'message_id' => $agentMessage->id,
        ]);
    }

    private function handleGetAgentStatus(Server $server, int $fd, array $data): void
    {
        $agentId = $data['agent_id'] ?? '';

        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Agent not found',
            ]);
            return;
        }

        $this->send($server, $fd, [
            'type' => 'agent_status',
            'agent_id' => $agentId,
            'name' => $agent->getName(),
            'status' => $agent->getStatus()->value,
            'health' => $agent->getHealth(),
            'metrics' => $agent->getMetrics(),
        ]);
    }

    private function handleGetSystemStatus(Server $server, int $fd): void
    {
        $this->send($server, $fd, [
            'type' => 'system_status',
            'status' => $this->agentRegistry->getSystemStatus(),
            'connections' => count($this->connections),
            'subscriptions' => array_map('count', $this->subscriptions),
        ]);
    }

    private function handleExecuteTask(Server $server, int $fd, array $data): void
    {
        $agentId = $data['agent_id'] ?? '';
        $task = $data['task'] ?? [];

        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            $this->send($server, $fd, [
                'type' => 'error',
                'message' => 'Agent not found',
            ]);
            return;
        }

        try {
            $result = $agent->processTask($task);

            $this->send($server, $fd, [
                'type' => 'task_result',
                'agent_id' => $agentId,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->send($server, $fd, [
                'type' => 'task_error',
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getAvailableAgents(): array
    {
        $agents = $this->agentRegistry->getAllAgents();
        $available = [];

        foreach ($agents as $agent) {
            $available[] = [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
                'type' => $agent->getType()->value,
                'status' => $agent->getStatus()->value,
            ];
        }

        return $available;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(Server $server, int $fd, array $data): void
    {
        if ($server->isEstablished($fd)) {
            $server->push($fd, json_encode($data));
        }
    }
}
