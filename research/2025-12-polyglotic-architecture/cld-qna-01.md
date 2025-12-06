## QUESTION: Conduct a study on how to...

call and stream responses from Claude Code SDK (python or node version) back to Laravel app and then to React frontend. I have Laravel app but I need it to collaborate with components written in Python or NodeJS - in some cases it requires immediate streaming of received responses first to PHP backend, then to Inertia/React frontend. Python and NodeJS components offer CLI / terminal API and only some can be connected via REST / streaming http apis.

## RESPONSE

I'll research the Claude Code SDK capabilities and streaming patterns first.Now I have sufficient context. Let me create a comprehensive technical study document.[View your study document](computer:///mnt/user-data/outputs/claude-sdk-laravel-streaming-study.md)

---

### Key Decisions

| Your Constraint | Recommendation |
|-----------------|----------------|
| Claude Agent SDK streaming | **FastAPI sidecar** wrapping `query()` async iterator as SSE |
| CLI-only Python/Node tools | **Subprocess + Redis pub/sub** for async streaming |
| Laravel → React/Inertia | **Direct SSE endpoint** bypassing Inertia's request cycle |
| Auth on SSE | Use `fetch` + ReadableStream (not `EventSource`) to send auth headers |

---

### Why Not Pure Laravel?

- PHP is synchronous—holding a process open during streaming blocks the worker
- Claude Agent SDK is Python/Node only; no PHP binding
- Sidecar pattern isolates SDK runtime and scales independently

### Critical Path

1. **FastAPI sidecar** wraps Claude SDK as `/stream` SSE endpoint
2. **Laravel controller** proxies SSE via Symfony HttpClient (handles auth)
3. **React hook** consumes stream via `fetch` + `ReadableStream` (not `EventSource`)
4. **Nginx config** must disable `proxy_buffering`

The document includes working code for all layers. For CLI-only tools, see the subprocess + Redis pub/sub pattern which adds a queue hop but works with any CLI.

## QUESTION: What am I missing?

What is a radically simpler way to use Python or Node components within Laravel app? Example: skip passing data to Laravel, only save the streamed data to DB or Redis, stream to browser and independently call Laravel app webhooks, etc.

## RESPONSE

You're right—I overcomplicated it. The proxy pattern is enterprise theater for most cases.

---

### The Radical Simplification: Direct Sidecar + Shared State

```
┌──────────┐  JWT token   ┌──────────────┐  direct SSE   ┌──────────┐
│  Laravel │ ───────────► │ Python/Node  │ ◄───────────► │  Browser │
│   (auth) │              │   Sidecar    │               │          │
└──────────┘              └──────────────┘               └──────────┘
      ▲                          │
      │ webhook                  │ write results
      │ (job done)               ▼
      └─────────────────── Redis/PostgreSQL
```

**What Laravel does:**
- Issues JWT tokens (user auth)
- Receives webhook when job completes
- Serves Inertia pages
- Reads final results from DB/Redis

**What Laravel doesn't do:**
- Proxy streams
- Hold connections open
- Touch streaming data in transit

---

### Pattern A: Direct SSE + DB Write

```python
# sidecar/main.py - Python talks directly to browser AND writes to DB
from fastapi import FastAPI, Depends, HTTPException
from fastapi.responses import StreamingResponse
import jwt
import asyncpg
from claude_agent_sdk import query
import httpx

app = FastAPI()
DB_URL = "postgresql://..."
LARAVEL_WEBHOOK = "http://laravel.local/api/webhooks/claude-complete"
JWT_SECRET = "shared-with-laravel"

async def verify_token(authorization: str = Header()):
    try:
        token = authorization.replace("Bearer ", "")
        return jwt.decode(token, JWT_SECRET, algorithms=["HS256"])
    except:
        raise HTTPException(401)

@app.post("/stream/{session_id}")
async def stream(session_id: str, prompt: str, user = Depends(verify_token)):
    
    async def generate():
        conn = await asyncpg.connect(DB_URL)
        full_result = ""
        
        try:
            async for msg in query(prompt=prompt):
                chunk = msg.content or msg.result or ""
                full_result += chunk
                
                # Stream to browser
                yield f"data: {json.dumps({'chunk': chunk})}\n\n"
            
            # Write final result to DB (Laravel can read this)
            await conn.execute("""
                INSERT INTO claude_results (session_id, user_id, result, created_at)
                VALUES ($1, $2, $3, NOW())
            """, session_id, user["sub"], full_result)
            
            # Notify Laravel via webhook
            async with httpx.AsyncClient() as client:
                await client.post(LARAVEL_WEBHOOK, json={
                    "session_id": session_id,
                    "user_id": user["sub"],
                    "status": "complete"
                })
            
            yield "data: [DONE]\n\n"
        finally:
            await conn.close()
    
    return StreamingResponse(generate(), media_type="text/event-stream")
```

```php
// Laravel just issues tokens and receives webhooks
// app/Http/Controllers/ClaudeController.php

class ClaudeController extends Controller
{
    public function getStreamToken(Request $request)
    {
        // Issue a short-lived JWT for the sidecar
        $token = JWT::encode([
            'sub' => $request->user()->id,
            'exp' => now()->addMinutes(30)->timestamp,
        ], config('services.sidecar.jwt_secret'));
        
        return response()->json([
            'token' => $token,
            'stream_url' => config('services.sidecar.url') . '/stream',
        ]);
    }
    
    public function webhookComplete(Request $request)
    {
        // Sidecar calls this when done
        $validated = $request->validate([
            'session_id' => 'required|string',
            'user_id' => 'required|integer',
            'status' => 'required|string',
        ]);
        
        // Update app state, notify user, trigger next steps
        event(new ClaudeJobCompleted($validated));
        
        return response()->json(['ok' => true]);
    }
}
```

```tsx
// React connects directly to sidecar
function useDirectStream() {
  const startStream = async (prompt: string) => {
    // Get token from Laravel
    const { token, stream_url } = await fetch('/api/claude/token').then(r => r.json());
    
    // Connect directly to sidecar
    const response = await fetch(`${stream_url}/${sessionId}?prompt=${encodeURIComponent(prompt)}`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
    });
    
    // Stream directly from sidecar
    const reader = response.body.getReader();
    // ... same streaming logic
  };
}
```

---

### Pattern B: Fire-and-Forget + Redis Streams

*Even simpler: Laravel dispatches, sidecar writes to Redis, browser subscribes*

```python
# Sidecar writes to Redis Stream (not pub/sub - streams persist)
import redis.asyncio as redis

@app.post("/run/{session_id}")
async def run_job(session_id: str, prompt: str, background_tasks: BackgroundTasks):
    background_tasks.add_task(run_claude, session_id, prompt)
    return {"status": "started", "session_id": session_id}

async def run_claude(session_id: str, prompt: str):
    r = await redis.from_url("redis://localhost")
    stream_key = f"claude:stream:{session_id}"
    
    async for msg in query(prompt=prompt):
        # Write each chunk to Redis Stream
        await r.xadd(stream_key, {"chunk": msg.content or "", "type": msg.type})
    
    await r.xadd(stream_key, {"type": "done"})
    await r.expire(stream_key, 3600)  # TTL 1 hour
```

```tsx
// Browser reads Redis Stream via simple polling or SSE from a tiny endpoint
// Or use Laravel Echo + Redis for the last mile
```

---

### Pattern C: Subprocess in Laravel + Streaming stdout

*Simplest possible: no sidecar service at all*

```php
// app/Http/Controllers/SimpleStreamController.php

public function stream(Request $request): StreamedResponse
{
    $prompt = $request->input('prompt');
    $scriptPath = base_path('tools/claude_query.py');
    
    return response()->stream(function () use ($prompt, $scriptPath) {
        $process = proc_open(
            ['python3', $scriptPath, '--prompt', $prompt],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ],
            $pipes,
            base_path('tools'),
            ['ANTHROPIC_API_KEY' => config('services.anthropic.key')]
        );
        
        // Stream stdout directly to browser
        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line) {
                echo "data: " . trim($line) . "\n\n";
                ob_flush();
                flush();
            }
        }
        
        fclose($pipes[1]);
        proc_close($process);
        
        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

```python
#!/usr/bin/env python3
# tools/claude_query.py - Just prints JSON lines to stdout
import sys
import json
import anyio
from claude_agent_sdk import query

async def main():
    prompt = sys.argv[sys.argv.index('--prompt') + 1]
    
    async for msg in query(prompt=prompt):
        # Print each chunk as JSON line (Laravel streams this)
        print(json.dumps({"type": msg.type, "content": getattr(msg, "content", "")}))
        sys.stdout.flush()

anyio.run(main)
```

---

### Comparison

| Pattern | Complexity | Scaling | Use When |
|---------|------------|---------|----------|
| **A: Direct SSE + DB** | Medium | Horizontal | Production, multi-user |
| **B: Fire-and-forget + Redis** | Low | Horizontal | Background jobs, async OK |
| **C: Subprocess stdout** | Lowest | Vertical only | Prototyping, single-tenant, low volume |

---

### What You Were Missing

1. **Laravel doesn't need to touch the stream** - it just needs to know when it's done
2. **JWT tokens let sidecar auth users** - Laravel stays the auth source of truth
3. **Shared DB/Redis is the integration point** - not HTTP proxying
4. **Webhooks close the loop** - sidecar notifies Laravel, Laravel updates app state

The proxy pattern makes sense when you need Laravel middleware on every chunk (rate limiting, logging, transformation). For most cases, you don't.




## OK, the streaming was a complex integration scenario

..., but what is a radically simple way of integrating NodeJS, Python, golang code into my system if main UX of endusers is driven through: - Laravel/Inertia/React webapp - for business users - same as above + Artisan/Symfony Console CLI commands - for a narrow group of admin users Regarding CLI / terminal users this feels like a trivial question, but please give me the options here too to expand my horizons.

The system we are discussing is enterprise scale Partner Relation Management OS a la Salesforce or Guidewire platform but for ecosystem management, managing partners and partnerships, partner driven sales and delivery, de facto - partner centric business management with agentic users and assistants embedded at every layer of the software.

## RESPONSE


---

## Integration Patterns: Polyglot Components in Laravel Enterprise Systems

*Ranked by simplicity and operational burden*

---

### The Core Insight

**Laravel is the orchestrator, not the runtime.** Other languages do specialized work; Laravel manages state, auth, and user experience.

---

## Pattern 1: Shared Database (Simplest)

*All components connect to the same PostgreSQL*

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Laravel   │     │   Python    │     │   Node.js   │
│   (main)    │     │  (AI/ML)    │     │  (realtime) │
└──────┬──────┘     └──────┬──────┘     └──────┬──────┘
       │                   │                   │
       └───────────────────┼───────────────────┘
                           ▼
                    ┌─────────────┐
                    │ PostgreSQL  │
                    │  (shared)   │
                    └─────────────┘
```

**How it works:**
- Laravel owns the schema (migrations)
- Other services connect read/write with their native drivers
- No HTTP calls, no queues—just SQL

**Python example:**
```python
# services/ai_scoring/main.py
import asyncpg
from claude_agent_sdk import query

async def score_partners():
    conn = await asyncpg.connect(DATABASE_URL)
    
    partners = await conn.fetch("""
        SELECT id, name, profile_data 
        FROM partners 
        WHERE ai_score IS NULL
    """)
    
    for partner in partners:
        score = await calculate_score(partner)
        await conn.execute(
            "UPDATE partners SET ai_score = $1, scored_at = NOW() WHERE id = $2",
            score, partner['id']
        )
```

**Laravel reads it:**
```php
// Just query normally - Python already updated the data
$scoredPartners = Partner::whereNotNull('ai_score')
    ->orderByDesc('ai_score')
    ->get();
```

**When to use:** Batch processing, background enrichment, analytics pipelines.

---

## Pattern 2: Job Queue with Polyglot Workers

*Laravel dispatches; any language consumes*

```
┌─────────────┐         ┌─────────────┐
│   Laravel   │ ──────► │    Redis    │
│  dispatch() │         │   Queue     │
└─────────────┘         └──────┬──────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
┌─────────────┐        ┌─────────────┐        ┌─────────────┐
│  PHP Worker │        │ Python      │        │ Go Worker   │
│  (Horizon)  │        │ Worker      │        │ (fast ops)  │
└─────────────┘        └─────────────┘        └─────────────┘
```

**Laravel dispatches:**
```php
// app/Jobs/AnalyzePartnerJob.php
class AnalyzePartnerJob implements ShouldQueue
{
    public $queue = 'ai';  // Python workers listen here
    
    public function __construct(
        public int $partnerId,
        public string $analysisType,
    ) {}
}

// Usage
AnalyzePartnerJob::dispatch($partner->id, 'risk_assessment');
```

**Python worker (using `rq` or raw Redis):**
```python
# workers/ai_worker.py
import redis
import json

r = redis.Redis()

while True:
    _, job_data = r.brpop('queues:ai')
    job = json.loads(job_data)
    
    if job['job'] == 'App\\Jobs\\AnalyzePartnerJob':
        partner_id = job['data']['partnerId']
        analysis_type = job['data']['analysisType']
        
        result = run_analysis(partner_id, analysis_type)
        
        # Write result to DB (Laravel reads it)
        # Or push to a results queue Laravel consumes
```

**Simpler: Use Laravel's job format with a Python library:**
```python
# pip install laravel-queue
from laravel_queue import Worker

worker = Worker(redis_url="redis://localhost", queue="ai")

@worker.job("App\\Jobs\\AnalyzePartnerJob")
def analyze_partner(partner_id: int, analysis_type: str):
    return run_analysis(partner_id, analysis_type)

worker.run()
```

**When to use:** Async work, decoupled processing, horizontal scaling.

---

## Pattern 3: HTTP Microservices (Internal APIs)

*Each component exposes REST; Laravel calls them*

```php
// app/Services/AIService.php
class AIService
{
    public function __construct(
        private HttpClient $client,
        private string $baseUrl = 'http://ai-service:8000',
    ) {}
    
    public function scorePartner(Partner $partner): float
    {
        $response = $this->client->post("{$this->baseUrl}/score", [
            'json' => [
                'partner_id' => $partner->id,
                'profile' => $partner->profile_data,
            ],
        ]);
        
        return $response->json('score');
    }
    
    public function generateProposal(Partner $partner, Deal $deal): string
    {
        $response = $this->client->post("{$this->baseUrl}/generate-proposal", [
            'json' => [
                'partner' => $partner->toArray(),
                'deal' => $deal->toArray(),
            ],
            'timeout' => 60,  // AI can be slow
        ]);
        
        return $response->json('proposal');
    }
}
```

**When to use:** Synchronous request/response, complex logic that needs its own runtime.

---

## Pattern 4: CLI Subprocess (Artisan Wraps Binaries)

*Artisan commands shell out to specialized tools*

```php
// app/Console/Commands/RunPartnerAnalysis.php
class RunPartnerAnalysis extends Command
{
    protected $signature = 'partners:analyze {--partner=} {--all}';
    
    public function handle()
    {
        $partners = $this->option('all') 
            ? Partner::all() 
            : Partner::where('id', $this->option('partner'))->get();
        
        foreach ($partners as $partner) {
            $this->info("Analyzing {$partner->name}...");
            
            $result = Process::path(base_path('tools'))
                ->timeout(300)
                ->run([
                    'python3', 'analyze_partner.py',
                    '--partner-id', $partner->id,
                    '--output', 'json',
                ]);
            
            if ($result->successful()) {
                $analysis = json_decode($result->output(), true);
                $partner->update(['analysis' => $analysis]);
                $this->info("  Score: {$analysis['score']}");
            } else {
                $this->error("  Failed: {$result->errorOutput()}");
            }
        }
    }
}
```

**When to use:** Admin tools, batch operations, data migrations, one-off scripts.

---

## Pattern 5: Embedded Binaries (Sidecar)

*Python/Node runs as a persistent service alongside Laravel*

```yaml
# docker-compose.yml
services:
  app:
    build: .
    depends_on: [db, redis, ai-service]
    
  ai-service:
    build: ./services/ai
    environment:
      DATABASE_URL: postgres://...
      REDIS_URL: redis://redis:6379
    
  queue-worker-php:
    build: .
    command: php artisan horizon
    
  queue-worker-python:
    build: ./services/ai
    command: python worker.py
```

**When to use:** Production deployments, multiple services need to coexist.

---

## CLI-Specific Patterns for Admin Users

### Option A: Artisan Delegates to Tools

```php
// php artisan ai:chat "What partners should we prioritize?"
class AIChatCommand extends Command
{
    protected $signature = 'ai:chat {prompt}';
    
    public function handle()
    {
        $process = Process::tty()->run([
            'python3', base_path('tools/chat.py'),
            '--prompt', $this->argument('prompt'),
        ]);
    }
}
```

### Option B: Makefile Orchestration

```makefile
# Makefile - unified CLI for all tools
.PHONY: analyze score report

analyze:  # Run Python analysis
	cd tools && python analyze.py --all

score:    # Run Go scoring engine  
	./bin/scorer --partners-file=export.json

report:   # Laravel generates report
	php artisan partners:report --format=pdf

full-pipeline: analyze score report
	@echo "Pipeline complete"
```

### Option C: Unified CLI Wrapper (Recommended for Enterprise)

```bash
#!/usr/bin/env bash
# bin/prm - unified entrypoint

case "$1" in
  artisan|a)
    shift; php artisan "$@" ;;
  ai)
    shift; python3 tools/ai_cli.py "$@" ;;
  score)
    shift; ./bin/scorer "$@" ;;
  *)
    echo "Usage: prm <artisan|ai|score> [args]" ;;
esac
```

```bash
# Admin usage
prm artisan partners:list
prm ai analyze --partner=123
prm score --recalculate
```

### Option D: Interactive REPL for Power Users

```python
# tools/prm_shell.py
import cmd
import subprocess

class PRMShell(cmd.Cmd):
    prompt = 'prm> '
    
    def do_partner(self, arg):
        """Partner commands: partner list, partner show <id>"""
        subprocess.run(['php', 'artisan', f'partner:{arg}'])
    
    def do_ai(self, arg):
        """AI commands: ai analyze <id>, ai chat <prompt>"""
        subprocess.run(['python3', 'ai_cli.py'] + arg.split())
    
    def do_sql(self, arg):
        """Run raw SQL"""
        subprocess.run(['psql', DATABASE_URL, '-c', arg])

if __name__ == '__main__':
    PRMShell().cmdloop()
```

---

## Recommended Architecture for Your PRM Platform

```
┌─────────────────────────────────────────────────────────────────┐
│                         User Interfaces                          │
├─────────────────────────────────────────────────────────────────┤
│  Laravel/Inertia/React    │   Artisan CLI    │   prm shell      │
│  (business users)         │   (admins)       │   (power users)  │
└─────────────────┬─────────┴────────┬─────────┴────────┬─────────┘
                  │                  │                  │
                  ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Laravel Application                           │
│  • Auth, sessions, permissions                                   │
│  • Business logic, workflows                                     │
│  • API endpoints (Inertia + REST)                               │
│  • Job dispatching                                               │
└───────────────────────────────┬─────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
┌───────────────┐       ┌───────────────┐       ┌───────────────┐
│  PHP Workers  │       │ Python Workers│       │  Go Services  │
│  (Horizon)    │       │ (AI/ML)       │       │  (scoring)    │
│               │       │               │       │               │
│ • Email       │       │ • Claude SDK  │       │ • Batch calc  │
│ • Webhooks    │       │ • Analysis    │       │ • Import/exp  │
│ • Workflows   │       │ • Embeddings  │       │ • Validation  │
└───────┬───────┘       └───────┬───────┘       └───────┬───────┘
        │                       │                       │
        └───────────────────────┼───────────────────────┘
                                ▼
                    ┌───────────────────────┐
                    │  PostgreSQL + Redis   │
                    │  (shared state)       │
                    └───────────────────────┘
```

---

## Integration Decision Matrix

| Need | Pattern | Example |
|------|---------|---------|
| AI enriches data async | Queue + DB | Partner scoring runs hourly |
| AI responds in UI | HTTP service | Chat widget calls AI service |
| Batch admin operation | CLI subprocess | `artisan partners:rescore` |
| Real-time streaming | Direct SSE | Proposal generation |
| High-throughput compute | Go service + queue | Bulk import validation |
| ML model inference | Python HTTP service | Risk prediction API |

---

## What Not to Do

| Anti-pattern | Why It Fails | Instead |
|--------------|--------------|---------|
| PHP FFI to Python | Fragile, debugging nightmare | HTTP or queue |
| Microservices for everything | Ops overhead, latency | Shared DB + queue |
| Custom RPC protocol | Maintenance burden | REST or gRPC |
| Embedding Python in PHP process | Memory leaks, crashes | Subprocess or service |
| Separate DBs per service | Data sync hell | Single PostgreSQL |

---

## Files to Create

```
├── app/
│   ├── Services/
│   │   └── AIService.php           # HTTP client to AI service
│   └── Console/Commands/
│       └── PartnerAnalyzeCommand.php
├── services/
│   ├── ai/                         # Python AI service
│   │   ├── main.py                 # FastAPI app
│   │   ├── worker.py               # Queue consumer
│   │   └── Dockerfile
│   └── scorer/                     # Go scoring service
│       ├── main.go
│       └── Dockerfile
├── tools/                          # CLI scripts
│   ├── analyze_partner.py
│   └── ai_cli.py
├── bin/
│   └── prm                         # Unified CLI wrapper
├── docker-compose.yml
└── Makefile
```

## QUESTION: The context of this design

We are building agent centric partner relationship management / channel management / ecosystem led growth operating system for companies who want to accelerate and automate their ecosystems of vendors, implementation partners, consulting partners, system integrators, innovation partners, technology partners, distribution and marketing partners, networks of independent collaborating consultants, or startups working for the company vertical, venture funds run by the company, etc. Basically we believe that future market shapers will not be individual corporations, but networks of business connected by intelligent agents identifying joint opportunities, faciliting co-sales and co-delivery, driving co-marketing and demand generation and assisting ecosystem participants in co-innovation.

Currently I have the system as a single Laravel webapp with REST APIs. Cross-cutting platform features code is in: app/Features/<feature-name>/
Examples of features are OptimisticLocking, Dictionaries, CustomFields, EntityLinks, Hierarchy, ActivityLog, PublicId, and many more.
Modules (currently part of Laravel app) are stored in app/Modules/<module-name> and resources/ for views and React code. Modules are domain specific functionalities, usually centered around specific areas of domain data model - People, Organizations, Projects, Deals, Partnerships, Products, Offers, Implementations, and many more.
I need to modularize the solution in a way that will keep current Laravel webapp as one of the components, but not the center of our system. The reason is to isolate system components (apps / subsystems) and allow them to be independently developed, if needed using different tech stacks.

Help me find a proven architecture and mental model for the solution that will scale and allow hyper parallelized development of the platform, so I can use not 2-3 teams as currently but 50 independent teams working on system components (e.g. functional modules) so we can shorted the program schedule by an order of magnitude (from 5 years to <1 year).

How other platforms like this one are designed to allow hyper disconnected development of the components yet remain manageable and deliver consistent and coherent user experience - both for business users and admins operating the solution.

## RESPONSE

This is the right question at the right time. You're describing the transition from "application" to "platform"—the same inflection point Salesforce, ServiceNow, and Atlassian navigated.

---

## The Core Problem

**50 teams cannot work on one Laravel app.** Even with perfect git hygiene, you'll have:
- Merge conflicts as the primary work product
- Shared database migrations as a bottleneck
- One team's bug breaks everyone
- Deployment coordination becomes a full-time job for 10 people

**The solution is not microservices.** That's 50 teams building 50 APIs that don't compose. You'll get a distributed monolith with network calls instead of function calls.

---

## The Mental Model: Platform Kernel + Autonomous Modules

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           User Experience Layer                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                    Unified Shell / Chrome                        │    │
│  │   Navigation │ Search │ Notifications │ Command Palette          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                  │                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│  │ Partners │ │  Deals   │ │ Projects │ │  People  │ │    ...   │      │
│  │  Module  │ │  Module  │ │  Module  │ │  Module  │ │ (N more) │      │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘      │
└───────┼────────────┼────────────┼────────────┼────────────┼─────────────┘
        │            │            │            │            │
        ▼            ▼            ▼            ▼            ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Platform Kernel                                  │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐       │
│  │  Identity   │ │   Entity    │ │   Event     │ │     UI      │       │
│  │  & Access   │ │   Registry  │ │    Bus      │ │  Registry   │       │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘       │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐       │
│  │  Storage    │ │   Search    │ │  Workflow   │ │   Agent     │       │
│  │  Gateway    │ │   Index     │ │   Engine    │ │  Runtime    │       │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Architectural Pattern: Kernel + Cell-Based Modules

### What the Kernel Owns (Small Core Team, 5-10 people)

| Capability | What It Does | Why Kernel |
|------------|--------------|------------|
| **Identity & Access** | Auth, tenancy, permissions, API keys | Security boundary |
| **Entity Registry** | Schema registry, entity metadata, relationships | Enables cross-module linking |
| **Event Bus** | Domain events, async messaging | Decouples modules |
| **UI Shell** | Navigation, layout, search, notifications | UX coherence |
| **Storage Gateway** | Multi-tenant data access, audit | Compliance, isolation |
| **Search Index** | Unified search across all entities | Cross-cutting UX |
| **Workflow Engine** | Cross-module automation | Business process spans modules |
| **Agent Runtime** | AI agent execution, tool registry | Core differentiator |

### What Modules Own (50 Teams, 1 per module)

Each module is a **self-contained cell** with:
- Its own database schema (namespaced tables or separate DB)
- Its own API surface (registered with gateway)
- Its own UI components (registered with shell)
- Its own domain events (published to bus)
- Its own team, repo, deployment pipeline

```
Module: Partners
├── api/                    # REST/GraphQL endpoints
│   ├── routes.yaml         # Registered with API gateway
│   └── handlers/
├── domain/                 # Business logic
│   ├── entities/
│   ├── services/
│   └── events/             # PartnerCreated, PartnerTierChanged, etc.
├── ui/                     # React components
│   ├── pages/
│   ├── components/
│   └── manifest.json       # Registered with UI shell
├── storage/
│   ├── migrations/
│   └── repositories/
├── agents/                 # AI agent definitions
│   └── partner-analyst.yaml
└── module.yaml             # Module manifest
```

---

## The Module Manifest: Contract with the Platform

```yaml
# modules/partners/module.yaml
name: partners
version: 2.4.0
team: partners-team

# What this module provides
provides:
  entities:
    - name: Partner
      schema: ./schemas/partner.json
      permissions:
        - partners.view
        - partners.edit
        - partners.delete
    - name: PartnerTier
      schema: ./schemas/partner-tier.json
      
  events:
    published:
      - PartnerCreated
      - PartnerTierChanged
      - PartnerHealthScoreUpdated
    
  ui:
    pages:
      - path: /partners
        component: PartnerListPage
        navigation:
          label: Partners
          icon: building
          section: ecosystem
      - path: /partners/:id
        component: PartnerDetailPage
        
    widgets:
      - name: PartnerCard
        description: Compact partner summary for embedding
        props:
          partnerId: { type: string, required: true }
          
    slots:
      # Slots this module fills in other modules' UIs
      - target: deals.detail.sidebar
        component: RelatedPartnersWidget
        
  api:
    base: /api/v1/partners
    spec: ./openapi.yaml
    
  agents:
    tools:
      - name: search_partners
        description: Find partners by criteria
        handler: ./agents/tools/search.py
      - name: get_partner_health
        handler: ./agents/tools/health.py

# What this module needs from others
requires:
  entities:
    - organizations.Organization  # Links to org module
    - people.Person               # Links to people module
    
  events:
    subscribed:
      - deals.DealClosed          # Update partner stats
      - projects.ProjectCompleted # Update partner delivery score
      
  permissions:
    - organizations.view          # To display linked orgs
```

---

## How Modules Integrate

### 1. Entity Linking (Not Foreign Keys)

Modules don't share tables. They reference entities by **URN**:

```
urn:prm:partners:partner:abc123
urn:prm:deals:deal:xyz789
urn:prm:people:person:def456
```

```php
// In Deals module - referencing a Partner
class Deal extends Entity
{
    // Not a foreign key - a URN reference
    public function partnerUrn(): string
    {
        return $this->attributes['partner_urn'];
    }
    
    // Resolved at runtime via Entity Registry
    public function partner(): ?EntityReference
    {
        return $this->platform->entities()->resolve($this->partnerUrn());
    }
}
```

```typescript
// Kernel: Entity Registry API
interface EntityReference {
  urn: string;
  type: string;
  displayName: string;
  url: string;
  summary: Record<string, any>;  // Module-defined summary fields
}

// Modules register how to resolve their entities
entityRegistry.register('partners.Partner', {
  resolve: async (id) => partnerService.getSummary(id),
  search: async (query) => partnerService.search(query),
  permissions: (id, user) => permissionService.check(user, 'partners.view', id),
});
```

### 2. Events (Async Decoupling)

```php
// Partners module publishes
class PartnerTierChanged extends DomainEvent
{
    public function __construct(
        public string $partnerUrn,
        public string $oldTier,
        public string $newTier,
        public string $changedBy,
    ) {}
}

// Kernel routes to subscribers
$eventBus->publish(new PartnerTierChanged(...));
```

```python
# Deals module subscribes (can be Python!)
@event_handler("partners.PartnerTierChanged")
async def update_deal_terms(event: dict):
    """When partner tier changes, flag deals for term review."""
    partner_urn = event["partner_urn"]
    new_tier = event["new_tier"]
    
    deals = await deal_repo.find_by_partner(partner_urn)
    for deal in deals:
        if deal.tier_locked != new_tier:
            await deal.flag_for_review("Partner tier changed")
```

### 3. UI Composition (Micro-Frontends)

```typescript
// Kernel: UI Shell loads module UIs dynamically
interface ModuleUI {
  name: string;
  pages: PageRegistration[];
  widgets: WidgetRegistration[];
  slots: SlotRegistration[];  // Components to inject into other modules
}

// Shell renders module content in standardized frame
function ModulePage({ module, page, params }) {
  const ModuleComponent = useModuleComponent(module, page);
  
  return (
    <ShellFrame 
      navigation={<MainNav />}
      breadcrumbs={<Breadcrumbs module={module} />}
    >
      <Suspense fallback={<PageSkeleton />}>
        <ModuleComponent params={params} />
      </Suspense>
      
      {/* Slots: other modules inject content here */}
      <SlotContainer location={`${module}.${page}.sidebar`} />
    </ShellFrame>
  );
}
```

```typescript
// Partners module registers a widget for Deals module
// modules/partners/ui/widgets/RelatedPartnersWidget.tsx
export function RelatedPartnersWidget({ dealUrn }: { dealUrn: string }) {
  const { data: partners } = useQuery(['deal-partners', dealUrn], () =>
    api.partners.findByDeal(dealUrn)
  );
  
  return (
    <WidgetCard title="Related Partners">
      {partners?.map(p => <PartnerMiniCard key={p.urn} partner={p} />)}
    </WidgetCard>
  );
}

// Registered in manifest.yaml to appear in deals.detail.sidebar slot
```

---

## How 50 Teams Work Without Stepping on Each Other

### Team Topology

```
┌─────────────────────────────────────────────────────────────────┐
│                      Platform Team (10)                          │
│   Kernel development, contracts, developer experience            │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Enablement Teams (5-10)                        │
│   Design system, testing tools, CI/CD, documentation             │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
┌───────────────┐       ┌───────────────┐       ┌───────────────┐
│ Module Teams  │       │ Module Teams  │       │ Module Teams  │
│   (40-50)     │       │   (cont.)     │       │   (cont.)     │
│               │       │               │       │               │
│ • Partners    │       │ • Analytics   │       │ • Marketplace │
│ • Deals       │       │ • Reporting   │       │ • Billing     │
│ • Projects    │       │ • Dashboards  │       │ • Integtic    │
│ • People      │       │ • Forecasting │       │ • Connectors  │
│ • Orgs        │       │ • Scoring     │       │ • Webhooks    │
│ • Products    │       │ • AI Agents   │       │ • ...         │
└───────────────┘       └───────────────┘       └───────────────┘
```

### Rules of Engagement

| Rule | Why |
|------|-----|
| **Modules never import from other modules** | Direct coupling = coordination |
| **All cross-module data via Entity Registry** | Standardized resolution |
| **All cross-module behavior via Events** | Async, no runtime coupling |
| **All UI injection via Slots** | No touching other module's code |
| **Schema changes require contract review** | Breaking changes affect consumers |
| **Each module has its own deployment pipeline** | Independent velocity |

### Module Independence Test

A module is correctly bounded if:
- [ ] It can be developed with only the kernel + mock events
- [ ] It can be deployed without deploying any other module
- [ ] It can be tested without any other module running
- [ ] Its team can go on vacation and no other team is blocked

---

## Platform Services Architecture

### Kernel Services

```yaml
# infrastructure/kernel-services.yaml
services:
  
  # API Gateway - routes to modules, handles auth
  gateway:
    type: kong/apisix
    responsibilities:
      - Route /api/v1/partners/* to partners-module
      - Route /api/v1/deals/* to deals-module
      - JWT validation
      - Rate limiting
      - API versioning
      
  # Entity Registry - cross-module entity resolution
  entity-registry:
    type: custom (Go/Rust for speed)
    responsibilities:
      - Register entity types from modules
      - Resolve URNs to summaries
      - Batch resolution for UI
      - Permission-filtered resolution
      
  # Event Bus - async module communication  
  event-bus:
    type: kafka/rabbitmq + schema-registry
    responsibilities:
      - Publish domain events
      - Route to subscribers
      - Event schema validation
      - Dead letter handling
      - Event replay for new modules
      
  # UI Shell - micro-frontend orchestration
  ui-shell:
    type: single-spa / module-federation
    responsibilities:
      - Load module UIs dynamically
      - Shared authentication state
      - Navigation composition
      - Slot system for injection
      - Design system enforcement
      
  # Search Index - unified search
  search:
    type: elasticsearch/meilisearch
    responsibilities:
      - Index all entities from all modules
      - Federated search across modules
      - Permission-aware results
      
  # Agent Runtime - AI agent execution
  agent-runtime:
    type: custom (Python)
    responsibilities:
      - Load agent definitions from modules
      - Execute agent loops
      - Tool invocation across modules
      - Conversation state management
```

### Data Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        PostgreSQL Cluster                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   kernel     │  │   partners   │  │    deals     │  ...     │
│  │   schema     │  │   schema     │  │   schema     │          │
│  │              │  │              │  │              │          │
│  │ • users      │  │ • partners   │  │ • deals      │          │
│  │ • tenants    │  │ • tiers      │  │ • stages     │          │
│  │ • permissions│  │ • scores     │  │ • activities │          │
│  │ • events_log │  │              │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│  Row-Level Security: tenant_id on every table                   │
│  Each module only has access to its own schema                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

**Key principle:** One database cluster, separate schemas. Modules cannot query other schemas directly—only through Entity Registry.

---

## Migration Path from Current State

### Phase 1: Extract Kernel (Months 1-3)

```
Current State                    Target State
─────────────────               ─────────────────
app/                            kernel/
├── Features/                   ├── identity/
│   ├── OptimisticLocking/     ├── entity-registry/
│   ├── Dictionaries/          ├── event-bus/
│   ├── CustomFields/          ├── ui-shell/
│   ├── EntityLinks/     →     └── ...
│   ├── Hierarchy/             
│   ├── ActivityLog/           modules/
│   └── PublicId/              └── legacy-laravel/  ← Everything else
│                                  ├── partners/
├── Modules/                       ├── deals/
│   ├── Partners/                  └── ...
│   ├── Deals/
│   └── ...
```

**Actions:**
1. Define Entity Registry contract (URN scheme, resolution API)
2. Define Event Bus contract (event schemas, publishing API)
3. Wrap existing features as kernel services
4. Current Laravel app becomes the "legacy module" (still works)

### Phase 2: Extract First Module (Months 3-4)

Pick a **leaf module** (few dependencies): e.g., ActivityLog or Notifications.

```bash
# New repo: modules/activity-log
modules/activity-log/
├── api/
├── domain/
├── ui/
├── storage/
├── module.yaml
└── docker-compose.yml  # Runs independently
```

**Success criteria:**
- Module deploys independently
- Module team doesn't need Laravel codebase
- Events flow between legacy and new module

### Phase 3: Parallel Extraction (Months 4-12)

```
Month 4-5:   Extract 3-5 more modules
Month 5-6:   Onboard 10 new teams to new modules
Month 6-8:   Extract remaining modules in parallel
Month 8-10:  New feature development on platform
Month 10-12: Deprecate legacy monolith
```

### Strangler Fig Pattern

```
                    ┌─────────────────────┐
                    │    API Gateway      │
                    └──────────┬──────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
    ┌───────────┐        ┌───────────┐        ┌───────────┐
    │  Legacy   │        │  New      │        │  New      │
    │  Laravel  │        │ Partners  │        │  Deals    │
    │  Module   │        │  Module   │        │  Module   │
    └───────────┘        └───────────┘        └───────────┘
          │                    │                    │
          └────────────────────┴────────────────────┘
                               │
                    ┌──────────┴──────────┐
                    │    Event Bus        │
                    └─────────────────────┘
```

Traffic gradually shifts from legacy to new modules as they're ready.

---

## UX Coherence at Scale

### Design System as Law

```
design-system/
├── tokens/
│   ├── colors.json
│   ├── typography.json
│   └── spacing.json
├── components/
│   ├── Button/
│   ├── Card/
│   ├── DataTable/
│   ├── EntityCard/      # Standard entity display
│   ├── EntityPicker/    # Standard entity selection
│   ├── WidgetCard/      # Standard widget container
│   └── ...
├── patterns/
│   ├── ListPage.mdx     # How to build list pages
│   ├── DetailPage.mdx   # How to build detail pages
│   └── FormPage.mdx     # How to build forms
└── package.json         # Published as npm package
```

**Enforcement:**
- All modules must use design system components
- CI fails if non-system components used for standard patterns
- Design review required for new patterns

### Module UI Contract

```typescript
// Every module's list page follows this structure
interface ListPageContract {
  // Standard filters panel
  filters: FilterDefinition[];
  
  // Standard columns with known types
  columns: ColumnDefinition[];
  
  // Standard actions
  actions: {
    create?: ActionDefinition;
    bulkActions?: ActionDefinition[];
    rowActions?: ActionDefinition[];
  };
  
  // Standard empty state
  emptyState: EmptyStateDefinition;
}

// Shell validates module pages against contracts
// Non-compliant pages flagged in dev, blocked in prod
```

---

## Agent Architecture for Ecosystem Platform

Since AI agents are your core differentiator:

```yaml
# Agent Definition Schema
# modules/partners/agents/partner-analyst.yaml
name: partner-analyst
version: 1.0.0
description: Analyzes partner health and recommends actions

triggers:
  - event: partners.PartnerCreated
  - event: deals.DealClosed
    filter: "event.partner_urn != null"
  - schedule: "0 6 * * *"  # Daily at 6am
  - manual: true

tools:
  # Tools from this module
  - partners.search_partners
  - partners.get_partner_health
  - partners.update_partner_score
  
  # Tools from other modules (via kernel)
  - deals.get_deals_by_partner
  - projects.get_delivery_metrics
  - people.get_contacts
  
  # Platform tools
  - kernel.send_notification
  - kernel.create_task
  - kernel.search_all

system_prompt: |
  You are a partner relationship analyst for {tenant.name}.
  Your role is to monitor partner health and recommend actions
  to partnership managers.
  
  Guidelines:
  - Flag partners with declining deal velocity
  - Identify upsell opportunities based on delivery success
  - Recommend tier changes based on performance
  
output:
  type: structured
  schema:
    recommendations:
      type: array
      items:
        partner_urn: string
        action: enum[review, escalate, celebrate, tier_change]
        rationale: string
        urgency: enum[low, medium, high]
```

### Agent Runtime Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       Agent Runtime                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │   Agent     │  │    Tool     │  │  Execution  │             │
│  │  Registry   │  │  Registry   │  │   Engine    │             │
│  │             │  │             │  │  (Python)   │             │
│  │ Load from   │  │ Tools from  │  │             │             │
│  │ all modules │  │ all modules │  │ Claude SDK  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│         │                │                │                     │
│         └────────────────┼────────────────┘                     │
│                          │                                      │
│  ┌───────────────────────┴───────────────────────┐             │
│  │              Tool Invocation                   │             │
│  │                                                │             │
│  │  Agent calls tool → Runtime resolves module   │             │
│  │  → Invokes module's tool API → Returns result │             │
│  └────────────────────────────────────────────────┘             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Governance for 50 Teams

### Architecture Decision Records (ADRs)

```markdown
# ADR-0042: Entity Reference Format

## Status
Accepted

## Context
Modules need to reference entities in other modules without direct coupling.

## Decision
All cross-module entity references use URN format:
`urn:prm:{module}:{entity-type}:{id}`

## Consequences
- Modules cannot use foreign keys across schemas
- Entity Registry must resolve all URNs
- Breaking change if URN format changes
```

### Module Certification

Before a module goes to production:

- [ ] Passes contract validation (manifest schema)
- [ ] Uses only design system components
- [ ] Events match registered schemas
- [ ] API matches OpenAPI spec
- [ ] Security review passed
- [ ] Performance budget met
- [ ] Documentation complete

### Weekly Platform Sync

```
Agenda (30 min):
1. Contract changes proposed this week (5 min)
2. Breaking changes in flight (5 min)
3. Cross-module dependencies identified (10 min)
4. Platform team announcements (5 min)
5. Open forum (5 min)

Attendees: One rep per module team (async update OK)
```

---

## Reference Architectures

| Platform | Key Pattern | What to Learn |
|----------|-------------|---------------|
| **Salesforce** | Metadata-driven, multi-tenant kernel | Entity schema as config, not code |
| **Shopify** | Apps + Admin API + Webhooks | Third-party module integration |
| **Atlassian Connect** | Iframe-based UI composition | Micro-frontend at scale |
| **SAP** | ABAP Dictionary + RFC | Centralized schema registry |
| **Stripe** | API-first, embeddable components | Developer experience |
| **ServiceNow** | Scoped applications | Module isolation in shared runtime |

---

## Summary: Your Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     PRM Platform Architecture                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  KERNEL (Platform Team)                                             │
│  ├── Identity & Multi-tenancy                                       │
│  ├── Entity Registry (URN resolution)                               │
│  ├── Event Bus (Kafka + Schema Registry)                            │
│  ├── UI Shell (Module Federation)                                   │
│  ├── API Gateway (Kong/APISIX)                                      │
│  ├── Search (Elasticsearch)                                         │
│  ├── Agent Runtime (Python + Claude SDK)                            │
│  └── Workflow Engine                                                │
│                                                                      │
│  MODULES (50 Teams)                                                 │
│  ├── Core: Partners, Deals, Projects, People, Organizations        │
│  ├── Products: Catalog, Offers, Pricing, Bundles                   │
│  ├── Operations: Implementations, Support, Training                │
│  ├── Growth: Campaigns, Events, Content, Referrals                 │
│  ├── Intelligence: Analytics, Scoring, Forecasting, Reports        │
│  ├── Agents: Partner Analyst, Deal Coach, Ecosystem Scout          │
│  └── Platform: Settings, Integrations, Marketplace, Billing        │
│                                                                      │
│  ENABLEMENT                                                         │
│  ├── Design System (npm package)                                    │
│  ├── Module Template (cookiecutter)                                 │
│  ├── Testing Framework                                              │
│  └── Documentation Site                                             │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Next Steps

| Week | Action |
|------|--------|
| 1 | Define URN scheme and Entity Registry contract |
| 2 | Define Event schema format and bus contract |
| 3 | Define Module manifest schema |
| 4 | Build kernel scaffolding (can be Laravel initially) |
| 5-6 | Extract first module, prove independence |
| 7-8 | Document patterns, create module template |
| 9+ | Begin parallel team onboarding |

The kernel can start as Laravel—what matters is the **contracts**. Once contracts are stable, kernel services can be rewritten in any language. Modules can be any language from day one.
