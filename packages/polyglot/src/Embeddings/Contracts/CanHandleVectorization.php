<?php declare(strict_types=1);
namespace Cognesy\Polyglot\Embeddings\Contracts;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;

/**
 * Interface CanVectorize
 *
 * Defines the contract for embedding generation services
 */
interface CanHandleVectorization
{
    /**
     * Generate embeddings for the input
     *
     * @param array<string> $input
     * @param array $options
     * @return HttpResponse
     */
    public function handle(EmbeddingsRequest $request) : HttpResponse;
    public function fromData(array $data): ?EmbeddingsResponse;
}
