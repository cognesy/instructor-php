<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Transformation;

use Cognesy\Retrieval\Indexing\Contracts\DocumentTransformer;
use Cognesy\Retrieval\Indexing\Data\TextDocument;
use InvalidArgumentException;
use Override;

final readonly class TextSplitter implements DocumentTransformer
{
    public function __construct(
        private int $maxCharacters = 2000,
        private string $version = 'text-splitter-v1',
    ) {
        if ($maxCharacters < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1');
        }
    }

    #[Override]
    public function transform(TextDocument $document): iterable
    {
        if ($document->content === '') {
            yield $document;

            return;
        }
        $length = strlen($document->content);
        $position = 0;
        $index = 0;
        while ($position < $length) {
            $content = substr($document->content, $position, $this->maxCharacters);
            yield new TextDocument(
                id: $this->chunkId($document, $index, $content),
                content: $content,
                metadata: [...$document->metadata, '_chunk_index' => $index, '_parent_id' => $document->id],
                sourceId: $document->sourceIdentity(),
                sourceVersion: $document->sourceVersion,
            );
            $position += $this->maxCharacters;
            $index++;
        }
    }

    private function chunkId(TextDocument $document, int $index, string $content): string
    {
        return hash('sha256', implode("\0", [
            $document->sourceIdentity(),
            $document->sourceVersion,
            $this->version,
            (string) $index,
            hash('sha256', $content),
        ]));
    }
}
