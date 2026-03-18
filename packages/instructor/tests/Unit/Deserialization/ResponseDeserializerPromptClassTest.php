<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Xprompt\Prompt;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ResponseDeserializerTestUser
{
    public function __construct(
        public string $name = '',
    ) {}
}

final class ResponseDeserializerTestRepairPrompt extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return <<<TEXT
        repair-error: {$ctx['error']}
        payload: {$ctx['invalid_payload']}
        schema: {$ctx['json_schema']}
        TEXT;
    }
}

final class AlwaysFailDeserializer implements CanDeserializeClass
{
    public function fromArray(array $data, string $dataType): mixed
    {
        throw new RuntimeException('forced deserialization failure');
    }
}

function makeResponseDeserializerTestModel(string $class): ResponseModel
{
    $config = new StructuredOutputConfig();
    $events = new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event;
        }
    };

    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        $events,
    );

    return $factory->fromAny($class);
}

function makePromptClassTestEvents(): EventDispatcherInterface
{
    return new class implements EventDispatcherInterface {
        public function dispatch(object $event): object
        {
            return $event;
        }
    };
}

it('renders deserialization failure with the configured prompt class', function () {
    $config = (new StructuredOutputConfig())
        ->withDeserializationErrorPromptClass(ResponseDeserializerTestRepairPrompt::class);
    $deserializer = new ResponseDeserializer(
        events: makePromptClassTestEvents(),
        deserializer: new AlwaysFailDeserializer(),
        config: $config,
    );

    $result = $deserializer->deserialize(
        ['name' => 123],
        makeResponseDeserializerTestModel(ResponseDeserializerTestUser::class),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errorMessage())->toContain('repair-error: forced deserialization failure')
        ->and($result->errorMessage())->toContain('"name": 123')
        ->and($result->errorMessage())->toContain('"type": "object"');
});

it('throws a configuration error when the repair prompt class is not a prompt', function () {
    $config = (new StructuredOutputConfig())
        ->withDeserializationErrorPromptClass(stdClass::class);
    $deserializer = new ResponseDeserializer(
        events: makePromptClassTestEvents(),
        deserializer: new AlwaysFailDeserializer(),
        config: $config,
    );

    expect(fn() => $deserializer->deserialize(
        ['name' => 123],
        makeResponseDeserializerTestModel(ResponseDeserializerTestUser::class),
    ))->toThrow(InvalidArgumentException::class, 'Prompt class must extend');
});
