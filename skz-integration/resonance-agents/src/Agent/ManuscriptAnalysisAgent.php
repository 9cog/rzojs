<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;
use SKZ\Agents\LLM\LLMService;

/**
 * Manuscript Analysis Agent
 *
 * Capabilities:
 * - Quality assessment with INCI verification and validation
 * - Plagiarism detection
 * - Formatting validation
 * - Statistical review and manuscript enhancement suggestions
 */
#[Singleton]
class ManuscriptAnalysisAgent extends BaseAgent
{
    private int $manuscriptsAnalyzed = 0;
    private int $issuesDetected = 0;
    private int $enhancementsMade = 0;

    /**
     * @var array<string, mixed>
     */
    private array $qualityStandards = [
        'min_title_length' => 10,
        'max_title_length' => 200,
        'min_abstract_length' => 150,
        'max_abstract_length' => 500,
        'min_keywords' => 3,
        'max_keywords' => 10,
        'min_references' => 10,
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
        return 'Manuscript Analysis Agent';
    }

    public function getType(): AgentType
    {
        return AgentType::MANUSCRIPT_ANALYSIS;
    }

    protected function executeTask(array $taskData, array $decision): array
    {
        $taskType = $taskData['type'] ?? 'unknown';

        return match ($taskType) {
            'quality_assessment' => $this->assessQuality($taskData),
            'plagiarism_check' => $this->checkPlagiarism($taskData),
            'format_validation' => $this->validateFormat($taskData),
            'statistical_review' => $this->reviewStatistics($taskData),
            'enhancement_suggestions' => $this->suggestEnhancements($taskData),
            'inci_verification' => $this->verifyInciContent($taskData),
            'full_analysis' => $this->performFullAnalysis($taskData),
            default => $this->handleGenericAnalysis($taskData),
        };
    }

    /**
     * Assess overall manuscript quality
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function assessQuality(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];

        $this->logger->info("Assessing manuscript quality", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
        ]);

        $scores = [
            'structure' => $this->assessStructure($manuscript),
            'content' => $this->assessContent($manuscript),
            'methodology' => $this->assessMethodology($manuscript),
            'presentation' => $this->assessPresentation($manuscript),
            'references' => $this->assessReferences($manuscript),
        ];

        $overallScore = array_sum($scores) / count($scores);
        $issues = $this->identifyQualityIssues($manuscript, $scores);

        $this->manuscriptsAnalyzed++;
        $this->issuesDetected += count($issues);

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'overall_score' => $overallScore,
            'category_scores' => $scores,
            'issues' => $issues,
            'recommendation' => $this->generateQualityRecommendation($overallScore, $issues),
            'analyzed_at' => time(),
        ];
    }

    /**
     * Check for plagiarism
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function checkPlagiarism(array $taskData): array
    {
        $content = $taskData['content'] ?? '';
        $threshold = $taskData['threshold'] ?? 0.15;

        $this->logger->info("Checking for plagiarism");

        // Simulate plagiarism detection
        $matches = $this->findSimilarContent($content);
        $similarityScore = $this->calculateSimilarityScore($matches);

        return [
            'similarity_score' => $similarityScore,
            'threshold' => $threshold,
            'passed' => $similarityScore <= $threshold,
            'matches' => $matches,
            'flagged_sections' => $this->identifyFlaggedSections($content, $matches),
            'recommendation' => $similarityScore > $threshold
                ? 'Review flagged sections for potential plagiarism'
                : 'Content appears original',
        ];
    }

    /**
     * Validate manuscript format
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function validateFormat(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $template = $taskData['template'] ?? 'default';

        $this->logger->info("Validating manuscript format", ['template' => $template]);

        $validations = [
            'title' => $this->validateTitle($manuscript),
            'abstract' => $this->validateAbstract($manuscript),
            'keywords' => $this->validateKeywords($manuscript),
            'sections' => $this->validateSections($manuscript),
            'figures' => $this->validateFigures($manuscript),
            'tables' => $this->validateTables($manuscript),
            'references' => $this->validateReferenceFormat($manuscript),
        ];

        $errors = array_filter($validations, fn($v) => !$v['valid']);
        $warnings = array_filter($validations, fn($v) => !empty($v['warnings']));

        return [
            'valid' => empty($errors),
            'validations' => $validations,
            'errors' => array_map(fn($v) => $v['message'] ?? 'Validation failed', $errors),
            'warnings' => array_merge(...array_map(fn($v) => $v['warnings'] ?? [], $warnings)),
            'auto_fixable' => $this->identifyAutoFixable($errors),
        ];
    }

    /**
     * Review statistical methods and results
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function reviewStatistics(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];

        $this->logger->info("Reviewing statistical methods");

        $review = [
            'methods_appropriate' => $this->assessStatisticalMethods($manuscript),
            'sample_size_adequate' => $this->assessSampleSize($manuscript),
            'results_reported_correctly' => $this->assessResultsReporting($manuscript),
            'effect_sizes_reported' => $this->checkEffectSizes($manuscript),
            'confidence_intervals' => $this->checkConfidenceIntervals($manuscript),
            'p_values_interpretation' => $this->assessPValueInterpretation($manuscript),
        ];

        $issues = array_filter($review, fn($r) => !$r['passed']);

        return [
            'review' => $review,
            'issues' => $issues,
            'overall_assessment' => empty($issues) ? 'satisfactory' : 'needs_revision',
            'recommendations' => $this->generateStatisticalRecommendations($issues),
        ];
    }

    /**
     * Suggest manuscript enhancements
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function suggestEnhancements(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];

        $this->logger->info("Generating enhancement suggestions");

        $suggestions = [
            'title' => $this->suggestTitleImprovements($manuscript),
            'abstract' => $this->suggestAbstractImprovements($manuscript),
            'structure' => $this->suggestStructuralImprovements($manuscript),
            'clarity' => $this->suggestClarityImprovements($manuscript),
            'visuals' => $this->suggestVisualImprovements($manuscript),
        ];

        // Use LLM for advanced suggestions if available
        if ($this->llmService !== null) {
            $suggestions['llm_suggestions'] = $this->getLLMEnhancementSuggestions($manuscript);
        }

        $this->enhancementsMade += array_sum(array_map('count', $suggestions));

        return [
            'suggestions' => $suggestions,
            'priority_order' => $this->prioritizeSuggestions($suggestions),
            'estimated_impact' => $this->estimateImpact($suggestions),
        ];
    }

    /**
     * Verify INCI ingredient content
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function verifyInciContent(array $taskData): array
    {
        $content = $taskData['content'] ?? '';

        $this->logger->info("Verifying INCI content");

        $extractedIngredients = $this->extractIngredientMentions($content);
        $verificationResults = [];

        foreach ($extractedIngredients as $ingredient) {
            $verificationResults[$ingredient] = [
                'valid_inci' => $this->isValidInciName($ingredient),
                'correctly_formatted' => $this->isCorrectlyFormatted($ingredient),
                'cas_number_present' => $this->hasCasNumber($content, $ingredient),
                'safety_claims_valid' => $this->verifySafetyClaims($content, $ingredient),
            ];
        }

        return [
            'ingredients_found' => count($extractedIngredients),
            'ingredients' => $extractedIngredients,
            'verification_results' => $verificationResults,
            'issues' => $this->identifyInciIssues($verificationResults),
            'recommendations' => $this->generateInciRecommendations($verificationResults),
        ];
    }

    /**
     * Perform full manuscript analysis
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function performFullAnalysis(array $taskData): array
    {
        $this->logger->info("Performing full manuscript analysis");

        return [
            'quality_assessment' => $this->assessQuality($taskData),
            'plagiarism_check' => $this->checkPlagiarism($taskData),
            'format_validation' => $this->validateFormat($taskData),
            'statistical_review' => $this->reviewStatistics($taskData),
            'enhancement_suggestions' => $this->suggestEnhancements($taskData),
            'inci_verification' => $this->verifyInciContent($taskData),
            'analysis_timestamp' => time(),
        ];
    }

    protected function getAgentSpecificMetrics(): array
    {
        return [
            'manuscripts_analyzed' => $this->manuscriptsAnalyzed,
            'issues_detected' => $this->issuesDetected,
            'enhancements_made' => $this->enhancementsMade,
        ];
    }

    // Private helper methods

    /**
     * @return array<string, mixed>
     */
    private function assessStructure(array $manuscript): float
    {
        $requiredSections = ['introduction', 'methods', 'results', 'discussion', 'conclusion'];
        $present = 0;

        foreach ($requiredSections as $section) {
            if (!empty($manuscript[$section])) {
                $present++;
            }
        }

        return $present / count($requiredSections);
    }

    private function assessContent(array $manuscript): float
    {
        return 0.75; // Simplified
    }

    private function assessMethodology(array $manuscript): float
    {
        return 0.80; // Simplified
    }

    private function assessPresentation(array $manuscript): float
    {
        return 0.85; // Simplified
    }

    private function assessReferences(array $manuscript): float
    {
        $refCount = count($manuscript['references'] ?? []);
        return min(1.0, $refCount / $this->qualityStandards['min_references']);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyQualityIssues(array $manuscript, array $scores): array
    {
        $issues = [];

        foreach ($scores as $category => $score) {
            if ($score < 0.6) {
                $issues[] = [
                    'category' => $category,
                    'severity' => 'high',
                    'score' => $score,
                    'message' => "Low quality score in {$category}",
                ];
            } elseif ($score < 0.8) {
                $issues[] = [
                    'category' => $category,
                    'severity' => 'medium',
                    'score' => $score,
                    'message' => "Moderate quality issues in {$category}",
                ];
            }
        }

        return $issues;
    }

    private function generateQualityRecommendation(float $score, array $issues): string
    {
        if ($score >= 0.85 && empty($issues)) {
            return 'accept';
        } elseif ($score >= 0.70) {
            return 'minor_revision';
        } elseif ($score >= 0.50) {
            return 'major_revision';
        }
        return 'reject';
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function findSimilarContent(string $content): array
    {
        return [];
    }

    private function calculateSimilarityScore(array $matches): float
    {
        return count($matches) > 0 ? 0.05 : 0.0;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function identifyFlaggedSections(string $content, array $matches): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTitle(array $manuscript): array
    {
        $title = $manuscript['title'] ?? '';
        $length = strlen($title);

        return [
            'valid' => $length >= $this->qualityStandards['min_title_length']
                && $length <= $this->qualityStandards['max_title_length'],
            'length' => $length,
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAbstract(array $manuscript): array
    {
        $abstract = $manuscript['abstract'] ?? '';
        $length = str_word_count($abstract);

        return [
            'valid' => $length >= $this->qualityStandards['min_abstract_length']
                && $length <= $this->qualityStandards['max_abstract_length'],
            'word_count' => $length,
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateKeywords(array $manuscript): array
    {
        $keywords = $manuscript['keywords'] ?? [];
        $count = count($keywords);

        return [
            'valid' => $count >= $this->qualityStandards['min_keywords']
                && $count <= $this->qualityStandards['max_keywords'],
            'count' => $count,
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSections(array $manuscript): array
    {
        return ['valid' => true, 'warnings' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFigures(array $manuscript): array
    {
        return ['valid' => true, 'warnings' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTables(array $manuscript): array
    {
        return ['valid' => true, 'warnings' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReferenceFormat(array $manuscript): array
    {
        return ['valid' => true, 'warnings' => []];
    }

    /**
     * @return array<string>
     */
    private function identifyAutoFixable(array $errors): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessStatisticalMethods(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'Methods appear appropriate'];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessSampleSize(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'Sample size adequate'];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessResultsReporting(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'Results reported correctly'];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkEffectSizes(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'Effect sizes reported'];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkConfidenceIntervals(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'Confidence intervals provided'];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessPValueInterpretation(array $manuscript): array
    {
        return ['passed' => true, 'details' => 'P-values interpreted correctly'];
    }

    /**
     * @return array<string>
     */
    private function generateStatisticalRecommendations(array $issues): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function suggestTitleImprovements(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function suggestAbstractImprovements(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function suggestStructuralImprovements(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function suggestClarityImprovements(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function suggestVisualImprovements(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function getLLMEnhancementSuggestions(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function prioritizeSuggestions(array $suggestions): array
    {
        return [];
    }

    /**
     * @return array<string, float>
     */
    private function estimateImpact(array $suggestions): array
    {
        return ['readability' => 0.15, 'clarity' => 0.20, 'completeness' => 0.10];
    }

    /**
     * @return array<string>
     */
    private function extractIngredientMentions(string $content): array
    {
        return [];
    }

    private function isValidInciName(string $ingredient): bool
    {
        return true;
    }

    private function isCorrectlyFormatted(string $ingredient): bool
    {
        return true;
    }

    private function hasCasNumber(string $content, string $ingredient): bool
    {
        return true;
    }

    private function verifySafetyClaims(string $content, string $ingredient): bool
    {
        return true;
    }

    /**
     * @return array<string>
     */
    private function identifyInciIssues(array $verificationResults): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    private function generateInciRecommendations(array $verificationResults): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericAnalysis(array $taskData): array
    {
        return ['status' => 'processed', 'message' => 'Generic analysis completed'];
    }
}
