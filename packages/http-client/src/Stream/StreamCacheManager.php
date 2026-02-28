<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Enums\StreamCachePolicy;

final class StreamCacheManager implements CanManageStreamCache
{
    #[\Override]
    public function manage(HttpResponse $response, StreamCachePolicy $policy): HttpResponse {
        if (!$response->isStreamed()) {
            return $response;
        }
        $stream = match ($policy) {
            StreamCachePolicy::None => new IterableStream($response->rawStream()),
            StreamCachePolicy::Memory => BufferedStream::fromStream($response->rawStream()),
        };
        return $response->withStream($stream);
    }
}

