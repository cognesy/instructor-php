<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class NoulCriteria
{
    private ?JsonContent $true;

    private ?JsonContent $false;

    public function __construct(
        string|JsonContent|null $true = null,
        string|JsonContent|null $false = null,
    ) {
        $this->true = is_string($true) ? JsonContent::text($true) : $true;
        $this->false = is_string($false) ? JsonContent::text($false) : $false;
    }

    public function trueDescription(): ?JsonContent
    {
        return $this->true;
    }

    public function falseDescription(): ?JsonContent
    {
        return $this->false;
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->true !== null) {
            $data['true'] = $this->true->value();
        }
        if ($this->false !== null) {
            $data['false'] = $this->false->value();
        }

        return $data;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['true', 'false'], 'Noul criteria');

        return new self(
            true: DecisionData::optionalJsonContent($data['true'] ?? null, 'Noul criteria true'),
            false: DecisionData::optionalJsonContent($data['false'] ?? null, 'Noul criteria false'),
        );
    }
}
