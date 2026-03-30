<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Support;

use Cognesy\Logging\Factories\SymfonyLoggingFactory as BaseSymfonyLoggingFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class SymfonyLoggingFactory
{
    /**
     * @param array<string, mixed> $config
     * @return callable(object): void
     */
    public function make(
        ContainerInterface $container,
        ?LoggerInterface $logger,
        array $config,
    ): callable {
        return BaseSymfonyLoggingFactory::create(
            $container,
            $logger ?? new NullLogger,
            $this->resolvedConfig($container, $config),
        );
    }

    /** @param array<string, mixed> $config */
    /** @return array<string, mixed> */
    private function resolvedConfig(ContainerInterface $container, array $config): array
    {
        $preset = is_string($config['preset'] ?? null) ? $config['preset'] : 'production';
        $baseConfig = match ($preset) {
            'development', 'default' => $this->developmentConfig($container),
            'production' => $this->productionConfig(),
            default => [],
        };

        return array_replace($baseConfig, $this->configOverrides($config));
    }

    /** @return array<string, mixed> */
    private function developmentConfig(ContainerInterface $container): array
    {
        $debug = $container->hasParameter('kernel.debug')
            && $container->getParameter('kernel.debug') === true;

        return [
            'channel' => 'instructor',
            'level' => $debug ? 'debug' : 'info',
            'exclude_events' => [
                \Cognesy\Http\Events\DebugRequestBodyUsed::class,
                \Cognesy\Http\Events\DebugResponseBodyReceived::class,
                \Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated::class,
                \Cognesy\Polyglot\Inference\Events\StreamEventParsed::class,
            ],
            'include_events' => [],
            'templates' => $this->defaultTemplates(),
        ];
    }

    /** @return array<string, mixed> */
    private function productionConfig(): array
    {
        return [
            'channel' => 'instructor',
            'level' => 'warning',
            'exclude_events' => [
                \Cognesy\Http\Events\DebugRequestBodyUsed::class,
                \Cognesy\Http\Events\DebugResponseBodyReceived::class,
                \Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated::class,
                \Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated::class,
                \Cognesy\Polyglot\Inference\Events\StreamEventParsed::class,
            ],
            'include_events' => [],
            'templates' => [],
        ];
    }

    /** @param array<string, mixed> $config */
    /** @return array<string, mixed> */
    private function configOverrides(array $config): array
    {
        $explicit = is_array($config['_explicit'] ?? null) ? $config['_explicit'] : [];

        return array_filter([
            'channel' => ($explicit['channel'] ?? false) && is_string($config['channel'] ?? null)
                ? $config['channel']
                : null,
            'level' => ($explicit['level'] ?? false) && is_string($config['level'] ?? null)
                ? $config['level']
                : null,
            'exclude_events' => ($explicit['exclude_events'] ?? false) && is_array($config['exclude_events'] ?? null)
                ? $config['exclude_events']
                : null,
            'include_events' => ($explicit['include_events'] ?? false) && is_array($config['include_events'] ?? null)
                ? $config['include_events']
                : null,
            'templates' => ($explicit['templates'] ?? false) && is_array($config['templates'] ?? null)
                ? $config['templates']
                : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, string> */
    private function defaultTemplates(): array
    {
        return [
            \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
                'Starting {responseClass} generation with {model}',
            \Cognesy\Instructor\Events\Response\ResponseValidationFailed::class =>
                'Validation failed for {responseClass}: {error}',
            \Cognesy\Http\Events\HttpRequestSent::class =>
                'HTTP {method} {url}',
            \Cognesy\Agents\Events\AgentExecutionStarted::class =>
                'Native agent {agentId} started with {messages} messages and {tools} tools',
            \Cognesy\Agents\Events\AgentStepCompleted::class =>
                'Native agent {agentId} step {step} completed in {durationMs}ms',
            \Cognesy\Agents\Events\AgentExecutionFailed::class =>
                'Native agent {agentId} failed: {error}',
            \Cognesy\AgentCtrl\Event\AgentExecutionStarted::class =>
                'Code agent {agentType} started',
            \Cognesy\AgentCtrl\Event\AgentExecutionCompleted::class =>
                'Code agent {agentType} completed with exit code {exitCode}',
            \Cognesy\AgentCtrl\Event\AgentErrorOccurred::class =>
                'Code agent {agentType} failed: {error}',
        ];
    }
}
