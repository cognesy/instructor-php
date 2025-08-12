<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule;

use Cognesy\InstructorHub\Data\Example;

interface CanBeTuned {
    /**
     * @param array<string, mixed> $context Value pairs that provide context for the prompt.
     */
    public function renderPrompt(array $context): string;
    public function instructions(): string;
    public function withInstructions(string $instructions): self;
    /** @returns Example[] */
    public function examples(): array;
    /** @param Example[] $examples */
    public function withExamples(array $examples): self;
    public function toArray(): array;
    public static function fromArray(array $data): self;
}