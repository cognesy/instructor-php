<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Messages\Messages;

it('rejects text mode on structured prompt materializer', function () {
    $execution = (new StructuredOutputExecutionBuilder(new EventDispatcher()))->createWith(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('hello'),
            requestedSchema: \stdClass::class,
        ),
        config: new StructuredOutputConfig(outputMode: OutputMode::Text),
    );

    expect(fn() => (new StructuredPromptRequestMaterializer())->toInferenceRequest($execution))
        ->toThrow(InvalidArgumentException::class, 'Structured prompt materializer does not support output mode: text');
});

it('rejects unrestricted mode on structured prompt materializer', function () {
    $execution = (new StructuredOutputExecutionBuilder(new EventDispatcher()))->createWith(
        request: new StructuredOutputRequest(
            messages: Messages::fromString('hello'),
            requestedSchema: \stdClass::class,
        ),
        config: new StructuredOutputConfig(outputMode: OutputMode::Unrestricted),
    );

    expect(fn() => (new StructuredPromptRequestMaterializer())->toInferenceRequest($execution))
        ->toThrow(InvalidArgumentException::class, 'Structured prompt materializer does not support output mode: unrestricted');
});
