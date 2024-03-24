<?php
namespace Cognesy\Instructor\LLMs\Anthropic\Data;

class Response
{
    public function __construct(
        public string $id,
        public string $type,
        public string $role,
        public array $content,
        public string $model,
        public ?string $stopReason,  // end_turn | stop_sequence | max_tokens | null
        public ?string $stopSequence,
        public Usage $usage,
    ) {}
}