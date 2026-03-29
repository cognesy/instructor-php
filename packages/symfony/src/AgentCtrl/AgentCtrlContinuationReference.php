<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

use InvalidArgumentException;

/**
 * Serializable continuation reference for Symfony handoff and resume flows.
 */
final readonly class AgentCtrlContinuationReference
{
    public function __construct(
        public string $backend,
        public AgentCtrlContinuationMode $mode,
        public string $sessionKey,
        public ?string $sessionId = null,
        public ?AgentCtrlExecutionContext $sourceContext = null,
    ) {
        if ($this->mode === AgentCtrlContinuationMode::ResumeSession && $this->sessionId === null) {
            throw new InvalidArgumentException('resume_session continuations require a non-empty session ID.');
        }
    }

    public static function fresh(string $backend, string $sessionKey): self
    {
        return new self(
            backend: $backend,
            mode: AgentCtrlContinuationMode::Fresh,
            sessionKey: $sessionKey,
        );
    }

    public static function continueLast(
        string $backend,
        string $sessionKey,
        ?AgentCtrlExecutionContext $sourceContext = null,
    ): self {
        return new self(
            backend: $backend,
            mode: AgentCtrlContinuationMode::ContinueLast,
            sessionKey: $sessionKey,
            sourceContext: $sourceContext,
        );
    }

    public static function resumeSession(
        string $backend,
        string $sessionId,
        string $sessionKey,
        ?AgentCtrlExecutionContext $sourceContext = null,
    ): self {
        return new self(
            backend: $backend,
            mode: AgentCtrlContinuationMode::ResumeSession,
            sessionKey: $sessionKey,
            sessionId: trim($sessionId) !== '' ? $sessionId : null,
            sourceContext: $sourceContext,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $backend = self::requiredString($data, 'backend');
        $mode = self::requiredMode($data);
        $sessionKey = self::requiredString($data, 'session_key');

        return new self(
            backend: $backend,
            mode: $mode,
            sessionKey: $sessionKey,
            sessionId: self::optionalString($data, 'session_id'),
            sourceContext: self::optionalContext($data, 'source_context'),
        );
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'backend' => $this->backend,
            'mode' => $this->mode->value,
            'session_key' => $this->sessionKey,
            'session_id' => $this->sessionId,
            'source_context' => $this->sourceContext?->value,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'AgentCtrl continuation reference is missing required "%s".',
                $key,
            ));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private static function requiredMode(array $data): AgentCtrlContinuationMode
    {
        $value = self::requiredString($data, 'mode');
        $mode = AgentCtrlContinuationMode::tryFrom($value);

        if ($mode !== null) {
            return $mode;
        }

        throw new InvalidArgumentException(sprintf(
            'AgentCtrl continuation reference has invalid "mode": %s.',
            $value,
        ));
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'AgentCtrl continuation reference has invalid "%s".',
                $key,
            ));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    private static function optionalContext(array $data, string $key): ?AgentCtrlExecutionContext
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'AgentCtrl continuation reference has invalid "%s".',
                $key,
            ));
        }

        $context = AgentCtrlExecutionContext::tryFrom(trim($value));

        if ($context !== null) {
            return $context;
        }

        throw new InvalidArgumentException(sprintf(
            'AgentCtrl continuation reference has invalid "%s": %s.',
            $key,
            $value,
        ));
    }
}
