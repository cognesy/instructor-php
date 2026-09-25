<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Models;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class DecisionCapabilities
{
    public function __construct(
        public DecisionPrimitiveSupport $choice = DecisionPrimitiveSupport::Unknown,
        public DecisionPrimitiveSupport $noul = DecisionPrimitiveSupport::Unknown,
        public DecisionPrimitiveSupport $score = DecisionPrimitiveSupport::Unknown,
    ) {}

    public static function unknown(): self
    {
        return new self;
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['choice', 'noul', 'score'], 'Decision capabilities');

        return new self(
            choice: DecisionPrimitiveSupport::fromMixed($data['choice'] ?? null),
            noul: DecisionPrimitiveSupport::fromMixed($data['noul'] ?? null),
            score: DecisionPrimitiveSupport::fromMixed($data['score'] ?? null),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'choice' => $this->choice->value,
            'noul' => $this->noul->value,
            'score' => $this->score->value,
        ], static fn (string $support): bool => $support !== DecisionPrimitiveSupport::Unknown->value);
    }
}
