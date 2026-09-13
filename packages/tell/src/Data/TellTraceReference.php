<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** Immutable location and health evidence for one local Tell execution trace. */
final readonly class TellTraceReference
{
    /** @param 'execution'|'session' $storageKind */
    public function __construct(
        public ?string $executionId,
        public TellTraceStatus $status,
        public string $storageKind = 'execution',
        public ?string $path = null,
    ) {}

    public static function disabled(): self {
        return new self(null, TellTraceStatus::Disabled);
    }

    /** @return array{executionId: ?string, status: string, storageKind: string, path: ?string} */
    public function toArray(): array {
        return [
            'executionId' => $this->executionId,
            'status' => $this->status->value,
            'storageKind' => $this->storageKind,
            'path' => $this->path,
        ];
    }
}
