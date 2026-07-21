<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

// Regression: configuration hydration failures must not echo raw credentials.
// Previously fromArray() appended a json_encode() dump of the full typed config
// (including apiKey) to the InvalidArgumentException message.

/**
 * @return array{exception: Throwable, snapshot: string}
 */
function capturedConfigException(callable $operation): array
{
    $previousSetting = ini_set('zend.exception_ignore_args', '0');

    try {
        $operation();
        throw new RuntimeException('Expected configuration operation to fail.');
    } catch (Throwable $exception) {
        $snapshot = configExceptionChainSnapshot($exception);
        return ['exception' => $exception, 'snapshot' => $snapshot];
    } finally {
        if ($previousSetting !== false) {
            ini_set('zend.exception_ignore_args', $previousSetting);
        }
    }
}

function configExceptionChainSnapshot(Throwable $exception): string
{
    $chain = [];
    $current = $exception;

    while ($current !== null) {
        $chain[] = [
            'class' => $current::class,
            'message' => $current->getMessage(),
            'trace' => array_values(array_filter(
                $current->getTrace(),
                static fn(array $frame): bool => str_starts_with(
                    $frame['class'] ?? '',
                    'Cognesy\\',
                ),
            )),
        ];
        $current = $current->getPrevious();
    }

    return var_export($chain, true);
}

it('does not leak the apiKey when LLMConfig hydration fails', function () {
    $secret = 'sk-llm-should-not-appear-1234567890';

    expect(fn() => LLMConfig::fromArray([
        'apiKey' => $secret,
        'unknownField' => 'boom',
    ]))->toThrow(InvalidArgumentException::class);

    try {
        LLMConfig::fromArray(['apiKey' => $secret, 'unknownField' => 'boom']);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain($secret)
            // safe, actionable diagnostics remain: field names and received types
            ->and($e->getMessage())->toContain('unknownField')
            ->and($e->getMessage())->toContain('string')
            ->and($e->getMessage())->toContain('LLMConfig');
    }
});

it('does not leak nested option credentials when LLMConfig hydration fails', function () {
    $nestedSecret = 'nested-access-token-abcdef';

    try {
        LLMConfig::fromArray([
            'options' => ['access_token' => $nestedSecret],
            'unknownField' => 'boom',
        ]);
        throw new RuntimeException('expected hydration to fail');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain($nestedSecret);
    }
});

it('does not leak the apiKey when EmbeddingsConfig hydration fails', function () {
    $secret = 'sk-embed-should-not-appear-0987654321';

    expect(fn() => EmbeddingsConfig::fromArray([
        'apiKey' => $secret,
        'unknownField' => 'boom',
    ]))->toThrow(InvalidArgumentException::class);

    try {
        EmbeddingsConfig::fromArray(['apiKey' => $secret, 'unknownField' => 'boom']);
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain($secret)
            ->and($e->getMessage())->toContain('unknownField')
            ->and($e->getMessage())->toContain('string')
            ->and($e->getMessage())->toContain('EmbeddingsConfig');
    }
});

it('redacts LLM credentials from wrapper and previous exception traces', function () {
    $secret = 'sk-llm-trace-should-not-appear-1234567890';
    $result = capturedConfigException(fn() => LLMConfig::fromArray([
        'apiKey' => $secret,
        'unknownField' => 'boom',
    ]));

    expect($result['exception'])->toBeInstanceOf(InvalidArgumentException::class)
        ->and($result['exception']->getPrevious())->not->toBeNull()
        ->and($result['snapshot'])->not->toContain($secret)
        ->and($result['exception']->getMessage())->toContain('unknownField')
        ->and($result['exception']->getMessage())->toContain('string');
});

it('redacts embeddings credentials from wrapper and previous exception traces', function () {
    $secret = 'sk-embed-trace-should-not-appear-0987654321';
    $result = capturedConfigException(fn() => EmbeddingsConfig::fromArray([
        'apiKey' => $secret,
        'unknownField' => 'boom',
    ]));

    expect($result['exception'])->toBeInstanceOf(InvalidArgumentException::class)
        ->and($result['exception']->getPrevious())->not->toBeNull()
        ->and($result['snapshot'])->not->toContain($secret)
        ->and($result['exception']->getMessage())->toContain('unknownField')
        ->and($result['exception']->getMessage())->toContain('string');
});

it('redacts credentials from LLM nested options and override traces', function () {
    $secret = 'nested-llm-trace-token-abcdef';
    $base = new LLMConfig(apiKey: 'base-key');
    $result = capturedConfigException(fn() => $base->withOverrides([
        'options' => [
            'access_token' => $secret,
            'retryPolicy' => ['maxAttempts' => 2],
        ],
    ]));

    expect($result['exception'])->toBeInstanceOf(InvalidArgumentException::class)
        ->and($result['snapshot'])->not->toContain($secret)
        ->and($result['snapshot'])->not->toContain('base-key');
});

it('redacts credentials from LLM and embeddings DSN traces', function (string $configClass, string $secret) {
    $result = capturedConfigException(fn() => $configClass::fromDsn(
        "apiKey={$secret},unknownField=boom",
    ));

    expect($result['exception'])->toBeInstanceOf(InvalidArgumentException::class)
        ->and($result['snapshot'])->not->toContain($secret);
})->with([
    'LLM config' => [LLMConfig::class, 'llm-dsn-secret-123'],
    'embeddings config' => [EmbeddingsConfig::class, 'embed-dsn-secret-456'],
]);

it('redacts a direct embeddings constructor apiKey when another argument is invalid', function () {
    $secret = 'direct-embed-secret-789';
    $result = capturedConfigException(
        fn() => new EmbeddingsConfig(apiKey: $secret, dimensions: 'not-an-int'),
    );

    expect($result['exception'])->toBeInstanceOf(TypeError::class)
        ->and($result['snapshot'])->not->toContain($secret);
});
