<?php
namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class Request
{
    public function __construct(
        public string $model,
        public array $messages,
        public int $maxTokens,
        public ?string $system,
        public ?Metadata $metadata,
        public ?array $stopSequences,
        public ?bool $stream,
        public ?float $temperature,
        public ?float $topP,
        public ?int $topK,
    ) {}
}