<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
it('rejects retryPolicy in options', function () {
    $builder = new InferenceRequestBuilder();

    $build = fn() => $builder
        ->withMessages('Retry')
        ->withOptions(['retryPolicy' => ['maxAttempts' => 2]])
        ->create();

    expect($build)->toThrow(InvalidArgumentException::class);
});
