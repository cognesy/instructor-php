<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\DependencyInjection;

use Cognesy\Events\Contracts\CanHandleEvents;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class Configuration implements ConfigurationInterface
{
    /** @var list<string> */
    private const CONNECTION_META_KEYS = ['default', 'items', 'connections'];

    /** @var list<string> */
    private const AGENT_CTRL_BACKENDS = [
        'claude_code',
        'codex',
        'opencode',
        'pi',
        'gemini',
    ];

    /** @var list<string> */
    private const SANDBOX_DRIVERS = [
        'host',
        'docker',
        'podman',
        'firejail',
        'bubblewrap',
    ];

    /** @var list<string> */
    private const AGENT_CTRL_TRANSPORTS = [
        'sync',
        'messenger',
    ];

    /** @var list<string> */
    private const AGENT_CTRL_CONTINUATION_MODES = [
        'fresh',
        'continue_last',
        'resume_session',
    ];

    /** @var list<string> */
    private const LOGGING_PRESETS = [
        'development',
        'default',
        'production',
        'custom',
    ];

    /** @var list<string> */
    private const LOG_LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /** @var list<string> */
    private const SESSION_STORE_DRIVERS = [
        'memory',
        'file',
    ];

    /** @var list<string> */
    private const TELEMETRY_DRIVERS = [
        'null',
        'otel',
        'langfuse',
        'logfire',
        'composite',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('instructor');
        $rootNode = $treeBuilder->getRootNode();
        $children = $rootNode->children();

        $this->configureConnectionsNode($children->arrayNode('connections'), 'items', 'connections');
        $this->configureConnectionsNode($children->arrayNode('embeddings'), 'connections', 'embeddings');
        $this->configureExtractionNode($children->arrayNode('extraction'));
        $this->configureHttpNode($children->arrayNode('http'));
        $this->configureEventsNode($children->arrayNode('events'));

        $children
            ->append($this->agentCtrlNode())
            ->variableNode('agents')
            ->defaultValue([])
            ->end()
            ->append($this->sessionsNode())
            ->append($this->telemetryNode())
            ->append($this->loggingNode())
            ->variableNode('testing')
            ->defaultValue([])
            ->end()
            ->append($this->deliveryNode())
            ->end();

        return $treeBuilder;
    }

    private function configureConnectionsNode(
        ArrayNodeDefinition $node,
        string $preferredBucket,
        string $subtreeName,
    ): void {
        $children = $node
            ->normalizeKeys(false)
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid(\sprintf('Expected instructor.%s to be an array, got %%s.', $subtreeName))
            ->end()
            ->beforeNormalization()
            ->ifArray()
            ->then(fn (array $value): array => $this->normalizeConnectionRoot($value, $preferredBucket, $subtreeName))
            ->end()
            ->addDefaultsIfNotSet()
            ->children();

        $children
            ->scalarNode('default')->defaultNull()->end();

        $itemsNode = $children->arrayNode('items');
        $this->configureConnectionEntryCollection($itemsNode);
        $itemsNode->defaultValue([])->end();

        $connectionsNode = $children->arrayNode('connections');
        $this->configureConnectionEntryCollection($connectionsNode);
        $connectionsNode->defaultValue([])->end();

        $children->end();
    }

    private function configureConnectionEntryCollection(ArrayNodeDefinition $node): void
    {
        $node
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->normalizeKeys(false)
            ->ignoreExtraKeys(false)
            ->children()
            ->scalarNode('driver')->defaultNull()->end()
            ->scalarNode('api_url')->defaultNull()->end()
            ->scalarNode('api_key')->defaultNull()->end()
            ->scalarNode('endpoint')->defaultNull()->end()
            ->scalarNode('model')->defaultNull()->end()
            ->scalarNode('max_tokens')->defaultNull()->end()
            ->scalarNode('context_length')->defaultNull()->end()
            ->scalarNode('max_output_length')->defaultNull()->end()
            ->scalarNode('organization')->defaultNull()->end()
            ->scalarNode('project')->defaultNull()->end()
            ->scalarNode('resource_name')->defaultNull()->end()
            ->scalarNode('deployment_id')->defaultNull()->end()
            ->scalarNode('api_version')->defaultNull()->end()
            ->scalarNode('beta')->defaultNull()->end()
            ->scalarNode('client_name')->defaultNull()->end()
            ->scalarNode('region')->defaultNull()->end()
            ->scalarNode('guardrail_id')->defaultNull()->end()
            ->scalarNode('guardrail_version')->defaultNull()->end()
            ->scalarNode('open_responses_version')->defaultNull()->end()
            ->scalarNode('dimensions')->defaultNull()->end()
            ->scalarNode('default_dimensions')->defaultNull()->end()
            ->scalarNode('max_inputs')->defaultNull()->end()
            ->arrayNode('query_params')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('metadata')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('options')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('pricing')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->end()
            ->end();
    }

    private function configureExtractionNode(ArrayNodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.extraction to be an array, got %s.')
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('output_mode')->defaultNull()->end()
            ->scalarNode('outputMode')->defaultNull()->end()
            ->scalarNode('max_retries')->defaultNull()->end()
            ->scalarNode('maxRetries')->defaultNull()->end()
            ->scalarNode('retry_prompt')->defaultNull()->end()
            ->scalarNode('retryPrompt')->defaultNull()->end()
            ->arrayNode('mode_prompts')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('modePromptClasses')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('mode_prompt_classes')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->scalarNode('retry_prompt_class')->defaultNull()->end()
            ->scalarNode('retryPromptClass')->defaultNull()->end()
            ->scalarNode('schema_name')->defaultNull()->end()
            ->scalarNode('schemaName')->defaultNull()->end()
            ->scalarNode('schema_description')->defaultNull()->end()
            ->scalarNode('schemaDescription')->defaultNull()->end()
            ->scalarNode('tool_name')->defaultNull()->end()
            ->scalarNode('toolName')->defaultNull()->end()
            ->scalarNode('tool_description')->defaultNull()->end()
            ->scalarNode('toolDescription')->defaultNull()->end()
            ->scalarNode('output_class')->defaultNull()->end()
            ->scalarNode('outputClass')->defaultNull()->end()
            ->scalarNode('default_to_std_class')->defaultNull()->end()
            ->scalarNode('defaultToStdClass')->defaultNull()->end()
            ->scalarNode('deserialization_error_prompt_class')->defaultNull()->end()
            ->scalarNode('deserializationErrorPromptClass')->defaultNull()->end()
            ->scalarNode('throw_on_transformation_failure')->defaultNull()->end()
            ->scalarNode('throwOnTransformationFailure')->defaultNull()->end()
            ->arrayNode('chat_structure')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('chatStructure')
            ->normalizeKeys(false)
            ->variablePrototype()->end()
            ->defaultValue([])
            ->end()
            ->scalarNode('response_cache_policy')->defaultNull()->end()
            ->scalarNode('responseCachePolicy')->defaultNull()->end()
            ->scalarNode('stream_materialization_interval')->defaultNull()->end()
            ->scalarNode('streamMaterializationInterval')->defaultNull()->end()
            ->end();
    }

    private function configureHttpNode(ArrayNodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.http to be an array, got %s.')
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('driver')->defaultNull()->end()
            ->scalarNode('connect_timeout')->defaultNull()->end()
            ->scalarNode('connectTimeout')->defaultNull()->end()
            ->scalarNode('request_timeout')->defaultNull()->end()
            ->scalarNode('requestTimeout')->defaultNull()->end()
            ->scalarNode('timeout')->defaultNull()->end()
            ->scalarNode('idle_timeout')->defaultNull()->end()
            ->scalarNode('idleTimeout')->defaultNull()->end()
            ->scalarNode('stream_chunk_size')->defaultNull()->end()
            ->scalarNode('streamChunkSize')->defaultNull()->end()
            ->scalarNode('stream_header_timeout')->defaultNull()->end()
            ->scalarNode('streamHeaderTimeout')->defaultNull()->end()
            ->scalarNode('fail_on_error')->defaultNull()->end()
            ->scalarNode('failOnError')->defaultNull()->end()
            ->end();
    }

    private function configureEventsNode(ArrayNodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.events to be an array, got %s.')
            ->end()
            ->beforeNormalization()
            ->ifArray()
            ->then(static function (array $value): array {
                if (array_key_exists('dispatch_to_symfony', $value)) {
                    return $value;
                }

                if (! array_key_exists('bridge_to_symfony', $value)) {
                    return $value;
                }

                $value['dispatch_to_symfony'] = $value['bridge_to_symfony'];

                return $value;
            })
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('dispatch_to_symfony')->defaultTrue()->end()
            ->scalarNode('bridge_to_symfony')->defaultNull()->end()
            ->end();
    }

    private function loggingNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('logging');
        $node = $treeBuilder->getRootNode();

        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.logging to be an array, got %s.')
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->enumNode('preset')
            ->values(self::LOGGING_PRESETS)
            ->defaultValue('production')
            ->end()
            ->scalarNode('event_bus_service')
            ->defaultValue(CanHandleEvents::class)
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('channel')
            ->defaultValue('instructor')
            ->cannotBeEmpty()
            ->end()
            ->enumNode('level')
            ->values(self::LOG_LEVELS)
            ->defaultValue('warning')
            ->end()
            ->arrayNode('exclude_events')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('include_events')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->arrayNode('templates')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $node;
    }

    private function sessionsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('sessions');
        $node = $treeBuilder->getRootNode();

        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.sessions to be an array, got %s.')
            ->end()
            ->beforeNormalization()
            ->ifArray()
            ->then(static fn (array $value): array => self::normalizeSessionsRoot($value))
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('store')
            ->values(self::SESSION_STORE_DRIVERS)
            ->defaultValue('memory')
            ->end()
            ->arrayNode('file')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('directory')
            ->defaultValue('%kernel.cache_dir%/instructor/agent-sessions')
            ->cannotBeEmpty()
            ->end()
            ->end()
            ->end()
            ->end();

        return $node;
    }

    private function agentCtrlNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('agent_ctrl');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
            ->ifArray()
            ->then(static fn (array $value): array => self::normalizeAgentCtrlRoot($value))
            ->end()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->enumNode('default_backend')
            ->values(self::AGENT_CTRL_BACKENDS)
            ->defaultValue('claude_code')
            ->end()
            ->append($this->agentCtrlDefaultsNode())
            ->append($this->agentCtrlExecutionNode())
            ->append($this->agentCtrlContinuationNode())
            ->append($this->agentCtrlBackendsNode())
            ->end();

        return $node;
    }

    private function telemetryNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('telemetry');
        $node = $treeBuilder->getRootNode();

        $node
            ->beforeNormalization()
            ->ifTrue(static fn (mixed $value): bool => ! is_array($value))
            ->thenInvalid('Expected instructor.telemetry to be an array, got %s.')
            ->end()
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->enumNode('driver')
            ->values(self::TELEMETRY_DRIVERS)
            ->defaultValue('null')
            ->end()
            ->scalarNode('service_name')
            ->defaultValue('symfony')
            ->cannotBeEmpty()
            ->end()
            ->append($this->telemetryProjectorsNode())
            ->append($this->telemetryHttpNode())
            ->append($this->telemetryDriversNode())
            ->end();

        return $node;
    }

    private function deliveryNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('delivery');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->append($this->messengerDeliveryNode())
            ->append($this->progressDeliveryNode())
            ->append($this->cliDeliveryNode())
            ->end();

        return $node;
    }

    private function progressDeliveryNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('progress');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()
            ->end();

        return $node;
    }

    private function cliDeliveryNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('cli');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->booleanNode('use_colors')
            ->defaultTrue()
            ->end()
            ->booleanNode('show_timestamps')
            ->defaultTrue()
            ->end()
            ->end();

        return $node;
    }

    private function telemetryProjectorsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('projectors');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('instructor')
            ->defaultTrue()
            ->end()
            ->booleanNode('polyglot')
            ->defaultTrue()
            ->end()
            ->booleanNode('http')
            ->defaultTrue()
            ->end()
            ->booleanNode('agent_ctrl')
            ->defaultTrue()
            ->end()
            ->booleanNode('agents')
            ->defaultTrue()
            ->end()
            ->end();

        return $node;
    }

    private function telemetryHttpNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('http');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('capture_streaming_chunks')
            ->defaultFalse()
            ->end()
            ->end();

        return $node;
    }

    private function telemetryDriversNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('drivers');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->append($this->telemetryCompositeDriverNode())
            ->append($this->telemetryOtelDriverNode())
            ->append($this->telemetryLangfuseDriverNode())
            ->append($this->telemetryLogfireDriverNode())
            ->end();

        return $node;
    }

    private function telemetryCompositeDriverNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('composite');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('exporters')
            ->enumPrototype()
            ->values(['otel', 'langfuse', 'logfire'])
            ->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $node;
    }

    private function telemetryOtelDriverNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('otel');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('endpoint')
            ->defaultNull()
            ->end()
            ->arrayNode('headers')
            ->normalizeKeys(false)
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $node;
    }

    private function telemetryLangfuseDriverNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('langfuse');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('host')
            ->defaultNull()
            ->end()
            ->scalarNode('public_key')
            ->defaultNull()
            ->end()
            ->scalarNode('secret_key')
            ->defaultNull()
            ->end()
            ->end();

        return $node;
    }

    private function telemetryLogfireDriverNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('logfire');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('endpoint')
            ->defaultNull()
            ->end()
            ->scalarNode('write_token')
            ->defaultNull()
            ->end()
            ->arrayNode('headers')
            ->normalizeKeys(false)
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $node;
    }

    private function messengerDeliveryNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('messenger');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->scalarNode('bus_service')
            ->defaultValue('message_bus')
            ->cannotBeEmpty()
            ->end()
            ->arrayNode('observe_events')
            ->scalarPrototype()->end()
            ->defaultValue([])
            ->end()
            ->end();

        return $node;
    }

    private function agentCtrlDefaultsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('defaults');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
            ->ifArray()
            ->then(static fn (array $value): array => self::normalizeAgentCtrlBackendConfig($value))
            ->end()
            ->children()
            ->scalarNode('model')
            ->defaultNull()
            ->end()
            ->integerNode('timeout')
            ->min(1)
            ->defaultValue(300)
            ->end()
            ->scalarNode('working_directory')
            ->defaultNull()
            ->end()
            ->enumNode('sandbox_driver')
            ->values(self::SANDBOX_DRIVERS)
            ->defaultValue('host')
            ->end()
            ->end();

        return $node;
    }

    private function agentCtrlExecutionNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('execution');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('transport')
            ->values(self::AGENT_CTRL_TRANSPORTS)
            ->defaultValue('sync')
            ->end()
            ->booleanNode('allow_cli')
            ->defaultTrue()
            ->end()
            ->booleanNode('allow_http')
            ->defaultFalse()
            ->end()
            ->booleanNode('allow_messenger')
            ->defaultTrue()
            ->end()
            ->end();

        return $node;
    }

    private function agentCtrlContinuationNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('continuation');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('mode')
            ->values(self::AGENT_CTRL_CONTINUATION_MODES)
            ->defaultValue('fresh')
            ->end()
            ->scalarNode('session_key')
            ->defaultValue('agent_ctrl_session_id')
            ->cannotBeEmpty()
            ->end()
            ->booleanNode('persist_session_id')
            ->defaultTrue()
            ->end()
            ->booleanNode('allow_cross_context_resume')
            ->defaultTrue()
            ->end()
            ->end();

        return $node;
    }

    private function agentCtrlBackendsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('backends');
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->children()
            ->append($this->agentCtrlBackendNode('claude_code'))
            ->append($this->agentCtrlBackendNode('codex'))
            ->append($this->agentCtrlBackendNode('opencode'))
            ->append($this->agentCtrlBackendNode('pi'))
            ->append($this->agentCtrlBackendNode('gemini'))
            ->end();

        return $node;
    }

    private function agentCtrlBackendNode(string $name): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);
        $node = $treeBuilder->getRootNode();

        $node
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
            ->ifArray()
            ->then(static fn (array $value): array => self::normalizeAgentCtrlBackendConfig($value))
            ->end()
            ->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->end()
            ->scalarNode('model')
            ->defaultNull()
            ->end()
            ->integerNode('timeout')
            ->min(1)
            ->defaultNull()
            ->end()
            ->scalarNode('working_directory')
            ->defaultNull()
            ->end()
            ->enumNode('sandbox_driver')
            ->values(self::SANDBOX_DRIVERS)
            ->defaultNull()
            ->end()
            ->end();

        return $node;
    }

    /** @param array<string, mixed> $value */
    /** @return array<string, mixed> */
    private function normalizeConnectionRoot(
        array $value,
        string $preferredBucket,
        string $subtreeName,
    ): array {
        if ($this->hasNestedConnectionBucket($value)) {
            return $value;
        }

        $flatConnections = [];

        foreach ($value as $name => $config) {
            if (in_array((string) $name, self::CONNECTION_META_KEYS, true)) {
                continue;
            }

            if (! is_array($config)) {
                throw new InvalidConfigurationException(\sprintf(
                    'The "%s.%s.%s" entry must be an array.',
                    'instructor',
                    $subtreeName,
                    (string) $name,
                ));
            }

            $flatConnections[(string) $name] = $config;
            unset($value[(string) $name]);
        }

        if ($flatConnections === []) {
            return $value;
        }

        $value[$preferredBucket] = $flatConnections;

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function hasNestedConnectionBucket(array $value): bool
    {
        return match (true) {
            array_key_exists('items', $value) => true,
            array_key_exists('connections', $value) => true,
            default => false,
        };
    }

    /** @param array<string, mixed> $value */
    private static function normalizeAgentCtrlRoot(array $value): array
    {
        $normalized = $value;

        if (array_key_exists('default_agent', $normalized) && ! array_key_exists('default_backend', $normalized)) {
            $normalized['default_backend'] = self::normalizeAgentCtrlBackendName($normalized['default_agent']);
        }

        $defaultsConfig = self::normalizeAgentCtrlBackendConfig(self::arrayValue($normalized, 'defaults'));
        $defaults = self::extractAgentCtrlBackendConfig($normalized);
        if ($defaultsConfig !== [] || $defaults !== []) {
            $normalized['defaults'] = array_merge(
                self::normalizeAgentCtrlBackendConfig($defaults),
                $defaultsConfig,
            );
        }

        $backends = self::arrayValue($normalized, 'backends');
        foreach (self::AGENT_CTRL_BACKENDS as $backend) {
            $backendConfig = self::normalizeAgentCtrlBackendConfig(self::arrayValue($backends, $backend));
            $legacyConfig = self::normalizeAgentCtrlBackendConfig(self::arrayValue($normalized, $backend));
            if ($backendConfig === [] && $legacyConfig === []) {
                continue;
            }

            $normalized['backends'][$backend] = array_merge($legacyConfig, $backendConfig);
        }

        return self::withoutAgentCtrlLegacyKeys($normalized);
    }

    /** @param array<string, mixed> $value */
    private static function normalizeSessionsRoot(array $value): array
    {
        $normalized = $value;
        $store = $normalized['store'] ?? $normalized['driver'] ?? $normalized['session_store'] ?? null;

        if (is_string($store) && $store !== '') {
            $normalized['store'] = $store;
        }

        $directory = $normalized['directory'] ?? null;
        if (! is_string($directory) || $directory === '') {
            return $normalized;
        }

        $file = self::arrayValue($normalized, 'file');
        if (array_key_exists('directory', $file)) {
            return $normalized;
        }

        $normalized['file'] = [...$file, 'directory' => $directory];

        return $normalized;
    }

    /** @param array<string, mixed> $value */
    private static function normalizeAgentCtrlBackendConfig(array $value): array
    {
        $normalized = $value;

        if (array_key_exists('directory', $normalized) && ! array_key_exists('working_directory', $normalized)) {
            $normalized['working_directory'] = $normalized['directory'];
        }

        if (array_key_exists('sandbox', $normalized) && ! array_key_exists('sandbox_driver', $normalized)) {
            $normalized['sandbox_driver'] = $normalized['sandbox'];
        }

        return array_diff_key($normalized, array_flip([
            'directory',
            'sandbox',
        ]));
    }

    /** @param array<string, mixed> $value */
    private static function extractAgentCtrlBackendConfig(array $value): array
    {
        return array_intersect_key($value, array_flip([
            'model',
            'timeout',
            'directory',
            'working_directory',
            'sandbox',
            'sandbox_driver',
        ]));
    }

    /** @param array<string, mixed> $value */
    private static function withoutAgentCtrlLegacyKeys(array $value): array
    {
        $legacyKeys = [
            'default_agent',
            'model',
            'timeout',
            'directory',
            'working_directory',
            'sandbox',
            'sandbox_driver',
        ];

        foreach (self::AGENT_CTRL_BACKENDS as $backend) {
            $legacyKeys[] = $backend;
        }

        return array_diff_key($value, array_flip($legacyKeys));
    }

    /** @param array<string, mixed> $value */
    private static function arrayValue(array $value, string $key): array
    {
        $candidate = $value[$key] ?? [];

        return is_array($candidate) ? $candidate : [];
    }

    private static function normalizeAgentCtrlBackendName(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_replace('-', '_', $value);
    }
}
