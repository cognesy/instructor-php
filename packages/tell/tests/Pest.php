<?php

declare(strict_types=1);

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Tell\Capability\Secrets\Standard\TellCredentialStore;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Capability\Model\Polyglot\PolyglotTellModelResolver;
use Cognesy\Tell\Capability\Secrets\Standard\StandardTellSecretResolver;
use Cognesy\Tell\Capability\Configuration\Standard\StandardTellConfigurationResolver;
use Cognesy\Tell\Capability\Discovery\Polyglot\PolyglotTellProviderCatalogue;
use Cognesy\Tell\Capability\Paths\Installed\StandardTellPathResolver;
use Cognesy\Tell\Core\Workspace\Execution\TellExecutionWorkspaceProvider;
use Cognesy\Tell\Core\Workspace\TellConversations;
use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Adapter\Console\Symfony\TellCommand;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Execution\CanCreateTellRuntime;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Capability\Observation\FilesystemTrace\StandardTellExecutionTracer;
use Cognesy\Tell\Core\Contract\Tool\CanDispatchTellTool;
use Cognesy\Tell\Core\Contract\Model\CanResolveTellModel;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Core\Discovery\StartupScanCounter;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Cognesy\Tell\Capability\Execution\System\SystemTellClock;
use Cognesy\Tell\Capability\Agent\ComposerDiscovery\ComposerTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Definitions\FilesystemTellAgentDefinitions;
use Cognesy\Tell\Capability\Agent\Standard\StandardTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Subagent\TellSubagentContribution;
use Cognesy\Tell\Capability\Tool\AskUser\AskUserToolContribution;
use Cognesy\Tell\Capability\Tool\Coding\CodingToolContribution;
use Cognesy\Tell\Core\Execution\TellRuntime;
use Cognesy\Tell\Capability\Tool\Standard\StandardTellToolDispatcher;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Testing\TellTestFactory;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemTellWorkspaceProvider;

/** @var list<string> $tellTemporaryRoots */
$tellTemporaryRoots = [];

/**
 * @param  callable(AgentLoop): AgentLoop|null  $decorate
 * @param  array<string, string>  $userAgents
 * @param  array<string, string>  $credentials
 */
function tellTestFactory(
    ?callable $decorate = null,
    array $userAgents = [],
    array $credentials = ['OPENAI_API_KEY' => 'tell-test-key'],
    ?StartupScanCounter $startupScans = null,
    ?CanUseTools $driver = null,
    ?string $composerVendorDir = null,
    ?string $rootComposerPath = null,
    ?CanResolveTellModel $modelResolver = null,
): TellAgentFactory {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-' . bin2hex(random_bytes(8));
    $package = $root . '/package-agents';
    $paths = new TellPaths($package, $root . '/tell-home');
    mkdir($package, 0755, true);
    mkdir($paths->userAgents, 0755, true);
    file_put_contents($package . '/default.md', <<<'MD'
---
name: default
label: Tell Test
description: Deterministic test agent
capabilities:
  - use_subagents
  - tell.coding
  - tell.ask_user
  - tell.system_prompt
  - tell.self_knowledge
  - tell.self_description
  - tell.agent_definitions
---

You are a deterministic test agent.
MD);
    foreach ($userAgents as $filename => $content) {
        file_put_contents($paths->userAgents . '/' . $filename, $content);
    }
    $credentialStore = new TellCredentialStore($paths);
    foreach ($credentials as $variable => $value) {
        $credentialStore->set($variable, $value);
    }
    $tellTemporaryRoots[] = $root;

    return tellAgentFactoryForPaths(
        paths: $paths,
        directory: $root,
        decorate: $decorate,
        driver: $driver,
        startupScans: $startupScans,
        composerVendorDir: $composerVendorDir,
        rootComposerPath: $rootComposerPath,
        modelResolver: $modelResolver,
    );
}

/** @param callable(AgentLoop): AgentLoop|null $decorate */
function tellAgentFactoryForPaths(
    TellPaths $paths,
    string $directory,
    ?callable $decorate = null,
    ?CanUseTools $driver = null,
    ?StartupScanCounter $startupScans = null,
    ?string $composerVendorDir = null,
    ?string $rootComposerPath = null,
    ?CanResolveTellModel $modelResolver = null,
): TellAgentFactory {
    return new TellAgentFactory(
        paths: $paths,
        tracer: new StandardTellExecutionTracer($paths),
        clock: new SystemTellClock(),
        modelResolver: $modelResolver ?? new PolyglotTellModelResolver($paths, new StandardTellSecretResolver($paths, $directory)),
        definitionLoader: new FilesystemTellAgentDefinitions($paths, $startupScans),
        contributions: [
            new ComposerTellAgentContribution($startupScans, $composerVendorDir, $rootComposerPath),
            new CodingToolContribution($paths),
            new AskUserToolContribution(),
            new TellSubagentContribution(),
            new StandardTellAgentContribution(),
        ],
        decorateLoop: $decorate,
        driver: $driver,
    );
}

function tellTestAgents(TellAgentFactory $factory): CanBuildTellAgent {
    return $factory;
}

function tellTestWorkspaces(): WorkspaceRepository {
    return new WorkspaceRepository();
}

function tellTestWorkspaceProvider(?WorkspaceRepository $repository = null): CanOpenTellWorkspace {
    return new FilesystemTellWorkspaceProvider($repository ?? tellTestWorkspaces());
}

function tellTestConversations(
    TellAgentFactory $factory,
    ?WorkspaceRepository $repository = null,
): CanAccessTellConversations {
    $repository ??= tellTestWorkspaces();
    $workspaces = new FilesystemTellWorkspaceProvider($repository);

    return new TellConversations(
        tellTestAgents($factory),
        tellTestRuntime($factory, workspaces: $repository),
        tellTestTracer($factory),
        $workspaces,
        $factory->paths(),
        tellTestProviderCatalogue($factory),
    );
}

function tellTestCredentials(TellAgentFactory $factory): TellCredentialStore {
    return new TellCredentialStore($factory->paths());
}

function tellTestTracer(TellAgentFactory $factory): StandardTellExecutionTracer {
    return new StandardTellExecutionTracer($factory->paths());
}

function tellTestProviderCatalogue(TellAgentFactory $factory): CanCatalogueTellProviders {
    return new PolyglotTellProviderCatalogue($factory->paths());
}

function tellTestRuntime(
    TellAgentFactory $factory,
    ?CanProvideCancellationSignal $cancellation = null,
    ?WorkspaceRepository $workspaces = null,
): TellRuntime {
    $repository = $workspaces ?? tellTestWorkspaces();
    $workspaceProvider = new FilesystemTellWorkspaceProvider($repository);

    return new TellRuntime(
        agents: tellTestAgents($factory),
        workspaces: new TellExecutionWorkspaceProvider($workspaceProvider),
        tracer: tellTestTracer($factory),
        configuration: new StandardTellConfigurationResolver(
            new StandardTellPathResolver($factory->paths()),
            $workspaceProvider,
        ),
        cancellation: $cancellation,
    );
}

function tellTestRuntimeFactory(TellAgentFactory $factory): CanCreateTellRuntime {
    return new readonly class($factory) implements CanCreateTellRuntime {
        public function __construct(private TellAgentFactory $factory) {}

        public function create(?CanProvideCancellationSignal $cancellation = null): TellRuntime {
            return tellTestRuntime($this->factory, $cancellation);
        }
    };
}

function tellTestCommand(TellAgentFactory $factory): TellCommand {
    return new TellCommand(
        tellTestRuntimeFactory($factory),
        tellTestAgents($factory),
        tellTestWorkspaceProvider(),
        tellTestWorkspaceProvider(),
        $factory->paths(),
    );
}

function tellTestToolDispatcher(TellAgentFactory $factory): CanDispatchTellTool {
    $agents = tellTestAgents($factory);

    return new StandardTellToolDispatcher(
        $agents,
        tellTestRuntime($factory),
        '.',
    );
}

function tellTestOpen(
    string $directory,
    TellAgentFactory $factory,
    ?CanProvideCancellationSignal $cancellation = null,
): Tell {
    return StandaloneTellHost::open(
        directory: $directory,
        paths: $factory->paths(),
        agentBuilder: tellTestAgents($factory),
        cancellation: $cancellation,
    );
}

function tellTestResponses(string $directory, string ...$responses): Tell {
    return TellTestFactory::responses(...$responses)->open($directory);
}

function tellTestApplication(
    TellAgentFactory $factory,
    ?WorkspaceRepository $workspaces = null,
): TellConsoleApplication {
    $cwd = getcwd();

    $host = StandaloneTellHost::cli(
        directory: is_string($cwd) ? $cwd : '.',
        paths: $factory->paths(),
        agentBuilder: tellTestAgents($factory),
        workspaces: $workspaces === null ? null : new FilesystemTellWorkspaceProvider($workspaces),
    );
    return new TellConsoleApplication(TellCommandDescriptors::merge(
        ...array_map(static fn ($contributor) => $contributor->commands(), $host->commandContributors()),
    ));
}

function tellLastTemporaryRoot(): string {
    global $tellTemporaryRoots;

    return $tellTemporaryRoots[array_key_last($tellTemporaryRoots)];
}

function tellTestProject(): string {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-' . bin2hex(random_bytes(8));
    mkdir($root, 0755, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

function standardHostPaths(string $project): TellPaths {
    $paths = new TellPaths($project . '/package-agents', $project . '/.tell-host');
    mkdir($paths->packageAgents, 0755, true);
    file_put_contents($paths->packageAgents . '/default.md', <<<'MD'
---
name: default
label: Standard Host Test
description: Deterministic standard host agent
capabilities:
  - tell.coding
---

You are deterministic.
MD);

    return $paths;
}

function tellMalformedComposerVendor(): string {
    $root = tellTestProject();
    $vendor = $root . '/vendor';
    mkdir($vendor . '/composer', 0755, true);
    file_put_contents($vendor . '/composer/installed.json', json_encode([
        'packages' => [[
            'name' => 'example/malformed-extension',
            'extra' => [
                'cognesy-agents' => [
                    'capabilities' => ['not-a-name-to-class-map'],
                ],
            ],
        ]],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    return $vendor;
}

function tellRemoveDirectory(string $directory): void {
    if (!str_starts_with($directory, sys_get_temp_dir() . '/instructor-tell-') || !is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

afterEach(function (): void {
    global $tellTemporaryRoots;
    // Null until some test asks for a temporary root, which a test file that
    // touches no filesystem never does.
    foreach ($tellTemporaryRoots ?? [] as $root) {
        tellRemoveDirectory($root);
    }
    $tellTemporaryRoots = [];
});
