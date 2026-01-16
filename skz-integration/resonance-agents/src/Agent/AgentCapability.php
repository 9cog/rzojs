<?php

declare(strict_types=1);

namespace SKZ\Agents\Agent;

/**
 * Enum representing the capabilities of autonomous agents
 */
enum AgentCapability: string
{
    // Research Discovery Agent capabilities
    case LITERATURE_SEARCH = 'literature_search';
    case TREND_IDENTIFICATION = 'trend_identification';
    case RESEARCH_GAP_ANALYSIS = 'research_gap_analysis';
    case INCI_DATABASE_MINING = 'inci_database_mining';
    case PATENT_ANALYSIS = 'patent_analysis';

    // Manuscript Analysis Agent capabilities
    case QUALITY_ASSESSMENT = 'quality_assessment';
    case PLAGIARISM_DETECTION = 'plagiarism_detection';
    case FORMATTING_VALIDATION = 'formatting_validation';
    case STATISTICAL_REVIEW = 'statistical_review';
    case MANUSCRIPT_ENHANCEMENT = 'manuscript_enhancement';

    // Peer Review Coordination Agent capabilities
    case REVIEWER_MATCHING = 'reviewer_matching';
    case WORKLOAD_MANAGEMENT = 'workload_management';
    case REVIEW_TRACKING = 'review_tracking';
    case TIMELINE_OPTIMIZATION = 'timeline_optimization';

    // Editorial Decision Agent capabilities
    case EDITORIAL_ORCHESTRATION = 'editorial_orchestration';
    case DECISION_SUPPORT = 'decision_support';
    case CONSENSUS_ANALYSIS = 'consensus_analysis';
    case CONFLICT_RESOLUTION = 'conflict_resolution';

    // Publication Formatting Agent capabilities
    case CONTENT_FORMATTING = 'content_formatting';
    case TYPESETTING = 'typesetting';
    case MULTI_FORMAT_EXPORT = 'multi_format_export';
    case METADATA_MANAGEMENT = 'metadata_management';

    // Quality Assurance Agent capabilities
    case CONTENT_QUALITY = 'content_quality';
    case COMPLIANCE_CHECKING = 'compliance_checking';
    case STANDARDS_VERIFICATION = 'standards_verification';
    case REGULATORY_COMPLIANCE = 'regulatory_compliance';

    // Workflow Orchestration Agent capabilities
    case WORKFLOW_COORDINATION = 'workflow_coordination';
    case AGENT_MANAGEMENT = 'agent_management';
    case PROCESS_OPTIMIZATION = 'process_optimization';
    case ANALYTICS_REPORTING = 'analytics_reporting';

    // Cross-agent capabilities
    case STAKEHOLDER_COMMUNICATION = 'stakeholder_communication';
    case STRATEGIC_PLANNING = 'strategic_planning';

    public function getAgentType(): AgentType
    {
        return match ($this) {
            self::LITERATURE_SEARCH,
            self::TREND_IDENTIFICATION,
            self::RESEARCH_GAP_ANALYSIS,
            self::INCI_DATABASE_MINING,
            self::PATENT_ANALYSIS => AgentType::RESEARCH_DISCOVERY,

            self::QUALITY_ASSESSMENT,
            self::PLAGIARISM_DETECTION,
            self::FORMATTING_VALIDATION,
            self::STATISTICAL_REVIEW,
            self::MANUSCRIPT_ENHANCEMENT => AgentType::MANUSCRIPT_ANALYSIS,

            self::REVIEWER_MATCHING,
            self::WORKLOAD_MANAGEMENT,
            self::REVIEW_TRACKING,
            self::TIMELINE_OPTIMIZATION => AgentType::PEER_REVIEW_COORDINATION,

            self::EDITORIAL_ORCHESTRATION,
            self::DECISION_SUPPORT,
            self::CONSENSUS_ANALYSIS,
            self::CONFLICT_RESOLUTION => AgentType::EDITORIAL_DECISION,

            self::CONTENT_FORMATTING,
            self::TYPESETTING,
            self::MULTI_FORMAT_EXPORT,
            self::METADATA_MANAGEMENT => AgentType::PUBLICATION_FORMATTING,

            self::CONTENT_QUALITY,
            self::COMPLIANCE_CHECKING,
            self::STANDARDS_VERIFICATION,
            self::REGULATORY_COMPLIANCE => AgentType::QUALITY_ASSURANCE,

            self::WORKFLOW_COORDINATION,
            self::AGENT_MANAGEMENT,
            self::PROCESS_OPTIMIZATION,
            self::ANALYTICS_REPORTING,
            self::STAKEHOLDER_COMMUNICATION,
            self::STRATEGIC_PLANNING => AgentType::WORKFLOW_ORCHESTRATION,
        };
    }
}
