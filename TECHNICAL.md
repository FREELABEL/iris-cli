# IRIS PHP SDK

Official PHP SDK for the **IRIS AI Platform** - Build intelligent agents, execute multi-step workflows, and manage leads with comprehensive CRM functionality.

## 🚀 Quick Examples

```bash
# 👤 Create user accounts (auto-generates secure password & phone)
./bin/iris sdk:call users.register email="user@example.com" full_name="John Doe"
./bin/iris sdk:call users.register email="user@example.com" phone="(555) 123-4567" password="CustomPass123!"

# 💬 Chat with AI agents (real-time progress display!)
./bin/iris chat 11 "Hello, what can you do?"
./bin/iris chat 337 "Analyze my leads" --bloq=40

# 🎯 Update lead status and add task
./bin/iris sdk:call leads.update 412 status=Won
./bin/iris sdk:call leads.tasks.create 412 title="Setup delivery meeting"

# 🔍 Search leads with beautiful colored output
./bin/iris sdk:call leads.search search=john bloq_id=40 status=Won

# 📦 Add deliverable
./bin/iris sdk:call leads.deliverables.create 24 type=link title="Trained AI Agent" external_url="https://app.heyiris.io/agent/356" user_id=193

# 📊 Get priority insights
./bin/iris sdk:call leads.aggregation.statistics
./bin/iris sdk:call leads.aggregation.list has_incomplete_tasks=1 sort=priority

# 📝 Generate article from YouTube video
./bin/iris tools article --url="https://www.youtube.com/watch?v=abc123" --length=medium --style=informative

# 📝 Generate article from research notes (NEW)
./bin/iris tools article --source-type=research --content="AI trends: telemedicine up 300%..." --profile-id=9203684

# 📝 Polish a draft article (NEW)
./bin/iris tools article --source-type=draft --file=/path/to/draft.md --edits="Make more casual" --profile-id=9203684

# 📰 Newsletter with multi-modal ingestion (videos + web links + topic)
./bin/iris tools newsletter-research --topic="AI trends" --videos="https://youtube.com/watch?v=abc" --links="https://example.com/article"
./bin/iris tools newsletter-write --selected-option=1 --outline-json="..." --context-json="..."

# ⚖️ Generate legal demand package
./bin/iris tools demand-package --case-id="Richard Ramos" --ai-model=gpt-5-nano

# 🎵 Download YouTube audio as MP3 (320kbps)
./bin/iris tools youtube-audio --url="https://www.youtube.com/watch?v=abc123" --agent-id=11
```

## Installation

```bash
composer require iris-ai/sdk
```

### Requirements
- PHP 8.1+
- Guzzle 7.0+

## CLI Tool

The SDK includes a lightweight CLI for quick access to all SDK features from the command line.

### Setup

Configure the SDK using the `.env` file:

```bash
# Copy the example and edit with your credentials
cp .env.example .env
```

**Required `.env` Configuration:**

```bash
# IRIS SDK Configuration
# ======================
# The SDK uses TWO separate APIs:
# - IRIS API: agents, chat, workflows (iris-api.freelabel.net)
# - FL-API: leads, deliverables, profiles, services (apiv2.heyiris.io)

# API Authentication (same token works for both APIs)
IRIS_API_KEY=your_sdk_key_from_developer_portal
IRIS_USER_ID=your_user_id

# Environment: 'production' or 'local'
IRIS_ENV=production

# ========================================
# Production API URLs
# ========================================
# IRIS API - agents, chat, workflows, bloqs
IRIS_API_URL=https://heyiris.io

# FL-API - leads, deliverables, profiles, services
FL_API_URL=https://apiv2.heyiris.io

# ========================================
# Local Development URLs (when IRIS_ENV=local)
# ========================================
IRIS_LOCAL_URL=https://local.iris.freelabel.net
FL_API_LOCAL_URL=https://local.raichu.freelabel.net

# Optional: OAuth credentials for advanced use
# IRIS_CLIENT_ID=your-oauth-client-id
# IRIS_CLIENT_SECRET=your-oauth-client-secret
```

### ⚠️ Critical: API Routing

The SDK automatically routes requests to the correct API based on the endpoint pattern:

**IRIS API** (`iris-api.freelabel.net`) handles:
- `/iris/*` - Core IRIS functionality
- `/chat/*` - AI chat and workflows
- `/workflows/*` - Multi-step workflows
- `/agents/*` - Agent management
- `/bloqs/*` - Knowledge bases

**FL-API** (`apiv2.heyiris.io`) handles:
- `/leads` - Lead management and CRM
- `/deliverables` - Lead deliverables
- `/profile` and `/profiles` - Profile creation and management (both singular and plural!)
- `/services` - Service offerings
- `/users/*` - User-specific endpoints

**Important:** The HTTP Client checks for endpoint patterns to route correctly. If you're getting "method not supported" errors, verify:
1. The endpoint pattern is included in the routing logic (see `src/Http/Client.php`)
2. Both `/profile` (singular) and `/profiles` (plural) route to FL-API
3. Your `.env` has the correct API URLs for your environment

### Configuration Status

Check your configuration:

```bash
./bin/iris config
```

### Override via CLI

You can override `.env` values using CLI flags:

```bash
./bin/iris chat 11 "Hello!" --api-key=sk_xxx --user-id=123
```

### Simplified Parameter Mapping

The CLI includes intelligent mapping to make commands more intuitive. You can use generic names like `id` which will automatically map to the specific ID required by the method (e.g., `agentId`, `leadId`, `bloqId`):

```bash
# Instead of agentId=337
./bin/iris sdk:call agents.get id=337

# Instead of leadId=53
./bin/iris sdk:call leads.get id=53
```

This mapping works for `id`, `agent`, `lead`, `bloq`, and `user`.

### Environment Switching (Local vs Production)

If your `.env` is set to `IRIS_ENV=local` for development, but you need to run a quick command against the **Production API**, you can override the environment variable directly in your shell command without changing your `.env` file:

```bash
# Force production environment for a single command
IRIS_ENV=production ./bin/iris sdk:call leads.list

# Force local environment
IRIS_ENV=local ./bin/iris chat 11 "Hello local agent"
```

This is the preferred way to interact with live production data while keeping your local development environment intact.

Once configured, use any CLI command:

```bash
./bin/iris chat 11 "Hello!"
./bin/iris sdk:call leads.search search=john bloq_id=40
```

### Usage

The CLI uses a dynamic proxy pattern to access any SDK resource and method:

```bash
# Pattern: iris sdk:call <resource>.<method> [params] [options]

# 🔍 Lead Search & Management
./vendor/bin/iris sdk:call leads.search search=john bloq_id=40
./vendor/bin/iris sdk:call leads.update 412 status=Won
./vendor/bin/iris sdk:call leads.tasks.create 412 title="Setup meeting"
./vendor/bin/iris sdk:call leads.deliverables.list 24
./vendor/bin/iris sdk:call leads.deliverables.create 24 type=link title="AI Agent" external_url="https://app.heyiris.io/agent/356" user_id=193

# 📊 Lead Aggregation & Analytics
./vendor/bin/iris sdk:call leads.aggregation.statistics --json
./vendor/bin/iris sdk:call leads.aggregation.getRecentLeads 10
./vendor/bin/iris sdk:call leads.aggregation.list has_incomplete_tasks=1 sort=priority
./vendor/bin/iris sdk:call leads.aggregation.list status=Won,Negotiation per_page=20

# 🤖 AI Agents
./vendor/bin/iris sdk:call agents.chat agent_id=5 message="Hello"
./vendor/bin/iris sdk:call workflows.execute '{"agent_id":5,"query":"Research"}'

# 📚 Knowledge Base
./vendor/bin/iris sdk:call bloqs.list
./vendor/bin/iris sdk:call rag.query question="vacation policy" topK=5
```

#### ⚠️ Important: String Parameters with Spaces

**Always use quotes for multi-word strings** - this is standard CLI behavior:

```bash
# ✅ CORRECT - Use quotes for content with spaces
./bin/iris sdk:call leads.addNote 518 "This is a multi-word note about the meeting"
./bin/iris sdk:call leads.tasks.create 412 title="Setup delivery meeting" description="Prepare demo materials"

# ❌ WRONG - Without quotes, each word becomes a separate argument
./bin/iris sdk:call leads.addNote 518 This is wrong

# Quote types (all valid)
./bin/iris sdk:call leads.addNote 518 "Double quotes work"
./bin/iris sdk:call leads.addNote 518 'Single quotes work too'
./bin/iris sdk:call leads.addNote 518 "He said \"hello\""  # Escaped quotes

# For long multi-line content, use heredoc or text files
./bin/iris sdk:call leads.addNote 518 "$(cat << 'EOF'
Line 1 of note
Line 2 of note
Line 3 of note
EOF
)"

# Or read from file
./bin/iris sdk:call leads.addNote 518 "$(cat meeting-notes.txt)"
```

**Why quotes are required:**
- Shell interprets spaces as argument separators
- Quotes group words into a single argument
- Standard behavior across all CLI tools (`git commit -m "message"`, `echo "hello world"`, etc.)
- Ensures predictable, type-safe parameter passing

### Output Formats

```bash
# JSON output (for scripting/automation)
iris sdk:call leads.list --json

# Raw output (no formatting)
iris sdk:call leads.get 123 --raw

# Colorful compact view (default) - Beautiful, readable format with emojis and colors
iris sdk:call leads.search search=john bloq_id=40
```

**Compact View Features:**
- 🎨 Color-coded fields (status, tasks, notes)
- 📊 Status badges with icons (✓ Won, ⚡ Negotiation, ✨ New, etc.)
- 🔗 Underlined URLs for easy clicking
- 📝 Smart field selection (only shows relevant data)
- Perfect for large datasets - no more unwieldy tables!

### Parameter Types

The CLI auto-detects parameter types:
- `true`/`false` → boolean
- `123` → integer  
- `12.5` → float
- `null` → null
- `{"key":"val"}` → JSON object
- `[1,2,3]` → JSON array
- `anything else` → string

### For Autonomous Agents

Perfect for programmatic access in autonomous development pipelines:

```bash
#!/bin/bash
# Platform AI Agent - Find high-priority work
LEADS=$(iris sdk:call leads.aggregation.list has_incomplete_tasks=1 --json)

# SDK AI Agent - Get requirements
REQS=$(iris sdk:call leads.aggregation.requirements 123 --json)

# QA Engineer Agent - Monitor stats
STATS=$(iris sdk:call leads.aggregation.statistics --json)

# Process results
echo $LEADS | jq '.[] | select(.priority_score > 50)'
```

### Extensibility

The CLI is a pure proxy - any new SDK resources or methods are automatically available without code changes.

### Recruitment Tools

Generate recruitment search queries and score candidates using AI-powered analysis.

#### List Available Tools

```bash
./bin/iris tools
```

#### Generate Recruitment Queries from Job Description

```bash
# From a PDF file
./bin/iris tools recruitment \
  --file=/path/to/job-description.pdf \
  --location="Austin, TX" \
  --experience=senior

# From text
./bin/iris tools recruitment \
  --job-description="Senior Solutions Engineer with 5+ years SaaS implementation..." \
  --platform=linkedin \
  --location="Austin, TX"

# JSON output for scripting
./bin/iris tools recruitment \
  --file=/path/to/job.pdf \
  --json
```

**Options:**
| Option | Description |
|--------|-------------|
| `--file`, `-f` | Path to PDF or DOCX file containing job description |
| `--job-description`, `-d` | Job description text (alternative to file) |
| `--platform`, `-p` | Target platform: `linkedin`, `github`, `twitter` (default: linkedin) |
| `--location`, `-l` | Target location for candidates |
| `--experience`, `-e` | Experience level: `entry`, `mid`, `senior`, `lead`, `executive` |
| `--json` | Output as JSON for scripting |

**Example Output:**
```
Generating recruitment queries...

Job Title: Senior Solutions Engineer, Insurance

=== Extracted Requirements ===
Must-Have Skills:
  • Client-facing SaaS implementation experience (4+ years)
  • Ownership of deployments from kickoff to go-live
  • Platform configuration for customers
  • Insurance or healthcare-adjacent industry experience

Nice-to-Have Skills:
  • Training, workshop, or enablement session facilitation
  • Ability to translate technical concepts into plain English

Title Keywords:
  Senior Solutions Engineer, Solutions Engineer, Implementation Engineer

Experience: 4+ years

=== Search URLs ===
Primary: Job Title Search:
  https://www.linkedin.com/search/results/people/?keywords=Senior+Solutions+Engineer...

Extended Network Search:
  https://www.linkedin.com/search/results/people/?keywords=Senior+Solutions+Engineer&network=...

=== Boolean Queries ===
Primary Boolean Query:
  ("Senior Solutions Engineer" OR "Implementation Engineer") AND (SaaS implementation OR...)

=== Browser Extraction Script ===
Copy this JavaScript into browser console on LinkedIn search results:
  // LinkedIn Profile Extractor v3.0...

=== Instructions ===
## How to Extract Candidate Profiles
...
```

#### Score Candidates

After extracting candidate profiles using the browser script:

```bash
# Score candidates against job requirements
./bin/iris tools candidate-score \
  --data='[{"name":"John Smith","title":"Solutions Engineer",...}]' \
  --requirements='{"must_have_skills":["SaaS","API"],...}'

# Or via sdk:call
./bin/iris sdk:call tools.scoreCandidates \
  candidate_data='[{"name":"John Smith","title":"Solutions Engineer",...}]' \
  requirements='{"must_have_skills":["SaaS","API"],...}'
```

```php
// PHP SDK usage
$result = $iris->tools->recruitment([
    'job_description_file' => '/path/to/job.pdf',
    'platform' => 'linkedin',
    'location' => 'Austin, TX',
]);

echo "Found " . count($result->searchUrls) . " search URLs\n";
echo "Must-have skills: " . implode(', ', $result->getMustHaveSkills()) . "\n";

// Score extracted candidates
$scoring = $iris->tools->scoreCandidates([
    'candidate_data' => $extractedCandidatesJson,
    'requirements' => $result->requirements,
]);

echo "Strong matches: " . count($scoring->strongMatches) . "\n";
foreach ($scoring->getTopCandidates(5) as $candidate) {
    echo "  {$candidate['rank']}. {$candidate['name']} - {$candidate['overall_score']}%\n";
}
```

#### Full Recruitment Workflow

```bash
# 1. Generate search queries from PDF
./bin/iris tools recruitment --file=/path/to/job.pdf --location="Austin, TX" --json > queries.json

# 2. Open LinkedIn search URLs from queries.json
# 3. Run extraction script in browser console
# 4. Copy extracted JSON data to candidates.json

# 5. Score candidates
./bin/iris tools candidate-score \
  --data="$(cat candidates.json)" \
  --requirements="$(jq '.requirements' queries.json)"
```

**Output includes:**
- Ranked candidate list with scores (0-100%)
- Categorized matches: Strong (80%+), Good (60-79%), Potential (40-59%), Low (<40%)
- Scoring breakdown per candidate (skills, experience, title, location, network)

### Article Generation

Generate articles from multiple source types using AI-powered content generation. The pipeline supports six input modes, each optimized for different content creation workflows.

#### Source Types Overview

| Source Type | Description | Use Case |
|-------------|-------------|----------|
| `video` | YouTube video URL | Convert video content to articles |
| `topic` | Research-based generation | Create articles from AI research |
| `webpage` | Single webpage URL | Transform web content |
| `rss` | RSS feed URL | Synthesize from multiple articles |
| `research-notes` | Raw notes/bullet points | Structure unorganized research |
| `draft` | Existing article draft | Polish and refine drafts |

#### From YouTube Video (Most Common)

```bash
# Generate article from YouTube video
./bin/iris tools article \
  --url="https://www.youtube.com/watch?v=dQw4w9WgXcQ" \
  --length=medium \
  --style=informative

# Dry run (don't publish to Freelabel)
./bin/iris tools article \
  --url="https://www.youtube.com/watch?v=abc123" \
  --length=long \
  --style=analysis \
  --no-publish

# Publish to specific profile
./bin/iris tools article \
  --url="https://www.youtube.com/watch?v=xyz789" \
  --profile-id=9203684 \
  --publish
```

#### From Topic (Research-Based)

```bash
# Generate article from research topic
./bin/iris tools article \
  --topic="The future of AI in healthcare" \
  --source-type=topic \
  --length=long \
  --style=editorial

# Short newsletter style
./bin/iris tools article \
  --topic="Top 10 productivity tips for remote workers" \
  --source-type=topic \
  --length=short \
  --style=newsletter
```

#### From Webpage or RSS Feed

```bash
# Generate from webpage content
./bin/iris tools article \
  --url="https://example.com/blog/interesting-article" \
  --source-type=webpage \
  --length=medium

# Generate from RSS feed
./bin/iris tools article \
  --url="https://example.com/feed.xml" \
  --source-type=rss \
  --length=short
```

#### From Research Notes (NEW)

Transform raw, unstructured research notes into polished, structured articles. This mode applies **heavy AI structuring** - extracting themes, building narrative flow, and creating coherent structure from disorganized content.

```bash
# From inline content
./bin/iris tools article \
  --source-type=research \
  --content="AI trends 2025: - Telemedicine up 300% - AI diagnostics improving - Patient engagement focus" \
  --length=medium \
  --style=informative \
  --profile-id=9203684

# From file (supports .md, .txt, .docx, .pdf)
./bin/iris tools article \
  --source-type=research-notes \
  --file=/path/to/research-notes.md \
  --length=long \
  --profile-id=9203684

# Using alias 'notes'
./bin/iris tools article \
  --source-type=notes \
  --content="Healthcare AI findings: ..." \
  --profile-id=9203684
```

**Source Type Aliases:** `research-notes` (canonical), `research`, `notes`

#### From Draft (NEW)

Polish an existing article draft to publication quality. This mode applies **light editing** - preserving your voice while improving grammar, clarity, and flow. Optionally provide specific editing instructions.

```bash
# Basic draft polishing
./bin/iris tools article \
  --source-type=draft \
  --content="# My Draft Article\n\nThis is my rough article..." \
  --profile-id=9203684

# With editing instructions
./bin/iris tools article \
  --source-type=draft \
  --file=/path/to/draft.md \
  --edits="Make more casual and conversational, add practical examples" \
  --profile-id=9203684

# Save as draft (don't publish)
./bin/iris tools article \
  --source-type=draft \
  --file=/path/to/rough-draft.docx \
  --edits="Strengthen the introduction" \
  --draft \
  --profile-id=9203684
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--url`, `-u` | YouTube URL, webpage URL, or RSS feed URL | - |
| `--topic`, `-t` | Topic for research-based article generation | - |
| `--content` | Inline content for research-notes or draft modes | - |
| `--file`, `-f` | File path (.md, .txt, .docx, .pdf) for research-notes or draft | - |
| `--edits` | Editing instructions for draft mode | - |
| `--source-type`, `-s` | Source type: `video`, `topic`, `webpage`, `rss`, `research-notes`, `draft` | `video` |
| `--length` | Article length: `short`, `medium`, `long` | `medium` |
| `--style` | Writing style: `informative`, `editorial`, `newsletter`, `analysis` | `informative` |
| `--profile-id` | Profile ID for publishing the article | - |
| `--publish` | Publish to Freelabel platform | true |
| `--draft` | Save as draft (unpublished) | false |
| `--no-publish` | Don't publish (dry run mode) | - |
| `--json` | Output as JSON for scripting | - |

**Example Output:**

```
Article Generation
==================

Source Type: video
Source: https://www.youtube.com/watch?v=dQw4w9WgXcQ
Length: medium
Style: informative
Publish: No (dry run)

 Dispatching article generation job...

 [OK] Article generation job dispatched!

The article is being generated in the background.

Job Details:
  Message: Article generation started
  Queue: article-generation
  Source: https://www.youtube.com/watch?v=dQw4w9WgXcQ

Note: Article generation takes 1-3 minutes. Check your dashboard for the result.
```

**How It Works:**

1. **For YouTube videos**: Extracts transcript via SupaData.ai API
2. **For topics**: Performs AI-powered research using web search
3. **For webpages**: Extracts and summarizes content
4. **For RSS feeds**: Synthesizes content from feed items
5. **For research-notes**: Extracts themes and structures raw content into coherent narrative
6. **For draft**: Applies light editing while preserving author voice, with optional guided instructions
7. NeuronAI processes content through specialized pipelines:
   - **Research-notes**: Heavy structuring → theme extraction → narrative building → article
   - **Draft**: Grammar/clarity polish → optional guided edits → publication-ready article
   - **Others**: 4-phase pipeline (Indexer → Editor → Reporter → Publisher)

**PHP SDK Usage:**

```php
// Generate from YouTube video
$result = $iris->articles->generateFromVideo([
    'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'article_length' => 'medium',
    'article_style' => 'informative',
]);

// Generate from topic
$result = $iris->articles->generateFromTopic(
    'The impact of AI on modern education',
    ['article_length' => 'long', 'article_style' => 'analysis']
);

// Generate from research notes (NEW)
$result = $iris->articles->generateFromResearchNotes(
    "AI trends 2025: - Telemedicine up 300% - AI diagnostics improving...",
    [
        'article_length' => 'medium',
        'article_style' => 'informative',
        'profile_id' => 9203684,
    ]
);

// Polish a draft with editing instructions (NEW)
$result = $iris->articles->generateFromDraft(
    "# My Draft Article\n\nThis is my rough article that needs polishing...",
    [
        'editing_instructions' => 'Make more casual, add practical examples',
        'profile_id' => 9203684,
    ]
);

// Generate from any source
$result = $iris->articles->generate([
    'source_type' => 'video',
    'source' => 'https://www.youtube.com/watch?v=abc123',
    'article_length' => 'medium',
    'article_style' => 'informative',
    'profile_id' => 9203684,
    'publish_to_fl' => true,
]);

// Create article directly (skip AI generation)
$article = $iris->articles->create([
    'profile_id' => 9203684,
    'title' => 'My Custom Article',
    'content' => '<p>Article content here...</p>',
]);
```

**Note:** Article generation is an **async operation**. The job is dispatched to a background queue and typically takes 1-3 minutes to complete. Check your dashboard or use webhooks to receive notifications when the article is ready.

### Newsletter Generation (Multi-Modal)

Generate professional newsletters using AI-powered research with **multi-modal ingestion** - combine text topics, YouTube video transcripts, and web content into rich, well-researched newsletters.

This is a **two-step Human-in-the-Loop (HITL)** workflow:
1. **Research**: Gather content from multiple sources and generate 3 outline options
2. **Write**: User selects preferred outline, AI generates the full newsletter

#### Multi-Modal Content Sources

The newsletter tool supports three content input types that can be combined:

| Source Type | Parameter | Description |
|-------------|-----------|-------------|
| **Topic** | `--topic` | Text description of the newsletter subject |
| **Videos** | `--videos` | YouTube URLs for transcript extraction (comma or newline separated) |
| **Links** | `--links` | Web URLs for content scraping (comma or newline separated) |

#### Step 1: Newsletter Research

```bash
# Basic research with topic only
./bin/iris tools newsletter-research \
  --topic="The future of AI in healthcare" \
  --audience="healthcare professionals" \
  --tone=professional

# Multi-modal: Topic + YouTube videos + Web links
./bin/iris tools newsletter-research \
  --topic="Graphic design trends 2025" \
  --videos="https://www.youtube.com/watch?v=m_GoB8SFOeM,https://www.youtube.com/watch?v=2Vcn2bAu2FA" \
  --links="https://designworklife.com/why-texture-matters-in-graphic-design/" \
  --audience="designers" \
  --tone=educational

# Research with different newsletter lengths
./bin/iris tools newsletter-research \
  --topic="Weekly tech roundup" \
  --newsletter-length=brief \
  --json
```

**Research Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--topic`, `-t` | Newsletter topic/description | Required |
| `--videos` | YouTube video URLs (comma or newline separated) | - |
| `--links` | Web URLs to scrape (comma or newline separated) | - |
| `--audience` | Target audience description | `general audience` |
| `--tone` | Writing tone: `professional`, `casual`, `educational`, `thought-leadership` | `professional` |
| `--newsletter-length` | Length: `brief`, `standard`, `detailed` | `standard` |
| `--json` | Output as JSON for scripting | - |

**Example Output:**

```
Newsletter Research
-------------------

 Topic: Graphic design trends 2025
 Videos: 2 video(s)
 Links: 1 link(s)
 Audience: designers
 Tone: educational
 Length: standard

 Videos for transcript extraction:
   1. https://www.youtube.com/watch?v=m_GoB8SFOeM
   2. https://www.youtube.com/watch?v=2Vcn2bAu2FA

 Links for content scraping:
   1. https://designworklife.com/why-texture-matters-in-graphic-design/

 Researching topic and generating outline options...
 Extracting video transcripts (this may take a moment)...
 Scraping web content...

Research Complete
=================

Sources Used:
  Video transcripts: 2
  Web pages scraped: 1
  Web search results: 13
  Total sources: 16

Themes Identified: 7
  1. The Evolution of Graphic Design
  2. Core Principles of Visual Communication
  3. The Strategic Role of Design
  ...

Outline Options:

 Option 1: "Design Trends That Matter: 2025 Edition"
   Approach: News-focused overview of current developments
   Sections: 4 sections covering technology, trends, tools, career outlook

 Option 2: "Mastering Modern Design: A Practical Guide"
   Approach: Educational deep-dive with actionable insights
   Sections: 5 sections with step-by-step guidance

 Option 3: "The Design Revolution: What's Next"
   Approach: Thought leadership perspective on industry shifts
   Sections: 4 sections analyzing future trajectory

✓ Awaiting human input: Select an outline option (1, 2, or 3)
```

#### Step 2: Newsletter Write

After reviewing the outline options, select your preferred option and generate the full newsletter:

```bash
# Generate newsletter from selected outline (option 1)
./bin/iris tools newsletter-write \
  --selected-option=1 \
  --outline-json='[{"option_number":1,"title":"Design Trends","sections":[...]}]' \
  --context-json='{"topic":"Graphic design","audience":"designers"}'

# With customization notes
./bin/iris tools newsletter-write \
  --selected-option=2 \
  --outline-json='...' \
  --context-json='...' \
  --customization="Focus more on practical code examples"

# Send to recipient email
./bin/iris tools newsletter-write \
  --selected-option=1 \
  --outline-json='...' \
  --context-json='...' \
  --recipient-email="team@company.com" \
  --recipient-name="Design Team" \
  --sender-name="Weekly Digest"
```

**Write Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--selected-option` | Outline option number (1, 2, or 3) | Required |
| `--outline-json` | JSON array of outline options from research | Required |
| `--context-json` | JSON context data from research | Required |
| `--customization` | Custom instructions for the writer | - |
| `--recipient-email` | Email to send newsletter to | - |
| `--recipient-name` | Recipient name for personalization | - |
| `--sender-name` | Sender name for the email | - |
| `--lead-id` | Lead ID for CRM tracking | - |
| `--json` | Output as JSON for scripting | - |

**PHP SDK Usage:**

```php
use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => 'your-api-key',
    'user_id' => 193,
]);

// Step 1: Research with multi-modal sources
$researchResult = $iris->tools->newsletterResearch([
    'topic' => 'Graphic design trends 2025',
    'videos' => 'https://www.youtube.com/watch?v=m_GoB8SFOeM,https://www.youtube.com/watch?v=2Vcn2bAu2FA',
    'links' => 'https://designworklife.com/why-texture-matters-in-graphic-design/',
    'audience' => 'designers',
    'tone' => 'educational',
    'newsletter_length' => 'standard',
]);

// Check results
echo "Topic: {$researchResult->topic}\n";
echo "Themes found: " . count($researchResult->themes) . "\n";
echo "Outline options: " . count($researchResult->outlineOptions) . "\n";
echo "Sources used:\n";
print_r($researchResult->sourcesUsed);
// Output: ['video_transcripts' => 2, 'web_pages_scraped' => 1, 'web_search_results' => 13, 'total_sources' => 16]

// Display outline titles
foreach ($researchResult->getOutlineTitles() as $i => $title) {
    echo ($i + 1) . ". $title\n";
}

// Step 2: Prepare write params using helper method
$writeParams = $researchResult->prepareWriteParams(
    selectedOption: 1,  // User's choice
    customizationNotes: 'Focus on practical examples',
    recipientEmail: 'team@company.com',
    recipientName: 'Design Team',
    senderName: 'Weekly Digest'
);

// Step 3: Generate the newsletter
$writeResult = $iris->tools->newsletterWrite($writeParams);
```

**NewsletterResearchResult Methods:**

```php
// Get specific outline by number
$outline = $researchResult->getOutline(1);  // Returns outline option 1

// Get all outline titles as array
$titles = $researchResult->getOutlineTitles();  // ['Title 1', 'Title 2', 'Title 3']

// Get theme names
$themes = $researchResult->getThemeNames();  // ['Theme 1', 'Theme 2', ...]

// Check if awaiting selection
if ($researchResult->isAwaitingSelection()) {
    // Show options to user
}

// Prepare write parameters (helper)
$params = $researchResult->prepareWriteParams(
    selectedOption: 2,
    customizationNotes: 'Make it more casual',
    recipientEmail: 'user@example.com'
);

// Convert to array
$data = $researchResult->toArray();
```

**How It Works:**

1. **Video Transcripts**: YouTube URLs are processed via Supadata.ai API to extract full transcripts
2. **Web Scraping**: Links are scraped via Firecrawl API to extract main content
3. **Web Search**: Tavily API performs additional research on the topic
4. **Theme Analysis**: AI analyzes all sources to identify key themes
5. **Outline Generation**: Three distinct newsletter outlines are created
6. **Human Selection**: User reviews and selects their preferred outline
7. **Newsletter Writing**: AI writes the full newsletter based on selected outline and context

**Note:** Newsletter generation uses a background queue for the write step. Research is synchronous and returns immediately with outline options.

### Legal Demand Package Generation

Generate comprehensive AI-powered legal demand packages for personal injury cases using ServisAI integration. Creates case summaries, medical chronologies, patient details, and settlement demand letters in multiple formats.

#### Generate Demand Package

```bash
# Generate demand package for a case
./bin/iris tools demand-package \
  --case-id="Richard Ramos" \
  --ai-model=gpt-5-nano

# Use different AI model
./bin/iris tools demand-package \
  --case-id="CAS100508" \
  --ai-model=gpt-4o

# Disable cloud upload (local only)
./bin/iris tools demand-package \
  --case-id="John Smith" \
  --no-publish

# Use cached results if available
./bin/iris tools demand-package \
  --case-id="Richard Ramos" \
  --use-cache

# JSON output for automation
./bin/iris tools demand-package \
  --case-id="Richard Ramos" \
  --json
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--case-id`, `-c` | Patient name or case number (e.g., "Richard Ramos", "CAS12345") | **Required** |
| `--ai-model`, `-m` | AI model: `gpt-4o`, `gpt-5-nano`, `claude-3-5-sonnet` | `gpt-5-nano` |
| `--upload-to-gcs` | Upload to Google Cloud Storage (enabled by default) | `true` |
| `--use-cache` | Use cached results if available | `false` |
| `--json` | Output as JSON for scripting | - |

**Example Output:**

```
Generating Legal Demand Package
-------------------------------

 Case ID: Richard Ramos
 AI Model: gpt-5-nano
 Upload to GCS: Yes
 Use Cache: No

 ⏳ Generating demand package via ServisAI...

 [OK] Demand package generated successfully!

Results
-------

 Case ID ........... 8c0d8d1c-98ba-4596-9239-d0d93b7690ac
 Output Type ....... demand_package
 AI Model .......... gpt-5-nano
 Execution Time .... 56.7s
 Total Billing ..... $0.00

Download
--------

 📄 https://storage.googleapis.com/gs-dev-media-assets/demand-packages/case-8c0d8d1c...

Components Generated
--------------------

 ✓ Case Summary
 ✓ Medical Chronology
 ✓ Patient Details
 ✓ Medical Services

Preview (First 500 chars)
--------------------------

 # Demand Package for Settlement
 
 **Case ID:** CAS100508
 **Patient:** Richard Ramos
 **Generated:** December 24, 2025
 
 ---
 
 Executive Summary
 Richard Ramos sustained injuries in an incident on February 17, 2022...
 
 Full length: 24,172 characters
```

**What Gets Generated:**

The demand package tool creates comprehensive legal documentation including:

1. **Executive Summary**: Overview of the case, injuries, and settlement demand
2. **Medical Chronology**: Detailed timeline of all medical treatments and services
3. **Patient Details**: Demographics, contact information, and case metadata
4. **Medical Services**: Itemized list of all treatments with dates and providers
5. **Demand Letter**: AI-drafted settlement demand with liability analysis
6. **Multi-Format Output**: 
   - PDF (print-ready)
   - DOCX (editable)
   - HTML (web-ready)
   - Markdown (source)
   - ZIP bundle (all formats)

**Alternative: Docker Direct Execution**

For development and testing, you can also run the demand package tool directly in the Docker container:

```bash
# Run in fl-iris-api container
docker exec fl-iris-api php test-demand-package.php "Richard Ramos"
```

**PHP SDK Usage:**

```php
// Generate demand package via ServisAI integration
$result = $iris->integrations->execute('servis-ai', 'create_demand_package', [
    'case_id' => 'Richard Ramos',
    'options' => [
        'ai_model' => 'gpt-5-nano',
        'upload_to_gcs' => true,
        'use_cache' => false,
    ],
]);

// Access results
echo "Case ID: {$result['case_id']}\n";
echo "Download URL: {$result['gcs_url']}\n";
echo "Components:\n";
if ($result['components']['summary']) echo "  ✓ Summary\n";
if ($result['components']['chronology']) echo "  ✓ Chronology\n";
if ($result['components']['patient_details']) echo "  ✓ Patient Details\n";
```

**How It Works:**

1. **Case Lookup**: Searches ServisAI system by case ID or patient name (natural language)
2. **Data Retrieval**: Fetches all medical records, treatments, and case details
3. **AI Analysis**: Uses GPT-4o/5-nano/Claude to analyze medical records and draft documents
4. **Document Generation**: Creates comprehensive demand package with all components
5. **Multi-Format Export**: Generates PDF, DOCX, HTML, and Markdown versions
6. **Cloud Upload**: Uploads to Google Cloud Storage and returns download URL
7. **BLOQ Integration**: Creates BLOQ item with all document formats attached

**Note:** Demand package generation is an **async operation** for large cases. It typically takes 30-90 seconds depending on case complexity and number of medical records. The tool runs in the background with real-time progress tracking.

## Quick Start

```php
<?php
use IRIS\SDK\IRIS;

// Initialize the SDK
$iris = new IRIS([
    'api_key' => 'sk_live_xxxxx',
    'user_id' => 193,  // Your user ID
]);

// Search for leads
$leads = $iris->leads->search([
    'search' => 'acme',
    'bloq_id' => 40,
    'status' => 'Won'
]);

// Update lead status
$lead = $iris->leads->update(412, ['status' => 'Won']);

// Add a task
$task = $iris->leads->tasks(412)->create([
    'title' => 'Setup delivery meeting',
    'due_date' => '2025-12-30'
]);

// Add deliverable
$deliverable = $iris->leads->deliverables(24)->create([
    'type' => 'link',
    'title' => 'Trained AI Agent',
    'external_url' => 'https://app.heyiris.io/agent/simple/356',
    'user_id' => 193
]);

// Chat with an agent
$response = $iris->agents->chat('agent_123', [
    ['role' => 'user', 'content' => 'Draft a marketing email']
]);

echo $response->content;
```

## 👤 User Account Creation

Create new user accounts programmatically via the SDK/CLI. Automatically generates secure passwords and phone numbers if not provided.

### CLI Usage

```bash
# Auto-generate password and phone number
./bin/iris sdk:call users.register \
  email="jane@example.com" \
  full_name="Jane Doe"

# Provide custom credentials
./bin/iris sdk:call users.register \
  email="user@example.com" \
  phone="(555) 123-4567" \
  password="SecurePass123!" \
  full_name="John Doe"

# JSON output for automation
./bin/iris sdk:call users.register \
  email="user@example.com" \
  --json
```

**Example Output:**
```
Key                Value                                                    
id                 5134                                                     
email              jane@example.com                                        
phone              (384) 545-7846                                           
user_name          jane                                                   
full_name          Jane Doe                                        
_credentials       {"password":"<auto-generated>","phone":"(384) 545-7846"}
```

### PHP SDK Usage

```php
// Auto-generate password and phone
$user = $iris->users->register([
    'email' => 'jane@example.com',
    'full_name' => 'Jane Doe',
]);

echo "✅ User created: {$user['email']}\n";
echo "   User ID: {$user['id']}\n";
echo "   Username: {$user['user_name']}\n";
echo "   Password: {$user['_credentials']['password']}\n";
echo "   Phone: {$user['_credentials']['phone']}\n";

// Provide custom credentials
$user = $iris->users->register([
    'email' => 'user@example.com',
    'phone' => '(555) 123-4567',
    'password' => 'SecurePass123!',
    'full_name' => 'John Doe',
]);
```

### Bulk User Creation

```bash
#!/bin/bash
# create-team-accounts.sh

USERS=(
  "jane@example.com:Jane Doe"
  "team@example.com:Team Member"
)

for user in "${USERS[@]}"; do
  IFS=':' read -r email name <<< "$user"
  ./bin/iris sdk:call users.register \
    email="$email" \
    full_name="$name" \
    --json >> users-created.json
done
```

### Required Fields

- `email` - Must be unique (validated against existing users)

### Optional Fields

- `phone` - Phone number in format `(555) 123-4567` (auto-generated if not provided)
- `password` - Minimum 8 characters (auto-generated secure password if not provided)
- `full_name` - User's full name

### Auto-Generated Values

- **Password**: 16-character secure password with uppercase, lowercase, numbers, and symbols
- **Phone**: Random US format phone `(XXX) XXX-XXXX`
- **Username**: Automatically derived from email (e.g., `jane@example.com` → `jane`)

### Response Fields

- `id` - User ID
- `email` - User email
- `phone` - Phone number
- `user_name` - Auto-generated username
- `_credentials` - Object containing generated `password` and `phone`
  - **Important**: Save these credentials! They're only returned once.

### Next Steps

After creating a user account:
1. Login at: https://app.heyiris.io/login
2. User may need to activate account via email
3. Complete platform onboarding flow

## Features

### 💬 Real-Time Chat

Chat with AI agents using the V5 workflow system with real-time progress tracking.

#### CLI Chat Command (Recommended)

The dedicated `chat` command provides beautiful progress display and formatted output:

```bash
# Basic chat
./bin/iris chat 11 "Hello, what can you do?"

# Chat with bloq context (for RAG)
./bin/iris chat 337 "Analyze the attached documents" --bloq=40

# JSON output for scripting
./bin/iris chat 11 "Generate a report" --json

# Custom timeout
./bin/iris chat 11 "Long running task" --timeout=600
```

**Example Output:**
```
╭─────────────────────────────────────────────────────────────╮
│ 🤖 Agent #11 (Bloq: 40)                                     │
╰─────────────────────────────────────────────────────────────╯

📤 Sending: "Hello, what can you do?"

⠙ ⏳ Running (2.3s)

✅ Complete!

╭─────────────────────────────────────────────────────────────╮
│ Hello! I'm IRIS AI. I can help you with:                    │
│                                                             │
│ • Lead management and CRM tasks                             │
│ • Content generation and analysis                           │
│ • Integration with Google Drive, Gmail, etc.                │
│ • Workflow automation                                       │
│                                                             │
│ What would you like to work on today?                       │
╰─────────────────────────────────────────────────────────────╯

📊 Tokens: 245 | Time: 2.3s | Model: gpt-4o-mini | Agent: IRIS AI
```

#### PHP SDK Usage

```php
// Simple blocking execution (recommended)
$result = $iris->chat->execute([
    'query' => 'Hello, what can you do?',
    'agentId' => 11,
    'bloqId' => 40,  // Optional: for RAG context
]);

echo $result['summary'];

// With progress callback
$result = $iris->chat->execute([
    'query' => 'Analyze my leads',
    'agentId' => 337,
], function($status) {
    echo "Status: {$status['status']}\n";
});

// Async usage (start + poll manually)
$response = $iris->chat->start([
    'query' => 'Generate a report',
    'agentId' => 11,
]);

$workflowId = $response['workflow_id'];

while (true) {
    $status = $iris->chat->getStatus($workflowId);

    if ($status['status'] === 'completed') {
        echo $status['summary'];
        break;
    }

    if ($status['status'] === 'failed') {
        throw new Exception($status['error']);
    }

    usleep(500000); // 500ms
}
```

#### Human-in-the-Loop (HITL)

Handle workflows that require human approval:

```php
$result = $iris->chat->execute([
    'query' => 'Send email to all leads',
    'agentId' => 11,
]);

// Check if paused for approval
if ($result['status'] === 'paused' && $result['requires_approval']) {
    echo "Approval needed: {$result['pending_approval']['approval_prompt']}\n";

    // Resume with approval
    $iris->chat->resume($result['workflow_id'], [
        'approved' => true,
        'comment' => 'Looks good, proceed!',
    ]);
}
```

#### Conversation History & Summarization

```php
// Get user's chat history
$history = $iris->chat->history([
    'status' => 'completed',
    'per_page' => 20,
]);

// Get workflow statistics
$stats = $iris->chat->stats();
echo "Total: {$stats['total_workflows']}, Success: {$stats['success_rate']}%\n";

// Summarize long conversations to save tokens
$summarized = $iris->chat->summarize($messages, keepRecent: 4, threshold: 20);
```

### 🧠 Persistent Memory & Knowledge Base (Bloqs)

Build long-term memory for your AI agents using Bloqs - intelligent containers that automatically index content for RAG (Retrieval-Augmented Generation).

#### How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│                    BLOQ (Knowledge Container)                    │
├─────────────────────────────────────────────────────────────────┤
│  📁 Lists (Categories)           │  🤖 Agents (AI Assistants)   │
│  ├── 📄 Items (Content)          │  ├── Recruiter Agent         │
│  │   ├── Text/Documents          │  ├── Sales Agent             │
│  │   ├── File Attachments ──────────── (auto-indexed for RAG)  │
│  │   └── Chat History            │  └── Support Agent           │
│  └── Custom Fields               │                              │
└─────────────────────────────────────────────────────────────────┘
                    │
                    ▼ Auto-Vectorized (OpenAI Embeddings)
         ┌─────────────────────────┐
         │   Pinecone Vector DB    │
         │   (Semantic Search)     │
         └─────────────────────────┘
                    │
                    ▼ RAG Context Retrieval
         ┌─────────────────────────┐
         │   Agent Chat Response   │
         │   (Enriched with KB)    │
         └─────────────────────────┘
```

#### Create a Knowledge Base

```php
// Create a bloq (knowledge container)
$kb = $iris->bloqs->create('Customer Support KB', [
    'description' => 'Support documentation and FAQs',
]);

// Create organized lists (categories)
$faqList = $iris->bloqs->lists($kb->id)->create([
    'title' => 'FAQs',
    'type' => 'document',
]);

// Add content (automatically vectorized for RAG search)
$item = $iris->bloqs->items($faqList->id)->create([
    'title' => 'Refund Policy',
    'content' => 'Our refund policy allows returns within 30 days...',
    'description' => 'Customer refund guidelines',
]);

// Upload files (PDF, CSV, TXT auto-extracted and indexed)
$file = $iris->bloqs->uploadFile($kb->id, '/path/to/handbook.pdf', [
    'title' => 'Employee Handbook',
]);
```

**CLI:**
```bash
# Create bloq
./bin/iris sdk:call bloqs.create title="Customer Support KB"

# Add content
./bin/iris sdk:call bloqs.addContent 40 title="Refund Policy" content="Returns within 30 days..."

# Upload file (auto-indexed for RAG)
./bin/iris sdk:call bloqs.uploadFile 40 /path/to/document.pdf
```

#### Assign Agent to Knowledge Base

```php
// Create an agent with bloq as its knowledge base
$agent = $iris->agents->create([
    'name' => 'Support Bot',
    'initial_prompt' => 'You are a helpful customer support agent.',
    'bloq_id' => $kb->id,  // Agent uses this bloq for RAG
    'config' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
    ],
]);

// Attach files directly to agent (also indexed for RAG)
$iris->agents->uploadAndAttachFiles($agent->id, [
    '/path/to/product_catalog.pdf',
    '/path/to/pricing.csv',
], $kb->id);

// Chat - agent automatically retrieves relevant context from KB
$response = $iris->chat->execute([
    'query' => 'What is your refund policy?',
    'agentId' => $agent->id,
    'bloqId' => $kb->id,
]);
// Response is enriched with relevant KB content via RAG
```

**CLI:**
```bash
# Create agent with bloq
./bin/iris sdk:call agents.create name="Support Bot" bloq_id=40

# Upload files to agent knowledge base
./bin/iris sdk:call cloudFiles.uploadForAgent /path/to/data.pdf bloq_id=40

# Chat (uses RAG automatically)
./bin/iris chat 337 "What is your refund policy?" --bloq=40
```

#### Share Knowledge Base (Collaboration)

```php
// Share bloq with team members
$iris->bloqs->share($kb->id, $teammateUserId, 'write');

// Get shared users
$sharedWith = $iris->bloqs->getSharedUsers($kb->id);

// Update permissions
$iris->bloqs->updateSharePermission($kb->id, $teammateUserId, 'admin');

// Remove access
$iris->bloqs->unshare($kb->id, $teammateUserId);
```

**CLI:**
```bash
# Share with teammate
./bin/iris sdk:call bloqs.share 40 user_id=456 permission=write

# List shared users
./bin/iris sdk:call bloqs.getSharedUsers 40
```

#### Public Sharing (External Access)

```php
// Make an item publicly accessible
$result = $iris->bloqs->makeItemPublic($itemId);
echo "Public URL: {$result['public_url']}";

// Revoke public access
$iris->bloqs->makeItemPrivate($itemId);
```

#### Custom Fields for Structured Data

```php
// Configure custom fields for leads/items in this bloq
$iris->bloqs->updateCustomFieldsConfig($kb->id, [
    'fields' => [
        ['id' => 'company', 'label' => 'Company Name', 'type' => 'text', 'required' => true],
        ['id' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
        ['id' => 'service', 'label' => 'Service Type', 'type' => 'select', 'options' => ['Web', 'Mobile', 'AI']],
    ],
]);

// Add single field
$iris->bloqs->addCustomField($kb->id, [
    'id' => 'budget',
    'label' => 'Budget Range',
    'type' => 'select',
    'options' => ['<$5k', '$5k-$10k', '$10k+'],
]);
```

---

### 🤖 AI Agents

Create, configure, and interact with intelligent AI agents.

#### List Agents

```bash
# List all agents (requires client credentials)
./bin/iris sdk:call agents.list

# Search with pagination
./bin/iris sdk:call agents.list search="recruiter" per_page=10 page=1
```

```php
// List all agents
$agents = $iris->agents->list([
    'search' => 'marketing',
    'per_page' => 20,
]);

foreach ($agents as $agent) {
    echo "{$agent->name} (#{$agent->id})\n";
}
```

#### Create an Agent

```bash
# Create via CLI - using simplified config
./bin/iris sdk:call agents.create name="Marketing Assistant" prompt="You are a helpful marketing assistant" model="gpt-4o-mini"
```

```php
// Create an agent
$agent = $iris->agents->create(new AgentConfig(
    name: 'Marketing Assistant',
    prompt: 'You are a helpful marketing assistant specializing in email campaigns.',
    model: 'gpt-4o-mini',
    integrations: ['gmail', 'google-drive'],
));

echo "Created agent #{$agent->id}: {$agent->name}\n";
```

#### Update an Agent

**⚠️ IMPORTANT: Partial Updates**

The `patch()` method updates ONLY the fields you specify without overwriting other data:

```bash
# Update just the prompt (recommended)
./bin/iris sdk:call agents.patch 356 initial_prompt="Updated instructions..."

# Update just the name
./bin/iris sdk:call agents.patch 356 name="New Agent Name"

# Update multiple fields
./bin/iris sdk:call agents.patch 356 \
  initial_prompt="New prompt..." \
  description="Updated description"
```

```php
// RECOMMENDED: Partial update (only changes specified fields)
$agent = $iris->agents->patch(356, [
    'initial_prompt' => 'Focus on positive testimonials...',
]);

// Full update (overwrites ALL fields - use with caution)
$agent = $iris->agents->update(358, [
    'name' => 'Talent Recruiter Agent',
    'initial_prompt' => 'You are an AI recruitment assistant...',
    'config' => [
        'model' => 'gpt-4o-mini-2024-07-18',
        'temperature' => 0.7,
        'maxTokens' => 2048,
    ],
    'settings' => [
        'communicationStyle' => 'professional',
        'responseMode' => 'balanced',
        'functionCalling' => true,
    ],
]);
```

**Why use `patch()` instead of `update()`?**
- `patch()`: Updates only what you specify, keeps everything else
- `update()`: Replaces ALL fields, can accidentally clear data

**Real-world example:**
```bash
# Production setup
export IRIS_API_KEY="your_production_token_from_browser"
export IRIS_USER_ID=193

# Update just the prompt without touching other config
./bin/iris sdk:call agents.patch 356 \
  initial_prompt="Enhanced instructions..."
```

#### Chat with an Agent

```bash
# Single message chat
./bin/iris sdk:call agents.chat 358 message="Analyze this resume: John Doe - 5 years experience..."
```

```php
// Chat with the agent
$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'Write a subject line for our product launch']
]);

echo $response->content;
```

#### Delete an Agent

```bash
# Delete an agent
./bin/iris sdk:call agents.delete 358
```

```php
// Delete an agent
$iris->agents->delete(358);
```

#### Add Knowledge to Agent

```php
// Add knowledge to agent's memory
$iris->agents->addMemory($agent->id, '/path/to/brand-guide.pdf');
```

#### Attach Files to Agent (RAG Knowledge Base)

Upload and attach files to give your agent access to custom training data. These files become part of the agent's knowledge base for RAG (Retrieval-Augmented Generation).

```php
// Method 1: Upload and attach in one step (recommended)
$agent = $iris->agents->uploadAndAttachFiles(335, [
    '/path/to/training_data.csv',
    '/path/to/product_catalog.pdf',
    '/path/to/faq.txt',
], 40);  // 40 is the bloq_id

echo "Agent now has " . count($agent->fileAttachments) . " files\n";

// Method 2: Upload separately, then attach
$attachment = $iris->cloudFiles->uploadForAgent('/path/to/data.csv', 40, [
    'title' => 'Lead Data',
    'description' => 'Training data for lead analysis'
]);
$agent = $iris->agents->addFileAttachments(335, [$attachment]);

// Method 3: Upload multiple files separately
$attachments = $iris->cloudFiles->uploadMultipleForAgent([
    '/path/to/file1.pdf',
    '/path/to/file2.csv',
], 40);
$agent = $iris->agents->addFileAttachments(335, $attachments);
```

**Managing File Attachments:**

```php
// Get current attachments
$files = $iris->agents->getFileAttachments(335);
foreach ($files as $file) {
    echo "{$file['name']} ({$file['type']})\n";
}

// Remove a specific file
$agent = $iris->agents->removeFileAttachment(335, $cloudFileId);

// Replace all attachments
$agent = $iris->agents->setFileAttachments(335, $newAttachments);

// Clear all attachments
$agent = $iris->agents->clearFileAttachments(335);
```

**CLI Usage:**

```bash
# Upload a file for agent attachment
./bin/iris sdk:call cloudFiles.uploadForAgent /path/to/data.csv bloq_id=40

# List files attached to an agent
./bin/iris sdk:call agents.getFileAttachments 335

# Clear all attachments
./bin/iris sdk:call agents.clearFileAttachments 335
```

#### Advanced Agent Configuration

**⚡ Quick Start with Templates**

Create fully-configured agents in seconds using built-in templates:

```php
// Elderly Care Assistant (medication reminders, daily check-ins)
$agent = $iris->agents->createFromTemplate('elderly-care', [
    'name' => 'Care Assistant for Mom',
    'medication_times' => ['08:00', '12:00', '18:00', '22:00'],
    'timezone' => 'America/New_York',
]);

// Customer Support (helpdesk automation)
$agent = $iris->agents->createFromTemplate('customer-support', [
    'name' => 'Support Bot',
    'knowledge_base_id' => 123,
]);

// Sales Assistant (lead management, CRM automation)
$agent = $iris->agents->createFromTemplate('sales-assistant', [
    'name' => 'Sales AI',
]);

// Research Agent (deep research, documentation)
$agent = $iris->agents->createFromTemplate('research-agent', [
    'name' => 'Research Assistant',
]);

// Educational Tutor (personalized learning, homework help)
$agent = $iris->agents->createFromTemplate('educational-tutor', [
    'name' => 'Math Tutor',
    'subject' => 'Mathematics',
    'grade_level' => '8th Grade',
]);

// Leadership Coach (executive coaching, professional development)
$agent = $iris->agents->createFromTemplate('leadership-coach', [
    'name' => 'Executive Coach',
]);
```

**List Available Templates:**

```php
// Get all template names
$templates = $iris->agents->listTemplates();
// → ['elderly-care', 'customer-support', 'sales-assistant', 'research-agent', 'educational-tutor', 'leadership-coach']

// Get template details
$template = $iris->agents->getTemplate('elderly-care');
echo $template->getName();        // "Elderly Care Assistant"
echo $template->getDescription(); // "Compassionate AI assistant for elderly care..."

// View default configuration
$config = $template->getDefaultConfig();
print_r($config);
```

**Custom Configuration (Full Control):**

```php
use IRIS\SDK\Resources\Agents\AgentSettings;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

// Create agent with comprehensive settings
$agent = $iris->agents->createFromConfig([
    'name' => 'Custom Assistant',
    'prompt' => 'You are a helpful assistant...',
    'model' => 'gpt-4o-mini',
    'bloq_id' => 40,
    'settings' => [
        'agentIntegrations' => [
            'gmail' => true,
            'google-calendar' => true,
            'slack' => true,
        ],
        'enabledFunctions' => [
            'manageLeads' => true,
            'deepResearch' => true,
        ],
        'schedule' => [
            'enabled' => true,
            'timezone' => 'America/Los_Angeles',
            'recurring_tasks' => [
                [
                    'time' => '09:00',
                    'frequency' => 'daily',
                    'message' => 'Daily morning briefing',
                    'channels' => ['sms', 'email'],
                ],
                [
                    'time' => '14:00',
                    'frequency' => 'weekly',
                    'day_of_week' => 'monday',
                    'message' => 'Weekly status update',
                    'channels' => ['email'],
                ],
            ],
        ],
        'voiceSettings' => [
            'language' => 'en-US',
            'speaking_rate' => 1.0,
            'pitch' => 0.0,
        ],
        'responseMode' => 'balanced',
        'communicationStyle' => 'professional',
        'memoryPersistence' => true,
        'contextWindow' => 10,
    ],
]);
```

**⚙️ Settings Management**

Get, update, and reset agent settings:

```php
// Get current settings
$settings = $iris->agents->getSettings($agentId);

echo "Integrations: " . implode(', ', array_keys(array_filter($settings->agentIntegrations)));
echo "Functions: " . implode(', ', array_keys(array_filter($settings->enabledFunctions)));
echo "Response Mode: " . $settings->responseMode;
echo "Style: " . $settings->communicationStyle;

// Check if integration is enabled
if ($settings->hasIntegration('gmail')) {
    echo "Gmail is enabled";
}

// Update settings (partial update)
$iris->agents->updateSettings($agentId, [
    'responseMode' => 'creative',
    'communicationStyle' => 'friendly',
    'memoryPersistence' => true,
]);

// Reset to defaults
$iris->agents->resetSettings($agentId);
```

**🔗 Integration Management**

Enable, disable, and test integrations:

```php
// Get all integration statuses
$integrations = $iris->agents->getIntegrations($agentId);
// → ['gmail' => true, 'slack' => false, 'google-drive' => true, ...]

// Enable a single integration
$iris->agents->enableIntegration($agentId, 'slack');

// Disable a single integration
$iris->agents->disableIntegration($agentId, 'gmail');

// Bulk set integrations
$iris->agents->setIntegrations($agentId, [
    'gmail' => true,
    'google-calendar' => true,
    'slack' => true,
    'google-drive' => false,  // Disable this one
]);

// Test integration connectivity
$result = $iris->agents->testIntegration($agentId, 'gmail');
if ($result['connected']) {
    echo "Gmail is connected and working";
} else {
    echo "Error: " . $result['error'];
}
```

**📅 Schedule Management**

Create and manage recurring tasks:

```php
// Get current schedule configuration
$schedule = $iris->agents->getSchedule($agentId);

if ($schedule->enabled) {
    echo "Timezone: {$schedule->timezone}\n";
    echo "Tasks: " . count($schedule->recurringTasks) . "\n";
    
    foreach ($schedule->recurringTasks as $task) {
        echo "- {$task['time']} ({$task['frequency']}): {$task['message']}\n";
    }
}

// Update schedule
$iris->agents->updateSchedule($agentId, [
    'enabled' => true,
    'timezone' => 'America/New_York',
    'recurring_tasks' => [
        [
            'time' => '08:00',
            'frequency' => 'daily',
            'message' => 'Morning medication reminder',
            'channels' => ['sms', 'voice'],
        ],
        [
            'time' => '20:00',
            'frequency' => 'daily',
            'message' => 'Evening check-in',
            'channels' => ['sms'],
        ],
    ],
]);

// Using helper classes for type safety
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

$scheduleConfig = new AgentScheduleConfig([
    'enabled' => true,
    'timezone' => 'UTC',
]);

// Add medication reminders
$scheduleConfig->addRecurringTask([
    'time' => '09:00',
    'frequency' => 'daily',
    'message' => 'Take morning medication',
    'channels' => ['sms'],
]);

// Or use helper methods
$scheduleConfig->medicationReminders(
    ['08:00', '14:00', '20:00'],
    ['sms', 'voice']
);

$scheduleConfig->dailyCheckIn('21:00', ['sms']);

// Apply to agent
$iris->agents->updateSchedule($agentId, $scheduleConfig->toArray());
```

**🎨 Using AgentSettings Helper Class**

The `AgentSettings` class provides a fluent interface for building complex configurations:

```php
use IRIS\SDK\Resources\Agents\AgentSettings;

$settings = new AgentSettings();

// Configure integrations
$settings->enableIntegration('gmail')
         ->enableIntegration('google-calendar')
         ->enableIntegration('slack');

// Enable functions
$settings->enableFunction('manageLeads')
         ->enableFunction('deepResearch');

// Configure voice
$settings->withVoiceSettings([
    'language' => 'en-US',
    'speaking_rate' => 0.9,  // Slower for clarity
]);

// Add schedule
$settings->withSchedule([
    'enabled' => true,
    'timezone' => 'America/New_York',
    'recurring_tasks' => [
        [
            'time' => '09:00',
            'frequency' => 'daily',
            'message' => 'Daily briefing',
            'channels' => ['email'],
        ],
    ],
]);

// Set communication style
$settings->communicationStyle = 'warm';
$settings->responseMode = 'balanced';
$settings->memoryPersistence = true;

// Create agent with these settings
$agent = $iris->agents->createFromConfig([
    'name' => 'My Assistant',
    'prompt' => 'You are helpful...',
    'settings' => $settings->toArray(),
]);

// Or update existing agent
$iris->agents->updateSettings($agentId, $settings->toArray());
```

**📋 Complete Configuration Example**

Here's a complete example creating an elderly care assistant:

```php
use IRIS\SDK\Resources\Agents\AgentSettings;
use IRIS\SDK\Resources\Agents\AgentScheduleConfig;

// Build schedule
$schedule = new AgentScheduleConfig([
    'enabled' => true,
    'timezone' => 'America/New_York',
]);

$schedule->medicationReminders(['08:00', '12:00', '18:00', '22:00']);
$schedule->dailyCheckIn('20:00');

// Build settings
$settings = new AgentSettings();
$settings->enableIntegration('gmail')
         ->enableIntegration('google-calendar')
         ->withVoiceSettings(['speaking_rate' => 0.9])
         ->withSchedule($schedule->toArray());

$settings->communicationStyle = 'warm';
$settings->responseMode = 'balanced';
$settings->memoryPersistence = true;

// Create agent
$agent = $iris->agents->createFromConfig([
    'name' => 'Care Assistant for Mom',
    'prompt' => 'You are a compassionate care assistant helping with daily reminders...',
    'model' => 'gpt-4o-mini',
    'bloq_id' => 40,
    'settings' => $settings->toArray(),
]);

echo "Created agent #{$agent->id}: {$agent->name}\n";
echo "Schedule: " . count($schedule->recurringTasks) . " recurring tasks\n";
```

**🔧 Custom Templates**

Create your own reusable templates:

```php
use IRIS\SDK\Resources\Agents\AgentTemplate;

class MyCustomTemplate extends AgentTemplate
{
    public function getName(): string
    {
        return 'My Custom Template';
    }

    public function getDescription(): string
    {
        return 'Custom agent for specific use case';
    }

    public function getDefaultConfig(): array
    {
        return [
            'name' => 'Custom Agent',
            'prompt' => 'Default prompt...',
            'model' => 'gpt-4o-mini',
            'settings' => [
                'agentIntegrations' => ['gmail' => true],
                'responseMode' => 'balanced',
            ],
        ];
    }

    public function build(array $customization = []): array
    {
        $config = $this->getDefaultConfig();
        
        // Merge customization
        if (isset($customization['name'])) {
            $config['name'] = $customization['name'];
        }
        
        return $config;
    }

    public function validate(array $customization): array
    {
        $errors = [];
        
        if (empty($customization['name'])) {
            $errors[] = 'Name is required';
        }
        
        return $errors;
    }
}

// Register and use
$iris->agents->registerTemplate('my-custom', new MyCustomTemplate());

$agent = $iris->agents->createFromTemplate('my-custom', [
    'name' => 'My Agent',
]);
```

📖 [Complete Agent Configuration Guide](AGENT_CONFIGURATION_GUIDE.md)

#### Get Agent URLs (Embed/Share)

Get shareable URLs for your agents. These URLs allow users to interact with your agent directly at **app.heyiris.io**.

```bash
# Get all URLs for an agent
./bin/iris sdk:call agents.getUrls 11

# Get just the simple URL
./bin/iris sdk:call agents.getUrl 11
```

```php
// Get all URLs
$urls = $iris->agents->getUrls(11);
echo $urls['simple'];   // https://app.heyiris.io/agent/simple/11?bloq=40
echo $urls['embed'];    // Same as simple (alias)
echo $urls['public'];   // https://app.heyiris.io/agent/my-slug (if public)

// Get just the embed/share URL
$url = $iris->agents->getUrl(11);
// → https://app.heyiris.io/agent/simple/11?bloq=40

// Or from an agent instance
$agent = $iris->agents->get(11);
$url = $agent->getSimpleUrl();
$url = $agent->getEmbedUrl();  // alias
$allUrls = $agent->getUrls();

// Custom base URL (for self-hosted or local dev)
$url = $agent->getSimpleUrl('https://local.elon.freelabel.net');
// → https://local.elon.freelabel.net/agent/simple/11?bloq=40
```

**URL Types:**
- **simple/embed**: Direct link to chat with the agent (`/agent/simple/{id}?bloq={bloqId}`)
- **public**: Slug-based URL if agent is public (`/agent/{slug}`)

### 🔄 V5 Multi-Step Workflows

Execute complex workflows with real-time progress tracking and human-in-the-loop support.

```php
// Execute a workflow
$workflow = $iris->workflows->execute([
    'agent_id' => 'research_agent',
    'query' => 'Research competitors in the CRM space and create a comparison report',
]);

// Track progress in real-time
foreach ($workflow->steps() as $step) {
    echo "[{$step->progress}%] {$step->description}\n";

    if ($step->isCompleted()) {
        echo "  Result: " . $step->getResultString() . "\n";
    }
}

// Handle human-in-the-loop approval
if ($workflow->needsHumanInput()) {
    $task = $workflow->pendingTask;
    echo "Approval needed: {$task->description}\n";

    // Approve and continue
    $workflow->approve('Looks good, proceed with the report.');
}

// Get final result
$result = $workflow->result();
echo $result->content;

// Access generated files
foreach ($result->getFileUrls() as $url) {
    echo "File: {$url}\n";
}
```

### 📚 Document Management (Bloqs)

Organize content with projects, lists, and items.

```php
// Create a knowledge base
$kb = $iris->bloqs->create('Company Knowledge Base', [
    'description' => 'Internal documentation and policies',
]);

// Upload documents
$iris->bloqs->uploadFile($kb->id, '/path/to/handbook.pdf', [
    'title' => 'Employee Handbook',
    'tags' => ['hr', 'policy'],
]);

// Create organized lists
$list = $iris->bloqs->lists($kb->id)->create([
    'title' => 'Q1 Marketing Materials',
    'type' => 'folder',
]);

// Add items to lists
$iris->bloqs->items($list->id)->create([
    'title' => 'Campaign Brief',
    'content' => 'Campaign details...',
]);
```

### 🔍 RAG (Retrieval-Augmented Generation)

Query your knowledge base with semantic search.

```php
// Index content
$iris->rag->index(
    content: 'Our vacation policy allows for 20 days of PTO...',
    metadata: [
        'bloq_id' => $kb->id,
        'type' => 'policy',
        'title' => 'Vacation Policy',
    ]
);

// Query with semantic search
$results = $iris->rag->query(
    question: 'What is our vacation policy?',
    filters: ['bloq_id' => $kb->id],
    topK: 3
);

foreach ($results->documents as $doc) {
    echo "Score: {$doc->score}\n";
    echo "Content: {$doc->content}\n\n";
}
```

### 👤 Lead Management

Comprehensive CRM functionality for managing contacts, outreach, and sales pipelines.

#### Search & Filter Leads

```php
// Advanced search with filters
$results = $iris->leads->search([
    'search' => 'john',
    'bloq_id' => 40,
    'status' => 'Won',
    'per_page' => 50,
    'sort' => 'updated_at',
    'order' => 'desc',
    'include_notes' => true,
    'include_events' => true
]);

foreach ($results['data'] as $lead) {
    echo "{$lead['nickname']} - {$lead['status']} - {$lead['note_count']} notes\n";
}
```

**CLI Search:**
```bash
# Search by name with bloq filter
iris sdk:call leads.search search=john bloq_id=40

# Get Won deals with notes
iris sdk:call leads.search status=Won include_notes=true per_page=20

# Beautiful colored output:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  #24 │ Rodney Mayo │ ✓ Won
  🔑 id: 24
  👤 nickname: Rodney Mayo
  📊 status: ✓ Won
  🏷️ lead_type: unknown
  📝 note_count: 7
  ✅ tasks_count: 2
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

#### Update Lead Status

```php
// Update lead status
$lead = $iris->leads->update(412, [
    'status' => 'Won',
    'price_bid' => 5000
]);

echo "Updated {$lead->name} to {$lead->status}\n";
```

**CLI Update:**
```bash
# Change status to Won
iris sdk:call leads.update 412 status=Won

# Update multiple fields
iris sdk:call leads.update 412 status=Negotiation price_bid=5000
```

#### Manage Tasks

```php
// Create a task
$task = $iris->leads->tasks(412)->create([
    'title' => 'Setup delivery meeting',
    'description' => 'Schedule video call to walk through deliverables',
    'due_date' => '2025-12-30',
    'status' => 'pending'
]);

// List all tasks
$tasks = $iris->leads->tasks(412)->all();
foreach ($tasks as $task) {
    echo "- {$task->title} ({$task->status})\n";
}

// Update task status
$iris->leads->tasks(412)->update($task->id, [
    'status' => 'completed'
]);
```

**CLI Tasks:**
```bash
# Create a task
iris sdk:call leads.tasks.create 412 title="Setup delivery meeting"

# Add task with details
iris sdk:call leads.tasks.create 412 title="Send proposal" description="Draft and send pricing proposal" due_date="2025-12-30"

# List tasks
iris sdk:call leads.tasks.all 412

# Mark task complete
iris sdk:call leads.tasks.update 412 5 status=completed
```

#### Deliverables Management

```php
// List deliverables for a lead
$deliverables = $iris->leads->deliverables(24)->list();
foreach ($deliverables as $item) {
    echo "{$item['title']} - {$item['url']}\n";
}

// Create link deliverable
$deliverable = $iris->leads->deliverables(24)->create([
    'type' => 'link',
    'title' => 'Trained AI Agent',
    'external_url' => 'https://app.heyiris.io/agent/simple/356?bloq=203',
    'user_id' => 193
]);

// Upload file deliverable
$deliverable = $iris->leads->deliverables(24)->uploadFile(
    '/path/to/report.pdf',
    ['title' => 'Q4 Analytics Report']
);

// Update deliverable
$iris->leads->deliverables(24)->update($deliverable['id'], [
    'title' => 'Updated Report Title'
]);

// Preview email before sending (AI-generated)
$preview = $iris->leads->deliverables(16)->previewEmail([
    'deliverable_ids' => [203],
    'message_mode' => 'ai',
    'subject' => 'Your deliverables are ready',
    'include_project_context' => true,
]);
echo "Preview:\n{$preview['body']}\n";

// Send email with AI content
$result = $iris->leads->deliverables(16)->send([
    'deliverable_ids' => [203],
    'message_mode' => 'ai',
    'subject' => 'Your deliverables are ready',
    'recipient_emails' => ['mike@greenleaf.co'],
    'include_project_context' => true,
]);

// Or generate and send in one step
$result = $iris->leads->deliverables(16)->generateAndSend(
    [203, 204],
    ['subject' => 'Your project is complete!']
);
```

**CLI Deliverables:**
```bash
# List all deliverables
iris sdk:call leads.deliverables.list 24

# Add agent link
iris sdk:call leads.deliverables.create 24 type=link title="Trained AI Agent" external_url="https://app.heyiris.io/agent/simple/356?bloq=203" user_id=193

# Preview email
iris sdk:call leads.deliverables.previewEmail 16 deliverable_ids='[203]' message_mode=ai

# Send with AI content
iris sdk:call leads.deliverables.send 16 deliverable_ids='[203]' message_mode=ai recipient_emails='["mike@greenleaf.co"]'

# Delete deliverable
iris sdk:call leads.deliverables.delete 24 333
```

#### Invoice Management

Create and manage invoices for leads.

```php
// List invoices
$invoices = $iris->leads->invoices(16)->list();

// Create invoice from lead pricing
$invoice = $iris->leads->invoices(16)->create([
    'price' => 25000,  // Amount in cents ($250.00)
    'description' => 'AI Agent Development - Phase 1',
]);

echo "Invoice #{$invoice['id']} created\n";
echo "Payment link: {$invoice['payment_url']}\n";

// Send invoice to lead
$result = $iris->leads->invoices(16)->send($invoice['id'], [
    'subject' => 'Invoice for AI Agent Development',
    'message' => 'Please find your invoice attached.',
]);

// Mark as paid
$iris->leads->invoices(16)->markPaid($invoice['id'], [
    'payment_method' => 'stripe',
    'transaction_id' => 'ch_xxxxx',
]);

// Void/cancel an invoice
$iris->leads->invoices(16)->void($invoice['id'], 'Client cancelled project');
```

**CLI Invoices:**
```bash
# List invoices for a lead
iris sdk:call leads.invoices.list 16

# Create invoice
iris sdk:call leads.invoices.create 16 price=25000 description="AI Agent Development"

# Send invoice
iris sdk:call leads.invoices.send 16 123 subject="Your invoice"

# Mark as paid
iris sdk:call leads.invoices.markPaid 16 123
```

#### Stripe Payment History

Get payment history from Stripe for a lead based on their email address.

```php
$payments = $iris->leads->stripePayments(16);

echo "Customer found: " . ($payments['has_stripe_customer'] ? 'Yes' : 'No') . "\n";
echo "Total paid: $" . number_format($payments['total_paid'] / 100, 2) . "\n";

// List recent payments
foreach ($payments['payments'] as $payment) {
    echo "- {$payment['description']}: \${$payment['amount'] / 100} ({$payment['status']})\n";
}

// List Stripe invoices
foreach ($payments['invoices'] as $invoice) {
    echo "Invoice #{$invoice['number']}: \${$invoice['amount_due'] / 100}\n";
}
```

**CLI:**
```bash
# Get Stripe payment history for a lead
iris sdk:call leads.stripePayments 16
```

#### Lead Aggregation & Priority Analysis

```php
// Get comprehensive statistics
$stats = $iris->leads->aggregation()->statistics();
echo "Total leads: {$stats['total_leads']}\n";
echo "Incomplete tasks: {$stats['total_incomplete_tasks']}\n";

// Get priority leads with tasks
$leads = $iris->leads->aggregation()->list([
    'has_incomplete_tasks' => true,
    'sort' => 'priority',
    'order' => 'desc',
    'per_page' => 10
]);

// Get recently updated leads
$recent = $iris->leads->aggregation()->getRecentLeads(10);

// Get specific lead with context
$lead = $iris->leads->aggregation()->get(24);
echo "Priority score: {$lead['priority_score']}\n";
echo "Tasks: {$lead['incomplete_tasks_count']}/{$lead['total_tasks_count']}\n";
```

**CLI Aggregation:**
```bash
# Statistics dashboard
iris sdk:call leads.aggregation.statistics

# High priority leads with tasks
iris sdk:call leads.aggregation.list has_incomplete_tasks=1 sort=priority order=desc

# Recently updated leads
iris sdk:call leads.aggregation.getRecentLeads 10 sort=updated_at

# Filter by status (comma-separated)
iris sdk:call leads.aggregation.list status=Won,Negotiation per_page=20
```

#### Basic Lead Operations

```php
// Create a lead
$lead = $iris->leads->create([
    'name' => 'John Smith',
    'email' => 'john@acme.com',
    'company' => 'Acme Corp',
    'tags' => ['enterprise', 'hot'],
]);

// Generate AI-powered outreach
$email = $iris->leads->generateResponse($lead->id,
    'Write a personalized introduction email based on their company profile'
);

// Track activity
$iris->leads->activities($lead->id)->create([
    'type' => 'email_sent',
    'content' => $email,
    'metadata' => ['campaign' => 'Q1_outreach'],
]);
```

#### RAG-Enhanced Email Generation

The SDK uses **Retrieval-Augmented Generation (RAG)** to enhance outreach emails with relevant context from lead notes. This ensures AI-generated emails are personalized and reference actual interactions, not just generic templates.

**How It Works:**

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     RAG-Enhanced Email Generation                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   📝 Lead Notes                    🔍 Semantic Search                     │
│   ├── Call notes                       ↓                                 │
│   ├── Meeting summaries    ──▶   Pinecone Vector DB                     │
│   ├── Email history               (OpenAI Embeddings)                    │
│   └── Interaction logs                 ↓                                 │
│                                   Relevant Context                       │
│                                        ↓                                 │
│                               ✉️ Personalized Email                      │
│                                  (GPT-4o-mini)                           │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

**Index Lead Notes for Search:**

```php
// Index all notes for a lead (run once or when notes are updated)
$result = $iris->leads->outreach()->indexNotes($leadId);
echo "Indexed {$result['indexed_count']} notes\n";

// Notes are vectorized using OpenAI text-embedding-3-small (1024 dimensions)
// and stored in Pinecone for semantic search
```

**Search Notes Semantically:**

```php
// Find relevant notes for a specific topic
$notes = $iris->leads->outreach()->searchNotes($leadId, [
    'query' => 'pricing discussion',
    'limit' => 5,  // Top K results
]);

foreach ($notes as $note) {
    echo "Score: {$note['similarity_score']} - {$note['content']}\n";
}
```

**Generate Email with RAG Context:**

```php
// The generateEmail method automatically uses RAG when notes are indexed
$email = $iris->leads->outreach()->generateEmail($leadId, [
    'email_type' => 'follow_up',
    'email_prompt' => 'Reference our last meeting about the AI integration project',
    'use_rag' => true,  // Default: true when notes exist
]);

// The email content now includes context from relevant notes:
// - Recent interactions mentioned
// - Project details referenced
// - Previous discussions incorporated
echo "Subject: {$email['subject']}\n";
echo "Body: {$email['body']}\n";

// Check what context was used
echo "RAG notes used: {$email['rag_notes_count']}\n";
echo "Recent notes used: {$email['recent_notes_count']}\n";
```

**CLI Usage:**

```bash
# Index notes for a lead
./bin/iris sdk:call leads.outreach.indexNotes 518

# Search notes semantically
./bin/iris sdk:call leads.outreach.searchNotes 518 query="pricing" limit=5

# Generate email (uses RAG automatically)
./bin/iris sdk:call leads.outreach.generateEmail 518 email_type=follow_up email_prompt="Reference our pricing discussion"
```

**Why RAG Matters:**

| Without RAG | With RAG |
|-------------|----------|
| Generic follow-up email | "Following up on our discussion about the AI agent integration..." |
| No context from history | "As we discussed in our call last week regarding the $5k budget..." |
| Template-style content | References actual projects, names, and details from notes |

**Best Practices:**
- Index notes when they're created or updated for real-time search
- Use specific prompts to guide what context to retrieve
- The system uses dynamic score thresholds (0.20-0.40) optimized for `text-embedding-3-small`
- Notes are automatically filtered by lead_id for data isolation

---

#### Lead Enrichment

Automatically enrich leads with additional data from external sources.

```php
// Enrich a lead (async process)
$result = $iris->leads->enrich(24, ['auto_update' => false]);
echo "Enrichment started: {$result['status']}\n";

// Check enrichment status
$status = $iris->leads->enrichmentStatus(24);
echo "Status: {$status['status']}\n";  // 'pending', 'processing', 'completed', 'failed'

if ($status['status'] === 'completed') {
    echo "Found data: " . json_encode($status['data']) . "\n";
}
```

**CLI Enrichment:**
```bash
# Start enrichment
iris sdk:call leads.enrich 24 auto_update=false

# Check status
iris sdk:call leads.enrichmentStatus 24
```

#### ReAct AI Enrichment (Advanced)

The ReAct (Reasoning + Acting) enrichment pattern provides intelligent, goal-driven lead enrichment. It uses AI reasoning to select optimal search strategies and includes free native HTTP scraping before using paid APIs.

**How ReAct Works:**

```
┌─────────────────────────────────────────────────────────────────┐
│                    ReAct Loop (max 3-5 iterations)              │
├─────────────────────────────────────────────────────────────────┤
│  1. OBSERVE: Analyze current state - what do we have/need?      │
│  2. THINK: AI reasons about best search strategy                │
│  3. ACT: Execute search (native HTTP → Tavily → FireCrawl)      │
│  4. EVALUATE: Did we achieve the goal? Stop or continue.        │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- **Goal-driven**: Specify what you need (email, phone, or all)
- **Cost-optimized**: Native HTTP scraping tries first (free)
- **AI-powered**: Smart strategy selection based on context
- **Early exit**: Stops as soon as goal is achieved

```php
// ReAct enrichment with email goal
$result = $iris->leads->enrichReAct(510, [
    'goal' => 'email',          // 'email', 'phone', or 'all'
    'max_iterations' => 3,      // 1-5 iterations
    'use_native_http' => true   // Try free scraping first
]);

if ($result['goal_achieved']) {
    echo "Found emails!\n";
    foreach ($result['found_contacts']['emails'] as $email) {
        echo "  - {$email}\n";
    }
}

// View AI reasoning
foreach ($result['reasoning'] as $thought) {
    echo "AI: {$thought}\n";
}

// Apply confirmed data to lead
if (!empty($result['found_contacts']['emails'])) {
    $iris->leads->applyEnrichment(510, [
        'email' => $result['found_contacts']['emails'][0],
        'company' => $result['found_contacts']['company'],
        'linkedin_url' => $result['found_contacts']['linkedin_url']
    ]);
}
```

**CLI ReAct Enrichment:**

```bash
# Find email using ReAct pattern
./bin/iris sdk:call leads.enrichReAct 510 goal=email max_iterations=3 use_native_http=true

# Find all contact info
./bin/iris sdk:call leads.enrichReAct 510 goal=all

# Apply confirmed data
./bin/iris sdk:call leads.applyEnrichment 510 email="john@example.com" company="Acme Corp"
```

**Response Structure:**

```json
{
    "success": true,
    "lead_id": 510,
    "found_contacts": {
        "emails": ["john@coffee.com", "info@coffee.com"],
        "phones": ["512-555-1234", "(512) 555-5678"],
        "company": "Jo's Coffee",
        "website": "https://joscoffee.com",
        "linkedin_url": "https://linkedin.com/company/joscoffee",
        "address": "123 Main St, Austin, TX"
    },
    "goal": "email",
    "goal_achieved": true,
    "iterations": 2,
    "reasoning": [
        "Lead has no email. Starting with general web search.",
        "Found website. Trying native HTTP scrape on contact page.",
        "Email found! Goal achieved."
    ],
    "sources": ["https://joscoffee.com/contact"]
}
```

**Best Practices:**
- Use `goal=email` for faster results when you only need email
- Set `use_native_http=true` (default) to minimize API costs
- Keep `max_iterations` at 3 unless you need exhaustive search
- Always review results before applying with `applyEnrichment()`

#### AI-Powered Lead Creation

Create leads from natural language descriptions using AI parsing.

```php
// Parse a freeform lead description
$parsed = $iris->leads->parseDescription(
    'David Park, freelance consultant, david.park.consulting@gmail.com, tech innovation',
    40  // bloq_id
);

echo "Parsed name: {$parsed['lead']['first_name']} {$parsed['lead']['last_name']}\n";
echo "Email: {$parsed['lead']['email']}\n";
echo "Tags: " . implode(', ', $parsed['lead']['tags']) . "\n";

// Create lead from description in one step (recommended)
$lead = $iris->leads->createFromDescription(
    'Sarah Chen, startup founder, sarah@techventure.io, AI enthusiast in San Francisco',
    40,  // bloq_id
    ['lifecycle_stage' => 'New']
);

echo "Created lead #{$lead->id}: {$lead->name}\n";

// Bulk create from multiple descriptions
$results = $iris->leads->bulkCreateFromDescriptions([
    'John Doe, developer, john@example.com',
    'Jane Smith, designer, jane@design.co, creative',
    'Bob Wilson, CTO at TechCorp, bob@techcorp.io',
], 40);

echo "Created {$results['successful']} leads\n";
```

**Helper Methods:**

```php
// Get available tags for a bloq
$tags = $iris->leads->getAvailableTags(40);
foreach ($tags as $tag) {
    echo "- {$tag['name']}\n";
}

// Get lifecycle stages
$stages = $iris->leads->getLifecycleStages();
// Returns: ['New', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost']

// Check for duplicate before creating
$duplicate = $iris->leads->checkDuplicate('david@example.com', 40);
if ($duplicate['exists']) {
    echo "Lead already exists: #{$duplicate['lead_id']}\n";
} else {
    // Safe to create
    $lead = $iris->leads->createFromDescription($description, 40);
}
```

**CLI Usage:**

```bash
# Parse a lead description (preview without creating)
./bin/iris sdk:call leads.parseDescription description="John Smith, CEO at Acme, john@acme.com" bloq_id=40

# Create lead from description
./bin/iris sdk:call leads.createFromDescription description="Sarah Chen, sarah@example.com, AI consultant" bloq_id=40

# Get available tags for a bloq
./bin/iris sdk:call leads.getAvailableTags 40

# Get lifecycle stages
./bin/iris sdk:call leads.getLifecycleStages

# Check for duplicate
./bin/iris sdk:call leads.checkDuplicate email="john@acme.com" bloq_id=40
```

### 🔎 Lead Discovery (Instagram Scraper)

Scrape Instagram post comments or profile followers and automatically create leads on a board. Uses Playwright browser automation with authenticated Instagram sessions.

#### CLI — `leads:scrape`

The primary interface. Scrapes Instagram, creates leads with comment text and timestamps as custom fields.

```bash
# Basic: scrape comments from a post, create leads on board 42
iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42

# With limit
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 -l 100

# Dry run first (scrape only, no API calls)
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --dry-run

# Show browser window
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --headed

# Scrape followers instead of comments
iris leads:scrape -u https://www.instagram.com/bravowwhl/ -b 42 --mode=followers -l 50

# With auto-enrichment (Instagram profile + web search + AI synthesis)
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --enrich

# Resume an interrupted run
iris leads:scrape -u https://www.instagram.com/p/DOgSXrCju2y/ -b 42 --resume

# Full options
iris leads:scrape \
  --url=https://www.instagram.com/p/DOgSXrCju2y/ \
  --board=42 \
  --limit=200 \
  --mode=comments \
  --label="NYC Post Campaign" \
  --batch-size=10 \
  --scroll-delay=3000 \
  --headed
```

**All flags:**

| Flag | Short | Default | Description |
|------|-------|---------|-------------|
| `--url` | `-u` | required | Instagram post or profile URL |
| `--board` | `-b` | required | Board/bloq ID to add leads to |
| `--limit` | `-l` | `50` | Max leads to create |
| `--mode` | `-m` | `comments` | Discovery mode: `comments`, `followers`, `profiles` |
| `--ig-account` | | `heyiris.io` | Instagram session cookies account |
| `--dry-run` | | | Scrape only — no API calls |
| `--enrich` | | | Auto-enrich leads after creation |
| `--label` | | | Campaign label for this run |
| `--batch-size` | | `5` | API batch concurrency |
| `--scroll-delay` | | `2000` | ms between scroll attempts |
| `--resume` | | | Resume a previously interrupted run |
| `--headed` | | | Show browser window (default: headless) |

#### Data Mapping

Each discovered profile creates a lead with:

| Lead Field | Source |
|-----------|--------|
| `name` | `@username` |
| `contact_info.instagram` | `username` |
| `custom_fields.comment` | Comment text from the post |
| `custom_fields.time_posted` | ISO timestamp of the comment |
| `custom_fields.discovery_source` | `comment on <post_url>` |

#### CLI — `leads:discover`

Import pre-scraped usernames (from a file or comma-separated list) into a board.

```bash
# From comma-separated usernames
iris leads:discover @user1,@user2,@user3 --board=42

# From a file (one username per line)
iris leads:discover ./discovered-profiles.txt --board=42

# With enrichment
iris leads:discover @creator1,@creator2 --board=42 --enrich

# Dry run
iris leads:discover @user1,@user2 --board=42 --dry-run

# With tag
iris leads:discover @user1,@user2 --board=42 --tag=creators
```

#### Typical Workflow

1. **Create a board** in the IRIS UI → get the board ID (visible in the URL, e.g. `/bloq/42`)
2. **Invite your client** to the board via the UI (they'll see leads in real-time)
3. **Run the scraper**: `iris leads:scrape -u <post_url> -b 42 -l 50`
4. Leads appear on the board with username, comment, timestamp, and source

#### Scalability

| Comments | Scrape Time | API Time (batch 5) | Total |
|----------|-------------|---------------------|-------|
| 50 | ~15s | ~5s | ~20s |
| 200 | ~40s | ~20s | ~1m |
| 500 | ~1.5m | ~50s | ~2.5m |
| 1,000 | ~3m | ~1.5m | ~5m |

Features: batched API calls (configurable concurrency), crash recovery via JSON progress files, resume capability, 3-layer deduplication (local set → pre-flight API check → backend duplicate detection).

#### Architecture

The scraper is built on Playwright with a provider/adapter pattern:

- **`InstagramCommentsProvider`** — scrapes comments using structural DOM selectors anchored on `a[href*="/c/"]` comment permalinks
- **`InstagramFollowersProvider`** — scrapes follower lists from profile pages
- **`InstagramProfilesProvider`** — extracts profile metadata (bio, followers, etc.)
- **`LeadgenApiClient`** — HTTP client with batched creation and pre-flight dedup
- **`ProgressManager`** — JSON file persistence for crash recovery and resume

### � Profile & Services Management

Create and manage user profiles with service offerings. Profiles live at public URLs on the FreeLABEL network:

- **Primary URL**: `freelabel.net/username` (redirects to production)
- **Production**: `the.freelabel.net/username`
- **Local Dev**: `local.elon.freelabel.net/username`

#### Create a Profile

```php
// Create a profile
$profile = $iris->profiles->create([
    'username' => 'nsgbillz',
    'name' => 'NSG Billz',
    'bio' => 'Credit repair specialist and videographer',
    'city' => 'Dallas',
    'state' => 'Texas',
    'instagram' => 'nsgbillz',
    'user_id' => 193,
]);

echo "Profile created: {$profile['id']}\n";

// Get public URL
$url = $profile->getPublicUrl();
echo "URL: {$url}\n";  // https://freelabel.net/nsg-billz

// Or specify environment
$prodUrl = $profile->getPublicUrl('https://the.freelabel.net');
$localUrl = $profile->getPublicUrl('https://local.elon.freelabel.net');
```

**CLI:**
```bash
# Create profile
./bin/iris sdk:call profiles.create \
  username=nsgbillz \
  name='NSG Billz' \
  bio='Credit repair specialist and videographer' \
  city='Dallas' \
  state='Texas' \
  instagram=nsgbillz \
  user_id=193

# Profile will be accessible at: https://freelabel.net/nsg-billz
```

#### Create Services for a Profile

Services define offerings that appear on the profile page with pricing.

```php
// Create a service
$service = $iris->services->create([
    'profile_id' => 9203684,
    'title' => 'Credit Repair Services',
    'description' => 'Professional credit restoration services',
    'price' => 500,
    'price_max' => 2500,  // Optional: for price ranges
    'user_id' => 193,
]);

echo "Service created: #{$service['id']}\n";
```

**CLI:**
```bash
# Create service with price range
./bin/iris sdk:call services.create \
  profile_id=9203684 \
  title='Credit Repair Services' \
  description='Professional credit restoration services' \
  price=500 \
  price_max=2500 \
  user_id=193

# Create service with fixed price
./bin/iris sdk:call services.create \
  profile_id=9203684 \
  title='Video Production' \
  description='Professional video editing and production' \
  price=1000 \
  user_id=193
```

#### List Services for a Profile

```php
// Get all services for a profile
$services = $iris->services->list(['profile_id' => 9203684]);

foreach ($services as $service) {
    $priceDisplay = $service['price_max'] 
        ? "\${$service['price']}-\${$service['price_max']}"
        : "\${$service['price']}";
    
    echo "{$service['title']}: {$priceDisplay}\n";
}
```

**CLI:**
```bash
# List services for a profile
./bin/iris sdk:call services.list profile_id=9203684
```

**⚠️ Important:** The `profile_id` filter is critical. Without it, `services.list` returns ALL services across the entire platform. Always specify `profile_id` when querying services for a specific profile.

#### Update Profile

```php
// Update profile fields
$profile = $iris->profiles->update(9203684, [
    'bio' => 'Updated bio text',
    'website_url' => 'https://example.com',
]);
```

#### Update Service

```php
// Update service pricing or details
$service = $iris->services->update(245, [
    'price' => 600,
    'price_max' => 3000,
    'description' => 'Updated service description',
]);
```

### �🔌 Integrations (17+ Services)

Connect your agents to external services with 17+ pre-built integrations. Perfect for users coming from N8N - use our integrations directly or build custom workflows.

#### Available Integrations

| Category | Integrations | Functions |
|----------|--------------|-----------|
| **Google Suite** | Drive, Gmail, Calendar | Search files, send emails, manage events |
| **Communication** | Slack, Discord | Send messages, manage channels |
| **Email Marketing** | Mailjet, Mailchimp, SMTP | Campaigns, transactional emails |
| **Content** | YouTube, YouTube Transcript | Search, analyze, extract transcripts |
| **AI Services** | ElevenLabs, Google Gemini | Voice synthesis, image/video generation |
| **Business** | Servis.ai (15+ functions) | CRM, appointments, case management |
| **Documents** | Case Reviewer, Gamma | AI document review, presentations |

#### Connect an Integration

```php
// Get OAuth URL for user authorization
$oauthUrl = $iris->integrations->getOAuthUrl('google-drive');
// Redirect user to $oauthUrl, they'll be redirected back after auth

// List connected integrations
$connected = $iris->integrations->enabled();

// Test an integration
$result = $iris->integrations->test($integrationId);
echo $result->success ? "Connected!" : "Error: {$result->error}";
```

#### Execute Integration Functions

```php
// Search Google Drive
$files = $iris->integrations->execute('google-drive', 'search_files', [
    'query' => 'Q1 Report',
    'limit' => 10,
]);

// Send an email via Gmail
$iris->integrations->execute('gmail', 'send_email', [
    'to' => 'client@example.com',
    'subject' => 'Your AI Agent is Ready',
    'body' => 'Your custom AI agent has been deployed...',
]);

// Post to Slack
$iris->integrations->execute('slack', 'send_message', [
    'channel' => '#general',
    'message' => 'New lead received: John Smith',
]);

// Get YouTube transcript
$transcript = $iris->integrations->execute('youtube-transcript', 'get_transcript', [
    'video_url' => 'https://youtube.com/watch?v=...',
]);

// Get calendar availability
$slots = $iris->integrations->execute('google-calendar', 'check_availability', [
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-07',
]);
```

**CLI:**
```bash
# List available integrations
./bin/iris sdk:call integrations.types

# Get OAuth URL
./bin/iris sdk:call integrations.getOAuthUrl google-drive

# Execute a function
./bin/iris sdk:call integrations.execute type=google-drive function=search_files params='{"query":"report"}'

# List integration functions
./bin/iris sdk:call integrations.getFunctions gmail
```

#### Integration Metadata & AI Context

```php
// Get all integration metadata (for building UI)
$metadata = $iris->integrations->getMetadata();

// Get AI context (function definitions for agents)
$aiContext = $iris->integrations->getAiContext();
// Agents use this to know which integrations they can call
```

#### MCP (Model Context Protocol) Support

For Claude and other MCP-compatible AI systems:

```php
// List MCP-compatible integrations
$mcpIntegrations = $iris->integrations->mcpIntegrations();

// Get functions for an MCP service
$functions = $iris->integrations->getFunctions('gmail');

// Execute via MCP protocol
$result = $iris->integrations->executeFunction('gmail', 'read_emails', [
    'limit' => 10,
    'unread_only' => true,
]);
```

---

### 🔄 N8N Workflow Compatibility

**For teams using N8N**: IRIS integrations work alongside your existing N8N workflows. You can:

1. **Use IRIS integrations directly** - No need to rebuild, we support the same services
2. **Trigger IRIS agents from N8N** - Call our API endpoints in N8N HTTP nodes
3. **Export data to IRIS** - Push workflow results to IRIS for AI processing

```bash
# Example: N8N HTTP Request node calling IRIS API
POST https://api.heyiris.io/api/chat/start
Authorization: Bearer your-api-key
{
    "query": "Process this lead data",
    "agentId": 11,
    "bloqId": 40
}
```

Your agents live in the cloud at **app.heyiris.io** and can be accessed from any workflow tool.

---

### 📞 Voice AI (VAPI)

Enable AI-powered phone calls with your agents using VAPI integration.

#### List & Configure Phone Numbers

```php
// List all phone numbers
$numbers = $iris->vapi->phoneNumbers();
foreach ($numbers as $number) {
    echo "{$number['phone_number']} - Agent: {$number['agent_id']}\n";
}

// Configure a phone number to use an agent
$iris->vapi->configurePhoneNumber('dd3905f2-08d6-4dc2-a50f-f0c937ada251', [
    'agent_id' => 335,
    'use_dynamic_assistant' => true,
    'allow_override' => true,
]);

// Disconnect phone from agent
$iris->vapi->disconnectPhoneNumber('dd3905f2-...');
```

#### Sync Agent with VAPI

```php
// Sync agent settings to VAPI assistant
$result = $iris->vapi->syncAssistant(335);
echo "VAPI Assistant ID: {$result['assistant_id']}\n";

// Update voice settings
$iris->vapi->updateVoice(335, [
    'voice' => 'Lily',
    'language' => 'en-US',
    'speed' => 1.0,
]);

// Get available voices
$voices = $iris->vapi->voices();
```

#### Call Handoff (Transfer to Human)

```php
// Configure handoff settings
$iris->vapi->updateHandoff(335, [
    'enabled' => true,
    'phone_number' => '8788765657',
    'mode' => 'blind',  // 'blind' or 'warm' transfer
    'message' => 'Transferring you to a human agent...',
    'sms_notifications' => true,
]);

// Get current handoff settings
$handoff = $iris->vapi->getHandoff(335);
```

#### Call Management

```php
// Initiate an outbound call
$call = $iris->vapi->initiateCall(335, '+15551234567', [
    'context' => [
        'lead_id' => 412,
        'purpose' => 'Follow-up on proposal',
    ],
]);

// Get call history
$calls = $iris->vapi->callHistory(['limit' => 50, 'agent_id' => 335]);

// Get call details and transcript
$details = $iris->vapi->getCall($callId);
$transcript = $iris->vapi->getTranscript($callId);
$recordingUrl = $iris->vapi->getRecording($callId);

// End an active call
$iris->vapi->endCall($callId);

// Get VAPI usage statistics
$usage = $iris->vapi->usage();
```

**CLI Usage:**

```bash
# List phone numbers
./bin/iris sdk:call vapi.phoneNumbers

# Configure phone for agent
./bin/iris sdk:call vapi.configurePhoneNumber dd3905f2-... agent_id=335 use_dynamic_assistant=true

# Sync agent with VAPI
./bin/iris sdk:call vapi.syncAssistant 335

# Update handoff settings
./bin/iris sdk:call vapi.updateHandoff 335 handoff='{"enabled":true,"phone_number":"8788765657","mode":"blind"}'

# Get call history
./bin/iris sdk:call vapi.callHistory agent_id=335 limit=20
```

### 🤖 AI Models

List and manage available AI models.

```php
// Get basic/fast models (nano, mini)
$basic = $iris->models->basic();

// Get popular models
$popular = $iris->models->popular();

// Get nano models (fastest, cheapest)
$nano = $iris->models->nano();

// Get models by provider
$openai = $iris->models->byProvider('openai');
$anthropic = $iris->models->byProvider('anthropic');

// Get specific model details
$model = $iris->models->get('gpt-4o-mini-2024-07-18');
echo "Model: {$model['name']}\n";
echo "Provider: {$model['provider']}\n";

// Get recommended model for use case
$recommended = $iris->models->recommended('coding');

// Get pricing info
$pricing = $iris->models->pricing();
```

**CLI Usage:**

```bash
# List basic models
./bin/iris sdk:call models.basic

# Get popular models
./bin/iris sdk:call models.popular

# Get nano models
./bin/iris sdk:call models.nano

# Get model by provider
./bin/iris sdk:call models.byProvider openai
```

### 💳 Credit & Billing Status

```php
// Get credit status
$credits = $iris->usage->creditStatus();
echo "Credits remaining: {$credits['credits_remaining']}\n";
echo "Credits used: {$credits['credits_used']}\n";

if ($credits['credits_remaining'] < 100) {
    echo "Warning: Low credits!\n";
}

// Get credit history
$history = $iris->usage->creditHistory(['limit' => 50]);

// Get subscription details
$subscription = $iris->usage->subscription();

// Get available upgrade plans
$plans = $iris->usage->availablePlans();
```

**CLI Usage:**

```bash
# Check credit status
./bin/iris sdk:call usage.creditStatus

# Get subscription info
./bin/iris sdk:call usage.subscription

# Get available plans
./bin/iris sdk:call usage.availablePlans
```

## Error Handling

```php
use IRIS\SDK\Exceptions\{
    IRISException,
    AuthenticationException,
    RateLimitException,
    ValidationException,
    WorkflowException
};

try {
    $response = $iris->agents->chat('agent_123', $messages);
} catch (AuthenticationException $e) {
    // Invalid API key
    echo "Auth failed: " . $e->getMessage();
} catch (RateLimitException $e) {
    // Rate limited - wait and retry
    echo "Rate limited. Retry after {$e->retryAfter} seconds";
    sleep($e->retryAfter);
    // Retry...
} catch (ValidationException $e) {
    // Validation errors
    foreach ($e->validationErrors as $field => $errors) {
        echo "{$field}: " . implode(', ', $errors) . "\n";
    }
} catch (WorkflowException $e) {
    // Workflow execution failed
    echo "Step '{$e->stepName}' failed: " . $e->getMessage();
} catch (IRISException $e) {
    // Generic error
    echo "Error: " . $e->getMessage();
}
```

## Laravel Integration

The SDK includes a Laravel service provider for seamless integration.

### Configuration

```php
// config/iris.php
return [
    'api_key' => env('IRIS_API_KEY'),
    'base_url' => env('IRIS_API_URL', 'https://api.iris.ai'),
];
```

### Usage

```php
use IRIS\SDK\Laravel\Facades\IRIS;

// Using the facade
$response = IRIS::agents()->chat($agentId, $messages);

// Or with dependency injection
use IRIS\SDK\IRIS;

class ChatController
{
    public function chat(IRIS $iris, Request $request)
    {
        return $iris->agents->chat(
            $request->agent_id,
            $request->messages
        );
    }
}
```

## Webhook Handling

Receive real-time workflow events via webhooks.

```php
// In your webhook controller
$handler = $iris->webhooks();

$handler->onStepCompleted(function ($event) {
    Log::info('Step completed', [
        'workflow_id' => $event->workflowId,
        'step' => $event->stepNumber,
        'progress' => $event->progress,
    ]);
});

$handler->onHumanInputRequired(function ($event) {
    // Notify user
    Notification::send($user, new ApprovalRequired($event->task));
});

$handler->onWorkflowCompleted(function ($event) {
    // Process result
    ProcessResult::dispatch($event->result);
});

// Handle incoming webhook
$handler->handle(request());
```

## Configuration Options

```php
$iris = new IRIS([
    'api_key' => 'sk_live_xxxxx',      // Required: API key
    'base_url' => 'https://api.iris.ai', // Optional: API base URL
    'iris_url' => 'https://iris.iris.ai', // Optional: IRIS workflows URL
    'user_id' => 123,                   // Optional: Default user context
    'timeout' => 30,                    // Optional: Request timeout (seconds)
    'retries' => 3,                     // Optional: Max retry attempts
    'webhook_secret' => 'whsec_xxx',   // Optional: Webhook verification secret
    'debug' => false,                   // Optional: Enable debug logging
    'polling_interval' => 500,          // Optional: Workflow polling interval (ms)
    'max_polling_duration' => 300,      // Optional: Max polling time (seconds)
]);

// Switch user context
$iris->asUser(456);
```

## Testing

The SDK includes comprehensive test suites and example scripts.

### Quick Start - Lead Aggregation Test

Test the Lead Aggregation API with automatic environment configuration:

```bash
# 1. Copy environment template
cp .env.example .env

# 2. Add your API key to .env
# IRIS_API_KEY=your_api_key_here

# 3. Run the test
php test-lead-aggregation-user-193.php
```

**Output:**
```
🔧 Configuration:
   Environment: local
   Base URL: https://local.raichu.freelabel.net
   User ID: 193

📊 Lead Statistics:
  ✓ Total Leads: 125
  ✓ Total Tasks: 487
  ✓ Incomplete Tasks: 234
  ✓ Active Leads: 89

  🔥 Top Priority Leads:
     [95] Acme Corp (active)
     [87] Tech Startup (qualified)
✅ Test completed successfully!
```

**Environment Configuration:**

For **local development** (default):
```env
IRIS_ENV=local
FL_API_LOCAL_URL=https://local.raichu.freelabel.net
```

For **production testing**:
```env
IRIS_ENV=production
FL_API_URL=https://apiv2.heyiris.io
```

📖 **[Full Testing Documentation →](TEST_README.md)**

### Unit Tests

```php
use IRIS\SDK\Http\MockClient;

// Create mock client for testing
$mockHttp = new MockClient();
$mockHttp->addResponse('POST', '/v1/bloqs/agents/generate-response', [
    'content' => 'Mocked response',
    'tokens_used' => 100,
]);

$iris = new IRIS([
    'api_key' => 'test_key',
    'http_client' => $mockHttp,
]);

// Your tests
$response = $iris->agents->chat('agent_123', $messages);
assert($response->content === 'Mocked response');
```

## 🧪 Agent Evaluation Harness

The SDK includes a comprehensive evaluation framework for testing agent performance, capabilities, and configuration effectiveness. Use it to validate agents before production deployment or to compare different configurations.

### Overview

The evaluation harness consists of two main classes:

- **`EvaluationTest`** - Defines individual test scenarios with prompts and expectations
- **`AgentEvaluator`** - Runs tests and generates reports

### CLI Usage

The fastest way to evaluate an agent is via the CLI:

```bash
# List all available core tests
./bin/iris eval --list

# Run all 7 core tests against agent 387
./bin/iris eval 387

# Run custom test scenarios
./bin/iris eval 387 --type=custom

# Compare with/without web search enabled
./bin/iris eval 387 --type=comparison

# Save results to JSON file
./bin/iris eval 387 --save
./bin/iris eval 387 --save=my-results.json

# Output as JSON (useful for CI/CD pipelines)
./bin/iris eval 387 --json
```

### Core Tests

The evaluator includes 7 built-in test scenarios:

| Test Name | Description | Key Checks |
|-----------|-------------|------------|
| `basic_conversation` | Tests introduction and capabilities | Response length, self-introduction |
| `web_search_capability` | Tests real-time information retrieval | Web search usage, keywords, timing |
| `market_research` | Tests analysis and research capabilities | Structure, keywords, length |
| `personalization` | Tests memory and personalization | User interest reference |
| `complex_reasoning` | Tests complex planning abilities | Multi-part response, structure |
| `tool_integration` | Tests external API/tool usage | Tool invocation, results |
| `error_handling` | Tests graceful failure handling | No error keywords, proper response |

### PHP API Usage

#### Basic Usage

```php
use IRIS\SDK\IRIS;
use IRIS\SDK\Evaluation\AgentEvaluator;
use IRIS\SDK\Evaluation\EvaluationTest;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

$evaluator = new AgentEvaluator($iris);

// Run all core tests
$results = $evaluator->runCoreTests(387);

// Generate and display report
echo $evaluator->generateReport($results);
```

#### Custom Test Scenarios

Create custom tests tailored to your agent's specific use case:

```php
use IRIS\SDK\Evaluation\EvaluationTest;

// Test product knowledge
$productTest = new EvaluationTest(
    'product_knowledge',                              // Test name
    'What are the key features of our enterprise plan?', // Prompt
    [                                                  // Expectations
        'keywords' => ['enterprise', 'features', 'unlimited', 'support'],
        'min_response_length' => 150,
        'max_response_time_ms' => 15000,
        'should_be_structured' => true,
    ],
    'Tests agent knowledge of enterprise plan features' // Description
);

// Test customer support scenario
$supportTest = new EvaluationTest(
    'refund_handling',
    'I want to request a refund for my subscription. How do I do that?',
    [
        'keywords' => ['refund', 'process', 'email', 'days'],
        'min_response_length' => 100,
        'forbidden_keywords' => ['error', 'cannot', 'impossible'],
        'should_personalize' => false,
    ],
    'Tests proper refund request handling'
);

// Run custom tests
$result1 = $evaluator->runTest(387, $productTest);
$result2 = $evaluator->runTest(387, $supportTest);
```

#### Available Expectation Options

| Option | Type | Description |
|--------|------|-------------|
| `keywords` | `array<string>` | Required keywords (50%+ must be present) |
| `forbidden_keywords` | `array<string>` | Keywords that should NOT appear |
| `min_response_length` | `int` | Minimum character count |
| `max_response_length` | `int` | Maximum character count |
| `max_response_time_ms` | `int` | Maximum response time in milliseconds |
| `requires_web_search` | `bool` | Expects web search to be used |
| `requires_tool_use` | `bool` | Expects tool/integration usage |
| `should_personalize` | `bool` | Should reference user context |
| `should_reference_interests` | `bool` | Should mention user interests |
| `should_break_down_complex` | `bool` | Should break down complex topics |
| `should_be_structured` | `bool` | Should use bullets/numbers/headers |
| `should_introduce_self` | `bool` | Should include self-introduction |

#### Comparison Testing

Compare agent performance with different configurations:

```php
// The comparison test automatically:
// 1. Enables web search
// 2. Runs test
// 3. Disables web search
// 4. Runs same test
// 5. Restores original settings

$results = []; // Use CLI: ./bin/iris eval 387 --type=comparison

// Or manually compare configurations:
$evaluator = new AgentEvaluator($iris);

// Test with current settings
$result1 = $evaluator->runTest($agentId, $myTest);

// Modify agent settings
$iris->agents->patch($agentId, [
    'settings' => ['enabledFunctions' => ['deepResearch' => true]]
]);

// Test with new settings
$result2 = $evaluator->runTest($agentId, $myTest);

// Compare scores
echo "Without web search: {$result1['evaluation']['score']}%\n";
echo "With web search: {$result2['evaluation']['score']}%\n";
```

#### Adding Custom Core Tests

Extend the evaluator with your own core tests:

```php
$evaluator = new AgentEvaluator($iris);

// Add a custom core test
$evaluator->addCoreTest('brand_voice', new EvaluationTest(
    'brand_voice',
    'Tell me about your company and what makes you different.',
    [
        'keywords' => ['innovative', 'customer-focused', 'reliable'],
        'min_response_length' => 200,
        'should_introduce_self' => true,
    ],
    'Tests consistent brand voice and messaging'
));

// Now runCoreTests() includes your custom test
$results = $evaluator->runCoreTests($agentId);
```

### Result Structure

Each test returns a structured result:

```php
[
    'test_name' => 'basic_conversation',
    'description' => 'Tests introduction and capabilities description',
    'prompt' => 'Hello! Please introduce yourself...',
    'success' => true,                    // Pass/fail based on 50% threshold
    'response' => 'Hello! I am...',       // Full agent response
    'response_time_ms' => 5674,           // Time to respond
    'response_length' => 361,             // Character count
    'evaluation' => [
        'score' => 100,                   // 0-100 percentage
        'checks_passed' => 3,             // Number passed
        'checks_total' => 3,              // Total checks
        'details' => [                    // Per-check results
            'min_response_length' => [
                'passed' => true,
                'expected' => '>= 50',
                'actual' => 361,
            ],
            'response_time' => [
                'passed' => true,
                'expected' => '<= 15000ms',
                'actual' => '5674ms',
            ],
            // ...
        ],
    ],
    'error' => null,                      // Error message if failed
]
```

### CI/CD Integration

Use the JSON output for automated testing:

```bash
# Run tests and capture JSON output
./bin/iris eval 387 --json > eval-results.json

# Check pass rate in CI script
PASS_RATE=$(cat eval-results.json | jq '[.[] | select(.success == true)] | length')
TOTAL=$(cat eval-results.json | jq 'length')

if [ $PASS_RATE -lt $((TOTAL / 2)) ]; then
    echo "Agent evaluation failed: $PASS_RATE/$TOTAL tests passed"
    exit 1
fi
```

### Standalone Script

A standalone CLI script is also available for quick testing:

```bash
# Run core tests
php test-agent-cli-eval.php 387 core

# Run custom tests
php test-agent-cli-eval.php 387 custom

# Run comparison tests
php test-agent-cli-eval.php 387 comparison
```

### Best Practices

1. **Run evaluations before deployment** - Always test agents before pushing to production
2. **Create domain-specific tests** - Add custom tests for your specific use case
3. **Monitor over time** - Save results to track performance changes
4. **Test after configuration changes** - Re-evaluate after modifying agent settings
5. **Use comparison mode** - Understand the impact of enabling/disabling features
6. **Set realistic thresholds** - Adjust expectations based on your agent's purpose

## API Reference

| Resource | Methods |
|----------|---------|
| `$iris->chat` | `start`, `getStatus`, `execute`, `resume`, `summarize`, `history`, `stats` |
| `$iris->agents` | `list`, `get`, `create`, `update`, `patch`, `delete`, `chat`, `multiStep`, `addMemory`, `togglePublic`, `generateWebhook`, `getFileAttachments`, `addFileAttachments`, `setFileAttachments`, `removeFileAttachment`, `clearFileAttachments`, `uploadAndAttachFiles`, `getUrl`, `getUrls` |
| `$iris->workflows` | `execute`, `getStatus`, `continue`, `completeTask`, `generate`, `generateWithAgents`, `templates`, `importTemplate`, `runs`, `getLogs` |
| `$iris->bloqs` | `list`, `get`, `create`, `update`, `delete`, `overview`, `agents`, `bloqAgents`, `workflows`, `lists`, `items`, `uploadFile`, `files`, `getCustomFieldsConfig`, `updateCustomFieldsConfig`, `addCustomField`, `removeCustomField`, `clearCustomFields`, `share`, `getSharedUsers`, `updateSharePermission`, `unshare`, `getContent`, `addContent`, `removeContent`, `makeItemPublic`, `makeItemPrivate`, `getPublicItem`, `storeChatMessage`, `getChatMessages`, `clearChatMessages` |
| `$iris->leads` | `list`, `get`, `create`, `update`, `delete`, `search`, `addNote`, `activities`, `tasks`, `deliverables`, `invoices`, `aggregation`, `outreach`, `outreachSteps`, `enrich`, `enrichReAct`, `applyEnrichment`, `enrichmentStatus`, `generateResponse`, `recordOutreach`, `parseDescription`, `createFromDescription`, `getAvailableTags`, `getLifecycleStages`, `checkDuplicate`, `bulkCreateFromDescriptions`, `stripePayments` |
| `$iris->leads->tasks()` | `all`, `create`, `update`, `delete`, `reorder` |
| `$iris->leads->deliverables()` | `list`, `create`, `uploadFile`, `update`, `delete`, `previewEmail`, `send`, `generateAndSend` |
| `$iris->leads->invoices()` | `list`, `get`, `create`, `update`, `delete`, `markPaid`, `send`, `getPaymentLink`, `void` |
| `$iris->leads->aggregation()` | `statistics`, `list`, `get`, `getRecentLeads`, `requirements` |
| `$iris->leads->outreach()` | `checkEligibility`, `getInfo`, `recordAttempt`, `setAutoRespond`, `generateEmail`, `sendEmail`, `generateAndSend`, `indexNotes`, `searchNotes` |
| `$iris->leads->outreachSteps()` | `list`, `all`, `create`, `update`, `complete`, `reopen`, `delete`, `reorder`, `initializeDefault`, `clearAll` |
| `$iris->cloudFiles` | `list`, `get`, `upload`, `update`, `delete`, `downloadUrl`, `status`, `content`, `supportedTypes`, `forBloq`, `forAgent`, `attachToAgent`, `detachFromAgent`, `reindex`, `uploadForAgent`, `uploadMultipleForAgent` |
| `$iris->usage` | `summary`, `details`, `byAgent`, `byModel`, `billing`, `package`, `quota`, `history`, `workflowStats`, `storage`, `creditStatus`, `creditHistory`, `subscription`, `availablePlans` |
| `$iris->vapi` | `phoneNumbers`, `getPhoneNumber`, `configurePhoneNumber`, `disconnectPhoneNumber`, `syncAssistant`, `updateHandoff`, `getHandoff`, `getAssistant`, `updateVoice`, `voices`, `callHistory`, `getCall`, `getTranscript`, `getRecording`, `initiateCall`, `endCall`, `usage` |
| `$iris->models` | `list`, `basic`, `popular`, `get`, `byProvider`, `recommended`, `providers`, `sync`, `pricing`, `nano` |
| `$iris->integrations` | `available`, `connected`, `getOAuthUrl`, `test`, `execute`, `functions` |
| `$iris->rag` | `query`, `index`, `indexFile`, `searchSimilar`, `delete` |
| `$iris->tools` | `list`, `invoke`, `recruitment`, `scoreCandidates`, `enrichLead`, `newsletterResearch`, `newsletterWrite` |
| `$iris->articles` | `generate`, `generateFromVideo`, `generateFromTopic`, `generateFromWebpage`, `generateFromRss`, `generateFromResearchNotes`, `generateFromDraft`, `create` |

## Troubleshooting

### "The POST method is not supported for route..."

**Problem:** Getting this error when trying to create profiles or other resources.

**Cause:** The HTTP Client's routing logic doesn't recognize the endpoint pattern, so it routes to the wrong API.

**Solution:**

1. **Check your endpoint pattern** - The Client routes based on URL patterns:
   - `/profile` (singular) → FL-API ✅
   - `/profiles` (plural) → FL-API ✅
   - `/leads` → FL-API ✅
   - `/agents/*` → IRIS API ✅
   - `/chat/*` → IRIS API ✅

2. **Verify routing in `src/Http/Client.php`**:
   ```php
   // This check must include BOTH singular and plural forms
   if (str_contains($endpoint, '/profile')  // ✅ Both work
       || str_contains($endpoint, '/leads')
       || str_contains($endpoint, '/services')
       || str_contains($endpoint, '/users/')) {
       return $this->config->flApiUrl . '/' . ltrim($endpoint, '/');
   }
   ```

3. **Check your `.env` configuration**:
   ```bash
   # Production
   IRIS_API_URL=https://iris-api.freelabel.net  # For agents/chat/workflows
   FL_API_URL=https://apiv2.heyiris.io          # For leads/profiles/services
   
   # Local
   IRIS_LOCAL_URL=https://local.iris.freelabel.net
   FL_API_LOCAL_URL=https://local.raichu.freelabel.net
   ```

**Key lesson:** Always check for both singular and plural forms of resource names in routing logic. Backend routes may use `/api/v1/profile` (singular) even though SDK resources are named `profiles` (plural).

### Services Returning All Records Instead of Filtered Results

**Problem:** Calling `services.list(profile_id=123)` returns ALL services from the entire platform.

**Cause:** Missing `profile_id` parameter or backend not applying the filter.

**Solution:**

1. **Always specify `profile_id`**:
   ```php
   // ✅ CORRECT - Only returns services for this profile
   $services = $iris->services->list(['profile_id' => 9203684]);
   
   // ❌ WRONG - Returns ALL services (can be thousands)
   $services = $iris->services->list();
   ```

2. **CLI usage**:
   ```bash
   # ✅ CORRECT
   ./bin/iris sdk:call services.list profile_id=9203684
   
   # ❌ WRONG - Returns everything
   ./bin/iris sdk:call services.list
   ```

3. **Backend fix** - Ensure `ServicesController.php` applies filters:
   ```php
   private function searchServices(Request $request) {
       $services = Service::query();
       
       // Must check profile_id FIRST
       $profileId = $request->query('profile_id');
       if ($profileId) {
           $services->where('profile_id', $profileId);
       }
       // ... other filters
   }
   ```

### Wrong API URL Configuration

**Problem:** SDK calls failing or routing to wrong endpoints.

**Symptoms:**
- 404 errors on valid endpoints
- CORS errors
- "Method not supported" on working endpoints

**Solution:** Verify your API URLs in `.env`:

```bash
# ❌ WRONG - Both pointing to same URL
IRIS_API_URL=https://apiv2.heyiris.io
FL_API_URL=https://apiv2.heyiris.io

# ✅ CORRECT - Separate APIs
IRIS_API_URL=https://iris-api.freelabel.net  # Chat, agents, workflows
FL_API_URL=https://apiv2.heyiris.io          # Leads, profiles, services
```

**Quick test:**
```bash
# Test IRIS API (agents, chat)
curl https://iris-api.freelabel.net/api/health

# Test FL-API (leads, profiles)
curl https://apiv2.heyiris.io/api/health

# Both should return: {"status":"ok","database":"connected"}
```

## � CopyCatAI Integration

Complete content generation and media processing toolkit for articles, videos, and audio.

### Features

- **Article Generation:** AI-powered article writing with custom topics and tone
- **Newsletter Generation:** Multi-modal newsletter creation with HITL workflow (videos + links + topics)
- **YouTube Audio Download:** High-quality MP3 extraction (320kbps) with metadata
- **Video Downloading:** Full video downloads from multiple platforms
- **Clip Cutting:** Frame-accurate video segment extraction

### Quick Start

```bash
# Generate article
./bin/iris tools article --topic="AI in Healthcare" --agent-id=11

# Download YouTube audio
./bin/iris tools youtube-audio --url="https://www.youtube.com/watch?v=abc123" --agent-id=11

# Download with custom filename
./bin/iris tools youtube-audio --url="..." --agent-id=11 --output-filename="my_song"
```

### PHP SDK

```php
use IRIS\SDK\IRIS;

$iris = new IRIS(['api_key' => 'your_api_key', 'user_id' => 193]);

// Generate article
$result = $iris->agents->callIntegration(11, 'copycat-ai', 'generate_article', [
    'topic' => 'Future of AI',
    'min_words' => 1000,
]);

// Download YouTube audio
$result = $iris->agents->callIntegration(11, 'copycat-ai', 'download_youtube_audio', [
    'youtube_url' => 'https://www.youtube.com/watch?v=abc123',
    'upload_to_gcs' => false,
    'output_filename' => 'my_song',
]);

echo $result['result']['download_url'];  // https://local.raichu.freelabel.net/storage/my_song.mp3
```

### Available Tools

| Tool | CLI Command | Description |
|------|-------------|-------------|
| Article Generation | `./bin/iris tools article` | AI-powered article writing |
| Newsletter Research | `./bin/iris tools newsletter-research` | Multi-modal research with outline generation |
| Newsletter Write | `./bin/iris tools newsletter-write` | Generate newsletter from selected outline |
| YouTube Audio | `./bin/iris tools youtube-audio` | Extract MP3 from YouTube (320kbps) |
| Video Download | `./bin/iris tools video-download` | Download full videos |
| Clip Cutting | `./bin/iris tools clip-cut` | Extract video segments |

### Requirements

- yt-dlp and FFmpeg installed in backend
- CopycatAI integration enabled in agent settings
- User integration record with status='active'

**📚 For detailed documentation, examples, troubleshooting, and advanced usage, see [COPYCAT_AI_INTEGRATION.md](COPYCAT_AI_INTEGRATION.md)**

## 🔌 Integration Management

Manage third-party integrations directly from the SDK and CLI. Connect services, manage credentials, and test connections without touching the dashboard.

### Overview

The Integration Management system provides unified access to 17+ third-party services:

| Category | Integrations | Auth Type |
|----------|--------------|-----------|
| **Google Suite** | Drive, Gmail, Calendar | OAuth |
| **Communication** | Slack, Discord, GitHub | OAuth |
| **Email** | Mailjet, Mailchimp, SMTP | API Key / Credentials |
| **AI Services** | Vapi, Servis.ai, ElevenLabs, Gemini | API Key |
| **Content** | YouTube, Buffer | API Key / OAuth |

**Key Features:**
- **OAuth Flow Support** - Automatic browser-based authorization for Google, Slack, GitHub, etc.
- **Type-Specific Flows** - Specialized prompts for Vapi (phone number), Servis.ai (client credentials), SMTP (server configs)
- **Status Checking** - Test connectivity and validate credentials
- **Connection Management** - Connect, disconnect, reconnect integrations seamlessly

### Quick Start

```bash
# Install dependencies
cd /path/to/sdk/php && composer install

# List available integration types
./bin/iris integrations types

# View connected integrations
./bin/iris integrations list

# Connect an integration (interactive)
./bin/iris integrations connect vapi

# Check status
./bin/iris integrations status vapi

# Disconnect
./bin/iris integrations disconnect vapi
```

### CLI Commands

#### `iris integrations types`

List all available integrations with their authentication requirements.

```bash
./bin/iris integrations types

# Output:
Available Integration Types
===========================

API Key Integrations (8)
  • vapi                 - Vapi Voice AI
  • servis-ai            - Servis.ai
  • smtp                 - SMTP Email
  • mailjet              - Mailjet
  • elevenlabs           - ElevenLabs
  • youtube              - YouTube
  • buffer               - Buffer
  • gemini               - Google Gemini

OAuth Integrations (9)
  • google-drive         - Google Drive
  • gmail                - Gmail
  • google-calendar      - Google Calendar
  • slack                - Slack
  • discord              - Discord
  • github               - GitHub
  • mailchimp            - Mailchimp
```

#### `iris integrations list`

View all connected integrations with their status.

```bash
./bin/iris integrations list

# Output:
Connected Integrations
=====================

✓ vapi (Voice AI)
  Status: active
  Connected: 2025-01-15
  Phone: +1-512-555-0100

✓ google-drive (Google Drive)
  Status: active
  Connected: 2025-01-10
  Scopes: drive.readonly, drive.file
```

#### `iris integrations connect <type>`

Connect a new integration with interactive prompts.

**API Key Example (Vapi):**
```bash
./bin/iris integrations connect vapi

# Prompts:
Enter Vapi API Key: ****************
Enter Vapi Phone Number ID (optional): dd3905f2-...
✓ Connected successfully!
```

**OAuth Example (Google Drive):**
```bash
./bin/iris integrations connect google-drive

# Opens browser automatically:
Opening authorization URL in browser...
https://accounts.google.com/o/oauth2/auth?client_id=...

Please authorize the application and return here.
✓ Connected successfully!
```

**SMTP Example (Custom Server):**
```bash
./bin/iris integrations connect smtp

# Prompts:
SMTP Host: smtp.gmail.com
SMTP Port [587]: 587
SMTP Username: user@example.com
SMTP Password: ****************
SMTP Encryption (tls/ssl) [tls]: tls
From Email: noreply@example.com
From Name: My App
✓ Connected successfully!
```

#### `iris integrations disconnect <type>`

Disconnect an integration with confirmation.

```bash
./bin/iris integrations disconnect vapi

# Prompts:
⚠️  Are you sure you want to disconnect vapi? (yes/no) [no]: yes
✓ Disconnected successfully!
```

#### `iris integrations test <type>`

Test connectivity and validate credentials.

```bash
./bin/iris integrations test vapi

# Output:
Testing vapi integration...

✓ Connection successful!
  API Key: Valid
  Phone Number: +1-512-555-0100
  Status: Active
```

#### `iris integrations status <type>`

Check detailed status and configuration.

```bash
./bin/iris integrations status vapi

# Output:
Integration Status: vapi
========================

Status: ✓ Active
Type: api_key
Connected: 2025-01-15 10:30:45

Configuration:
  API Key: ****...****
  Phone Number ID: dd3905f2-...
  Phone Number: +1-512-555-0100
  
Last Tested: 2025-01-20 14:22:13
Test Result: Success
```

### PHP SDK Usage

```php
use IRIS\SDK\IRIS;

$iris = new IRIS(['api_key' => 'your-api-key', 'user_id' => 193]);

// List available integration types
$types = $iris->integrations->types();
foreach ($types as $type) {
    echo "{$type['type']} - {$type['name']} ({$type['auth_type']})\n";
}

// Get connected integrations
$connected = $iris->integrations->connected();
foreach ($connected as $integration) {
    echo "{$integration->type}: {$integration->status}\n";
}

// Check status of a specific integration
$status = $iris->integrations->status('vapi');
if ($status->isConnected()) {
    echo "Vapi is connected\n";
}

// Connect with API key (Vapi example)
$integration = $iris->integrations->connectVapi([
    'api_key' => 'vapi_key_xxxxx',
    'phone_number_id' => 'dd3905f2-...',
]);

// Connect with API key (Servis.ai example)
$integration = $iris->integrations->connectServisAi([
    'client_id' => 'client_xxxxx',
    'client_secret' => 'secret_xxxxx',
]);

// Connect with credentials (SMTP example)
$integration = $iris->integrations->connectSmtp([
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'user@example.com',
    'smtp_password' => 'app_password',
    'smtp_encryption' => 'tls',
    'smtp_from_email' => 'noreply@example.com',
    'smtp_from_name' => 'My App',
]);

// Generic API key connection
$integration = $iris->integrations->connectWithApiKey('youtube', [
    'api_key' => 'youtube_key_xxxxx',
]);

// Start OAuth flow (Google Drive example)
$authUrl = $iris->integrations->startOAuthFlow('google-drive');
// Redirect user to $authUrl for authorization
// User returns with auth code, backend handles token exchange automatically

// Disconnect an integration
$result = $iris->integrations->disconnect('vapi');
echo $result['message'];  // "Integration disconnected successfully"

// Helper methods
$usesOAuth = $iris->integrations->usesOAuth('google-drive');    // true
$usesApiKey = $iris->integrations->usesApiKey('vapi');          // true
```

### Integration Collection Helpers

The SDK provides a collection class with helper methods for filtering integrations:

```php
// Get connected integrations as a collection
$collection = $iris->integrations->connected();

// Find by type
$vapi = $collection->findByType('vapi');
if ($vapi) {
    echo "Vapi API Key: {$vapi->credentials['api_key']}\n";
}

// Filter by status
$active = $collection->filterByStatus('active');
$inactive = $collection->filterByStatus('error');

// Filter by category
$oauth = $collection->filterByCategory('oauth');
$apiKey = $collection->filterByCategory('api_key');

// Count and iterate
echo "Total connected: " . $collection->count() . "\n";
foreach ($collection as $integration) {
    echo "- {$integration->type}\n";
}
```

### Type-Specific Connection Flows

#### Vapi (Voice AI)

```php
// Connect Vapi with phone number
$vapi = $iris->integrations->connectVapi([
    'api_key' => 'vapi_key_xxxxx',
    'phone_number_id' => 'dd3905f2-08d6-4dc2-a50f-f0c937ada251',
]);

// CLI interactive flow
./bin/iris integrations connect vapi
# Prompts for:
#   - Vapi API Key (required)
#   - Phone Number ID (optional)
```

#### Servis.ai (Legal CRM)

```php
// Connect Servis.ai with OAuth-like credentials
$servis = $iris->integrations->connectServisAi([
    'client_id' => 'client_xxxxx',
    'client_secret' => 'secret_xxxxx',
]);

// CLI interactive flow
./bin/iris integrations connect servis-ai
# Prompts for:
#   - Client ID (required)
#   - Client Secret (required)
```

#### SMTP Email

```php
// Connect SMTP server
$smtp = $iris->integrations->connectSmtp([
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'user@example.com',
    'smtp_password' => 'app_password',
    'smtp_encryption' => 'tls',  // 'tls' or 'ssl'
    'smtp_from_email' => 'noreply@example.com',
    'smtp_from_name' => 'My Application',
]);

// CLI interactive flow
./bin/iris integrations connect smtp
# Prompts for all SMTP configuration fields
# with smart defaults (port 587, encryption tls)
```

#### OAuth Services (Google, Slack, GitHub)

```php
// Start OAuth authorization flow
$authUrl = $iris->integrations->startOAuthFlow('google-drive', [
    'redirect_uri' => 'https://yourapp.com/oauth/callback',  // Optional
]);

// User visits $authUrl and authorizes
// Backend handles callback and stores tokens automatically

// CLI flow (opens browser automatically)
./bin/iris integrations connect google-drive
# Opens browser to Google authorization page
# User approves, CLI detects success and confirms connection
```

### Error Handling

```php
use IRIS\SDK\Exceptions\IntegrationException;

try {
    $integration = $iris->integrations->connectVapi([
        'api_key' => 'invalid_key',
    ]);
} catch (IntegrationException $e) {
    echo "Connection failed: {$e->getMessage()}\n";
    
    // Check error details
    if ($e->getCode() === 401) {
        echo "Invalid API key\n";
    }
}

// Status checking
$status = $iris->integrations->status('vapi');
if ($status->hasError()) {
    echo "Error: {$status->error}\n";
    echo "Last tested: {$status->last_tested_at}\n";
}
```

### Testing Connections

```php
// Test a specific integration
$result = $iris->integrations->test('vapi');

if ($result->success) {
    echo "✓ Connection successful\n";
    echo "Details: {$result->message}\n";
} else {
    echo "✗ Connection failed: {$result->error}\n";
}

// CLI testing
./bin/iris integrations test vapi
```

### Environment-Specific Configuration

The SDK automatically detects your environment:

```bash
# .env configuration
IRIS_ENV=local  # or 'production'

# Local development
IRIS_LOCAL_API_KEY=your_local_token
FL_API_LOCAL_URL=https://local.raichu.freelabel.net

# Production
IRIS_API_KEY=your_production_token
FL_API_URL=https://apiv2.heyiris.io
```

### Troubleshooting

#### "Unauthenticated" Errors

**Problem:** CLI commands return 401 errors even with valid `.env` configuration.

**Cause:** JWT tokens expire (typically 24-48 hours) and `.env` files store static tokens.

**Solutions:**

1. **Generate fresh token from browser:**
   ```bash
   # 1. Login to https://app.heyiris.io
   # 2. Open browser DevTools → Application → Local Storage
   # 3. Copy 'auth_token' value
   # 4. Update .env:
   IRIS_API_KEY=<paste_token_here>
   ```

2. **Use token generation utility** (if available):
   ```bash
   ./bin/iris auth generate
   # Automatically updates .env with fresh token
   ```

3. **Check environment mismatch:**
   ```bash
   # Ensure IRIS_ENV matches your API URL:
   IRIS_ENV=local  # Must use FL_API_LOCAL_URL
   IRIS_ENV=production  # Must use FL_API_URL
   ```

**📚 For complete authentication troubleshooting, see [docs/AUTH_ISSUES_AND_SOLUTIONS.md](docs/AUTH_ISSUES_AND_SOLUTIONS.md)**

#### Integration Not Found

```bash
./bin/iris integrations connect unknown-service
# Error: Integration type 'unknown-service' not found

# Solution: List available types
./bin/iris integrations types
```

#### OAuth Callback Issues

**Problem:** OAuth flow fails after browser authorization.

**Cause:** Incorrect redirect URI or callback not handled.

**Solution:** Ensure your callback endpoint is registered and the backend has the correct OAuth credentials configured.

### Complete Example: Full Integration Workflow

```php
use IRIS\SDK\IRIS;

$iris = new IRIS(['api_key' => 'your-api-key', 'user_id' => 193]);

// 1. List available integrations
echo "Available Integrations:\n";
$types = $iris->integrations->types();
foreach ($types as $type) {
    echo "  - {$type['name']} ({$type['auth_type']})\n";
}

// 2. Connect Vapi
echo "\nConnecting Vapi...\n";
try {
    $vapi = $iris->integrations->connectVapi([
        'api_key' => getenv('VAPI_API_KEY'),
        'phone_number_id' => getenv('VAPI_PHONE_ID'),
    ]);
    echo "✓ Vapi connected\n";
} catch (Exception $e) {
    echo "✗ Failed: {$e->getMessage()}\n";
    exit(1);
}

// 3. Test the connection
echo "\nTesting connection...\n";
$test = $iris->integrations->test('vapi');
if ($test->success) {
    echo "✓ Test passed\n";
} else {
    echo "✗ Test failed: {$test->error}\n";
}

// 4. List all connected integrations
echo "\nConnected Integrations:\n";
$connected = $iris->integrations->connected();
foreach ($connected as $integration) {
    $status = $integration->status === 'active' ? '✓' : '✗';
    echo "  {$status} {$integration->type}\n";
}

// 5. Use the integration (execute function)
echo "\nExecuting integration function...\n";
$result = $iris->integrations->execute('vapi', 'get_phone_numbers');
echo "Phone numbers: " . count($result) . "\n";
```

**CLI Equivalent:**

```bash
#!/bin/bash

# 1. List available integrations
./bin/iris integrations types

# 2. Connect Vapi (interactive)
./bin/iris integrations connect vapi

# 3. Test connection
./bin/iris integrations test vapi

# 4. List connected integrations
./bin/iris integrations list

# 5. Check status
./bin/iris integrations status vapi
```

### Next Steps

- **[Full Integration Management Guide →](docs/INTEGRATION_MANAGEMENT.md)** - Complete documentation with advanced examples
- **[Authentication Solutions →](docs/AUTH_ISSUES_AND_SOLUTIONS.md)** - Token management and troubleshooting
- **[Integration Endpoints Reference →](docs/API_REFERENCE.md)** - Complete API documentation

## License

MIT License - see [LICENSE](LICENSE) for details.

## Support

- Documentation: https://docs.iris.ai/sdk/php
- Issues: https://github.com/iris-ai/php-sdk/issues
- Email: support@iris.ai
