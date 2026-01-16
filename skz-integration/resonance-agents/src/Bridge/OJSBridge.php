<?php

declare(strict_types=1);

namespace SKZ\Agents\Bridge;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Http\Client;

/**
 * Bridge for OJS (Open Journal Systems) integration
 * Provides bidirectional communication between agents and OJS
 */
#[Singleton]
class OJSBridge
{
    private ?string $authToken = null;
    private int $requestCount = 0;

    /**
     * @var array<array<string, mixed>>
     */
    private array $requestHistory = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret = '',
    ) {
    }

    /**
     * Authenticate with OJS API
     */
    public function authenticate(string $username = '', string $password = ''): bool
    {
        try {
            $response = $this->request('POST', '/auth/token', [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'username' => $username,
                'password' => $password,
            ]);

            if (isset($response['token'])) {
                $this->authToken = $response['token'];
                $this->logger->info("OJS authentication successful");
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            $this->logger->error("OJS authentication failed", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get manuscript/submission by ID
     *
     * @return array<string, mixed>|null
     */
    public function getManuscript(int $submissionId): ?array
    {
        return $this->request('GET', "/submissions/{$submissionId}");
    }

    /**
     * Get list of manuscripts with optional filters
     *
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    public function getManuscripts(array $filters = []): array
    {
        $query = http_build_query($filters);
        $result = $this->request('GET', "/submissions?{$query}");

        return $result['items'] ?? [];
    }

    /**
     * Update manuscript
     *
     * @param array<string, mixed> $updates
     * @return array<string, mixed>|null
     */
    public function updateManuscript(int $submissionId, array $updates): ?array
    {
        return $this->request('PUT', "/submissions/{$submissionId}", $updates);
    }

    /**
     * Get reviewers for potential assignment
     *
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    public function getReviewers(array $filters = []): array
    {
        $query = http_build_query($filters);
        $result = $this->request('GET', "/users/reviewers?{$query}");

        return $result['items'] ?? [];
    }

    /**
     * Assign reviewer to submission
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function assignReviewer(int $submissionId, int $reviewerId, array $options = []): ?array
    {
        return $this->request('POST', "/submissions/{$submissionId}/reviewers", array_merge(
            ['reviewerId' => $reviewerId],
            $options
        ));
    }

    /**
     * Get reviews for a submission
     *
     * @return array<array<string, mixed>>
     */
    public function getReviews(int $submissionId): array
    {
        $result = $this->request('GET', "/submissions/{$submissionId}/reviews");
        return $result['items'] ?? [];
    }

    /**
     * Create editorial decision
     *
     * @param array<string, mixed> $decisionData
     * @return array<string, mixed>|null
     */
    public function createEditorialDecision(int $submissionId, array $decisionData): ?array
    {
        return $this->request('POST', "/submissions/{$submissionId}/decisions", $decisionData);
    }

    /**
     * Get publication data
     *
     * @return array<string, mixed>|null
     */
    public function getPublicationData(int $submissionId): ?array
    {
        return $this->request('GET', "/submissions/{$submissionId}/publication");
    }

    /**
     * Submit agent processing result to OJS
     *
     * @param array<string, mixed> $resultData
     * @return array<string, mixed>|null
     */
    public function sendAgentResult(string $agentId, array $resultData): ?array
    {
        return $this->request('POST', '/agent-results', [
            'agent_id' => $agentId,
            'result' => $resultData,
            'timestamp' => time(),
        ]);
    }

    /**
     * Register webhook for OJS events
     *
     * @return array<string, mixed>|null
     */
    public function registerWebhook(string $eventType, string $callbackUrl): ?array
    {
        return $this->request('POST', '/webhooks', [
            'event_type' => $eventType,
            'callback_url' => $callbackUrl,
        ]);
    }

    /**
     * Get connection statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $successCount = count(array_filter(
            $this->requestHistory,
            fn($r) => ($r['status'] ?? 0) < 400
        ));

        return [
            'api_url' => $this->apiUrl,
            'authenticated' => $this->authToken !== null,
            'request_count' => $this->requestCount,
            'success_rate' => $this->requestCount > 0
                ? $successCount / $this->requestCount
                : 1.0,
            'recent_requests' => array_slice($this->requestHistory, -10),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        $url = parse_url($this->apiUrl);
        $host = $url['host'] ?? 'localhost';
        $port = $url['port'] ?? ($url['scheme'] === 'https' ? 443 : 80);
        $basePath = $url['path'] ?? '/api/v1';
        $ssl = ($url['scheme'] ?? 'http') === 'https';

        $client = new Client($host, $port, $ssl);
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Api-Key' => $this->apiKey,
        ]);

        if ($this->authToken !== null) {
            $client->setHeaders([
                'Authorization' => 'Bearer ' . $this->authToken,
            ]);
        }

        // Add HMAC signature for security
        if (!empty($this->apiSecret)) {
            $timestamp = time();
            $signature = hash_hmac('sha256', $method . $endpoint . $timestamp, $this->apiSecret);
            $client->setHeaders([
                'X-Timestamp' => (string) $timestamp,
                'X-Signature' => $signature,
            ]);
        }

        $fullPath = $basePath . $endpoint;

        $this->requestCount++;
        $requestLog = [
            'method' => $method,
            'endpoint' => $endpoint,
            'timestamp' => time(),
        ];

        try {
            switch (strtoupper($method)) {
                case 'GET':
                    $client->get($fullPath);
                    break;
                case 'POST':
                    $client->post($fullPath, json_encode($data));
                    break;
                case 'PUT':
                    $client->put($fullPath, json_encode($data));
                    break;
                case 'DELETE':
                    $client->delete($fullPath);
                    break;
            }

            $requestLog['status'] = $client->statusCode;
            $this->requestHistory[] = $requestLog;

            // Keep only last 50 requests
            if (count($this->requestHistory) > 50) {
                array_shift($this->requestHistory);
            }

            if ($client->statusCode >= 400) {
                $this->logger->warning("OJS API error", [
                    'status' => $client->statusCode,
                    'endpoint' => $endpoint,
                ]);
                return null;
            }

            return json_decode($client->body, true);
        } catch (\Throwable $e) {
            $this->logger->error("OJS API request failed", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);

            $requestLog['error'] = $e->getMessage();
            $this->requestHistory[] = $requestLog;

            return null;
        }
    }
}
