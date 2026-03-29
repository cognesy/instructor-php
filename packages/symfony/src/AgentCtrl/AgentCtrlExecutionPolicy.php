<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

/**
 * Runtime execution policy derived from the Symfony agent_ctrl config subtree.
 */
final readonly class AgentCtrlExecutionPolicy
{
    public function __construct(
        public AgentCtrlExecutionContext $context,
        public string $transport,
        public bool $allowCli,
        public bool $allowHttp,
        public bool $allowMessenger,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(AgentCtrlExecutionContext $context, array $config): self
    {
        return new self(
            context: $context,
            transport: self::normalizeTransport($config['transport'] ?? 'sync'),
            allowCli: self::toBool($config['allow_cli'] ?? true),
            allowHttp: self::toBool($config['allow_http'] ?? false),
            allowMessenger: self::toBool($config['allow_messenger'] ?? true),
        );
    }

    public function allowsCurrentContext(): bool
    {
        return match ($this->context) {
            AgentCtrlExecutionContext::Cli => $this->allowCli,
            AgentCtrlExecutionContext::Http => $this->allowHttp,
            AgentCtrlExecutionContext::Messenger => $this->allowMessenger,
        };
    }

    public function allowsInlineExecution(): bool
    {
        if (! $this->allowsCurrentContext()) {
            return false;
        }

        return match ($this->context) {
            AgentCtrlExecutionContext::Messenger => true,
            default => $this->transport === 'sync',
        };
    }

    public function requiresMessengerDispatch(): bool
    {
        if (! $this->allowsCurrentContext()) {
            return false;
        }

        return match ($this->context) {
            AgentCtrlExecutionContext::Messenger => false,
            default => $this->transport === 'messenger',
        };
    }

    public function assertContextAllowed(): void
    {
        if ($this->allowsCurrentContext()) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'AgentCtrl %s execution is disabled. Set instructor.agent_ctrl.execution.%s to true to enable it.',
            $this->context->value,
            $this->context->configKey(),
        ));
    }

    public function assertInlineExecutionAllowed(): void
    {
        $this->assertContextAllowed();

        if (! $this->requiresMessengerDispatch()) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'AgentCtrl %s execution is configured for messenger transport. Dispatch it through the Messenger runtime instead of executing inline.',
            $this->context->value,
        ));
    }

    private static function normalizeTransport(mixed $value): string
    {
        return match (is_string($value) ? strtolower($value) : null) {
            'messenger' => 'messenger',
            default => 'sync',
        };
    }

    private static function toBool(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            default => (bool) $value,
        };
    }
}
