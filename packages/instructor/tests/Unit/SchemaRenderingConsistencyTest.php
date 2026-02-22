<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Tests\Examples\ResponseModel\User;
use Cognesy\Instructor\Tests\Examples\ResponseModel\UserWithProvider;
use Cognesy\Schema\Data\Schema\Schema;

final class ToolSelectionUserModel implements CanHandleToolSelection
{
    public function toSchema(): Schema {
        return (new StructuredOutputSchemaRenderer(new StructuredOutputConfig()))
            ->schemaFactory()
            ->schema(User::class);
    }

    public function toJsonSchema(): array {
        return [
            'x-php-class' => User::class,
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
            'required' => ['name', 'email'],
        ];
    }

    public function toToolCallsJson(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'tool_selection_user_model',
                'description' => 'Use explicit tool selection',
                'parameters' => $this->toJsonSchema(),
            ],
        ];
    }
}

dataset('requested_schema_for_consistency', [
    'class-string' => [User::class],
    'json-schema-array' => [[
        'x-php-class' => User::class,
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ],
        'required' => ['name', 'email'],
    ]],
    'json-schema-provider' => [UserWithProvider::class],
    'tool-selection-provider' => [ToolSelectionUserModel::class],
]);

it('keeps schema rendering consistent between execution builder and response model factory', function (string|array|object $requestedSchema) {
    $events = new EventDispatcher('test');
    $config = new StructuredOutputConfig();
    $renderer = new StructuredOutputSchemaRenderer($config);
    $factory = new ResponseModelFactory(
        schemaRenderer: $renderer,
        config: $config,
        events: $events,
    );

    $executionBuilder = new StructuredOutputExecutionBuilder(
        events: $events,
        responseModelFactory: $factory,
    );

    $execution = $executionBuilder->createWith(
        request: new StructuredOutputRequest(
            requestedSchema: $requestedSchema,
        ),
        config: $config,
    );
    $fromExecution = $execution->responseModel();
    $fromFactory = $factory->fromAny($requestedSchema);

    expect($fromExecution)->not->toBeNull();
    expect($fromExecution?->toJsonSchema())->toBe($fromFactory->toJsonSchema());
    expect($fromExecution?->toolCallSchema())->toBe($fromFactory->toolCallSchema());
    expect($fromExecution?->responseFormat())->toBe($fromFactory->responseFormat());
    expect($fromExecution?->schemaName())->toBe($fromFactory->schemaName());
})->with('requested_schema_for_consistency');
