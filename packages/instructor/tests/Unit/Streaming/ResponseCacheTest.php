<?php declare(strict_types=1);

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Streaming\ResponseCache;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

function cacheResponse(): StructuredOutputResponse {
    return StructuredOutputResponse::partial(
        value: (object) ['x' => 1],
        inferenceResponse: new InferenceResponse(content: ''),
    );
}

it('retains nothing and cannot replay under the None policy', function () {
    $cache = new ResponseCache(ResponseCachePolicy::None);
    $cache->remember(cacheResponse());

    expect($cache->canReplay())->toBeFalse();
    expect($cache->replay())->toBe([]);
});

it('retains responses and replays them under the Memory policy', function () {
    $cache = new ResponseCache(ResponseCachePolicy::Memory);
    $first = cacheResponse();
    $second = cacheResponse();
    $cache->remember($first);
    $cache->remember($second);

    expect($cache->canReplay())->toBeTrue();
    expect($cache->replay())->toBe([$first, $second]);
});

it('invalidates itself instead of retaining a partial history when the cap is exceeded', function () {
    $cache = new ResponseCache(ResponseCachePolicy::Memory);
    $response = cacheResponse();
    for ($i = 0; $i <= ResponseCache::MAX_CACHED_RESPONSES; $i++) {
        $cache->remember($response);
    }

    // replay must be complete or explicitly unavailable — never truncated
    expect($cache->canReplay())->toBeFalse();
    expect($cache->replay())->toBe([]);
});
