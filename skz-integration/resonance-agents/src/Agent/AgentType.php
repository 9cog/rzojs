<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

/**
 * Enum representing the 7 autonomous agent types
 */
enum AgentType: string
{
    case RESEARCH_DISCOVERY = 'research_discovery';
    case MANUSCRIPT_ANALYSIS = 'manuscript_analysis';
    case PEER_REVIEW_COORDINATION = 'peer_review_coordination';
    case EDITORIAL_DECISION = 'editorial_decision';
    case PUBLICATION_FORMATTING = 'publication_formatting';
    case QUALITY_ASSURANCE = 'quality_assurance';
    case WORKFLOW_ORCHESTRATION = 'workflow_orchestration';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::RESEARCH_DISCOVERY => 'Research Discovery Agent',
            self::MANUSCRIPT_ANALYSIS => 'Manuscript Analysis Agent',
            self::PEER_REVIEW_COORDINATION => 'Peer Review Coordination Agent',
            self::EDITORIAL_DECISION => 'Editorial Decision Agent',
            self::PUBLICATION_FORMATTING => 'Publication Formatting Agent',
            self::QUALITY_ASSURANCE => 'Quality Assurance Agent',
            self::WORKFLOW_ORCHESTRATION => 'Workflow Orchestration Agent',
        };
    }

    public function getDefaultPort(): int
    {
        return match ($this) {
            self::RESEARCH_DISCOVERY => 8001,
            self::MANUSCRIPT_ANALYSIS => 8002,
            self::PEER_REVIEW_COORDINATION => 8003,
            self::EDITORIAL_DECISION => 8004,
            self::PUBLICATION_FORMATTING => 8005,
            self::QUALITY_ASSURANCE => 8006,
            self::WORKFLOW_ORCHESTRATION => 8007,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::RESEARCH_DISCOVERY => 'INCI database mining, patent analysis, trend identification, and research gap analysis',
            self::MANUSCRIPT_ANALYSIS => 'Quality assessment, plagiarism detection, formatting validation, and statistical review',
            self::PEER_REVIEW_COORDINATION => 'Reviewer matching, workload management, review tracking, and timeline optimization',
            self::EDITORIAL_DECISION => 'Editorial orchestration, decision support, consensus analysis, and conflict resolution',
            self::PUBLICATION_FORMATTING => 'Content formatting, typesetting, multi-format export, and metadata management',
            self::QUALITY_ASSURANCE => 'Content quality, compliance checking, standards verification, and regulatory compliance',
            self::WORKFLOW_ORCHESTRATION => 'Agent coordination, workflow management, process optimization, and analytics',
        };
    }

    /**
     * @return array<AgentCapability>
     */
    public function getCapabilities(): array
    {
        return match ($this) {
            self::RESEARCH_DISCOVERY => [
                AgentCapability::LITERATURE_SEARCH,
                AgentCapability::TREND_IDENTIFICATION,
                AgentCapability::RESEARCH_GAP_ANALYSIS,
                AgentCapability::INCI_DATABASE_MINING,
                AgentCapability::PATENT_ANALYSIS,
            ],
            self::MANUSCRIPT_ANALYSIS => [
                AgentCapability::QUALITY_ASSESSMENT,
                AgentCapability::PLAGIARISM_DETECTION,
                AgentCapability::FORMATTING_VALIDATION,
                AgentCapability::STATISTICAL_REVIEW,
                AgentCapability::MANUSCRIPT_ENHANCEMENT,
            ],
            self::PEER_REVIEW_COORDINATION => [
                AgentCapability::REVIEWER_MATCHING,
                AgentCapability::WORKLOAD_MANAGEMENT,
                AgentCapability::REVIEW_TRACKING,
                AgentCapability::TIMELINE_OPTIMIZATION,
            ],
            self::EDITORIAL_DECISION => [
                AgentCapability::EDITORIAL_ORCHESTRATION,
                AgentCapability::DECISION_SUPPORT,
                AgentCapability::CONSENSUS_ANALYSIS,
                AgentCapability::CONFLICT_RESOLUTION,
            ],
            self::PUBLICATION_FORMATTING => [
                AgentCapability::CONTENT_FORMATTING,
                AgentCapability::TYPESETTING,
                AgentCapability::MULTI_FORMAT_EXPORT,
                AgentCapability::METADATA_MANAGEMENT,
            ],
            self::QUALITY_ASSURANCE => [
                AgentCapability::CONTENT_QUALITY,
                AgentCapability::COMPLIANCE_CHECKING,
                AgentCapability::STANDARDS_VERIFICATION,
                AgentCapability::REGULATORY_COMPLIANCE,
            ],
            self::WORKFLOW_ORCHESTRATION => [
                AgentCapability::WORKFLOW_COORDINATION,
                AgentCapability::AGENT_MANAGEMENT,
                AgentCapability::PROCESS_OPTIMIZATION,
                AgentCapability::ANALYTICS_REPORTING,
                AgentCapability::STAKEHOLDER_COMMUNICATION,
                AgentCapability::STRATEGIC_PLANNING,
            ],
        };
    }
}
