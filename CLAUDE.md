# CLAUDE.md - Project Intelligence

## Project Overview

Enhanced Open Journal Systems (OJS) integrated with SKZ (Skin Zone Journal) autonomous agents framework. The system combines OJS academic publishing capabilities with 7 specialized AI agents for automated manuscript processing and editorial workflow management.

## Tech Stack

- **Core Platform**: PHP 7.4+ (Open Journal Systems)
- **AI Agents Framework (Python)**: Python 3.11+ (Flask, PyTorch, Transformers)
- **AI Agents Framework (PHP)**: PHP 8.2+ (Resonance/Swoole)
- **Frontend Dashboards**: React 18+, Node.js 18+
- **Database**: MySQL 5.7+/8.0+, PostgreSQL (agents), Redis (caching)
- **Testing**: PHPUnit, Pytest, Cypress

## Project Structure

```
/                           # OJS Core (PHP)
├── classes/                # OJS PHP classes
├── controllers/            # MVC controllers
├── pages/                  # Page handlers
├── plugins/                # OJS plugins
├── templates/              # Smarty templates
├── lib/pkp/                # PKP shared library (submodule)
├── config.inc.php          # Main OJS configuration
└── skz-integration/        # SKZ Agents Framework
    ├── autonomous-agents-framework/   # Main Python agents
    │   ├── src/                       # Agent source code
    │   ├── tests/                     # Pytest tests
    │   └── requirements.txt
    ├── microservices/                 # Per-agent microservices
    ├── workflow-visualization-dashboard/  # React dashboard
    ├── simulation-dashboard/          # Agent simulation UI
    ├── scripts/                       # Deployment scripts
    └── skin-zone-journal/             # Skin Zone backend
```

## Development Setup

### Prerequisites
```bash
python3 --version  # 3.11+
node --version     # 18+
php --version      # 7.4+
```

### Quick Setup
```bash
# OJS dependencies
composer --working-dir=lib/pkp install

# Agent framework setup
cd skz-integration/autonomous-agents-framework
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Dashboard setup
cd ../workflow-visualization-dashboard
npm install && npm run build
```

### Configuration
1. Copy `config.TEMPLATE.inc.php` to `config.inc.php`
2. Copy `.env.template` to `.env`
3. Set `USE_PROVIDER_IMPLEMENTATIONS=true` in `.env` for production

## Common Commands

### Start Services
```bash
# OJS development server
php -S localhost:8000

# Agent framework
cd skz-integration/autonomous-agents-framework
source venv/bin/activate && python src/main.py

# Dashboards
cd skz-integration/workflow-visualization-dashboard
npm run dev
```

### Testing

**Python agents (pytest)**
```bash
cd skz-integration/autonomous-agents-framework
pytest                      # All tests
pytest -m unit              # Unit tests only
pytest -m integration       # Integration tests
pytest -m "not slow"        # Skip slow tests
```

**PHP/OJS tests**
```bash
lib/pkp/tools/runAllTests.sh
```

**Cypress E2E**
```bash
npx cypress run
```

### Health Check
```bash
./skz-integration/scripts/health-check.sh
```

## Key Configuration Files

| File | Purpose |
|------|---------|
| `config.inc.php` | Main OJS configuration |
| `.env` | Environment variables for agents |
| `skz-integration/.env.template` | Agent service configuration |
| `cypress.json` | Cypress E2E test config |
| `skz-integration/autonomous-agents-framework/pytest.ini` | Pytest configuration |

## The 7 Autonomous Agents

1. **Research Discovery** - INCI database mining, patent analysis
2. **Submission Assistant** - Quality assessment, compliance review
3. **Editorial Orchestration** - Workflow coordination, decision making
4. **Review Coordination** - Reviewer matching, workload management
5. **Content Quality** - Scientific validation, standards enforcement
6. **Publishing Production** - Formatting, distribution, metadata
7. **Analytics & Monitoring** - Performance tracking, insights

## API Endpoints

- OJS API: `http://localhost:8000/api/v1/`
- Agent Framework: `http://localhost:5000/api/v1/agents`
- Skin Zone Journal: `http://localhost:5001/api/`

## Important Conventions

- OJS follows PKP coding standards
- Python code uses Flask patterns with async support
- Agent state managed in PostgreSQL, caching in Redis
- All agent actions are logged for audit trail
- Git LFS used for large files (models, images, JSON > 10MB)

## Environment Variables

Key variables in `.env`:
- `USE_PROVIDER_IMPLEMENTATIONS` - Enable real providers (vs mocks)
- `OJS_API_KEY` - API authentication
- `POSTGRES_DSN` - Agent database connection
- `REDIS_URL` - Cache connection
- `ML_DECISION_MODEL_PATH` - Path to ML models

## Resonance Agents Framework (PHP)

A high-performance PHP implementation of the 7 agents using the Resonance framework (Swoole-based).

### Location
`skz-integration/resonance-agents/`

### Quick Start
```bash
cd skz-integration/resonance-agents
composer install
cp config/config.ini.template config/config.ini
php bin/resonance.php serve
```

### API Endpoints (Resonance)
- HTTP Server: `http://localhost:9501`
- WebSocket: `ws://localhost:9502`
- Health: `GET /health`
- Agents: `GET /api/v1/agents`
- Workflows: `POST /api/v1/workflows`

### Key Files
| File | Purpose |
|------|---------|
| `bin/resonance.php` | Main entry point |
| `config/config.ini` | Resonance configuration |
| `src/Agent/*.php` | 7 agent implementations |
| `src/Controller/*.php` | HTTP API controllers |
| `src/WebSocket/*.php` | Real-time communication |
| `src/Bridge/OJSBridge.php` | OJS integration |

## Debugging Tips

- Agent logs: Check `agent_startup.log`, `health_check.log`
- OJS errors: Check PHP error log and `deployment.log`
- Run provider smoke test: `python skz-integration/scripts/smoke_providers.py`
- Validate production config: `python production_config_validator.py`
- Resonance agents: Check Swoole logs at `http://localhost:9501/health`
