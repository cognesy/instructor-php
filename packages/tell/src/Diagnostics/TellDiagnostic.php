<?php

declare(strict_types=1);

namespace Cognesy\Tell\Diagnostics;

final readonly class TellDiagnostic
{
    public function __construct(
        public string $code,
        public string $source,
        public string $severity,
        public string $message,
    ) {}

    /** @return array{code: string, source: string, severity: string, message: string} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'source' => $this->source,
            'severity' => $this->severity,
            'message' => $this->message,
        ];
    }

    /** @return array{code: string, source: string, severity: string, message: string} */
    public function toExternalArray(): array
    {
        return [
            'code' => $this->code,
            'source' => $this->source,
            'severity' => $this->severity,
            'message' => 'Installed Composer extension metadata is invalid; inspect local Tell diagnostics.',
        ];
    }
}
