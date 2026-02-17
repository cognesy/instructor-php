<?php declare(strict_types=1);

use Cognesy\Auxiliary\Web\Filters\EmbeddingsSimilarityFilter;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;

it('requires constructor-provided embeddings creator', function () {
    $constructor = new \ReflectionMethod(EmbeddingsSimilarityFilter::class, '__construct');
    $embeddings = $constructor->getParameters()[1];

    expect((string) $embeddings->getType())->toBe(CanCreateEmbeddings::class);
    expect($embeddings->isOptional())->toBeFalse();
});
