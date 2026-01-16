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
 * HTTP Controller for Workflow management
 */
#[Singleton]
class WorkflowController extends HttpResponder
{
    public function __construct(
        private readonly AgentRegistry $agentRegistry,
    ) {
    }

    /**
     * Start a new workflow
     */
    #[RespondsToHttp(
        method: RequestMethod::POST,
        pattern: '/api/v1/workflows',
    )]
    public function startWorkflow(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true) ?? [];

        $orchestrationAgent = $this->agentRegistry->getAgentByType(
            AgentType::WORKFLOW_ORCHESTRATION
        );

        if ($orchestrationAgent === null) {
            return $this->jsonResponse([
                'error' => 'Workflow Orchestration Agent not available',
            ], 503);
        }

        $result = $orchestrationAgent->processTask([
            'type' => 'start_workflow',
            'workflow_type' => $body['workflow_type'] ?? 'new_submission',
            'submission_id' => $body['submission_id'] ?? '',
            'context' => $body['context'] ?? [],
        ]);

        return $this->jsonResponse($result, 201);
    }

    /**
     * Get workflow status
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/workflows/{workflowId}',
    )]
    public function getWorkflow(ServerRequestInterface $request, string $workflowId): ResponseInterface
    {
        $orchestrationAgent = $this->agentRegistry->getAgentByType(
            AgentType::WORKFLOW_ORCHESTRATION
        );

        if ($orchestrationAgent === null) {
            return $this->jsonResponse([
                'error' => 'Workflow Orchestration Agent not available',
            ], 503);
        }

        $result = $orchestrationAgent->processTask([
            'type' => 'monitor_progress',
            'workflow_id' => $workflowId,
        ]);

        return $this->jsonResponse($result);
    }

    /**
     * List all workflows
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/workflows',
    )]
    public function listWorkflows(ServerRequestInterface $request): ResponseInterface
    {
        $orchestrationAgent = $this->agentRegistry->getAgentByType(
            AgentType::WORKFLOW_ORCHESTRATION
        );

        if ($orchestrationAgent === null) {
            return $this->jsonResponse([
                'error' => 'Workflow Orchestration Agent not available',
            ], 503);
        }

        $result = $orchestrationAgent->processTask([
            'type' => 'monitor_progress',
        ]);

        return $this->jsonResponse($result);
    }

    /**
     * Get analytics
     */
    #[RespondsToHttp(
        method: RequestMethod::GET,
        pattern: '/api/v1/analytics',
    )]
    public function getAnalytics(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $period = $queryParams['period'] ?? 'day';

        $orchestrationAgent = $this->agentRegistry->getAgentByType(
            AgentType::WORKFLOW_ORCHESTRATION
        );

        if ($orchestrationAgent === null) {
            return $this->jsonResponse([
                'error' => 'Workflow Orchestration Agent not available',
            ], 503);
        }

        $result = $orchestrationAgent->processTask([
            'type' => 'generate_analytics',
            'period' => $period,
        ]);

        return $this->jsonResponse($result);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
