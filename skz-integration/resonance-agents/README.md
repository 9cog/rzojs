# SKZ Resonance Agents Framework

> **7 Autonomous Agents for Academic Publishing** built on the [Resonance PHP Framework](https://github.com/distantmagic/resonance)

This module provides a high-performance, async PHP implementation of the SKZ autonomous agents architecture using Swoole and the Resonance framework.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SKZ Resonance Agents                         │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  Research   │  │ Manuscript  │  │ Peer Review │             │
│  │  Discovery  │  │  Analysis   │  │ Coordination│             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  Editorial  │  │ Publication │  │   Quality   │             │
│  │  Decision   │  │ Formatting  │  │  Assurance  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│                  ┌─────────────┐                               │
│                  │  Workflow   │                               │
│                  │Orchestration│                               │
│                  └─────────────┘                               │
├─────────────────────────────────────────────────────────────────┤
│  Message Broker  │  Memory Service  │  Decision Engine  │ LLM  │
├─────────────────────────────────────────────────────────────────┤
│                       OJS Bridge                                │
└─────────────────────────────────────────────────────────────────┘
```

## Requirements

- PHP 8.2+
- Swoole extension
- Data Structures extension (ds)
- Redis (optional, for persistent memory)
- llama.cpp server (optional, for LLM features)

## Installation

```bash
# Install dependencies
composer install

# Copy configuration
cp config/config.ini.template config/config.ini

# Edit configuration
nano config/config.ini
```

## Quick Start

```bash
# Start the agents framework
php bin/resonance.php serve

# Or use composer script
composer serve
```

## The 7 Autonomous Agents

| Agent | Port | Description |
|-------|------|-------------|
| **Research Discovery** | 8001 | INCI database mining, patent analysis, trend identification |
| **Manuscript Analysis** | 8002 | Quality assessment, plagiarism detection, statistical review |
| **Peer Review Coordination** | 8003 | Reviewer matching, workload management, timeline optimization |
| **Editorial Decision** | 8004 | Decision support, consensus analysis, conflict resolution |
| **Publication Formatting** | 8005 | Typesetting, multi-format export, metadata generation |
| **Quality Assurance** | 8006 | Compliance checking, regulatory validation, safety assessment |
| **Workflow Orchestration** | 8007 | Agent coordination, process optimization, analytics |

## API Endpoints

### Health & Status

```bash
# Health check
GET /health

# System status
GET /api/v1/system/status
```

### Agents

```bash
# List all agents
GET /api/v1/agents

# Get agent details
GET /api/v1/agents/{agentId}

# Get agent health
GET /api/v1/agents/{agentId}/health

# Execute task on agent
POST /api/v1/agents/{agentId}/task
Content-Type: application/json
{
    "type": "task_type",
    "data": {...}
}
```

### Workflows

```bash
# Start new workflow
POST /api/v1/workflows
Content-Type: application/json
{
    "workflow_type": "new_submission",
    "submission_id": "123",
    "context": {}
}

# Get workflow status
GET /api/v1/workflows/{workflowId}

# List all workflows
GET /api/v1/workflows

# Get analytics
GET /api/v1/analytics?period=day
```

## WebSocket API

Connect to `ws://localhost:9502` for real-time updates.

### Actions

```javascript
// Subscribe to agent events
{"action": "subscribe", "topic": "agent:agent_id"}
{"action": "subscribe", "topic": "agents:all"}

// Send message to agent
{"action": "send_to_agent", "agent_id": "...", "message": {...}}

// Execute task
{"action": "execute_task", "agent_id": "...", "task": {...}}

// Get status
{"action": "get_system_status"}
{"action": "get_agent_status", "agent_id": "..."}
```

## Configuration

Edit `config/config.ini`:

```ini
[http]
host = 0.0.0.0
port = 9501

[websocket]
enabled = true
port = 9502

[redis]
host = localhost
port = 6379

[llm]
enabled = true
host = 127.0.0.1
port = 8089

[ojs]
api_url = http://localhost:8000/api/v1
api_key = your_api_key
```

## Project Structure

```
resonance-agents/
├── bin/
│   └── resonance.php          # Entry point
├── config/
│   └── config.ini             # Configuration
├── src/
│   ├── Agent/                 # Agent implementations
│   │   ├── AgentInterface.php
│   │   ├── BaseAgent.php
│   │   ├── AgentRegistry.php
│   │   ├── ResearchDiscoveryAgent.php
│   │   ├── ManuscriptAnalysisAgent.php
│   │   ├── PeerReviewCoordinationAgent.php
│   │   ├── EditorialDecisionAgent.php
│   │   ├── PublicationFormattingAgent.php
│   │   ├── QualityAssuranceAgent.php
│   │   └── WorkflowOrchestrationAgent.php
│   ├── Controller/            # HTTP Controllers
│   │   ├── AgentController.php
│   │   └── WorkflowController.php
│   ├── Message/               # Inter-agent messaging
│   │   ├── AgentMessage.php
│   │   ├── MessageBroker.php
│   │   └── MessageType.php
│   ├── Service/               # Core services
│   │   ├── MemoryService.php
│   │   └── DecisionEngine.php
│   ├── LLM/                   # LLM integration
│   │   └── LLMService.php
│   ├── WebSocket/             # Real-time communication
│   │   └── AgentWebSocketHandler.php
│   └── Bridge/                # External integrations
│       └── OJSBridge.php
└── tests/                     # PHPUnit tests
```

## Agent Capabilities

### Research Discovery Agent
- `literature_search` - Search scientific databases
- `trend_analysis` - Analyze research trends
- `patent_search` - Search and analyze patents
- `inci_lookup` - Lookup INCI ingredient information
- `research_gap_analysis` - Identify research gaps
- `regulatory_check` - Check regulatory status

### Manuscript Analysis Agent
- `quality_assessment` - Assess manuscript quality
- `plagiarism_check` - Check for plagiarism
- `format_validation` - Validate manuscript format
- `statistical_review` - Review statistical methods
- `enhancement_suggestions` - Suggest improvements
- `inci_verification` - Verify INCI content

### Peer Review Coordination Agent
- `find_reviewers` - Find suitable reviewers
- `assign_reviewer` - Assign reviewer to manuscript
- `track_review` - Track review progress
- `send_reminder` - Send reviewer reminders
- `assess_review_quality` - Assess review quality
- `manage_workload` - Manage reviewer workload

### Editorial Decision Agent
- `make_decision` - Make editorial decision
- `triage_submission` - Triage new submission
- `analyze_consensus` - Analyze reviewer consensus
- `resolve_conflict` - Resolve reviewer conflicts
- `generate_letter` - Generate decision letter

### Publication Formatting Agent
- `format_manuscript` - Format manuscript
- `generate_pdf` - Generate PDF version
- `generate_html` - Generate HTML version
- `generate_xml` - Generate JATS XML
- `format_references` - Format references
- `generate_metadata` - Generate metadata

### Quality Assurance Agent
- `validate_content` - Validate content quality
- `check_compliance` - Check compliance
- `verify_standards` - Verify against standards
- `assess_scientific_quality` - Assess scientific quality
- `check_regulatory` - Check regulatory compliance
- `safety_assessment` - Perform safety assessment

### Workflow Orchestration Agent
- `start_workflow` - Start new workflow
- `coordinate_agents` - Coordinate multiple agents
- `monitor_progress` - Monitor workflow progress
- `generate_analytics` - Generate analytics
- `optimize_process` - Optimize processes
- `handle_alert` - Handle system alerts

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test -- --coverage-html coverage/

# Static analysis
composer analyze
```

## Integration with Python Agents

This PHP implementation is designed to work alongside the existing Python agents framework. Both can run simultaneously and communicate via:

1. **Shared Redis** - For memory and state synchronization
2. **HTTP APIs** - Cross-framework task execution
3. **WebSocket** - Real-time event broadcasting
4. **OJS Database** - Shared data layer

## License

MIT License - See [LICENSE](LICENSE) for details.

## Related Documentation

- [SKZ Integration Strategy](../SKZ_INTEGRATION_STRATEGY.md)
- [Agent Architecture](../autonomous_agent_architecture.md)
- [Python Agents Framework](../autonomous-agents-framework/README.md)
