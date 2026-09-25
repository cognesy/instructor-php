<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Retrieval\Context\AssembledContext;
use Cognesy\Utils\Json\Json;
use RuntimeException;
use Stringable;

final readonly class RetrievalSearchResult implements Stringable
{
    /** @param list<array{citation: string, id: int|string, score: float}> $citations */
    private function __construct(
        public string $context,
        public array $citations,
        public int $omitted,
        private int $maxOutputBytes,
    ) {}

    public static function fromContext(AssembledContext $context, int $maxOutputBytes): self {
        $citations = [];
        foreach ($context->evidence as $reference) {
            $citations[] = [
                'citation' => $reference->citation,
                'id' => $reference->id,
                'score' => $reference->score->value,
            ];
        }
        return new self($context->text, $citations, $context->omitted, $maxOutputBytes);
    }

    /** @return array{context: string, citations: list<array{citation: string, id: int|string, score: float}>, omitted: int} */
    public function toArray(): array {
        return [
            'context' => $this->context,
            'citations' => $this->citations,
            'omitted' => $this->omitted,
        ];
    }

    public function __toString(): string {
        $json = Json::encode($this->toArray());
        if (strlen($json) > $this->maxOutputBytes) {
            throw new RuntimeException('Rendered retrieval tool output exceeds its configured byte limit');
        }
        return $json;
    }
}
