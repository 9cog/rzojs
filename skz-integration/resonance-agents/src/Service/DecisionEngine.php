<?php

declare(strict_types=1);

namespace SKZ\Agents\Service;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Agent\AgentCapability;
use SKZ\Agents\LLM\LLMService;

/**
 * ML-powered decision engine for agents
 * Provides intelligent decision making based on context, capabilities, and learned patterns
 */
#[Singleton]
class DecisionEngine
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $decisionHistory = [];

    /**
     * @var array<string, float>
     */
    private array $confidenceThresholds = [
        'accept' => 0.85,
        'major_revision' => 0.70,
        'minor_revision' => 0.80,
        'reject' => 0.60,
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?LLMService $llmService = null,
    ) {
    }

    /**
     * Analyze task data and provide decision context
     *
     * @param array<string, mixed> $taskData
     * @param array<AgentCapability> $capabilities
     * @return array<string, mixed>
     */
    public function analyze(array $taskData, array $capabilities): array
    {
        $startTime = microtime(true);

        // Extract task type and parameters
        $taskType = $taskData['type'] ?? 'unknown';
        $priority = $taskData['priority'] ?? 'normal';

        // Match task to capabilities
        $relevantCapabilities = $this->matchCapabilities($taskData, $capabilities);

        // Calculate confidence score
        $confidence = $this->calculateConfidence($taskData, $relevantCapabilities);

        // Determine recommended action
        $recommendedAction = $this->determineAction($taskData, $confidence);

        // Get risk assessment
        $riskAssessment = $this->assessRisk($taskData, $recommendedAction);

        // Use LLM for complex reasoning if available
        $llmInsight = null;
        if ($this->llmService !== null && $confidence < 0.7) {
            $llmInsight = $this->getLLMInsight($taskData);
        }

        $decision = [
            'task_type' => $taskType,
            'confidence' => $confidence,
            'recommended_action' => $recommendedAction,
            'relevant_capabilities' => array_map(fn($c) => $c->value, $relevantCapabilities),
            'risk_assessment' => $riskAssessment,
            'priority' => $priority,
            'llm_insight' => $llmInsight,
            'processing_time' => microtime(true) - $startTime,
            'timestamp' => time(),
        ];

        // Store in history for learning
        $this->storeDecision($decision);

        $this->logger->debug("Decision analysis complete", [
            'task_type' => $taskType,
            'confidence' => $confidence,
            'action' => $recommendedAction,
        ]);

        return $decision;
    }

    /**
     * Make a decision for editorial workflow
     *
     * @param array<string, mixed> $reviewData
     * @return array<string, mixed>
     */
    public function makeEditorialDecision(array $reviewData): array
    {
        $reviews = $reviewData['reviews'] ?? [];
        $manuscriptQuality = $reviewData['quality_score'] ?? 0.5;

        // Calculate consensus from reviews
        $consensus = $this->calculateReviewConsensus($reviews);

        // Combine with quality score
        $overallScore = ($consensus['score'] * 0.6) + ($manuscriptQuality * 0.4);

        // Determine decision based on thresholds
        $decision = match (true) {
            $overallScore >= $this->confidenceThresholds['accept'] => 'accept',
            $overallScore >= $this->confidenceThresholds['minor_revision'] => 'minor_revision',
            $overallScore >= $this->confidenceThresholds['major_revision'] => 'major_revision',
            default => 'reject',
        };

        return [
            'decision' => $decision,
            'confidence' => $overallScore,
            'consensus' => $consensus,
            'manuscript_quality' => $manuscriptQuality,
            'reasoning' => $this->generateReasoning($decision, $consensus, $manuscriptQuality),
            'recommendations' => $this->generateRecommendations($decision, $reviews),
        ];
    }

    /**
     * Match reviewer to manuscript
     *
     * @param array<string, mixed> $manuscript
     * @param array<array<string, mixed>> $reviewers
     * @return array<array<string, mixed>>
     */
    public function matchReviewers(array $manuscript, array $reviewers): array
    {
        $matches = [];
        $keywords = $manuscript['keywords'] ?? [];
        $topic = $manuscript['topic'] ?? '';

        foreach ($reviewers as $reviewer) {
            $expertise = $reviewer['expertise'] ?? [];
            $availability = $reviewer['availability'] ?? 1.0;
            $workload = $reviewer['current_workload'] ?? 0;

            // Calculate expertise match
            $expertiseScore = $this->calculateExpertiseMatch($keywords, $topic, $expertise);

            // Adjust for availability and workload
            $availabilityFactor = $availability * (1 - min($workload / 10, 0.5));

            $matchScore = $expertiseScore * 0.7 + $availabilityFactor * 0.3;

            $matches[] = [
                'reviewer_id' => $reviewer['id'],
                'match_score' => $matchScore,
                'expertise_score' => $expertiseScore,
                'availability_factor' => $availabilityFactor,
                'recommendation' => $matchScore >= 0.7 ? 'highly_recommended' :
                    ($matchScore >= 0.5 ? 'recommended' : 'possible'),
            ];
        }

        // Sort by match score
        usort($matches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        return $matches;
    }

    /**
     * Set confidence thresholds
     *
     * @param array<string, float> $thresholds
     */
    public function setThresholds(array $thresholds): void
    {
        $this->confidenceThresholds = array_merge($this->confidenceThresholds, $thresholds);
    }

    /**
     * Get decision statistics
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalDecisions = count($this->decisionHistory);
        $avgConfidence = 0;
        $byAction = [];

        foreach ($this->decisionHistory as $decision) {
            $avgConfidence += $decision['confidence'];
            $action = $decision['recommended_action'] ?? 'unknown';
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
        }

        return [
            'total_decisions' => $totalDecisions,
            'avg_confidence' => $totalDecisions > 0 ? $avgConfidence / $totalDecisions : 0,
            'by_action' => $byAction,
            'thresholds' => $this->confidenceThresholds,
        ];
    }

    /**
     * @param array<string, mixed> $taskData
     * @param array<AgentCapability> $capabilities
     * @return array<AgentCapability>
     */
    private function matchCapabilities(array $taskData, array $capabilities): array
    {
        $taskType = $taskData['type'] ?? '';
        $matched = [];

        foreach ($capabilities as $capability) {
            $capValue = strtolower($capability->value);
            if (str_contains(strtolower($taskType), $capValue) ||
                str_contains($capValue, strtolower($taskType))) {
                $matched[] = $capability;
            }
        }

        // If no direct match, return all capabilities
        return !empty($matched) ? $matched : $capabilities;
    }

    /**
     * @param array<string, mixed> $taskData
     * @param array<AgentCapability> $capabilities
     */
    private function calculateConfidence(array $taskData, array $capabilities): float
    {
        $baseConfidence = 0.5;

        // Increase confidence if we have matching capabilities
        if (!empty($capabilities)) {
            $baseConfidence += 0.2;
        }

        // Increase confidence based on data completeness
        $requiredFields = ['type', 'content', 'source'];
        $presentFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($taskData[$field])) {
                $presentFields++;
            }
        }
        $baseConfidence += ($presentFields / count($requiredFields)) * 0.2;

        // Check historical success rate for similar tasks
        $historicalRate = $this->getHistoricalSuccessRate($taskData['type'] ?? 'unknown');
        $baseConfidence = ($baseConfidence * 0.7) + ($historicalRate * 0.3);

        return min(1.0, max(0.0, $baseConfidence));
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function determineAction(array $taskData, float $confidence): string
    {
        $taskType = $taskData['type'] ?? 'unknown';

        if ($confidence >= 0.8) {
            return 'execute';
        } elseif ($confidence >= 0.6) {
            return 'execute_with_monitoring';
        } elseif ($confidence >= 0.4) {
            return 'request_clarification';
        } else {
            return 'escalate';
        }
    }

    /**
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    private function assessRisk(array $taskData, string $action): array
    {
        $riskLevel = match ($action) {
            'execute' => 'low',
            'execute_with_monitoring' => 'medium',
            'request_clarification' => 'medium',
            'escalate' => 'high',
            default => 'unknown',
        };

        return [
            'level' => $riskLevel,
            'factors' => [
                'data_completeness' => !empty($taskData['content']),
                'has_validation' => !empty($taskData['validation']),
                'historical_issues' => false,
            ],
            'mitigation' => $this->suggestMitigation($riskLevel),
        ];
    }

    private function suggestMitigation(string $riskLevel): string
    {
        return match ($riskLevel) {
            'low' => 'Standard monitoring sufficient',
            'medium' => 'Enable detailed logging and validation checks',
            'high' => 'Require manual review before proceeding',
            default => 'Assess situation manually',
        };
    }

    /**
     * @param array<string, mixed> $taskData
     */
    private function getLLMInsight(array $taskData): ?string
    {
        if ($this->llmService === null) {
            return null;
        }

        try {
            $prompt = sprintf(
                "Analyze this task and provide a brief recommendation: %s",
                json_encode($taskData)
            );

            return $this->llmService->complete($prompt, 100);
        } catch (\Throwable $e) {
            $this->logger->warning("LLM insight generation failed", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @param array<string, mixed> $decision
     */
    private function storeDecision(array $decision): void
    {
        $id = bin2hex(random_bytes(8));
        $this->decisionHistory[$id] = $decision;

        // Keep only last 1000 decisions
        if (count($this->decisionHistory) > 1000) {
            array_shift($this->decisionHistory);
        }
    }

    private function getHistoricalSuccessRate(string $taskType): float
    {
        $relevant = array_filter(
            $this->decisionHistory,
            fn($d) => ($d['task_type'] ?? '') === $taskType
        );

        if (empty($relevant)) {
            return 0.5; // Default to neutral
        }

        $successful = array_filter($relevant, fn($d) => ($d['confidence'] ?? 0) >= 0.6);
        return count($successful) / count($relevant);
    }

    /**
     * @param array<array<string, mixed>> $reviews
     * @return array<string, mixed>
     */
    private function calculateReviewConsensus(array $reviews): array
    {
        if (empty($reviews)) {
            return ['score' => 0.5, 'agreement' => 0, 'reviews_count' => 0];
        }

        $scores = array_map(fn($r) => $r['score'] ?? 0.5, $reviews);
        $avgScore = array_sum($scores) / count($scores);

        // Calculate agreement (inverse of standard deviation)
        $variance = array_sum(array_map(fn($s) => pow($s - $avgScore, 2), $scores)) / count($scores);
        $agreement = 1 - min(sqrt($variance), 1);

        return [
            'score' => $avgScore,
            'agreement' => $agreement,
            'reviews_count' => count($reviews),
        ];
    }

    /**
     * @param array<string, mixed> $consensus
     */
    private function generateReasoning(string $decision, array $consensus, float $quality): string
    {
        return sprintf(
            "Decision '%s' based on review consensus (%.2f with %.0f%% agreement) and manuscript quality (%.2f).",
            $decision,
            $consensus['score'],
            $consensus['agreement'] * 100,
            $quality
        );
    }

    /**
     * @param array<array<string, mixed>> $reviews
     * @return array<string>
     */
    private function generateRecommendations(string $decision, array $reviews): array
    {
        $recommendations = [];

        if ($decision === 'major_revision' || $decision === 'minor_revision') {
            foreach ($reviews as $review) {
                if (!empty($review['comments'])) {
                    $recommendations[] = $review['comments'];
                }
            }
        }

        return array_slice($recommendations, 0, 5);
    }

    /**
     * @param array<string> $keywords
     * @param array<string> $expertise
     */
    private function calculateExpertiseMatch(array $keywords, string $topic, array $expertise): float
    {
        if (empty($expertise)) {
            return 0.3;
        }

        $matches = 0;
        $total = count($keywords) + 1; // +1 for topic

        // Check topic match
        foreach ($expertise as $exp) {
            if (str_contains(strtolower($topic), strtolower($exp)) ||
                str_contains(strtolower($exp), strtolower($topic))) {
                $matches += 0.5;
                break;
            }
        }

        // Check keyword matches
        foreach ($keywords as $keyword) {
            foreach ($expertise as $exp) {
                if (str_contains(strtolower($keyword), strtolower($exp)) ||
                    str_contains(strtolower($exp), strtolower($keyword))) {
                    $matches++;
                    break;
                }
            }
        }

        return min(1.0, $matches / $total);
    }
}
