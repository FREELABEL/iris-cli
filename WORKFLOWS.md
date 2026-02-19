# IRIS Agentic Workflows

```
╔═══════════════════════════════════════════════════════════════════════════════╗
║                                                                               ║
║              🔄 AGENTIC WORKFLOWS — THE EVOLUTION OF AUTOMATION               ║
║                                                                               ║
║         From rigid node-based flows to intelligent, adaptive AI agents        ║
║                                                                               ║
╚═══════════════════════════════════════════════════════════════════════════════╝
```

---

## The Problem with Traditional Automation

If you've used N8N, Zapier, or Make, you know the drill:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     TRADITIONAL NODE-BASED WORKFLOW                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│   [Trigger] → [Parse] → [If X?] → [Route A]                                    │
│                  │                                                              │
│                  ├───── [If Y?] → [Route B]                                    │
│                  │                    │                                         │
│                  │                    └── [If Z?] → [Route C]                  │
│                  │                                                              │
│                  └───── [Else] → [Route D] → [Format] → [Send]                 │
│                                                                                 │
│   ❌ Every path must be pre-defined                                             │
│   ❌ New scenarios = rebuild the workflow                                       │
│   ❌ Edge cases break things                                                    │
│   ❌ Complex logic = spaghetti nightmare                                        │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

**What happens when:**
- A customer writes in a mix of languages?
- The email has multiple topics?
- Someone uses sarcasm and your keyword matching fails?
- A new category of request comes in?

**Answer:** It breaks, or gets routed wrong, and someone has to manually fix it.

---

## The IRIS Approach: Agentic Workflows

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        IRIS AGENTIC WORKFLOW                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│                              ┌─────────────────┐                                │
│                              │   🤖 AI AGENT   │                                │
│   [Incoming Request] ──────▶ │                 │ ──────▶ [Intelligent Output]   │
│                              │  "Triage by     │                                │
│                              │   urgency and   │                                │
│                              │   route to the  │                                │
│                              │   right team"   │                                │
│                              └─────────────────┘                                │
│                                                                                 │
│   ✅ Handles edge cases intelligently                                           │
│   ✅ Adapts to new scenarios without changes                                    │
│   ✅ Understands context, tone, and intent                                      │
│   ✅ Describe goals in English, not flowcharts                                  │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## How to Build Workflows

### Method 1: Natural Language (Recommended)

Just describe what you want in plain English:

```
"When a new lead comes in:
 1. Research their company on LinkedIn
 2. Find relevant case studies in our knowledge base
 3. Draft a personalized outreach email
 4. Wait for my approval before sending"
```

IRIS understands this and creates the workflow automatically.

### Method 2: SDK/CLI (For Developers)

```php
// Via SDK
$workflow = $iris->workflows->execute([
    'agent_id' => $agent->id,
    'query' => 'Research Acme Corp and draft an outreach email',
    'require_approval' => true,  // Human-in-the-loop
]);

// Via CLI
./bin/iris chat 11 "Research Acme Corp and draft an outreach email" --bloq=40
```

---

## Workflow Types

### 1️⃣ Simple Workflow — Fully Automated

**How it works:** Trigger → AI Processes → Output (no human needed)

**Best for:** Routine tasks, reports, data processing, content generation

**Example prompts:**
- "Summarize the last 10 support tickets and email me the report"
- "Every Monday, compile a summary of last week's sales activities"
- "When a new file is uploaded, extract key data and add to the spreadsheet"

---

### 2️⃣ Human-in-the-Loop Workflow — AI + Human Approval

**How it works:** Trigger → AI Drafts → Human Reviews → AI Executes

**Approval options:**
- ✅ **Approve** - Send as-is
- ✏️ **Edit** - Modify and approve
- ❌ **Reject** - Cancel with feedback
- 💬 **Feedback** - Ask AI to revise

**Best for:** Customer communications, contracts, anything requiring oversight

**Example prompts:**
- "Draft a refund response for customer #12345, wait for my approval"
- "Create a proposal for the enterprise client, let me review before sending"
- "Write a press release about our new feature, I'll approve the final version"

**SDK Example:**
```php
$workflow = $iris->workflows->execute([
    'agent_id' => $agent->id,
    'query' => 'Process refund request from customer #12345',
    'require_approval' => true,
]);

// Later, when human reviews:
if ($workflow->needsHumanInput()) {
    echo "Pending: " . $workflow->pendingTask->description;

    $workflow->approve("Looks good, send it.");
    // or: $workflow->reject("Make it more empathetic.");
}
```

---

### 3️⃣ Multi-Step Workflow — Complex Operations

**How it works:** Multiple steps with progress tracking

```
Step 1: Gather Data    →  Search files, scrape web, query APIs
Step 2: Analyze        →  Process, summarize, extract insights
Step 3: Decide         →  AI determines next actions
Step 4: Create         →  Generate content, reports, emails
Step 5: Deliver        →  Send results, update systems
```

**Progress tracking:** Real-time visibility into each step's status

**Best for:** Research, content creation, data analysis, complex automation

---

## Real-World Examples

### 📹 Example 1: YouTube Video → Blog Article

**Goal:** Turn a YouTube video into a well-researched blog article

**What happens:**

1. **Extract Video Content**
   - Supadata API extracts transcript
   - AI summarizes key points
   - Identifies main topics and speakers

2. **Research Supporting Content**
   - Firecrawl scrapes 3 related articles
   - Crawls industry news site for latest data
   - Searches knowledge base for internal docs

3. **Generate Article**
   - AI writes comprehensive 2,000-word article
   - Includes citations and sources
   - Adds relevant statistics from research
   - Suggests meta description and tags

**How to run it:**

```bash
# Via CLI
./bin/iris chat 11 "Create a blog article from this YouTube video:
https://youtube.com/watch?v=abc123. Research 3 supporting articles
and include statistics." --bloq=40
```

```php
// Via SDK
$result = $iris->chat->execute([
    'agentId' => 11,
    'bloqId' => 40,
    'query' => 'Create a blog article from YouTube video xyz...'
]);

echo $result['summary'];  // The generated article
```

---

### 🔍 Example 2: Lead Research & Outreach

**Goal:** Research a new lead and craft a personalized outreach email

**What happens:**

1. **Research Company**
   - Scrape company website
   - Find recent news articles
   - Check LinkedIn for company size
   - Identify their tech stack

2. **Find Relevant Content**
   - Search knowledge base for similar clients
   - Find case studies in their industry
   - Identify pain points we can solve

3. **Draft Personalized Email**
   - Reference their specific challenges
   - Mention relevant case study
   - Include personalized value proposition

4. **Human Approval**
   - Review email draft
   - Edit if needed
   - Approve to send

5. **Send & Log**
   - Send via Gmail integration
   - Log activity in CRM
   - Schedule follow-up task

---

### 📊 Example 3: Competitive Analysis Report

**Goal:** Research competitors and generate a comparison report

**What happens:**

1. **Parallel Research** (runs simultaneously for each competitor)
   - Crawl competitor websites
   - Find recent news and announcements
   - Check pricing pages
   - Analyze feature lists

2. **Synthesize Findings**
   - Compare features across competitors
   - Analyze pricing positioning
   - Identify market gaps
   - Find our competitive edge

3. **Generate Report**
   - Feature comparison matrix
   - Pricing analysis table
   - Strategic recommendations
   - Executive summary

---

## Built-in Workflow Tools

IRIS workflows can automatically use these tools:

**🔥 Web Scraping (Firecrawl)**
- Scrape any webpage into clean markdown
- Crawl entire sites (respects robots.txt)
- Extract structured data (prices, contacts, etc.)

**🎥 Video Processing (Supadata)**
- Extract transcripts from YouTube videos
- Get video metadata and chapters
- Analyze video content

**📚 Knowledge Search (RAG)**
- Semantic search across all your documents
- Find relevant content automatically
- Cite sources in outputs

**🔗 Integrations (17+)**
- Google Drive, Gmail, Calendar
- Slack, Discord
- Stripe, Mailjet, and more

**🤖 AI Models**
- GPT-4o, GPT-4o-mini
- Claude 3.5 Sonnet, Claude 3 Haiku
- Gemini Pro, DeepSeek

---

## CLI Quick Reference

```bash
# Simple automated workflow
./bin/iris chat 11 "Summarize the last 10 emails in my inbox"

# With knowledge base context
./bin/iris chat 11 "Using our case studies, draft an email for a healthcare client" --bloq=40

# Get JSON output for scripting
./bin/iris chat 11 "Generate a weekly report" --json

# Complex multi-step
./bin/iris chat 11 "Research TechCorp, find 3 news articles, create a company brief" --bloq=40
```

---

## Why Agentic > Node-Based

| Traditional Automation | IRIS Agentic Workflows |
|------------------------|------------------------|
| Build flows for every scenario | AI adapts to new situations |
| Technical setup required | Describe goals in English |
| Breaks on edge cases | Handles complexity intelligently |
| Hard to modify | Update the prompt to change behavior |
| Requires developer | Anyone can create workflows |
| Rigid decision trees | Contextual, nuanced decisions |

---

## Get Started

1. **Create an agent** at [app.heyiris.io](https://app.heyiris.io)
2. **Give it integrations** (Gmail, Drive, Slack, etc.)
3. **Upload knowledge** to your knowledge base
4. **Describe your workflow** in plain English
5. **Let AI do the work**

Or use the SDK:

```bash
composer require iris-ai/sdk
./bin/iris config setup
./bin/iris chat 11 "Your workflow description here"
```

---

📖 See [TECHNICAL.md](TECHNICAL.md) for complete API documentation.

📖 See [README.md](README.md) for platform overview.
