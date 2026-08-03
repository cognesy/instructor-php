<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\Contracts\CanDescribeTool;

final readonly class ToolProfile
{
    /**
     * @param list<string> $promptGuidelines
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $instructions
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?string $promptSnippet,
        public array $promptGuidelines,
        public array $metadata,
        public array $instructions,
        public bool $deferred = false,
    ) {}

    public static function fromDescriptor(CanDescribeTool $descriptor, bool $deferred = false): self {
        $snippet = match (true) {
            $descriptor instanceof CanContributeToPrompt => $descriptor->promptSnippet(),
            default => self::fallbackSnippet($descriptor),
        };
        $guidelines = match (true) {
            $descriptor instanceof CanContributeToPrompt => $descriptor->promptGuidelines(),
            default => [],
        };

        return new self(
            name: $descriptor->name(),
            description: $descriptor->description(),
            promptSnippet: $snippet,
            promptGuidelines: $guidelines,
            metadata: $descriptor->metadata(),
            instructions: $descriptor->instructions(),
            deferred: $deferred,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'promptSnippet' => $this->promptSnippet,
            'promptGuidelines' => $this->promptGuidelines,
            'metadata' => $this->metadata,
            'instructions' => $this->instructions,
            'deferred' => $this->deferred,
        ];
    }

    private static function fallbackSnippet(CanDescribeTool $descriptor): ?string {
        $metadata = $descriptor->metadata();
        $candidate = $metadata['summary'] ?? $descriptor->description();
        if (!is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $firstLine = explode("\n", $candidate, 2)[0];
        $sentenceEnd = strpos($firstLine, '.');
        $oneSentence = match (true) {
            $sentenceEnd !== false => substr($firstLine, 0, $sentenceEnd + 1),
            default => $firstLine,
        };

        return mb_strlen($oneSentence) > 160
            ? rtrim(mb_substr($oneSentence, 0, 157)) . '...'
            : $oneSentence;
    }
}
