<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

/**
 * Continuation and handoff policy derived from the Symfony agent_ctrl config subtree.
 */
final readonly class AgentCtrlContinuationPolicy
{
    public function __construct(
        public AgentCtrlContinuationMode $mode,
        public string $sessionKey,
        public bool $persistSessionId,
        public bool $allowCrossContextResume,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self(
            mode: AgentCtrlContinuationMode::from(is_string($config['mode'] ?? null) ? $config['mode'] : 'fresh'),
            sessionKey: self::normalizeSessionKey($config['session_key'] ?? 'agent_ctrl_session_id'),
            persistSessionId: self::toBool($config['persist_session_id'] ?? true),
            allowCrossContextResume: self::toBool($config['allow_cross_context_resume'] ?? true),
        );
    }

    public function createsHandoff(): bool
    {
        return $this->persistSessionId;
    }

    private static function normalizeSessionKey(mixed $value): string
    {
        return match (true) {
            is_string($value) && trim($value) !== '' => $value,
            default => 'agent_ctrl_session_id',
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
