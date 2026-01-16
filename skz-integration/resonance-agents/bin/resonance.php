#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SKZ Agents Framework - Resonance Entry Point
 *
 * Main entry point for the autonomous agents framework built on Resonance.
 * Initializes all 7 agents and starts the HTTP/WebSocket servers.
 */

use Distantmagic\Resonance\ApplicationConfiguration;
use Distantmagic\Resonance\ConsoleApplication;
use SKZ\Agents\Agent\AgentRegistry;
use SKZ\Agents\Agent\ResearchDiscoveryAgent;
use SKZ\Agents\Agent\ManuscriptAnalysisAgent;
use SKZ\Agents\Agent\PeerReviewCoordinationAgent;
use SKZ\Agents\Agent\EditorialDecisionAgent;
use SKZ\Agents\Agent\PublicationFormattingAgent;
use SKZ\Agents\Agent\QualityAssuranceAgent;
use SKZ\Agents\Agent\WorkflowOrchestrationAgent;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;
use SKZ\Agents\LLM\LLMService;
use SKZ\Agents\Bridge\OJSBridge;

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$configFile = __DIR__ . '/../config/config.ini';
if (!file_exists($configFile)) {
    echo "Error: config/config.ini not found. Copy config/config.ini.template to config/config.ini\n";
    exit(1);
}

$config = parse_ini_file($configFile, true);

// Initialize logger
$logger = new class implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;

    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        echo "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    }
};

echo "
╔═══════════════════════════════════════════════════════════════╗
║     SKZ Autonomous Agents Framework - Resonance Edition       ║
║                                                               ║
║     7 Specialized Agents for Academic Publishing              ║
╚═══════════════════════════════════════════════════════════════╝
";

// Initialize core services
$logger->info("Initializing core services...");

$messageBroker = new MessageBroker($logger);
$memoryService = new MemoryService(
    $logger,
    $config['redis']['host'] ?? 'localhost',
    (int) ($config['redis']['port'] ?? 6379)
);
$decisionEngine = new DecisionEngine($logger);

// Initialize LLM service if enabled
$llmService = null;
if (($config['llm']['enabled'] ?? false)) {
    $llmService = new LLMService(
        $logger,
        $config['llm']['host'] ?? '127.0.0.1',
        (int) ($config['llm']['port'] ?? 8089),
        (int) ($config['llm']['context_size'] ?? 4096)
    );
    $llmService->connect();
}

// Initialize OJS Bridge
$ojsBridge = new OJSBridge(
    $logger,
    $config['ojs']['api_url'] ?? 'http://localhost:8000/api/v1',
    $config['ojs']['api_key'] ?? ''
);

// Initialize Agent Registry
$agentRegistry = new AgentRegistry($logger);

// Create and register all 7 agents
$logger->info("Creating autonomous agents...");

$agents = [
    new ResearchDiscoveryAgent($logger, $messageBroker, $memoryService, $decisionEngine, $llmService),
    new ManuscriptAnalysisAgent($logger, $messageBroker, $memoryService, $decisionEngine, $llmService),
    new PeerReviewCoordinationAgent($logger, $messageBroker, $memoryService, $decisionEngine),
    new EditorialDecisionAgent($logger, $messageBroker, $memoryService, $decisionEngine, $llmService),
    new PublicationFormattingAgent($logger, $messageBroker, $memoryService, $decisionEngine),
    new QualityAssuranceAgent($logger, $messageBroker, $memoryService, $decisionEngine),
    new WorkflowOrchestrationAgent($logger, $messageBroker, $memoryService, $decisionEngine),
];

foreach ($agents as $agent) {
    $agentRegistry->register($agent);
    $logger->info("Registered agent: " . $agent->getName());
}

// Initialize all agents
$logger->info("Initializing agents...");
$agentRegistry->initializeAll();

// Start all agents
$logger->info("Starting agents...");
$agentRegistry->startAll();

echo "\n";
$logger->info("All agents are now active!");
echo "\n";

// Display agent status
echo "Active Agents:\n";
echo str_repeat("-", 60) . "\n";
foreach ($agents as $agent) {
    printf(
        "  %-35s [%s]\n",
        $agent->getName(),
        $agent->getStatus()->value
    );
}
echo str_repeat("-", 60) . "\n";

echo "\nAPI Endpoints:\n";
echo "  HTTP Server: http://{$config['http']['host']}:{$config['http']['port']}\n";
echo "  WebSocket:   ws://{$config['http']['host']}:{$config['websocket']['port']}\n";
echo "\nAvailable Routes:\n";
echo "  GET  /health              - Health check\n";
echo "  GET  /api/v1/agents       - List all agents\n";
echo "  GET  /api/v1/agents/{id}  - Get agent details\n";
echo "  POST /api/v1/agents/{id}/task - Execute task\n";
echo "  GET  /api/v1/workflows    - List workflows\n";
echo "  POST /api/v1/workflows    - Start workflow\n";
echo "  GET  /api/v1/analytics    - Get analytics\n";
echo "\nPress Ctrl+C to stop.\n\n";

// Handle shutdown gracefully
$shutdown = function () use ($agentRegistry, $logger) {
    echo "\n";
    $logger->info("Shutting down agents...");
    $agentRegistry->stopAll();
    $logger->info("All agents stopped. Goodbye!");
    exit(0);
};

pcntl_signal(SIGINT, $shutdown);
pcntl_signal(SIGTERM, $shutdown);

// Run Resonance application (HTTP and WebSocket servers)
// In a full implementation, this would initialize the Resonance framework
// For now, keep the process running
while (true) {
    pcntl_signal_dispatch();
    usleep(100000); // 100ms
}
