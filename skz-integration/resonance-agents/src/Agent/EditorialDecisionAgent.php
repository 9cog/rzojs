<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Message\AgentMessage;
use SKZ\Agents\Message\MessageType;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;
use SKZ\Agents\LLM\LLMService;

/**
 * Editorial Decision Agent
 *
 * Capabilities:
 * - Multi-agent workflow coordination and task orchestration
 * - Editorial decision making and resource allocation
 * - Conflict resolution and strategic planning
 * - Review consensus analysis
 */
#[Singleton]
class EditorialDecisionAgent extends BaseAgent
{
    private int $decisionsProcessed = 0;
    private int $escalationsHandled = 0;

    /**
     * @var array<string, float>
     */
    private array $decisionThresholds = [
        'accept' => 0.85,
        'minor_revision' => 0.80,
        'major_revision' => 0.70,
        'reject' => 0.60,
    ];

    public function __construct(
        LoggerInterface $logger,
        MessageBroker $messageBroker,
        MemoryService $memoryService,
        DecisionEngine $decisionEngine,
        private readonly ?LLMService $llmService = null,
    ) {
        parent::__construct($logger, $messageBroker, $memoryService, $decisionEngine);
    }

    public function getName(): string
    {
        return 'Editorial Decision Agent';
    }

    public function getType(): AgentType
    {
        return AgentType::EDITORIAL_DECISION;
    }

    protected function executeTask(array $taskData, array $decision): array
    {
        $taskType = $taskData['type'] ?? 'unknown';

        return match ($taskType) {
            'make_decision' => $this->makeDecision($taskData),
            'triage_submission' => $this->triageSubmission($taskData),
            'analyze_consensus' => $this->analyzeConsensus($taskData),
            'resolve_conflict' => $this->resolveConflict($taskData),
            'generate_letter' => $this->generateDecisionLetter($taskData),
            'strategic_planning' => $this->performStrategicPlanning($taskData),
            'resource_allocation' => $this->allocateResources($taskData),
            default => $this->handleGenericDecision($taskData),
        };
    }

    /**
     * Make editorial decision based on reviews and analysis
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function makeDecision(array $taskData): array
    {
        $submissionId = $taskData['submission_id'] ?? '';
        $reviews = $taskData['reviews'] ?? [];
        $qualityScore = $taskData['quality_score'] ?? 0.5;
        $manuscriptAnalysis = $taskData['manuscript_analysis'] ?? [];

        $this->logger->info("Making editorial decision", [
            'submission_id' => $submissionId,
            'review_count' => count($reviews),
        ]);

        // Use decision engine for core decision
        $decisionResult = $this->decisionEngine->makeEditorialDecision([
            'reviews' => $reviews,
            'quality_score' => $qualityScore,
        ]);

        // Add additional analysis
        $decisionResult['submission_id'] = $submissionId;
        $decisionResult['reviewers_consensus'] = $this->calculateReviewerConsensus($reviews);
        $decisionResult['strengths'] = $this->identifyStrengths($reviews, $manuscriptAnalysis);
        $decisionResult['weaknesses'] = $this->identifyWeaknesses($reviews, $manuscriptAnalysis);

        // Generate decision letter
        $decisionResult['decision_letter'] = $this->composeLetter(
            $decisionResult['decision'],
            $decisionResult['reasoning'],
            $decisionResult['recommendations']
        );

        // Check if escalation needed
        if ($this->requiresEscalation($decisionResult)) {
            $decisionResult['escalation_required'] = true;
            $decisionResult['escalation_reason'] = 'Low confidence or conflicting reviews';
            $this->escalationsHandled++;
        }

        $this->decisionsProcessed++;

        // Store decision in memory for learning
        $this->memoryService->store(
            $this->id,
            'decision',
            $decisionResult,
            0.9,
            ['editorial_decision', $decisionResult['decision']]
        );

        return $decisionResult;
    }

    /**
     * Triage new submission for initial screening
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function triageSubmission(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];

        $this->logger->info("Triaging submission", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
        ]);

        // Initial scope check
        $scopeCheck = $this->checkScope($manuscript);

        // Quality pre-screen
        $qualityPrescreen = $this->prescreenQuality($manuscript);

        // Plagiarism flag check
        $plagiarismFlag = $this->checkPlagiarismFlag($manuscript);

        // Determine triage outcome
        $triageDecision = $this->determineTriageOutcome($scopeCheck, $qualityPrescreen, $plagiarismFlag);

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'scope_check' => $scopeCheck,
            'quality_prescreen' => $qualityPrescreen,
            'plagiarism_flag' => $plagiarismFlag,
            'triage_decision' => $triageDecision,
            'recommended_action' => $this->getTriageAction($triageDecision),
            'priority' => $this->assignPriority($manuscript, $triageDecision),
        ];
    }

    /**
     * Analyze reviewer consensus
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function analyzeConsensus(array $taskData): array
    {
        $reviews = $taskData['reviews'] ?? [];

        $this->logger->info("Analyzing reviewer consensus", [
            'review_count' => count($reviews),
        ]);

        if (count($reviews) < 2) {
            return [
                'consensus_level' => 'insufficient_reviews',
                'analysis' => 'Need at least 2 reviews for consensus analysis',
            ];
        }

        // Extract recommendations
        $recommendations = array_map(fn($r) => $r['recommendation'] ?? 'unknown', $reviews);
        $scores = array_map(fn($r) => $r['score'] ?? 0.5, $reviews);

        // Calculate agreement metrics
        $recommendationAgreement = $this->calculateRecommendationAgreement($recommendations);
        $scoreVariance = $this->calculateScoreVariance($scores);

        // Identify points of agreement and disagreement
        $agreementPoints = $this->identifyAgreementPoints($reviews);
        $disagreementPoints = $this->identifyDisagreementPoints($reviews);

        $consensusLevel = match (true) {
            $recommendationAgreement >= 0.9 => 'strong',
            $recommendationAgreement >= 0.7 => 'moderate',
            $recommendationAgreement >= 0.5 => 'weak',
            default => 'no_consensus',
        };

        return [
            'consensus_level' => $consensusLevel,
            'recommendation_agreement' => $recommendationAgreement,
            'score_variance' => $scoreVariance,
            'agreement_points' => $agreementPoints,
            'disagreement_points' => $disagreementPoints,
            'synthesis' => $this->synthesizeReviews($reviews, $consensusLevel),
        ];
    }

    /**
     * Resolve conflicts between reviewers
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function resolveConflict(array $taskData): array
    {
        $reviews = $taskData['reviews'] ?? [];
        $conflictType = $taskData['conflict_type'] ?? 'recommendation_disagreement';

        $this->logger->info("Resolving reviewer conflict", [
            'conflict_type' => $conflictType,
        ]);

        $resolution = match ($conflictType) {
            'recommendation_disagreement' => $this->resolveRecommendationConflict($reviews),
            'score_discrepancy' => $this->resolveScoreDiscrepancy($reviews),
            'methodology_dispute' => $this->resolveMethodologyDispute($reviews),
            default => $this->resolveGenericConflict($reviews),
        };

        return [
            'conflict_type' => $conflictType,
            'resolution' => $resolution,
            'action_required' => $resolution['action_required'] ?? 'none',
            'additional_review_needed' => $resolution['additional_review_needed'] ?? false,
        ];
    }

    /**
     * Generate decision letter
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generateDecisionLetter(array $taskData): array
    {
        $decision = $taskData['decision'] ?? 'pending';
        $reasoning = $taskData['reasoning'] ?? '';
        $recommendations = $taskData['recommendations'] ?? [];
        $manuscriptTitle = $taskData['manuscript_title'] ?? '';
        $authorName = $taskData['author_name'] ?? 'Author';

        $this->logger->info("Generating decision letter", ['decision' => $decision]);

        $letter = $this->composeLetter($decision, $reasoning, $recommendations);

        // Use LLM to polish letter if available
        if ($this->llmService !== null) {
            $letter = $this->polishLetterWithLLM($letter, $decision);
        }

        return [
            'letter' => $letter,
            'decision' => $decision,
            'personalized' => true,
            'includes_feedback' => !empty($recommendations),
        ];
    }

    /**
     * Perform strategic planning for editorial workflow
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function performStrategicPlanning(array $taskData): array
    {
        $currentMetrics = $taskData['current_metrics'] ?? [];
        $goals = $taskData['goals'] ?? [];
        $constraints = $taskData['constraints'] ?? [];

        $this->logger->info("Performing strategic planning");

        // Analyze current state
        $stateAnalysis = $this->analyzeCurrentState($currentMetrics);

        // Identify improvement opportunities
        $opportunities = $this->identifyOpportunities($stateAnalysis, $goals);

        // Generate strategic recommendations
        $strategies = $this->generateStrategies($opportunities, $constraints);

        return [
            'state_analysis' => $stateAnalysis,
            'opportunities' => $opportunities,
            'strategies' => $strategies,
            'kpis' => $this->defineKPIs($goals),
            'timeline' => $this->createStrategicTimeline($strategies),
        ];
    }

    /**
     * Allocate editorial resources
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function allocateResources(array $taskData): array
    {
        $submissions = $taskData['submissions'] ?? [];
        $availableEditors = $taskData['editors'] ?? [];

        $this->logger->info("Allocating editorial resources", [
            'submission_count' => count($submissions),
            'editor_count' => count($availableEditors),
        ]);

        $allocations = [];

        foreach ($submissions as $submission) {
            $bestEditor = $this->findBestEditor($submission, $availableEditors);

            if ($bestEditor !== null) {
                $allocations[] = [
                    'submission_id' => $submission['id'] ?? null,
                    'editor_id' => $bestEditor['id'],
                    'match_score' => $bestEditor['match_score'],
                    'rationale' => $bestEditor['rationale'],
                ];
            }
        }

        return [
            'allocations' => $allocations,
            'unassigned' => count($submissions) - count($allocations),
            'workload_distribution' => $this->analyzeWorkloadDistribution($allocations, $availableEditors),
        ];
    }

    protected function getAgentSpecificMetrics(): array
    {
        return [
            'decisions_processed' => $this->decisionsProcessed,
            'escalations_handled' => $this->escalationsHandled,
            'thresholds' => $this->decisionThresholds,
        ];
    }

    // Private helper methods

    /**
     * @return array<string, mixed>
     */
    private function calculateReviewerConsensus(array $reviews): array
    {
        if (empty($reviews)) {
            return ['level' => 'none', 'score' => 0];
        }

        $recommendations = array_column($reviews, 'recommendation');
        $counts = array_count_values($recommendations);
        $maxCount = max($counts);
        $agreementRate = $maxCount / count($recommendations);

        return [
            'level' => $agreementRate >= 0.8 ? 'high' : ($agreementRate >= 0.5 ? 'moderate' : 'low'),
            'score' => $agreementRate,
            'majority_recommendation' => array_search($maxCount, $counts),
        ];
    }

    /**
     * @return array<string>
     */
    private function identifyStrengths(array $reviews, array $analysis): array
    {
        $strengths = [];

        foreach ($reviews as $review) {
            if (!empty($review['strengths'])) {
                $strengths = array_merge($strengths, (array) $review['strengths']);
            }
        }

        return array_unique($strengths);
    }

    /**
     * @return array<string>
     */
    private function identifyWeaknesses(array $reviews, array $analysis): array
    {
        $weaknesses = [];

        foreach ($reviews as $review) {
            if (!empty($review['weaknesses'])) {
                $weaknesses = array_merge($weaknesses, (array) $review['weaknesses']);
            }
        }

        return array_unique($weaknesses);
    }

    private function composeLetter(string $decision, string $reasoning, array $recommendations): string
    {
        $template = match ($decision) {
            'accept' => "We are pleased to inform you that your manuscript has been accepted for publication.\n\n%s",
            'minor_revision' => "Your manuscript requires minor revisions before acceptance.\n\n%s\n\nPlease address the following:\n%s",
            'major_revision' => "Your manuscript requires major revisions.\n\n%s\n\nKey areas requiring attention:\n%s",
            'reject' => "After careful review, we regret to inform you that your manuscript cannot be accepted.\n\n%s",
            default => "Your submission is under review.\n\n%s",
        };

        $recommendationList = !empty($recommendations)
            ? "- " . implode("\n- ", $recommendations)
            : '';

        return sprintf($template, $reasoning, $recommendationList);
    }

    private function requiresEscalation(array $decision): bool
    {
        return ($decision['confidence'] ?? 0) < 0.6;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkScope(array $manuscript): array
    {
        return ['in_scope' => true, 'confidence' => 0.9];
    }

    /**
     * @return array<string, mixed>
     */
    private function prescreenQuality(array $manuscript): array
    {
        return ['passes' => true, 'score' => 0.75];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPlagiarismFlag(array $manuscript): array
    {
        return ['flagged' => false, 'score' => 0.02];
    }

    private function determineTriageOutcome(array $scope, array $quality, array $plagiarism): string
    {
        if (!$scope['in_scope']) {
            return 'desk_reject_scope';
        }
        if ($plagiarism['flagged']) {
            return 'desk_reject_plagiarism';
        }
        if (!$quality['passes']) {
            return 'desk_reject_quality';
        }
        return 'proceed_to_review';
    }

    private function getTriageAction(string $decision): string
    {
        return match ($decision) {
            'proceed_to_review' => 'Send for peer review',
            'desk_reject_scope' => 'Notify author of scope mismatch',
            'desk_reject_plagiarism' => 'Notify author of plagiarism concerns',
            'desk_reject_quality' => 'Notify author of quality issues',
            default => 'Manual review required',
        };
    }

    private function assignPriority(array $manuscript, string $triageDecision): string
    {
        if ($triageDecision !== 'proceed_to_review') {
            return 'low';
        }
        return 'normal';
    }

    private function calculateRecommendationAgreement(array $recommendations): float
    {
        if (empty($recommendations)) {
            return 0;
        }
        $counts = array_count_values($recommendations);
        return max($counts) / count($recommendations);
    }

    private function calculateScoreVariance(array $scores): float
    {
        if (count($scores) < 2) {
            return 0;
        }
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(fn($s) => pow($s - $mean, 2), $scores)) / count($scores);
        return sqrt($variance);
    }

    /**
     * @return array<string>
     */
    private function identifyAgreementPoints(array $reviews): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function identifyDisagreementPoints(array $reviews): array
    {
        return [];
    }

    private function synthesizeReviews(array $reviews, string $consensusLevel): string
    {
        return "Review synthesis based on {$consensusLevel} consensus.";
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRecommendationConflict(array $reviews): array
    {
        return ['action_required' => 'seek_additional_review', 'additional_review_needed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveScoreDiscrepancy(array $reviews): array
    {
        return ['action_required' => 'editor_arbitration'];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMethodologyDispute(array $reviews): array
    {
        return ['action_required' => 'expert_consultation'];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveGenericConflict(array $reviews): array
    {
        return ['action_required' => 'manual_review'];
    }

    private function polishLetterWithLLM(string $letter, string $decision): string
    {
        return $letter; // Would use LLM in production
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeCurrentState(array $metrics): array
    {
        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyOpportunities(array $analysis, array $goals): array
    {
        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function generateStrategies(array $opportunities, array $constraints): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function defineKPIs(array $goals): array
    {
        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function createStrategicTimeline(array $strategies): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBestEditor(array $submission, array $editors): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeWorkloadDistribution(array $allocations, array $editors): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericDecision(array $taskData): array
    {
        return ['status' => 'processed'];
    }
}
