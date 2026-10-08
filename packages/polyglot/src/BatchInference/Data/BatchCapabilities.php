<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use InvalidArgumentException;

final readonly class BatchCapabilities
{
    public function __construct(
        private bool $canCancel,
        private bool $canList,
        private bool $supportsPartialResults,
        /** @var list<BatchInputSupport> */
        private array $inputModes = [],
        private bool $canReadResults = true,
    ) {
        $seen = [];
        foreach ($inputModes as $mode) {
            if (!$mode instanceof BatchInputSupport || isset($seen[$mode->kind()->value])) {
                throw new InvalidArgumentException('Batch input modes must be unique capability values.');
            }
            $seen[$mode->kind()->value] = true;
        }
    }

    public function canCancel(): bool
    {
        return $this->canCancel;
    }
    public function canList(): bool
    {
        return $this->canList;
    }
    public function supportsPartialResults(): bool
    {
        return $this->supportsPartialResults;
    }
    public function canReadResults(): bool
    {
        return $this->canReadResults;
    }

    /** @return list<BatchInputSupport> */
    public function inputModes(): array
    {
        return $this->inputModes;
    }

    public function inputMode(BatchInputKind $kind): ?BatchInputSupport
    {
        foreach ($this->inputModes as $mode) {
            if ($mode->kind() === $kind) {
                return $mode;
            }
        }
        return null;
    }
}
