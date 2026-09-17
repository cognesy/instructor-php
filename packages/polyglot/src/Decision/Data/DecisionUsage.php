<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Polyglot\Decision\Internal\DecisionData;

final readonly class DecisionUsage
{
    public function __construct(
        private ?int $inputTokens = null,
        private ?int $outputTokens = null,
    ) {
        DecisionData::optionalNonNegativeInt($inputTokens, 'Decision input token count');
        DecisionData::optionalNonNegativeInt($outputTokens, 'Decision output token count');
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['input', 'output'], 'Decision usage');

        return new self(
            inputTokens: DecisionData::optionalNonNegativeInt($data['input'] ?? null, 'Decision input token count'),
            outputTokens: DecisionData::optionalNonNegativeInt($data['output'] ?? null, 'Decision output token count'),
        );
    }

    public function inputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function totalTokens(): ?int
    {
        if ($this->inputTokens === null || $this->outputTokens === null) {
            return null;
        }

        return $this->inputTokens + $this->outputTokens;
    }

    public function toArray(): array
    {
        return ['input' => $this->inputTokens, 'output' => $this->outputTokens];
    }
}
