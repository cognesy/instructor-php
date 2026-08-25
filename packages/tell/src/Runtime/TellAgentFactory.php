<?php

declare(strict_types=1);

namespace Cognesy\Tell\Runtime;

use Closure;
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Coding\UseCodingTools;
use Cognesy\Agents\Capability\Definitions\UseAgentDefinitions;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Capability\SelfKnowledge\UseSelfKnowledge;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Discovery\CapabilityDiscovery;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\FileSessionStore;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Config\Dsn;
use Cognesy\Config\EnvTemplate;
use Cognesy\Config\Secrets\DotenvFileSecretSource;
use Cognesy\Config\Secrets\EnvironmentSecretSource;
use Cognesy\Config\Secrets\SecretResolver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Tell\Observability\ExecutionTraceWriter;
use Cognesy\Tell\Workspace\WorkspaceManager;
use RuntimeException;

final readonly class TellAgentFactory
{
    /** @var Closure(AgentLoop): AgentLoop|null */
    private ?Closure $decorateLoop;

    /** @param callable(AgentLoop): AgentLoop|null $decorateLoop */
    public function __construct(
        private TellPaths $paths,
        ?callable $decorateLoop = null,
    ) {
        $this->decorateLoop = match ($decorateLoop) {
            null => null,
            default => Closure::fromCallable($decorateLoop),
        };
    }

    public static function installed(): self
    {
        return new self(TellPaths::installed());
    }

    public function definitions(string $projectPath): AgentDefinitionRegistry
    {
        $registry = new AgentDefinitionRegistry;
        $registry->autoDiscover(
            projectPath: $projectPath,
            packagePath: $this->paths->packageAgents,
            userPath: $this->paths->userAgents,
        );

        return $registry;
    }

    public function definition(TellOptions $options): AgentDefinition
    {
        $definition = $this->definitions($options->directory)->get($options->agent);

        return $this->configureDefinition($definition, $options);
    }

    public function configureDefinition(AgentDefinition $definition, TellOptions $options): AgentDefinition
    {
        return new AgentDefinition(
            name: $definition->name,
            description: $definition->description,
            systemPrompt: $definition->systemPrompt,
            label: $definition->label,
            llmConfig: $this->llmConfig($options),
            capabilities: $definition->capabilities,
            tools: $definition->tools,
            toolsDeny: $definition->toolsDeny,
            skills: $definition->skills,
            budget: new ExecutionBudget(maxSteps: $options->maxSteps),
            metadata: $definition->metadata,
        );
    }

    public function build(TellOptions $options, ?AgentDefinition $definition = null): AgentLoop
    {
        $definition = match (true) {
            $definition === null => $this->definition($options),
            default => $this->configureDefinition($definition, $options),
        };
        $definitions = $this->definitions($options->directory);
        $capabilities = new AgentCapabilityRegistry;
        $tools = new ToolRegistry;
        CapabilityDiscovery::discover($capabilities, $tools);

        $capabilities->register('tell.coding', new UseCodingTools($options->directory));
        $capabilities->register('tell.system_prompt', new UseSystemPrompt);
        $capabilities->register('tell.self_knowledge', new UseSelfKnowledge);
        $capabilities->register('tell.self_description', new UseSelfDescription);
        $capabilities->register('tell.agent_definitions', new UseAgentDefinitions(
            registry: $definitions,
            validator: new AgentDefinitionValidator($capabilities, $tools),
        ));

        $loop = (new DefinitionLoopFactory($capabilities, $tools))->instantiateAgentLoop($definition);
        $loop = $this->filterTools($loop, $options->tools);

        return match ($this->decorateLoop) {
            null => $loop,
            default => ($this->decorateLoop)($loop),
        };
    }

    public function sessions(): SessionRuntime
    {
        return new SessionRuntime(
            sessions: $this->sessionRepository(),
            events: new EventDispatcher('tell-sessions'),
        );
    }

    public function sessionRepository(): SessionRepository
    {
        (new TellStorage($this->paths))->ensureSessions();

        return new SessionRepository(new FileSessionStore($this->paths->sessions));
    }

    public function attachExecutionTrace(AgentLoop $loop, TellOptions $options): void
    {
        (new ExecutionTraceWriter($this->paths, $this->config(), $options))->attach($loop);
    }

    public function config(): TellConfig
    {
        return TellConfig::fromFile($this->paths->configFile);
    }

    public function credentials(): TellCredentialStore
    {
        return new TellCredentialStore($this->paths);
    }

    public function secretResolver(string $projectPath): SecretResolver
    {
        return new SecretResolver(
            new EnvironmentSecretSource,
            DotenvFileSecretSource::optional(
                rtrim($projectPath, '/\\').DIRECTORY_SEPARATOR.'.env',
                'workspace-env',
            ),
            $this->credentials()->source(),
        );
    }

    public function paths(): TellPaths
    {
        return $this->paths;
    }

    public function workspace(): WorkspaceManager
    {
        return new WorkspaceManager;
    }

    public function assertReady(TellOptions $options): void
    {
        if ($options->dsn !== '') {
            return;
        }

        $this->assertCredentialAvailable($this->llmConfig($options), $options->connection);
    }

    private function llmConfig(TellOptions $options): LLMConfig
    {
        if ($options->dsn !== '') {
            return LLMConfig::fromArray(Dsn::fromString($options->dsn)->toArray());
        }

        TellCredentialNames::forProvider($options->connection);
        $config = LLMConfig::fromPreset(
            preset: $options->connection,
            basePath: $this->connectionDirectory($options),
            template: new EnvTemplate($this->secretResolver($options->directory)),
        );

        return match ($options->model) {
            '' => $config,
            default => $config->withOverrides(['model' => $options->model]),
        };
    }

    private function connectionDirectory(TellOptions $options): ?string
    {
        $project = rtrim($options->directory, '/\\').'/config/llm/presets';

        return match (true) {
            is_file($project.'/'.$options->connection.'.yaml') => $project,
            is_file($this->paths->connections.'/'.$options->connection.'.yaml') => $this->paths->connections,
            default => null,
        };
    }

    private function assertCredentialAvailable(LLMConfig $config, string $connection): void
    {
        $host = parse_url($config->apiUrl, PHP_URL_HOST);
        $local = $config->driver === 'ollama'
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($config->apiKey !== '' || $local) {
            return;
        }
        $variable = TellCredentialNames::forProvider($connection);

        throw new RuntimeException(
            "Missing credential {$variable} for connection '{$connection}'. "
            ."Set it in the process environment, workspace .env, or with `tell auth set {$connection} --stdin`.",
        );
    }

    /** @param list<string> $allowed */
    private function filterTools(AgentLoop $loop, array $allowed): AgentLoop
    {
        if ($allowed === []) {
            return $loop;
        }
        $selected = [];
        foreach ($loop->tools()->all() as $name => $tool) {
            if (in_array($name, $allowed, true)) {
                $selected[] = $tool;
            }
        }

        return $loop->withTools(new Tools(...$selected));
    }
}
