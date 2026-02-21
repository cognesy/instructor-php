<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

final readonly class SaveResult
{
    private function __construct(
        private bool $ok,
        public ?AgentSession $session = null,
        public ?string $message = null,
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////////

    public static function ok(AgentSession $session): self {
        return new self(ok: true, session: $session);
    }

    public static function conflict(string $message): self {
        return new self(ok: false, message: $message);
    }

    // ACCESSORS ///////////////////////////////////////////////////

    public function isOk(): bool {
        return $this->ok;
    }

    public function isConflict(): bool {
        return !$this->ok;
    }
}
