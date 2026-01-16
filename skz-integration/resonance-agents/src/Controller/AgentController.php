<?php

declare(strict_types=1);

namespace SKZ\Agents\Controller;

use Distantmagic\Resonance\Attribute\RespondsToHttp;
use Distantmagic\Resonance\Attribute\Singleton;
use Distantmagic\Resonance\HttpResponder\HttpResponder;
use Distantmagic\Resonance\RequestMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SKZ\Agents\Agent\AgentRegistry;
use SKZ\Agents\Agent\AgentType;

/**
 * HTTP Controller for Agent management and API endpoints
 */
#[Singleton]
class AgentController extends HttpResponder
{
    public function __construct(
        private readonly AgentRegistry $agentRegistry,
    ) {
    }

    /**
     * List all registered agents
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/agents',
    )]
    public function listAgents(ServerRequestInterface $request): ResponseInterface
    {
        $agents = $this->agentRegistry->getAllAgents();

        $agentList = [];
        foreach ($agents as $agent) {
            $agentList[] = [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
                'type' => $agent->getType()->value,
                'status' => $agent->getStatus()->value,
                'capabilities' => array_map(fn($c) => $c->value, $agent->getCapabilities()),
            ];
        }

        return $this->jsonResponse([
            'agents' => $agentList,
            'total' => count($agentList),
        ]);
    }

    /**
     * Get specific agent details
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/agents/{agentId}',
    )]
    public function getAgent(ServerRequestInterface $request, string $agentId): ResponseInterface
    {
        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            return $this->jsonResponse(['error' => 'Agent not found'], 404);
        }

        return $this->jsonResponse([
            'id' => $agent->getId(),
            'name' => $agent->getName(),
            'type' => $agent->getType()->value,
            'status' => $agent->getStatus()->value,
            'capabilities' => array_map(fn($c) => $c->value, $agent->getCapabilities()),
            'metrics' => $agent->getMetrics(),
            'health' => $agent->getHealth(),
        ]);
    }

    /**
     * Get agent health status
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/agents/{agentId}/health',
    )]
    public function getAgentHealth(ServerRequestInterface $request, string $agentId): ResponseInterface
    {
        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            return $this->jsonResponse(['error' => 'Agent not found'], 404);
        }

        return $this->jsonResponse($agent->getHealth());
    }

    /**
     * Execute task on specific agent
     */
    #[RespondsToHttp(
        method: RequestMethod::POST,
        pattern: '/api/v1/agents/{agentId}/task',
    )]
    public function executeTask(ServerRequestInterface $request, string $agentId): ResponseInterface
    {
        $agent = $this->agentRegistry->getAgent($agentId);

        if ($agent === null) {
            return $this->jsonResponse(['error' => 'Agent not found'], 404);
        }

        $body = json_decode((string) $request->getBody(), true) ?? [];

        try {
            $result = $agent->processTask($body);
            return $this->jsonResponse($result);
        } catch (\Throwable $e) {
            return $this->jsonResponse([
                'error' => 'Task execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get agents by type
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/agents/type/{type}',
    )]
    public function getAgentsByType(ServerRequestInterface $request, string $type): ResponseInterface
    {
        try {
            $agentType = AgentType::from($type);
        } catch (\ValueError $e) {
            return $this->jsonResponse(['error' => 'Invalid agent type'], 400);
        }

        $agents = $this->agentRegistry->getAgentsByType($agentType);

        $agentList = [];
        foreach ($agents as $agent) {
            $agentList[] = [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
                'status' => $agent->getStatus()->value,
            ];
        }

        return $this->jsonResponse([
            'type' => $type,
            'agents' => $agentList,
        ]);
    }

    /**
     * Get system status
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/system/status',
    )]
    public function getSystemStatus(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse($this->agentRegistry->getSystemStatus());
    }

    /**
     * Health check endpoint
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/health',
    )]
    public function healthCheck(ServerRequestInterface $request): ResponseInterface
    {
        return $this->jsonResponse([
            'status' => 'healthy',
            'timestamp' => time(),
            'agents_active' => count($this->agentRegistry->getAllAgents()),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-SKZ-Agent-Version', '1.0.0');
    }
}
