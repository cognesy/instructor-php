# Laravel Integration Guide

## Overview

This document outlines how to integrate the bd/bv PHP API into Laravel as a first-class service with facades, service providers, Artisan commands, and proper dependency injection.

## Package Structure

```
app/Services/Beads/
├── BeadsServiceProvider.php      # Laravel service provider
├── Facades/
│   └── Beads.php                 # Facade for convenient access
├── Client/
│   ├── BdClient.php              # bd command wrapper
│   ├── BvClient.php              # bv command wrapper
│   └── BeadsManager.php          # Unified interface
├── Executor/
│   ├── CanExecuteCommand.php    # Interface
│   ├── ProcessExecutor.php      # Symfony Process implementation
│   └── SandboxedExecutor.php    # Optional sandboxed version
├── Data/
│   ├── Issue.php                 # Value object
│   ├── IssueCollection.php       # Collection
│   ├── CreateIssueRequest.php    # DTO
│   ├── UpdateIssueRequest.php    # DTO
│   ├── IssueFilter.php           # Query builder
│   └── GraphInsights.php         # bv metrics VO
├── Exceptions/
│   ├── BeadsException.php        # Base exception
│   ├── BdCommandException.php    # Command errors
│   └── IssueNotFoundException.php # Not found
└── Console/
    ├── IssuesListCommand.php     # artisan beads:list
    ├── IssuesShowCommand.php     # artisan beads:show
    ├── IssuesCreateCommand.php   # artisan beads:create
    ├── InsightsCommand.php       # artisan beads:insights
    └── PlanCommand.php           # artisan beads:plan

config/
└── beads.php                     # Configuration file

tests/Unit/Beads/
└── ...                           # Unit tests

tests/Feature/Beads/
└── ...                           # Integration tests
```

## Configuration File

```php
<?php
// config/beads.php

return [
    /*
    |--------------------------------------------------------------------------
    | bd Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the bd executable. Defaults to system PATH lookup.
    |
    */
    'bd_binary' => env('BD_BINARY', '/usr/local/bin/bd'),

    /*
    |--------------------------------------------------------------------------
    | bv Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the bv executable. Defaults to system PATH lookup.
    |
    */
    'bv_binary' => env('BV_BINARY', '/usr/local/bin/bv'),

    /*
    |--------------------------------------------------------------------------
    | Working Directory
    |--------------------------------------------------------------------------
    |
    | The directory where .beads/ is located. Usually the project root.
    |
    */
    'working_dir' => env('BD_WORKING_DIR', base_path()),

    /*
    |--------------------------------------------------------------------------
    | Execution Timeout
    |--------------------------------------------------------------------------
    |
    | Default timeout for bd/bv commands in seconds.
    |
    */
    'timeout' => (int) env('BD_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Idle Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout for commands producing no output in seconds.
    |
    */
    'idle_timeout' => (int) env('BD_IDLE_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Configuration
    |--------------------------------------------------------------------------
    |
    | Enable sandboxing for extra security (requires firejail, docker, etc).
    |
    */
    'use_sandbox' => env('BD_USE_SANDBOX', false),
    'sandbox_driver' => env('BD_SANDBOX_DRIVER', 'firejail'), // firejail|docker|podman|bubblewrap

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache bv insights to improve performance for read-heavy workloads.
    |
    */
    'cache_insights' => env('BD_CACHE_INSIGHTS', true),
    'cache_ttl' => (int) env('BD_CACHE_TTL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Retry logic for handling database lock contention.
    |
    */
    'max_retries' => (int) env('BD_MAX_RETRIES', 3),
    'retry_delay' => (int) env('BD_RETRY_DELAY', 100), // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log bd/bv command execution for debugging.
    |
    */
    'log_commands' => env('BD_LOG_COMMANDS', false),
    'log_channel' => env('BD_LOG_CHANNEL', 'stack'),
];
```

## Service Provider

```php
<?php
// app/Services/Beads/BeadsServiceProvider.php

namespace App\Services\Beads;

use Illuminate\Support\ServiceProvider;
use App\Services\Beads\Client\BeadsManager;
use App\Services\Beads\Client\BdClient;
use App\Services\Beads\Client\BvClient;
use App\Services\Beads\Executor\CanExecuteCommand;
use App\Services\Beads\Executor\ProcessExecutor;
use App\Services\Beads\Executor\SandboxedExecutor;
use App\Services\Beads\Console\IssuesListCommand;
use App\Services\Beads\Console\IssuesShowCommand;
use App\Services\Beads\Console\IssuesCreateCommand;
use App\Services\Beads\Console\InsightsCommand;
use App\Services\Beads\Console\PlanCommand;

class BeadsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/beads.php', 'beads'
        );

        // Register command executor
        $this->app->singleton(CanExecuteCommand::class, function ($app) {
            if (config('beads.use_sandbox')) {
                return new SandboxedExecutor(
                    driver: config('beads.sandbox_driver'),
                    timeout: config('beads.timeout'),
                );
            }

            return new ProcessExecutor(
                timeout: config('beads.timeout'),
                idleTimeout: config('beads.idle_timeout'),
            );
        });

        // Register bd client
        $this->app->singleton(BdClient::class, function ($app) {
            return new BdClient(
                executor: $app->make(CanExecuteCommand::class),
                workingDir: config('beads.working_dir'),
                bdBinary: config('beads.bd_binary'),
                maxRetries: config('beads.max_retries'),
                retryDelay: config('beads.retry_delay'),
            );
        });

        // Register bv client
        $this->app->singleton(BvClient::class, function ($app) {
            return new BvClient(
                executor: $app->make(CanExecuteCommand::class),
                workingDir: config('beads.working_dir'),
                bvBinary: config('beads.bv_binary'),
                cacheTtl: config('beads.cache_ttl'),
            );
        });

        // Register unified manager
        $this->app->singleton(BeadsManager::class, function ($app) {
            return new BeadsManager(
                bd: $app->make(BdClient::class),
                bv: $app->make(BvClient::class),
            );
        });

        // Alias for facade
        $this->app->alias(BeadsManager::class, 'beads');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../../config/beads.php' => config_path('beads.php'),
        ], 'beads-config');

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                IssuesListCommand::class,
                IssuesShowCommand::class,
                IssuesCreateCommand::class,
                InsightsCommand::class,
                PlanCommand::class,
            ]);
        }
    }
}
```

## Facade

```php
<?php
// app/Services/Beads/Facades/Beads.php

namespace App\Services\Beads\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Beads\Data\IssueCollection issues(?\App\Services\Beads\Data\IssueFilter $filter = null)
 * @method static \App\Services\Beads\Data\IssueCollection openIssues()
 * @method static \App\Services\Beads\Data\IssueCollection closedIssues()
 * @method static \App\Services\Beads\Data\IssueCollection readyWork(int $limit = 10)
 * @method static \App\Services\Beads\Data\IssueCollection blockedIssues()
 * @method static \App\Services\Beads\Data\Issue find(string $id)
 * @method static \App\Services\Beads\Data\Issue create(\App\Services\Beads\Data\CreateIssueRequest $request)
 * @method static \App\Services\Beads\Data\Issue update(string $id, \App\Services\Beads\Data\UpdateIssueRequest $request)
 * @method static \App\Services\Beads\Data\Issue close(string $id, string $reason)
 * @method static \App\Services\Beads\Data\GraphInsights insights()
 * @method static array plan()
 * @method static array priority()
 * @method static array stats()
 * @method static \App\Services\Beads\Client\BdClient bd()
 * @method static \App\Services\Beads\Client\BvClient bv()
 *
 * @see \App\Services\Beads\Client\BeadsManager
 */
class Beads extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'beads';
    }
}
```

## Usage Examples

### Basic Usage

```php
<?php

use App\Services\Beads\Facades\Beads;
use App\Services\Beads\Data\IssueFilter;
use App\Services\Beads\Data\CreateIssueRequest;
use App\Services\Beads\Data\UpdateIssueRequest;

// List issues
$openIssues = Beads::openIssues();
foreach ($openIssues as $issue) {
    echo "{$issue->id}: {$issue->title}\n";
}

// Filter issues
$highPriority = Beads::issues(
    IssueFilter::create()
        ->status('open')
        ->priority(0)
);

// Find specific issue
$issue = Beads::find('bd-abc123');

// Create issue
$newIssue = Beads::create(new CreateIssueRequest(
    title: '[feature] Add user dashboard',
    type: 'feature',
    priority: 1,
    description: 'Comprehensive user analytics dashboard',
));

// Update issue
$updated = Beads::update($newIssue->id, new UpdateIssueRequest(
    status: 'in_progress',
    assignee: 'john@example.com',
));

// Close issue
$closed = Beads::close($newIssue->id, 'Implemented and tested');

// Get ready work
$ready = Beads::readyWork(limit: 5);

// Get blocked issues
$blocked = Beads::blockedIssues();

// Get graph insights
$insights = Beads::insights();
echo "PageRank leaders:\n";
foreach ($insights->topPageRank(5) as $id => $score) {
    $issue = Beads::find($id);
    echo "  {$issue->title}: {$score}\n";
}

// Get execution plan
$plan = Beads::plan();
echo "Parallel tracks: " . count($plan['tracks']) . "\n";

// Get statistics
$stats = Beads::stats();
echo "Open: {$stats['open']}, Closed: {$stats['closed']}\n";
```

### Controller Example

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Beads\Facades\Beads;
use App\Services\Beads\Data\CreateIssueRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IssueController extends Controller
{
    public function index()
    {
        $issues = Beads::openIssues();
        $stats = Beads::stats();

        return Inertia::render('Issues/Index', [
            'issues' => $issues->all(),
            'stats' => $stats,
        ]);
    }

    public function show(string $id)
    {
        $issue = Beads::find($id);
        $dependencies = Beads::bd()->dependencyTree($id);

        return Inertia::render('Issues/Show', [
            'issue' => $issue,
            'dependencies' => $dependencies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'type' => 'required|in:task,bug,feature,epic',
            'priority' => 'required|integer|min:0|max:4',
            'description' => 'nullable|string',
        ]);

        $issue = Beads::create(new CreateIssueRequest(
            title: $validated['title'],
            type: $validated['type'],
            priority: $validated['priority'],
            description: $validated['description'] ?? null,
        ));

        return redirect()
            ->route('issues.show', $issue->id)
            ->with('success', __('Issue created successfully'));
    }

    public function close(string $id, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        Beads::close($id, $validated['reason']);

        return back()->with('success', __('Issue closed'));
    }
}
```

### Dashboard Widget Example

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Beads\Facades\Beads;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // Cache insights for 5 minutes
        $insights = Cache::remember('beads:insights', 300, function () {
            return Beads::insights();
        });

        $plan = Cache::remember('beads:plan', 300, function () {
            return Beads::plan();
        });

        $stats = Beads::stats();
        $ready = Beads::readyWork(limit: 10);

        return Inertia::render('Dashboard', [
            'beads' => [
                'stats' => $stats,
                'ready' => $ready->all(),
                'insights' => [
                    'density' => $insights->density(),
                    'cycles' => count($insights->cycles()),
                    'topPageRank' => $insights->topPageRank(5),
                    'bottlenecks' => $insights->topBetweenness(5),
                ],
                'plan' => [
                    'tracks' => count($plan['tracks']),
                    'summary' => $plan['summary'],
                ],
            ],
        ]);
    }
}
```

### API Controller Example

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Beads\Facades\Beads;
use App\Services\Beads\Data\CreateIssueRequest;
use App\Services\Beads\Data\IssueFilter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IssueApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = IssueFilter::create();

        if ($request->has('status')) {
            $filter->status($request->input('status'));
        }

        if ($request->has('priority')) {
            $filter->priority((int) $request->input('priority'));
        }

        $issues = Beads::issues($filter);

        return response()->json([
            'data' => $issues->all(),
            'meta' => [
                'count' => $issues->count(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $issue = Beads::find($id);

        return response()->json(['data' => $issue]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'type' => 'required|in:task,bug,feature,epic',
            'priority' => 'required|integer|min:0|max:4',
            'description' => 'nullable|string',
        ]);

        $issue = Beads::create(new CreateIssueRequest(
            title: $validated['title'],
            type: $validated['type'],
            priority: $validated['priority'],
            description: $validated['description'] ?? null,
        ));

        return response()->json(
            ['data' => $issue],
            201
        );
    }

    public function insights(): JsonResponse
    {
        $insights = Beads::insights();

        return response()->json([
            'data' => [
                'density' => $insights->density(),
                'cycles' => $insights->cycles(),
                'pagerank' => $insights->topPageRank(10),
                'betweenness' => $insights->topBetweenness(10),
                'critical_path' => $insights->criticalPath(),
            ],
        ]);
    }

    public function plan(): JsonResponse
    {
        $plan = Beads::plan();

        return response()->json(['data' => $plan]);
    }
}
```

## Artisan Commands

### List Command

```php
<?php
// app/Services/Beads/Console/IssuesListCommand.php

namespace App\Services\Beads\Console;

use Illuminate\Console\Command;
use App\Services\Beads\Facades\Beads;
use App\Services\Beads\Data\IssueFilter;

class IssuesListCommand extends Command
{
    protected $signature = 'beads:list
                            {--status= : Filter by status (open|in_progress|closed)}
                            {--priority= : Filter by priority (0-4)}
                            {--type= : Filter by type (task|bug|feature|epic)}
                            {--ready : Show only ready (unblocked) issues}
                            {--json : Output as JSON}';

    protected $description = 'List bd issues';

    public function handle(): int
    {
        if ($this->option('ready')) {
            $issues = Beads::readyWork(limit: 100);
        } else {
            $filter = IssueFilter::create();

            if ($status = $this->option('status')) {
                $filter->status($status);
            }

            if ($priority = $this->option('priority')) {
                $filter->priority((int) $priority);
            }

            if ($type = $this->option('type')) {
                $filter->type($type);
            }

            $issues = Beads::issues($filter);
        }

        if ($this->option('json')) {
            $this->line(json_encode($issues->all(), JSON_PRETTY_PRINT));
            return 0;
        }

        $this->table(
            ['ID', 'Title', 'Status', 'Priority', 'Type'],
            $issues->map(fn($issue) => [
                $issue->id,
                $issue->title,
                $issue->status,
                $issue->priority,
                $issue->type,
            ])
        );

        $this->info("Total: {$issues->count()}");

        return 0;
    }
}
```

### Show Command

```php
<?php
// app/Services/Beads/Console/IssuesShowCommand.php

namespace App\Services\Beads\Console;

use Illuminate\Console\Command;
use App\Services\Beads\Facades\Beads;

class IssuesShowCommand extends Command
{
    protected $signature = 'beads:show {id : Issue ID}';
    protected $description = 'Show bd issue details';

    public function handle(): int
    {
        $id = $this->argument('id');
        $issue = Beads::find($id);

        $this->info("Issue: {$issue->id}");
        $this->line("Title: {$issue->title}");
        $this->line("Status: {$issue->status}");
        $this->line("Type: {$issue->type}");
        $this->line("Priority: {$issue->priority}");
        $this->line("Created: {$issue->createdAt}");

        if ($issue->description) {
            $this->newLine();
            $this->line("Description:");
            $this->line($issue->description);
        }

        return 0;
    }
}
```

### Insights Command

```php
<?php
// app/Services/Beads/Console/InsightsCommand.php

namespace App\Services\Beads\Console;

use Illuminate\Console\Command;
use App\Services\Beads\Facades\Beads;

class InsightsCommand extends Command
{
    protected $signature = 'beads:insights
                            {--json : Output as JSON}';

    protected $description = 'Show bv graph insights';

    public function handle(): int
    {
        $insights = Beads::insights();

        if ($this->option('json')) {
            $this->line(json_encode([
                'density' => $insights->density(),
                'cycles' => $insights->cycles(),
                'pagerank' => $insights->topPageRank(10),
                'betweenness' => $insights->topBetweenness(10),
            ], JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info("Graph Metrics");
        $this->line("Density: {$insights->density()}");
        $this->line("Cycles: " . count($insights->cycles()));

        $this->newLine();
        $this->info("Top PageRank (Foundational):");
        foreach ($insights->topPageRank(5) as $id => $score) {
            $issue = Beads::find($id);
            $this->line("  {$id}: {$issue->title} ({$score})");
        }

        $this->newLine();
        $this->info("Top Betweenness (Bottlenecks):");
        foreach ($insights->topBetweenness(5) as $id => $score) {
            $issue = Beads::find($id);
            $this->line("  {$id}: {$issue->title} ({$score})");
        }

        return 0;
    }
}
```

## Testing

### Unit Test Example

```php
<?php

namespace Tests\Unit\Beads;

use Tests\TestCase;
use App\Services\Beads\Client\BdClient;
use App\Services\Beads\Executor\CanExecuteCommand;
use App\Services\Beads\Executor\CommandResult;
use App\Services\Beads\Data\CreateIssueRequest;
use App\Services\Beads\Data\IssueFilter;
use Mockery;

class BdClientTest extends TestCase
{
    public function test_list_returns_issues(): void
    {
        $executor = Mockery::mock(CanExecuteCommand::class);
        $executor->shouldReceive('execute')
            ->once()
            ->with(
                ['/usr/local/bin/bd', 'list', '--json'],
                Mockery::any()
            )
            ->andReturn(new CommandResult(
                exitCode: 0,
                stdout: json_encode([
                    [
                        'id' => 'bd-abc',
                        'title' => 'Test issue',
                        'status' => 'open',
                        'type' => 'task',
                        'priority' => 1,
                        'created_at' => '2025-12-01T00:00:00Z',
                    ],
                ]),
                stderr: '',
                executionTime: 0.05,
            ));

        $client = new BdClient($executor, '/tmp');
        $issues = $client->list();

        $this->assertCount(1, $issues);
        $this->assertEquals('bd-abc', $issues->first()->id);
    }

    public function test_create_validates_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreateIssueRequest(
            title: 'Test',
            type: 'invalid_type',
        );
    }
}
```

### Feature Test Example

```php
<?php

namespace Tests\Feature\Beads;

use Tests\TestCase;
use App\Services\Beads\Facades\Beads;
use App\Services\Beads\Data\CreateIssueRequest;

class BeadsIntegrationTest extends TestCase
{
    public function test_can_list_issues(): void
    {
        $issues = Beads::openIssues();

        $this->assertIsObject($issues);
        $this->assertGreaterThanOrEqual(0, $issues->count());
    }

    public function test_can_get_insights(): void
    {
        $insights = Beads::insights();

        $this->assertIsFloat($insights->density());
        $this->assertIsArray($insights->cycles());
    }

    public function test_can_get_stats(): void
    {
        $stats = Beads::stats();

        $this->assertArrayHasKey('open', $stats);
        $this->assertArrayHasKey('closed', $stats);
    }
}
```

## Middleware Example (Authorization)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CanManageIssues
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->can('manage-issues')) {
            abort(403, 'You do not have permission to manage issues');
        }

        return $next($request);
    }
}
```

## Event Example (Issue Created)

```php
<?php

namespace App\Events;

use App\Services\Beads\Data\Issue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IssueCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Issue $issue,
    ) {}
}
```

## Listener Example (Notify Assignee)

```php
<?php

namespace App\Listeners;

use App\Events\IssueCreated;
use App\Notifications\IssueAssignedNotification;
use App\Models\User;

class NotifyIssueAssignee
{
    public function handle(IssueCreated $event): void
    {
        if ($event->issue->assignee === null) {
            return;
        }

        $user = User::where('email', $event->issue->assignee)->first();

        if ($user) {
            $user->notify(new IssueAssignedNotification($event->issue));
        }
    }
}
```

## Conclusion

This Laravel integration provides:

- ✅ **Service Provider** for dependency injection
- ✅ **Facade** for convenient access
- ✅ **Configuration** for flexibility
- ✅ **Artisan Commands** for CLI usage
- ✅ **Type Safety** throughout
- ✅ **Testing Support** with mocking
- ✅ **Events/Listeners** for extensibility
- ✅ **Middleware** for authorization
- ✅ **API Controllers** for external access
- ✅ **Inertia Controllers** for web UI

The result is a first-class Laravel integration that feels native to the framework while providing full access to bd/bv functionality.
