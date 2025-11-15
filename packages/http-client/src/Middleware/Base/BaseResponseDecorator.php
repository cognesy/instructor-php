<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\Base;

use Closure;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\TransformStream;

/**
 * BaseResponseDecorator
 *
 * Composition-based response decoration utility.
 * Transforms the response stream without subclassing HttpResponse.
 */
final class BaseResponseDecorator
{
    /**
     * Decorate the response by mapping each chunk through a transformer.
     *
     * @param callable(string):string $transformChunk
     */
    public static function decorate(HttpResponse $response, callable $transformChunk): HttpResponse {
        return $response->withStream(
            new TransformStream(
                $response->rawStream(),
                Closure::fromCallable($transformChunk),
            )
        );
    }
}
