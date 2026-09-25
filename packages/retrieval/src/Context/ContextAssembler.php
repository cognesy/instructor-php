<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Context;

use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenizer;

final readonly class ContextAssembler
{
    /** @param list<string> $metadataKeys */
    public function __construct(
        private ?CanCountTokens $tokenizer = null,
        private array $metadataKeys = [],
    ) {}

    public function assemble(SearchHits $hits, ContextBudget $budget = new ContextBudget): AssembledContext
    {
        $text = '';
        $evidence = [];
        $seen = [];
        foreach ($hits as $hit) {
            if (count($evidence) >= $budget->maxEvidence || $hit->content === null || trim($hit->content) === '') {
                continue;
            }
            $identity = get_debug_type($hit->id).':'.(string) $hit->id;
            if (isset($seen[$identity])) {
                continue;
            }
            $citation = 'S'.(count($evidence) + 1);
            $prefix = $text === '' ? '' : "\n\n";
            $content = self::truncateBytes($hit->content, $budget->maxExcerptBytes);
            $block = $this->fitBlock($prefix, $citation, $content, $text, $budget);
            if ($block === null) {
                continue;
            }
            $includedContent = substr($block, strlen($prefix.'['.$citation.'] '));
            $text .= $block;
            $evidence[] = new EvidenceReference(
                $citation,
                $hit->id,
                $includedContent,
                $hit->score,
                $this->metadata($hit),
            );
            $seen[$identity] = true;
        }

        return new AssembledContext(
            $text,
            $evidence,
            strlen($text),
            $this->tokens($text),
            max(0, $hits->count() - count($evidence)),
        );
    }

    private function fitBlock(
        string $separator,
        string $citation,
        string $content,
        string $current,
        ContextBudget $budget,
    ): ?string {
        $prefix = $separator.'['.$citation.'] ';
        $minimum = $prefix.self::truncateBytes($content, 1);
        if (! $this->fits($current.$minimum, $budget)) {
            return null;
        }
        $low = 1;
        $high = strlen($content);
        $best = self::truncateBytes($content, 1);
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $candidateContent = self::truncateBytes($content, $middle);
            if ($this->fits($current.$prefix.$candidateContent, $budget)) {
                $best = $candidateContent;
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return $prefix.$best;
    }

    private function fits(string $text, ContextBudget $budget): bool
    {
        return strlen($text) <= $budget->maxBytes && $this->tokens($text) <= $budget->maxTokens;
    }

    private function tokens(string $text): int
    {
        return ($this->tokenizer ?? Tokenizer::default())->tokenCount($text);
    }

    /** @return array<string, mixed> */
    private function metadata(SearchHit $hit): array
    {
        $selected = [];
        foreach ($this->metadataKeys as $key) {
            if (array_key_exists($key, $hit->metadata)) {
                $selected[$key] = $hit->metadata[$key];
            }
        }

        return $selected;
    }

    private static function truncateBytes(string $text, int $maxBytes): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }
        $truncated = substr($text, 0, $maxBytes);
        while ($truncated !== '' && preg_match('//u', $truncated) !== 1) {
            $truncated = substr($truncated, 0, -1);
        }

        return $truncated;
    }
}
