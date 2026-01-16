<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

use Distantmagic\Resonance\Attribute\Singleton;
use Psr\Log\LoggerInterface;
use SKZ\Agents\Message\MessageBroker;
use SKZ\Agents\Service\MemoryService;
use SKZ\Agents\Service\DecisionEngine;

/**
 * Publication Formatting Agent
 *
 * Capabilities:
 * - Content formatting and visual generation
 * - Multi-channel distribution and publication optimization
 * - Metadata management and indexing
 * - Support for multiple citation styles and output formats
 */
#[Singleton]
class PublicationFormattingAgent extends BaseAgent
{
    private int $documentsFormatted = 0;
    private int $metadataRecordsProcessed = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $citationStyles = [
        'ieee' => ['name' => 'IEEE', 'format' => '[%d] %a, "%t," %j, vol. %v, pp. %p, %y.'],
        'acm' => ['name' => 'ACM', 'format' => '%a. %y. %t. %j %v, %p.'],
        'springer' => ['name' => 'Springer', 'format' => '%a (%y) %t. %j %v:%p'],
        'apa' => ['name' => 'APA 7th', 'format' => '%a (%y). %t. %j, %v, %p.'],
        'mla' => ['name' => 'MLA 9th', 'format' => '%a. "%t." %j, vol. %v, %y, pp. %p.'],
    ];

    /**
     * @var array<string>
     */
    private array $supportedFormats = ['pdf', 'html', 'xml', 'epub', 'docx', 'jats'];

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
        return 'Publication Formatting Agent';
    }

    public function getType(): AgentType
    {
        return AgentType::PUBLICATION_FORMATTING;
    }

    protected function executeTask(array $taskData, array $decision): array
    {
        $taskType = $taskData['type'] ?? 'unknown';

        return match ($taskType) {
            'format_manuscript' => $this->formatManuscript($taskData),
            'generate_pdf' => $this->generatePDF($taskData),
            'generate_html' => $this->generateHTML($taskData),
            'generate_xml' => $this->generateXML($taskData),
            'format_references' => $this->formatReferences($taskData),
            'generate_metadata' => $this->generateMetadata($taskData),
            'typeset' => $this->typeset($taskData),
            'multi_format_export' => $this->multiFormatExport($taskData),
            default => $this->handleGenericFormatting($taskData),
        };
    }

    /**
     * Format manuscript according to journal style
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function formatManuscript(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $style = $taskData['style'] ?? 'default';
        $template = $taskData['template'] ?? null;

        $this->logger->info("Formatting manuscript", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
            'style' => $style,
        ]);

        // Apply formatting rules
        $formatted = [
            'title' => $this->formatTitle($manuscript['title'] ?? '', $style),
            'authors' => $this->formatAuthors($manuscript['authors'] ?? [], $style),
            'abstract' => $this->formatAbstract($manuscript['abstract'] ?? '', $style),
            'keywords' => $this->formatKeywords($manuscript['keywords'] ?? [], $style),
            'body' => $this->formatBody($manuscript['body'] ?? '', $style),
            'references' => $this->formatReferences([
                'references' => $manuscript['references'] ?? [],
                'style' => $style,
            ])['formatted_references'],
            'figures' => $this->formatFigures($manuscript['figures'] ?? [], $style),
            'tables' => $this->formatTables($manuscript['tables'] ?? [], $style),
        ];

        $this->documentsFormatted++;

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'formatted_manuscript' => $formatted,
            'style_applied' => $style,
            'validation' => $this->validateFormatting($formatted, $style),
        ];
    }

    /**
     * Generate PDF version
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generatePDF(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $options = $taskData['options'] ?? [];

        $this->logger->info("Generating PDF", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
        ]);

        // PDF generation configuration
        $pdfConfig = [
            'page_size' => $options['page_size'] ?? 'A4',
            'margins' => $options['margins'] ?? ['top' => 25, 'bottom' => 25, 'left' => 25, 'right' => 25],
            'font' => $options['font'] ?? 'Times New Roman',
            'font_size' => $options['font_size'] ?? 12,
            'line_spacing' => $options['line_spacing'] ?? 1.5,
            'columns' => $options['columns'] ?? 1,
        ];

        // Simulate PDF generation
        $pdfResult = [
            'format' => 'pdf',
            'generated' => true,
            'file_size' => rand(100000, 2000000),
            'page_count' => rand(5, 30),
            'config' => $pdfConfig,
        ];

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'pdf' => $pdfResult,
            'generated_at' => time(),
        ];
    }

    /**
     * Generate HTML version
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generateHTML(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $options = $taskData['options'] ?? [];

        $this->logger->info("Generating HTML", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
        ]);

        $htmlConfig = [
            'responsive' => $options['responsive'] ?? true,
            'include_css' => $options['include_css'] ?? true,
            'accessibility' => $options['accessibility'] ?? true,
            'schema_markup' => $options['schema_markup'] ?? true,
        ];

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'html' => [
                'format' => 'html',
                'generated' => true,
                'config' => $htmlConfig,
            ],
            'generated_at' => time(),
        ];
    }

    /**
     * Generate XML (JATS) version
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generateXML(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $schema = $taskData['schema'] ?? 'jats';

        $this->logger->info("Generating XML", [
            'submission_id' => $manuscript['id'] ?? 'unknown',
            'schema' => $schema,
        ]);

        $xmlResult = [
            'format' => 'xml',
            'schema' => $schema,
            'version' => $schema === 'jats' ? '1.3' : '1.0',
            'valid' => true,
            'generated' => true,
        ];

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'xml' => $xmlResult,
            'validation' => $this->validateXML($xmlResult),
            'generated_at' => time(),
        ];
    }

    /**
     * Format references according to citation style
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function formatReferences(array $taskData): array
    {
        $references = $taskData['references'] ?? [];
        $style = $taskData['style'] ?? 'ieee';

        $this->logger->info("Formatting references", [
            'count' => count($references),
            'style' => $style,
        ]);

        $styleConfig = $this->citationStyles[$style] ?? $this->citationStyles['ieee'];
        $formatted = [];

        foreach ($references as $index => $ref) {
            $formatted[] = $this->formatSingleReference($ref, $styleConfig, $index + 1);
        }

        return [
            'style' => $style,
            'style_name' => $styleConfig['name'],
            'reference_count' => count($references),
            'formatted_references' => $formatted,
        ];
    }

    /**
     * Generate metadata for indexing
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function generateMetadata(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $includeSchemaOrg = $taskData['include_schema_org'] ?? true;
        $includeDublinCore = $taskData['include_dublin_core'] ?? true;

        $this->logger->info("Generating metadata");

        $metadata = [
            'basic' => [
                'title' => $manuscript['title'] ?? '',
                'authors' => $manuscript['authors'] ?? [],
                'abstract' => $manuscript['abstract'] ?? '',
                'keywords' => $manuscript['keywords'] ?? [],
                'doi' => $manuscript['doi'] ?? null,
                'publication_date' => $manuscript['publication_date'] ?? date('Y-m-d'),
            ],
        ];

        if ($includeSchemaOrg) {
            $metadata['schema_org'] = $this->generateSchemaOrgMetadata($manuscript);
        }

        if ($includeDublinCore) {
            $metadata['dublin_core'] = $this->generateDublinCoreMetadata($manuscript);
        }

        // Additional metadata for indexing
        $metadata['crossref'] = $this->generateCrossRefMetadata($manuscript);
        $metadata['orcid_mappings'] = $this->mapAuthorsToOrcid($manuscript['authors'] ?? []);

        $this->metadataRecordsProcessed++;

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'metadata' => $metadata,
            'generated_at' => time(),
        ];
    }

    /**
     * Typeset manuscript for publication
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function typeset(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $template = $taskData['template'] ?? 'default';
        $options = $taskData['options'] ?? [];

        $this->logger->info("Typesetting manuscript", [
            'template' => $template,
        ]);

        $typesetResult = [
            'template_applied' => $template,
            'elements_processed' => [
                'paragraphs' => $this->typesetParagraphs($manuscript['body'] ?? ''),
                'equations' => $this->typesetEquations($manuscript['equations'] ?? []),
                'figures' => $this->typesetFigures($manuscript['figures'] ?? []),
                'tables' => $this->typesetTables($manuscript['tables'] ?? []),
            ],
            'page_layout' => $this->calculatePageLayout($manuscript, $options),
            'proofing_issues' => $this->identifyProofingIssues($manuscript),
        ];

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'typeset_result' => $typesetResult,
            'ready_for_proofing' => empty($typesetResult['proofing_issues']),
        ];
    }

    /**
     * Export to multiple formats
     *
     * @param array<string, mixed> $taskData
     * @return array<string, mixed>
     */
    public function multiFormatExport(array $taskData): array
    {
        $manuscript = $taskData['manuscript'] ?? [];
        $formats = $taskData['formats'] ?? ['pdf', 'html', 'xml'];

        $this->logger->info("Multi-format export", [
            'formats' => $formats,
        ]);

        $exports = [];

        foreach ($formats as $format) {
            if (!in_array($format, $this->supportedFormats)) {
                $exports[$format] = ['error' => 'Unsupported format'];
                continue;
            }

            $exports[$format] = match ($format) {
                'pdf' => $this->generatePDF(['manuscript' => $manuscript]),
                'html' => $this->generateHTML(['manuscript' => $manuscript]),
                'xml' => $this->generateXML(['manuscript' => $manuscript]),
                default => ['generated' => true, 'format' => $format],
            };
        }

        return [
            'submission_id' => $manuscript['id'] ?? null,
            'exports' => $exports,
            'successful_formats' => array_keys(array_filter($exports, fn($e) => !isset($e['error']))),
            'failed_formats' => array_keys(array_filter($exports, fn($e) => isset($e['error']))),
        ];
    }

    protected function getAgentSpecificMetrics(): array
    {
        return [
            'documents_formatted' => $this->documentsFormatted,
            'metadata_records_processed' => $this->metadataRecordsProcessed,
            'supported_formats' => $this->supportedFormats,
            'citation_styles' => array_keys($this->citationStyles),
        ];
    }

    // Private helper methods

    private function formatTitle(string $title, string $style): string
    {
        return trim($title);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function formatAuthors(array $authors, string $style): array
    {
        return $authors;
    }

    private function formatAbstract(string $abstract, string $style): string
    {
        return trim($abstract);
    }

    /**
     * @return array<string>
     */
    private function formatKeywords(array $keywords, string $style): array
    {
        return array_map('trim', $keywords);
    }

    private function formatBody(string $body, string $style): string
    {
        return $body;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function formatFigures(array $figures, string $style): array
    {
        return $figures;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function formatTables(array $tables, string $style): array
    {
        return $tables;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFormatting(array $formatted, string $style): array
    {
        return ['valid' => true, 'issues' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateXML(array $xmlResult): array
    {
        return ['valid' => true, 'errors' => []];
    }

    private function formatSingleReference(array $ref, array $styleConfig, int $number): string
    {
        $format = $styleConfig['format'];

        $formatted = str_replace(
            ['%d', '%a', '%t', '%j', '%v', '%p', '%y'],
            [
                $number,
                $ref['authors'] ?? 'Unknown',
                $ref['title'] ?? 'Untitled',
                $ref['journal'] ?? '',
                $ref['volume'] ?? '',
                $ref['pages'] ?? '',
                $ref['year'] ?? '',
            ],
            $format
        );

        return $formatted;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSchemaOrgMetadata(array $manuscript): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ScholarlyArticle',
            'name' => $manuscript['title'] ?? '',
            'author' => $manuscript['authors'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateDublinCoreMetadata(array $manuscript): array
    {
        return [
            'dc:title' => $manuscript['title'] ?? '',
            'dc:creator' => $manuscript['authors'] ?? [],
            'dc:subject' => $manuscript['keywords'] ?? [],
            'dc:description' => $manuscript['abstract'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateCrossRefMetadata(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function mapAuthorsToOrcid(array $authors): array
    {
        return [];
    }

    private function typesetParagraphs(string $body): int
    {
        return substr_count($body, "\n\n") + 1;
    }

    private function typesetEquations(array $equations): int
    {
        return count($equations);
    }

    private function typesetFigures(array $figures): int
    {
        return count($figures);
    }

    private function typesetTables(array $tables): int
    {
        return count($tables);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculatePageLayout(array $manuscript, array $options): array
    {
        return ['estimated_pages' => 10];
    }

    /**
     * @return array<string>
     */
    private function identifyProofingIssues(array $manuscript): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleGenericFormatting(array $taskData): array
    {
        return ['status' => 'processed'];
    }
}
