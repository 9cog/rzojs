<?php

declare(strict_types=1);

namespace SKZ\Agents\LLM;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Http\Client;

/**
 * LLM Service for AI-powered agent capabilities
 * Integrates with llama.cpp for local model inference
 */
#[Singleton]
class LLMService
{
    private bool $connected = false;
    private int $requestCount = 0;
    private float $totalLatency = 0.0;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8089,
        private readonly int $contextSize = 4096,
        private readonly float $temperature = 0.7,
    ) {
    }

    /**
     * Initialize connection to LLM server
     */
    public function connect(): bool
    {
        try {
            $client = new Client($this->host, $this->port);
            $client->get('/health');

            if ($client->statusCode === 200) {
                $this->connected = true;
                $this->logger->info("Connected to LLM server", [
                    'host' => $this->host,
                    'port' => $this->port,
                ]);
                return true;
            }

            $this->logger->warning("LLM server health check failed", [
                'status' => $client->statusCode,
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Failed to connect to LLM server", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate text completion
     *
     * @param array<string, mixed> $options
     */
    public function complete(
        string $prompt,
        int $maxTokens = 256,
        array $options = [],
    ): string {
        $startTime = microtime(true);

        $payload = [
            'prompt' => $prompt,
            'n_predict' => $maxTokens,
            'temperature' => $options['temperature'] ?? $this->temperature,
            'top_p' => $options['top_p'] ?? 0.9,
            'top_k' => $options['top_k'] ?? 40,
            'stop' => $options['stop'] ?? ["\n\n", "###"],
        ];

        try {
            $client = new Client($this->host, $this->port);
            $client->setHeaders(['Content-Type' => 'application/json']);
            $client->post('/completion', json_encode($payload));

            $this->requestCount++;
            $this->totalLatency += microtime(true) - $startTime;

            if ($client->statusCode !== 200) {
                $this->logger->error("LLM completion failed", [
                    'status' => $client->statusCode,
                ]);
                return '';
            }

            $response = json_decode($client->body, true);
            return $response['content'] ?? '';
        } catch (\Throwable $e) {
            $this->logger->error("LLM request failed", [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Chat completion with message history
     *
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): string
    {
        // Format messages into prompt
        $prompt = $this->formatChatPrompt($messages);

        return $this->complete($prompt, $options['max_tokens'] ?? 512, $options);
    }

    /**
     * Analyze text using LLM
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function analyze(string $text, string $analysisType, array $options = []): array
    {
        $prompt = $this->buildAnalysisPrompt($text, $analysisType);

        $response = $this->complete($prompt, 1024, $options);

        return $this->parseAnalysisResponse($response, $analysisType);
    }

    /**
     * Generate embeddings for text
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        try {
            $client = new Client($this->host, $this->port);
            $client->setHeaders(['Content-Type' => 'application/json']);
            $client->post('/embedding', json_encode(['content' => $text]));

            if ($client->statusCode !== 200) {
                return [];
            }

            $response = json_decode($client->body, true);
            return $response['embedding'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->error("Embedding generation failed", [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if LLM service is available
     */
    public function isAvailable(): bool
    {
        return $this->connected;
    }

    /**
     * Get LLM service statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'connected' => $this->connected,
            'host' => $this->host,
            'port' => $this->port,
            'request_count' => $this->requestCount,
            'avg_latency' => $this->requestCount > 0
                ? $this->totalLatency / $this->requestCount
                : 0,
            'context_size' => $this->contextSize,
        ];
    }

    /**
     * Format messages into a chat prompt
     *
     * @param array<array{role: string, content: string}> $messages
     */
    private function formatChatPrompt(array $messages): string
    {
        $prompt = '';

        foreach ($messages as $message) {
            $role = $message['role'];
            $content = $message['content'];

            $prompt .= match ($role) {
                'system' => "### System:\n{$content}\n\n",
                'user' => "### User:\n{$content}\n\n",
                'assistant' => "### Assistant:\n{$content}\n\n",
                default => "{$content}\n\n",
            };
        }

        $prompt .= "### Assistant:\n";

        return $prompt;
    }

    private function buildAnalysisPrompt(string $text, string $analysisType): string
    {
        $instructions = match ($analysisType) {
            'sentiment' => 'Analyze the sentiment of the following text. Respond with: positive, negative, or neutral, followed by a confidence score (0-1) and brief explanation.',
            'summary' => 'Provide a concise summary of the following text in 2-3 sentences.',
            'quality' => 'Assess the quality of this academic text. Rate structure (0-1), clarity (0-1), and completeness (0-1). Provide brief feedback.',
            'keywords' => 'Extract the 5 most important keywords from the following text. List them separated by commas.',
            'entities' => 'Identify named entities (people, organizations, locations, concepts) in the following text.',
            default => "Analyze the following text for {$analysisType}.",
        };

        return "### Instructions:\n{$instructions}\n\n### Text:\n{$text}\n\n### Analysis:\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAnalysisResponse(string $response, string $analysisType): array
    {
        return match ($analysisType) {
            'sentiment' => $this->parseSentimentResponse($response),
            'keywords' => $this->parseKeywordsResponse($response),
            default => ['raw_response' => $response, 'analysis_type' => $analysisType],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSentimentResponse(string $response): array
    {
        // Simple parsing - in production, use more robust parsing
        $sentiment = 'neutral';
        $confidence = 0.5;

        if (stripos($response, 'positive') !== false) {
            $sentiment = 'positive';
            $confidence = 0.8;
        } elseif (stripos($response, 'negative') !== false) {
            $sentiment = 'negative';
            $confidence = 0.8;
        }

        return [
            'sentiment' => $sentiment,
            'confidence' => $confidence,
            'explanation' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseKeywordsResponse(string $response): array
    {
        $keywords = array_map('trim', explode(',', $response));

        return [
            'keywords' => array_filter($keywords),
            'count' => count($keywords),
        ];
    }
}
