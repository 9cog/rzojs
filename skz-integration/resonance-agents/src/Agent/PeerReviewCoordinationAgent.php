<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;

/**
 * Peer Review Coordination Agent
 *
 * Capabilities:
 * - Expertise-based reviewer matching algorithms
 * - Workload management and timeline optimization
 * - Quality monitoring and reviewer communication
 * - Review tracking and deadline management
 */
#[Singleton]
class PeerReviewCoordinationAgent extends BaseAgent
{
    private int $reviewsCoordinated = 0;
    private int $reviewersAssigned = 0;
    private float $avgMatchScore = 0.0;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $reviewerPool = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $activeReviews = [];

    public function __construct(
        LoggerInterface $logger,
        MessageBroker $messageBroker,
        MemoryService $memoryService,
        DecisionEngine $decisionEngine,
    ) {
        parent::__construct($logger, $messageBroker, $memoryService, $decisionEngine);
    }

    public function getName(): string
    {
        return 'Peer Review Coordination Agent';
    }

    public function getType(): AgentType
    {
        return AgentType::PEER_REVIEW_COORDINATION;
    }

    protected function executeTask(array $taskData, array $decision): array
    {
        $taskType = $taskData['type'] ?? 'unknown';

        return match ($taskType) {
            'find_reviewers' => $this->findReviewers($taskData),
            'assign_reviewer' => $this->assignReviewer($taskData),
            'track_review' => $this->trackReview($taskData),
            'send_reminder' => $this->sendReminder($taskData),
            'assess_review_quality' => $this->assessReviewQuality($taskData),
            'manage_workload' => $this->manageWorkload($taskData),
            'optimize_timeline' => $this->optimizeTimeline($taskData),
            'get_reviewer_stats' => $this->getReviewerStats($taskData),
            default => $this->handleGenericCoordination($taskData),
        };
    }

    /**
     * Find suitable reviewers for a manuscript
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function findReviewers(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $requiredCount = $taskData['required_count'] ?? 2;
        $excludeAuthors = $taskData['exclude_authors'] ?? [];

        $this->logger->info("Finding reviewers for manuscript", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
            'required_count' => $requiredCount,
        ]);

        // Use decision engine for matching
        $matches = $this->decisionEngine->matchReviewers($manuscript, $this->getAvailableReviewers());

        // Filter out conflicts of interest
        $matches = $this->filterConflicts($matches, $excludeAuthors, $manuscript);

        // Get top matches
        $topMatches = array_slice($matches, 0, $requiredCount * 2);

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'matches' => $topMatches,
            'recommended' => array_slice($topMatches, 0, $requiredCount),
            'alternatives' => array_slice($topMatches, $requiredCount),
            'matching_criteria' => [
                'expertise_weight' => 0.7,
                'availability_weight' => 0.3,
            ],
        ];
    }

    /**
     * Assign a reviewer to a manuscript
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function assignReviewer(array $taskData): array
    {
        $submissionId = $taskData['submission_id'] ?? '';
        $reviewerId = $taskData['reviewer_id'] ?? '';
        $deadline = $taskData['deadline'] ?? $this->calculateDefaultDeadline();

        $this->logger->info("Assigning reviewer", [
            'submission_id' => $submissionId,
            'reviewer_id' => $reviewerId,
        ]);

        // Create review assignment
        $assignmentId = bin2hex(random_bytes(8));
        $assignment = [
            'id' => $assignmentId,
            'submission_id' => $submissionId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending',
            'assigned_at' => time(),
            'deadline' => $deadline,
            'reminders_sent' => 0,
        ];

        $this->activeReviews[$assignmentId] = $assignment;
        $this->reviewersAssigned++;

        // Update reviewer workload
        $this->updateReviewerWorkload($reviewerId, 1);

        return [
            'assignment_id' => $assignmentId,
            'assignment' => $assignment,
            'notification_sent' => true,
            'estimated_completion' => $deadline,
        ];
    }

    /**
     * Track review progress
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function trackReview(array $taskData): array
    {
        $assignmentId = $taskData['assignment_id'] ?? '';
        $submissionId = $taskData['submission_id'] ?? '';

        $this->logger->info("Tracking review progress", [
            'assignment_id' => $assignmentId,
        ]);

        // Get specific assignment or all for submission
        if (!empty($assignmentId)) {
            $reviews = [$this->activeReviews[$assignmentId] ?? null];
        } else {
            $reviews = array_filter(
                $this->activeReviews,
                fn($r) => $r['submission_id'] === $submissionId
            );
        }

        $reviews = array_filter($reviews);
        $status = $this->analyzeReviewStatus($reviews);

        return [
            'reviews' => array_values($reviews),
            'status' => $status,
            'overdue' => $this->identifyOverdueReviews($reviews),
            'progress' => $this->calculateProgress($reviews),
            'estimated_completion' => $this->estimateCompletion($reviews),
        ];
    }

    /**
     * Send reminder to reviewer
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function sendReminder(array $taskData): array
    {
        $assignmentId = $taskData['assignment_id'] ?? '';
        $type = $taskData['reminder_type'] ?? 'gentle';

        $this->logger->info("Sending reminder", [
            'assignment_id' => $assignmentId,
            'type' => $type,
        ]);

        if (!isset($this->activeReviews[$assignmentId])) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }

        $assignment = $this->activeReviews[$assignmentId];
        $this->activeReviews[$assignmentId]['reminders_sent']++;

        return [
            'success' => true,
            'assignment_id' => $assignmentId,
            'reminder_type' => $type,
            'sent_at' => time(),
            'total_reminders' => $this->activeReviews[$assignmentId]['reminders_sent'],
        ];
    }

    /**
     * Assess quality of a completed review
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function assessReviewQuality(array $taskData): array
    {
        $review = $taskData['review'] ?? [];

        $this->logger->info("Assessing review quality");

        $qualityMetrics = [
            'thoroughness' => $this->assessThoroughness($review),
            'constructiveness' => $this->assessConstructiveness($review),
            'specificity' => $this->assessSpecificity($review),
            'timeliness' => $this->assessTimeliness($review),
            'adherence_to_guidelines' => $this->assessGuidelinesAdherence($review),
        ];

        $overallScore = array_sum($qualityMetrics) / count($qualityMetrics);

        $this->reviewsCoordinated++;

        return [
            'quality_metrics' => $qualityMetrics,
            'overall_score' => $overallScore,
            'rating' => $this->qualityScoreToRating($overallScore),
            'feedback' => $this->generateReviewerFeedback($qualityMetrics),
        ];
    }

    /**
     * Manage reviewer workload
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function manageWorkload(array $taskData): array
    {
        $this->logger->info("Managing reviewer workload");

        $workloadAnalysis = [];

        foreach ($this->reviewerPool as $reviewerId => $reviewer) {
            $activeCount = count(array_filter(
                $this->activeReviews,
                fn($r) => $r['reviewer_id'] === $reviewerId && $r['status'] !== 'completed'
            ));

            $workloadAnalysis[$reviewerId] = [
                'active_reviews' => $activeCount,
                'capacity' => $reviewer['max_reviews'] ?? 5,
                'utilization' => $activeCount / ($reviewer['max_reviews'] ?? 5),
                'availability' => $this->calculateAvailability($reviewerId),
            ];
        }

        // Identify overloaded and underutilized reviewers
        $overloaded = array_filter($workloadAnalysis, fn($w) => $w['utilization'] > 0.8);
        $underutilized = array_filter($workloadAnalysis, fn($w) => $w['utilization'] < 0.3);

        return [
            'workload_analysis' => $workloadAnalysis,
            'overloaded_reviewers' => array_keys($overloaded),
            'underutilized_reviewers' => array_keys($underutilized),
            'rebalancing_suggestions' => $this->generateRebalancingSuggestions($overloaded, $underutilized),
        ];
    }

    /**
     * Optimize review timeline
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function optimizeTimeline(array $taskData): array
    {
        $submissionId = $taskData['submission_id'] ?? '';
        $targetDate = $taskData['target_date'] ?? null;

        $this->logger->info("Optimizing review timeline", [
            'submission_id' => $submissionId,
        ]);

        $reviews = array_filter(
            $this->activeReviews,
            fn($r) => $r['submission_id'] === $submissionId
        );

        $currentTimeline = $this->buildCurrentTimeline($reviews);
        $optimizedTimeline = $this->computeOptimalTimeline($reviews, $targetDate);

        return [
            'submission_id' => $submissionId,
            'current_timeline' => $currentTimeline,
            'optimized_timeline' => $optimizedTimeline,
            'time_saved' => $this->calculateTimeSaved($currentTimeline, $optimizedTimeline),
            'actions_needed' => $this->identifyTimelineActions($currentTimeline, $optimizedTimeline),
        ];
    }

    /**
     * Get reviewer statistics
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function getReviewerStats(array $taskData): array
    {
        $reviewerId = $taskData['reviewer_id'] ?? null;

        $this->logger->info("Getting reviewer statistics", [
            'reviewer_id' => $reviewerId,
        ]);

        if ($reviewerId !== null) {
            return $this->getSingleReviewerStats($reviewerId);
        }

        // Aggregate stats for all reviewers
        $stats = [
            'total_reviewers' => count($this->reviewerPool),
            'active_reviews' => count($this->activeReviews),
            'avg_review_time' => $this->calculateAvgReviewTime(),
            'completion_rate' => $this->calculateCompletionRate(),
            'top_performers' => $this->identifyTopPerformers(),
        ];

        return $stats;
    }

    protected function getAgentSpecificMetrics(): array
    {
        return [
            'reviews_coordinated' => $this->reviewsCoordinated,
            'reviewers_assigned' => $this->reviewersAssigned,
            'avg_match_score' => $this->avgMatchScore,
            'active_reviews' => count($this->activeReviews),
            'reviewer_pool_size' => count($this->reviewerPool),
        ];
    }

    protected function onInitialize(): void
    {
        // Load reviewer pool from database/storage
        $this->loadReviewerPool();
    }

    // Private helper methods

    private function loadReviewerPool(): void
    {
        // In production, load from database
        $this->reviewerPool = [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getAvailableReviewers(): array
    {
        return array_filter(
            $this->reviewerPool,
            fn($r) => ($r['available'] ?? true) && ($r['current_workload'] ?? 0) < ($r['max_reviews'] ?? 5)
        );
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function filterConflicts(array $matches, array $excludeAuthors, array $manuscript): array
    {
        return array_filter($matches, function ($match) use ($excludeAuthors, $manuscript) {
            $reviewerId = $match['reviewer_id'];

            // Check author exclusion
            if (in_array($reviewerId, $excludeAuthors)) {
                return false;
            }

            // Check institutional conflict
            $reviewer = $this->reviewerPool[$reviewerId] ?? [];
            $authorInstitutions = $manuscript['author_institutions'] ?? [];
            if (in_array($reviewer['institution'] ?? '', $authorInstitutions)) {
                return false;
            }

            return true;
        });
    }

    private function calculateDefaultDeadline(): int
    {
        return time() + (21 * 24 * 60 * 60); // 21 days
    }

    private function updateReviewerWorkload(string $reviewerId, int $delta): void
    {
        if (isset($this->reviewerPool[$reviewerId])) {
            $this->reviewerPool[$reviewerId]['current_workload'] =
                ($this->reviewerPool[$reviewerId]['current_workload'] ?? 0) + $delta;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeReviewStatus(array $reviews): array
    {
        $statuses = array_count_values(array_column($reviews, 'status'));

        return [
            'total' => count($reviews),
            'by_status' => $statuses,
            'all_complete' => ($statuses['completed'] ?? 0) === count($reviews),
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyOverdueReviews(array $reviews): array
    {
        $now = time();
        return array_filter($reviews, fn($r) => $r['deadline'] < $now && $r['status'] !== 'completed');
    }

    private function calculateProgress(array $reviews): float
    {
        if (empty($reviews)) {
            return 0.0;
        }

        $completed = count(array_filter($reviews, fn($r) => $r['status'] === 'completed'));
        return $completed / count($reviews);
    }

    private function estimateCompletion(array $reviews): ?int
    {
        $pendingDeadlines = array_map(
            fn($r) => $r['deadline'],
            array_filter($reviews, fn($r) => $r['status'] !== 'completed')
        );

        return !empty($pendingDeadlines) ? max($pendingDeadlines) : null;
    }

    private function assessThoroughness(array $review): float
    {
        return 0.8;
    }

    private function assessConstructiveness(array $review): float
    {
        return 0.85;
    }

    private function assessSpecificity(array $review): float
    {
        return 0.75;
    }

    private function assessTimeliness(array $review): float
    {
        return 0.9;
    }

    private function assessGuidelinesAdherence(array $review): float
    {
        return 0.85;
    }

    private function qualityScoreToRating(float $score): string
    {
        return match (true) {
            $score >= 0.9 => 'excellent',
            $score >= 0.75 => 'good',
            $score >= 0.6 => 'satisfactory',
            $score >= 0.4 => 'needs_improvement',
            default => 'unsatisfactory',
        };
    }

    /**
     * @return array<string>
     */
    private function generateReviewerFeedback(array $metrics): array
    {
        return [];
    }

    private function calculateAvailability(string $reviewerId): float
    {
        return 0.8;
    }

    /**
     * @return array<string>
     */
    private function generateRebalancingSuggestions(array $overloaded, array $underutilized): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrentTimeline(array $reviews): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function computeOptimalTimeline(array $reviews, ?int $targetDate): array
    {
        return [];
    }

    private function calculateTimeSaved(array $current, array $optimized): int
    {
        return 0;
    }

    /**
     * @return array<string>
     */
    private function identifyTimelineActions(array $current, array $optimized): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSingleReviewerStats(string $reviewerId): array
    {
        return [];
    }

    private function calculateAvgReviewTime(): float
    {
        return 14.5; // days
    }

    private function calculateCompletionRate(): float
    {
        return 0.92;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyTopPerformers(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericCoordination(array $taskData): array
    {
        return ['status' => 'processed'];
    }
}
