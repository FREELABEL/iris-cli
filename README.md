# IRIS SDK

PHP SDK and CLI for the IRIS AI platform. Build agents, run workflows, manage leads, and ship automations — from your terminal or your codebase.

```bash
composer require iris-ai/sdk
```

---

## Two interfaces, one platform

**The CLI** is built for coding agents. Claude Code, Cursor, Windsurf — any AI assistant that can run shell commands can now create agents, search leads, trigger workflows, and query knowledge bases through IRIS. Your coding agent becomes an orchestrator.

**The PHP SDK** is built for developers. Create multi-step workflows in code, embed agents into Laravel apps, build custom integrations. Full programmatic control over 31 resource modules and 200+ methods.

Both hit the same API. Build in the CLI, ship in PHP. Or the other way around.

---

## Install

```bash
# Via Composer
composer require iris-ai/sdk

# Or one-line installer (includes CLI setup + auth)
curl -fsSL https://heyiris.io/install-iris.sh | bash
```

**Requirements:** PHP 8.1+, Composer

## Configure

```bash
# .env
IRIS_API_KEY=your_api_key
IRIS_USER_ID=your_user_id
IRIS_API_URL=https://apiv2.heyiris.io
```

```php
$iris = new IRIS\SDK\IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => (int) $_ENV['IRIS_USER_ID'],
]);
```

---

## CLI

The CLI mirrors the full SDK. Every resource, every method — accessible from your terminal.

```bash
# Chat with an agent
./bin/iris chat 11 "Analyze our Q4 pipeline"

# Create an agent
./bin/iris sdk:call agents.create name="Support Bot" prompt="Handle customer questions"

# Search leads
./bin/iris sdk:call leads.search search=acme status=Won

# Scrape Instagram comments → create leads on a board
./bin/iris leads:scrape --url=https://www.instagram.com/p/ABC123/ --board=42 --limit=100

# Import pre-scraped usernames as leads
./bin/iris leads:discover @user1,@user2 --board=42 --enrich

# Trigger a workflow
./bin/iris sdk:call workflows.execute agent_id=11 query="Research competitors"

# Upload files to an agent's knowledge base
./bin/iris sdk:call agents.uploadAndAttachFiles 123 /docs/playbook.pdf bloq_id=40

# Evaluate agent performance (7 built-in test scenarios)
./bin/iris eval 387 --json
```

This is where it gets interesting: **any AI coding agent that can execute shell commands can now operate your entire IRIS platform.** Tell Claude Code to "create a support agent, upload the FAQ docs, and test it" — it can do all of that through the CLI.

---

## SDK

### Agents

Create, configure, chat, schedule, and monitor AI agents. 6 built-in templates, 50+ integrations, full lifecycle management.

```php
// Create from template
$agent = $iris->agents->createFromTemplate('customer-support', [
    'name' => 'Support Bot',
]);

// Chat
$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'How do I reset my password?']
], ['bloq_id' => 40]);

echo $response->content;

// Multi-step workflow execution
$workflow = $iris->agents->multiStep($agent->id,
    'Research all open support tickets and draft response templates'
);
```

### Scheduling & Heartbeats

Autonomous agents that work on their own schedule. Create recurring jobs, monitor execution history, and let agents run heartbeat cycles — checking their knowledge base, executing pending tasks, and reporting back without human intervention.

```php
// Configure recurring tasks on an agent
$iris->agents->setSchedule($agent->id, [
    'timezone' => 'America/New_York',
    'recurring_tasks' => [
        ['name' => 'Morning briefing', 'time' => '09:00', 'frequency' => 'daily'],
        ['name' => 'Pipeline review', 'time' => '14:00', 'frequency' => 'weekdays'],
        ['name' => 'Weekly report', 'time' => '17:00', 'day' => 'friday', 'frequency' => 'weekly'],
    ],
]);

// Create a scheduled job directly
$iris->schedules->create([
    'agent_id' => 11,
    'prompt' => 'Review all open leads and update their status',
    'frequency' => 'daily',
    'time' => '08:00',
]);

// Run a job immediately (don't wait for the schedule)
$iris->schedules->run($jobId);

// View execution history and rate results
$history = $iris->schedules->executions($jobId);
$iris->schedules->rateExecution($executionId, 'good', 'Accurate summary');
```

```bash
# CLI — full schedule management
iris schedule status                     # Overview of all schedules
iris schedule list --agent-id=11         # List jobs for an agent
iris schedule create 11                  # Create a new scheduled job
iris schedule run 42                     # Trigger a job immediately
iris schedule history 42                 # View execution history
iris schedule agent-history 11           # All executions for an agent
iris schedule sync 11                    # Sync agent's recurring tasks
```

Heartbeat mode takes this further — agents autonomously gather context from their knowledge base, execute pending tasks, and write results back. The heartbeat engine runs server-side (iris-api) with quality evaluation disabled for local model compatibility.

### Workflows

Multi-step executions with real-time progress and human-in-the-loop approval.

```php
$workflow = $iris->workflows->execute([
    'agent_id' => 11,
    'query' => 'Research competitors and create a comparison report',
]);

// Poll progress
$status = $iris->workflows->getStatus($workflow->id);

// Handle human approval points
if ($status->needsHumanInput()) {
    $iris->workflows->completeTask($status->taskId, [
        'decision' => 'approved',
        'feedback' => 'Looks good, proceed.',
    ]);
}
```

### Leads (CRM)

Full pipeline: contacts, tasks, notes, activities, invoices, deliverables, outreach.

```php
$leads = $iris->leads->search([
    'query' => 'enterprise',
    'status' => 'Negotiation',
    'tags' => ['high-value'],
]);

$iris->leads->tasks(412)->create(['title' => 'Send proposal', 'due_date' => '2026-03-01']);
$iris->leads->notes(412)->create(['content' => 'Discussed pricing on call']);
$iris->leads->deliverables(412)->create([
    'type' => 'link',
    'title' => 'Custom Agent',
    'external_url' => 'https://app.heyiris.io/agent/simple/42',
]);
```

### Lead Discovery (Instagram Scraper)

Scrape Instagram post comments or profile followers and create leads directly on a board.

```bash
# Scrape comments from a post → create leads on board 42
iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42

# With options: limit, dry run, enrichment, browser visible
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 -l 100 --headed
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --dry-run
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --enrich

# Scrape followers instead of comments
iris leads:scrape -u https://www.instagram.com/bravowwhl/ -b 42 --mode=followers

# Import pre-scraped usernames
iris leads:discover @user1,@user2,@user3 --board=42
iris leads:discover ./profiles.txt --board=42 --enrich
```

Each lead gets: `@username` as name, comment text, ISO timestamp, and discovery source as custom fields. Supports batched API calls, crash recovery, and resume (`--resume`).

### Knowledge Base (RAG)

Vector-indexed knowledge. Upload files, index content, semantic search. OpenAI embeddings + Pinecone.

```php
// Upload docs to an agent's knowledge base
$iris->agents->uploadAndAttachFiles($agentId, [
    '/docs/product-guide.pdf',
    '/docs/pricing.csv',
], $bloqId);

// Direct RAG operations
$iris->rag->index('Company policy document content...', [
    'bloq_id' => 40,
    'title' => 'HR Policy 2026',
]);

$results = $iris->rag->query('What is our vacation policy?', ['bloq_id' => 40]);
```

### Integrations

50+ services. Google Drive, Gmail, Calendar, Slack, Discord, Mailjet, Stripe, and more. OAuth flows built in.

```php
$iris->agents->enableIntegration($agentId, 'gmail');
$iris->agents->enableIntegration($agentId, 'google-drive');
$iris->agents->enableIntegration($agentId, 'slack');

// Execute integration functions directly
$iris->integrations->execute('google-drive', 'search_files', [
    'query' => 'Q4 report',
]);
```

---

## All resources

| Resource | CLI | What it does |
|----------|-----|-------------|
| `$iris->agents` | `iris agent:create`, `iris chat` | Create, chat, schedule, monitor AI agents |
| `$iris->schedules` | `iris schedule` | Scheduled jobs, execution history, heartbeats |
| `$iris->workflows` | `iris sdk:call workflows.*` | Multi-step execution with human-in-the-loop |
| `$iris->leads` | `iris leads:scrape`, `iris leads:discover` | CRM — contacts, tasks, notes, invoices, outreach |
| `$iris->bloqs` | `iris bloq:ingest`, `iris bloq:ingestion-status` | Knowledge bases — lists, items, documents |
| `$iris->chat` | `iris chat`, `iris v6:chat` | Real-time conversations with progress tracking |
| `$iris->rag` | `iris sdk:call rag.*` | Vector search, semantic retrieval, file indexing |
| `$iris->integrations` | `iris integrations` | 50+ service connections, OAuth, function execution |
| `$iris->courses` | `iris sdk:call courses.*` | LMS — courses, chapters, progress, enrollment |
| `$iris->voice` | `iris voice` | Voice AI configuration (ElevenLabs, Google TTS) |
| `$iris->phone` | `iris phone` | Phone numbers for voice agents (VAPI, Twilio) |
| `$iris->tools` | `iris tools` | Recruitment, data enrichment, newsletter research |
| `$iris->articles` | `iris sdk:call articles.*` | Generate articles from videos, topics, transcripts |
| `$iris->audio` | `iris sdk:call audio.*` | Merge, crossfade, convert audio (FFMPEG) |
| `$iris->social` | `iris sdk:call social.*` | Publish to Instagram, TikTok, X, LinkedIn |
| `$iris->payments` | `iris payments`, `iris wallet` | Agent wallets, transactions, billing |
| `$iris->products` | `iris sdk:call products.*` | E-commerce catalog, variants, inventory |
| `$iris->pages` | `iris pages` | Composable landing pages |
| `$iris->programs` | `iris sdk:call programs.*` | Membership programs, funnels, enrollment |
| `$iris->marketplace` | `iris marketplace` | Browse and install reusable agent skills |
| `$iris->automations` | `iris automation` | V6 goal-driven agentic automations |
| `$iris->users` | `iris users` | Account management |
| `$iris->profiles` | `iris profile` | User profiles, media library |
| `$iris->services` | `iris sdk:call services.*` | Service offerings and pricing |
| `$iris->usage` | `iris sdk:call usage.*` | Token usage, cost tracking, rate limits |
| `$iris->models` | `iris sdk:call models.*` | Available AI models and capabilities |

---

## Laravel

Native service provider with auto-discovery.

```bash
composer require iris-ai/sdk
```

```php
// .env
IRIS_API_KEY=your_key
IRIS_USER_ID=your_id

// Use the facade anywhere
use IRIS\SDK\Laravel\Facades\IRIS;

$agents = IRIS::agents()->list();
$response = IRIS::agents()->chat(11, [
    ['role' => 'user', 'content' => 'Hello']
]);

// Or inject via DI
public function handle(IRIS\SDK\IRIS $iris)
{
    $leads = $iris->leads->search(['status' => 'Won']);
}
```

---

## Agent evaluation

Built-in test harness. 7 core scenarios. Custom tests. JSON output for CI/CD.

```bash
./bin/iris eval 387              # Run all tests
./bin/iris eval 387 --json       # Machine-readable output
./bin/iris eval 387 --save       # Save results
```

```php
$evaluator = new AgentEvaluator($iris);
$results = $evaluator->runCoreTests(387);
echo $evaluator->generateReport($results);
```

Tests: `basic_conversation`, `web_search_capability`, `market_research`, `personalization`, `complex_reasoning`, `tool_integration`, `error_handling`.

---

## AI models

Provider-agnostic. Switch models without changing agents.

| Provider | Models |
|----------|--------|
| OpenAI | GPT-4o, GPT-4o-mini, GPT-4.1-nano, GPT-5-nano |
| Anthropic | Claude 3.5 Sonnet, Claude 3 Haiku |
| Google | Gemini Pro, Gemini Flash |
| Local | DeepSeek, Llama, Qwen (via Ollama) |

---

## Documentation

| Doc | Description |
|-----|-------------|
| [TECHNICAL.md](TECHNICAL.md) | Full API reference — every method, every parameter |
| [WORKFLOWS.md](WORKFLOWS.md) | Agentic workflows vs traditional automation |
| [SETUP_EXAMPLES.md](SETUP_EXAMPLES.md) | Agent templates and configuration examples |
| [COURSES_API.md](COURSES_API.md) | Learning management system API |

Feed `TECHNICAL.md` to your AI coding assistant and it becomes an IRIS expert.

---

## License

MIT — see [LICENSE](LICENSE).

**Docs:** [heyiris.io](https://heyiris.io) | **Support:** support@heyiris.io
