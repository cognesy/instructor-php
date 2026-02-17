<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Utils\EmbedUtils;

it('accepts only runtime creator contract in findSimilar', function () {
    $method = new \ReflectionMethod(EmbedUtils::class, 'findSimilar');
    $embeddings = $method->getParameters()[0];

    expect((string) $embeddings->getType())->toBe(CanCreateEmbeddings::class);
});
