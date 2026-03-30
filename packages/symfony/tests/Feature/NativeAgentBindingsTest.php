<?php

declare(strict_types=1);

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\CanManageAgentCapabilities;
use Cognesy\Agents\Capability\StructuredOutput\CanManageSchemas;
use Cognesy\Agents\Capability\StructuredOutput\SchemaRegistry;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Symfony\Agents\AgentRegistryTags;
use Cognesy\Instructor\Symfony\Agents\SchemaRegistration;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\Result\Result;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

it('registers the baseline native-agent runtime services and contracts', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $definitions = $app->service(CanManageAgentDefinitions::class);
            $tools = $app->service(CanManageTools::class);
            $capabilities = $app->service(CanManageAgentCapabilities::class);
            $schemas = $app->service(CanManageSchemas::class);
            $store = $app->service(CanStoreSessions::class);
            $repository = $app->service(SessionRepository::class);
            $loopFactory = $app->service(CanInstantiateAgentLoop::class);
            $sessions = $app->service(CanManageAgentSessions::class);

            expect($definitions)->toBeInstanceOf(AgentDefinitionRegistry::class)
                ->and($tools)->toBeInstanceOf(ToolRegistry::class)
                ->and($capabilities)->toBeInstanceOf(AgentCapabilityRegistry::class)
                ->and($schemas)->toBeInstanceOf(SchemaRegistry::class)
                ->and($store)->toBeInstanceOf(InMemorySessionStore::class)
                ->and($repository)->toBeInstanceOf(SessionRepository::class)
                ->and($loopFactory)->toBeInstanceOf(DefinitionLoopFactory::class)
                ->and($sessions)->toBeInstanceOf(SessionRuntime::class);

            $loopFactoryReflection = new ReflectionObject($loopFactory);
            $loopFactoryEvents = $loopFactoryReflection->getProperty('events')->getValue($loopFactory);

            $sessionRuntimeReflection = new ReflectionObject($sessions);
            $sessionRepository = $sessionRuntimeReflection->getProperty('sessions')->getValue($sessions);
            $sessionEvents = $sessionRuntimeReflection->getProperty('events')->getValue($sessions);

            $repositoryReflection = new ReflectionObject($repository);
            $resolvedStore = $repositoryReflection->getProperty('store')->getValue($repository);

            expect($loopFactoryEvents)->toBe($events)
                ->and($sessionRepository)->toBe($repository)
                ->and($sessionEvents)->toBe($events)
                ->and($resolvedStore)->toBe($store);
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
    );
});

it('hydrates native-agent registries from explicit Symfony service tags', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $definitions = $app->service(CanManageAgentDefinitions::class);
            $tools = $app->service(CanManageTools::class);
            $capabilities = $app->service(CanManageAgentCapabilities::class);
            $schemas = $app->service(CanManageSchemas::class);

            expect($definitions->has('tagged-definition'))->toBeTrue()
                ->and($definitions->get('tagged-definition')->description)->toBe('Tagged definition')
                ->and($tools->has('tagged-tool'))->toBeTrue()
                ->and($tools->get('tagged-tool'))->toBeInstanceOf(TaggedSymfonyTool::class)
                ->and($capabilities->has('tagged-capability'))->toBeTrue()
                ->and($capabilities->get('tagged-capability'))->toBeInstanceOf(TaggedSymfonyCapability::class)
                ->and($capabilities->has('named-capability'))->toBeTrue()
                ->and($capabilities->get('named-capability'))->toBeInstanceOf(TaggedNamedSymfonyCapability::class)
                ->and($schemas->has('tagged_schema'))->toBeTrue()
                ->and($schemas->get('tagged_schema')->class)->toBe(TaggedSchemaData::class);
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('tagged-agent-definition', (new Definition(AgentDefinition::class))
                    ->setArguments([
                        '$name' => 'tagged-definition',
                        '$description' => 'Tagged definition',
                        '$systemPrompt' => 'Be helpful',
                    ])
                    ->addTag(AgentRegistryTags::DEFINITIONS));

                $container->setDefinition(TaggedSymfonyTool::class, (new Definition(TaggedSymfonyTool::class))
                    ->addTag(AgentRegistryTags::TOOLS));

                $container->setDefinition(TaggedSymfonyCapability::class, (new Definition(TaggedSymfonyCapability::class))
                    ->addTag(AgentRegistryTags::CAPABILITIES));

                $container->setDefinition(TaggedNamedSymfonyCapability::class, (new Definition(TaggedNamedSymfonyCapability::class))
                    ->addTag(AgentRegistryTags::CAPABILITIES, ['name' => 'named-capability']));

                $container->setDefinition('tagged-schema-registration', (new Definition(SchemaRegistration::class))
                    ->setArguments([
                        '$name' => 'tagged_schema',
                        '$schema' => TaggedSchemaData::class,
                    ])
                    ->addTag(AgentRegistryTags::SCHEMAS));
            },
        ],
    );
});

it('autoconfigures native-agent contributions from their service types', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $definitions = $app->service(CanManageAgentDefinitions::class);
            $tools = $app->service(CanManageTools::class);
            $capabilities = $app->service(CanManageAgentCapabilities::class);
            $schemas = $app->service(CanManageSchemas::class);

            expect($definitions->has('autoconfigured-definition'))->toBeTrue()
                ->and($tools->has('autoconfigured-tool'))->toBeTrue()
                ->and($tools->get('autoconfigured-tool'))->toBeInstanceOf(AutoconfiguredSymfonyTool::class)
                ->and($capabilities->has('autoconfigured-capability'))->toBeTrue()
                ->and($capabilities->get('autoconfigured-capability'))->toBeInstanceOf(AutoconfiguredSymfonyCapability::class)
                ->and($schemas->has('autoconfigured_schema'))->toBeTrue()
                ->and($schemas->get('autoconfigured_schema')->class)->toBe(AutoconfiguredSchemaData::class);
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('autoconfigured-agent-definition', (new Definition(AgentDefinition::class))
                    ->setArguments([
                        '$name' => 'autoconfigured-definition',
                        '$description' => 'Autoconfigured definition',
                        '$systemPrompt' => 'Be explicit',
                    ])
                    ->setAutoconfigured(true));

                $container->setDefinition(AutoconfiguredSymfonyTool::class, (new Definition(AutoconfiguredSymfonyTool::class))
                    ->setAutoconfigured(true));

                $container->setDefinition(AutoconfiguredSymfonyCapability::class, (new Definition(AutoconfiguredSymfonyCapability::class))
                    ->setAutoconfigured(true));

                $container->setDefinition('autoconfigured-schema-registration', (new Definition(SchemaRegistration::class))
                    ->setArguments([
                        '$name' => 'autoconfigured_schema',
                        '$schema' => AutoconfiguredSchemaData::class,
                    ])
                    ->setAutoconfigured(true));
            },
        ],
    );
});

it('rejects invalid tagged native-agent tools during container compilation', function (): void {
    $boot = static fn () => SymfonyTestApp::boot(
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('invalid-tagged-tool', (new Definition(InvalidTaggedSymfonyTool::class))
                    ->addTag(AgentRegistryTags::TOOLS));
            },
        ],
    );

    expect($boot)->toThrow(InvalidArgumentException::class, 'Tagged native agent tools must resolve to ToolInterface services.');
});

class TaggedSymfonyTool implements ToolInterface, CanDescribeTool
{
    public function use(mixed ...$args): Result
    {
        return Result::from('ok');
    }

    public function toToolSchema(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'tagged-tool',
            description: 'Tagged tool',
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    public function descriptor(): self
    {
        return $this;
    }

    public function name(): string
    {
        return 'tagged-tool';
    }

    public function description(): string
    {
        return 'Tagged tool';
    }

    public function metadata(): array
    {
        return [
            'name' => 'tagged-tool',
            'summary' => 'Tagged tool',
        ];
    }

    public function instructions(): array
    {
        return [
            'name' => 'tagged-tool',
            'description' => 'Tagged tool',
            'parameters' => [],
            'usage' => [],
            'examples' => [],
            'errors' => [],
            'notes' => [],
        ];
    }
}

final class TaggedSymfonyCapability implements CanProvideAgentCapability
{
    public static function capabilityName(): string
    {
        return 'tagged-capability';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent;
    }
}

final class TaggedNamedSymfonyCapability implements CanProvideAgentCapability
{
    public static function capabilityName(): string
    {
        return 'unused-tagged-capability-name';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent;
    }
}

final class AutoconfiguredSymfonyTool extends TaggedSymfonyTool
{
    public function name(): string
    {
        return 'autoconfigured-tool';
    }

    public function description(): string
    {
        return 'Autoconfigured tool';
    }
}

final class AutoconfiguredSymfonyCapability implements CanProvideAgentCapability
{
    public static function capabilityName(): string
    {
        return 'autoconfigured-capability';
    }

    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent;
    }
}

final readonly class AutoconfiguredSchemaData
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class TaggedSchemaData
{
    public function __construct(
        public string $message,
    ) {}
}

final class InvalidTaggedSymfonyTool
{
}
