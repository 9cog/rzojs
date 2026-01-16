<?php

declare(strict_types=1);

namespace SKZ\Agents\Service;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Redis;

/**
 * Persistent memory service for agents
 * Provides vector storage, knowledge graphs, and experience storage
 */
#[Singleton]
class MemoryService
{
    private ?Redis $redis = null;

    /**
     * @var array<string, array<array<string, mixed>>>
     */
    private array $memoryCache = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $redisHost = 'localhost',
        private readonly int $redisPort = 6379,
    ) {
    }

    /**
     * Store a memory item
     *
     * @param array<string, mixed> $content
     * @param array<string> $tags
     */
    public function store(
        string $agentId,
        string $memoryType,
        array $content,
        float $importanceScore = 0.5,
        array $tags = [],
    ): string {
        $memoryId = $this->generateMemoryId();

        $memory = [
            'id' => $memoryId,
            'agent_id' => $agentId,
            'type' => $memoryType,
            'content' => $content,
            'importance' => $importanceScore,
            'tags' => $tags,
            'created_at' => time(),
            'accessed_at' => time(),
            'access_count' => 0,
        ];

        $key = $this->getMemoryKey($agentId, $memoryType);

        // Store in local cache
        if (!isset($this->memoryCache[$key])) {
            $this->memoryCache[$key] = [];
        }
        $this->memoryCache[$key][$memoryId] = $memory;

        // Store in Redis if available
        $this->persistToRedis($key, $memoryId, $memory);

        $this->logger->debug("Memory stored", [
            'memory_id' => $memoryId,
            'agent_id' => $agentId,
            'type' => $memoryType,
            'importance' => $importanceScore,
        ]);

        return $memoryId;
    }

    /**
     * Retrieve memories for an agent
     *
     * @return array<array<string, mixed>>
     */
    public function retrieve(
        string $agentId,
        string $memoryType,
        int $limit = 10,
        ?float $minImportance = null,
    ): array {
        $key = $this->getMemoryKey($agentId, $memoryType);

        // Get from cache first
        $memories = $this->memoryCache[$key] ?? [];

        // Filter by importance if specified
        if ($minImportance !== null) {
            $memories = array_filter(
                $memories,
                fn($m) => $m['importance'] >= $minImportance
            );
        }

        // Sort by importance and recency
        usort($memories, function ($a, $b) {
            $scoreA = $a['importance'] * 0.7 + (1 - (time() - $a['accessed_at']) / 86400) * 0.3;
            $scoreB = $b['importance'] * 0.7 + (1 - (time() - $b['accessed_at']) / 86400) * 0.3;
            return $scoreB <=> $scoreA;
        });

        // Limit results
        $memories = array_slice($memories, 0, $limit);

        // Update access counts
        foreach ($memories as &$memory) {
            $memory['accessed_at'] = time();
            $memory['access_count']++;
        }

        return array_values($memories);
    }

    /**
     * Search memories by content similarity (simplified semantic search)
     *
     * @param array<string> $keywords
     * @return array<array<string, mixed>>
     */
    public function search(
        string $agentId,
        array $keywords,
        int $limit = 10,
    ): array {
        $results = [];

        foreach ($this->memoryCache as $key => $memories) {
            if (str_starts_with($key, "memory:{$agentId}:")) {
                foreach ($memories as $memory) {
                    $score = $this->calculateRelevance($memory['content'], $keywords);
                    if ($score > 0) {
                        $memory['relevance_score'] = $score;
                        $results[] = $memory;
                    }
                }
            }
        }

        // Sort by relevance
        usort($results, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Get memory by ID
     *
     * @return array<string, mixed>|null
     */
    public function get(string $agentId, string $memoryType, string $memoryId): ?array
    {
        $key = $this->getMemoryKey($agentId, $memoryType);

        return $this->memoryCache[$key][$memoryId] ?? null;
    }

    /**
     * Delete a memory
     */
    public function delete(string $agentId, string $memoryType, string $memoryId): bool
    {
        $key = $this->getMemoryKey($agentId, $memoryType);

        if (isset($this->memoryCache[$key][$memoryId])) {
            unset($this->memoryCache[$key][$memoryId]);
            $this->deleteFromRedis($key, $memoryId);
            return true;
        }

        return false;
    }

    /**
     * Clean up old memories
     */
    public function cleanup(int $maxAgeDays = 30): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($maxAgeDays * 86400);

        foreach ($this->memoryCache as $key => &$memories) {
            foreach ($memories as $id => $memory) {
                // Delete if old and low importance
                if ($memory['accessed_at'] < $cutoffTime && $memory['importance'] < 0.5) {
                    unset($memories[$id]);
                    $deleted++;
                }
            }
        }

        $this->logger->info("Memory cleanup completed", ['deleted' => $deleted]);

        return $deleted;
    }

    /**
     * Get memory statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalMemories = 0;
        $byType = [];
        $byAgent = [];

        foreach ($this->memoryCache as $key => $memories) {
            $count = count($memories);
            $totalMemories += $count;

            // Parse key to extract agent and type
            if (preg_match('/^memory:([^:]+):(.+)$/', $key, $matches)) {
                $agentId = $matches[1];
                $type = $matches[2];

                $byAgent[$agentId] = ($byAgent[$agentId] ?? 0) + $count;
                $byType[$type] = ($byType[$type] ?? 0) + $count;
            }
        }

        return [
            'total_memories' => $totalMemories,
            'by_type' => $byType,
            'by_agent' => $byAgent,
            'cache_keys' => count($this->memoryCache),
        ];
    }

    private function getMemoryKey(string $agentId, string $memoryType): string
    {
        return "memory:{$agentId}:{$memoryType}";
    }

    private function generateMemoryId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string> $keywords
     */
    private function calculateRelevance(array $content, array $keywords): float
    {
        $contentStr = strtolower(json_encode($content));
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($contentStr, strtolower($keyword))) {
                $matches++;
            }
        }

        return count($keywords) > 0 ? $matches / count($keywords) : 0;
    }

    /**
     * @param array<string, mixed> $memory
     */
    private function persistToRedis(string $key, string $memoryId, array $memory): void
    {
        try {
            if ($this->redis === null) {
                $this->redis = new Redis();
                $this->redis->connect($this->redisHost, $this->redisPort);
            }

            $this->redis->hSet($key, $memoryId, json_encode($memory));
        } catch (\Throwable $e) {
            $this->logger->warning("Redis persistence failed", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deleteFromRedis(string $key, string $memoryId): void
    {
        try {
            if ($this->redis !== null) {
                $this->redis->hDel($key, $memoryId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("Redis delete failed", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
