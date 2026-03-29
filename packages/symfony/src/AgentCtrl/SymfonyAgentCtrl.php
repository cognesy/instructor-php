<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\GeminiBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\PiBridgeBuilder;
use Cognesy\AgentCtrl\Config\AgentCtrlConfig;
use Cognesy\AgentCtrl\Contract\AgentBridgeBuilder;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Config\Contracts\CanProvideConfig;

/**
 * Symfony container entrypoint for AgentCtrl builders.
 */
final readonly class SymfonyAgentCtrl
{
    public function __construct(
        private CanProvideConfig $configProvider,
    ) {}

    public function defaultBuilder(): AgentBridgeBuilder
    {
        return $this->make($this->defaultBackend());
    }

    public function defaultBackendName(): string
    {
        return $this->defaultBackend();
    }

    public function backend(AgentType|string $type): string
    {
        return $this->normalizeBackend($type);
    }

    public function make(AgentType|string $type): AgentBridgeBuilder
    {
        $backend = $this->normalizeBackend($type);
        $this->assertEnabled();
        $config = $this->resolveConfig($backend);

        return match ($backend) {
            'claude_code' => AgentCtrl::claudeCode()->withConfig($config),
            'codex' => AgentCtrl::codex()->withConfig($config),
            'opencode' => AgentCtrl::openCode()->withConfig($config),
            'pi' => AgentCtrl::pi()->withConfig($config),
            'gemini' => AgentCtrl::gemini()->withConfig($config),
        };
    }

    public function continueLast(AgentType|string $type): AgentBridgeBuilder
    {
        $backend = $this->normalizeBackend($type);

        return match ($backend) {
            'claude_code' => $this->claudeCode()->continueSession(),
            'codex' => $this->codex()->continueSession(),
            'opencode' => $this->openCode()->continueSession(),
            'pi' => $this->pi()->continueSession(),
            'gemini' => $this->gemini()->continueSession(),
        };
    }

    public function resumeSession(AgentType|string $type, string $sessionId): AgentBridgeBuilder
    {
        $backend = $this->normalizeBackend($type);

        return match ($backend) {
            'claude_code' => $this->claudeCode()->resumeSession($sessionId),
            'codex' => $this->codex()->resumeSession($sessionId),
            'opencode' => $this->openCode()->resumeSession($sessionId),
            'pi' => $this->pi()->resumeSession($sessionId),
            'gemini' => $this->gemini()->resumeSession($sessionId),
        };
    }

    public function claudeCode(): ClaudeCodeBridgeBuilder
    {
        /** @var ClaudeCodeBridgeBuilder $builder */
        $builder = $this->make('claude_code');

        return $builder;
    }

    public function codex(): CodexBridgeBuilder
    {
        /** @var CodexBridgeBuilder $builder */
        $builder = $this->make('codex');

        return $builder;
    }

    public function openCode(): OpenCodeBridgeBuilder
    {
        /** @var OpenCodeBridgeBuilder $builder */
        $builder = $this->make('opencode');

        return $builder;
    }

    public function pi(): PiBridgeBuilder
    {
        /** @var PiBridgeBuilder $builder */
        $builder = $this->make('pi');

        return $builder;
    }

    public function gemini(): GeminiBridgeBuilder
    {
        /** @var GeminiBridgeBuilder $builder */
        $builder = $this->make('gemini');

        return $builder;
    }

    private function defaultBackend(): string
    {
        $value = $this->configProvider->get('instructor.agent_ctrl.default_backend', 'claude_code');

        return $this->normalizeBackend(is_string($value) ? $value : 'claude_code');
    }

    private function assertEnabled(): void
    {
        if ((bool) $this->configProvider->get('instructor.agent_ctrl.enabled', false)) {
            return;
        }

        throw new \RuntimeException(
            'AgentCtrl integration is disabled. Set instructor.agent_ctrl.enabled to true to use Symfony AgentCtrl services.',
        );
    }

    private function resolveConfig(string $backend): AgentCtrlConfig
    {
        $backendConfig = $this->backendConfig($backend);
        $this->assertBackendEnabled($backend, $backendConfig);

        $defaults = AgentCtrlConfig::fromArray($this->toAgentCtrlConfig($this->defaultsConfig()));
        $overrides = $this->toAgentCtrlConfig($backendConfig);

        return $defaults->withOverrides($overrides);
    }

    /** @return array<string, mixed> */
    private function defaultsConfig(): array
    {
        $value = $this->configProvider->get('instructor.agent_ctrl.defaults', []);

        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    private function backendConfig(string $backend): array
    {
        $value = $this->configProvider->get("instructor.agent_ctrl.backends.{$backend}", []);

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $config */
    private function assertBackendEnabled(string $backend, array $config): void
    {
        if (($config['enabled'] ?? true) === true) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'AgentCtrl backend "%s" is disabled under instructor.agent_ctrl.backends.%s.enabled.',
            $backend,
            $backend,
        ));
    }

    /** @param array<string, mixed> $config */
    private function toAgentCtrlConfig(array $config): array
    {
        $mapped = $config;

        if (array_key_exists('working_directory', $mapped)) {
            $mapped['workingDirectory'] = $mapped['working_directory'];
        }

        if (array_key_exists('sandbox_driver', $mapped)) {
            $mapped['sandboxDriver'] = $mapped['sandbox_driver'];
        }

        return array_intersect_key($mapped, [
            'model' => true,
            'timeout' => true,
            'workingDirectory' => true,
            'sandboxDriver' => true,
        ]);
    }

    private function normalizeBackend(AgentType|string $type): string
    {
        if ($type instanceof AgentType) {
            return $this->normalizeBackendName($type->value);
        }

        return $this->normalizeBackendName($type);
    }

    private function normalizeBackendName(string $backend): string
    {
        return match (str_replace('-', '_', strtolower($backend))) {
            'claude_code' => 'claude_code',
            'codex' => 'codex',
            'opencode' => 'opencode',
            'pi' => 'pi',
            'gemini' => 'gemini',
            default => throw new \InvalidArgumentException(sprintf('Unsupported AgentCtrl backend: %s', $backend)),
        };
    }
}
