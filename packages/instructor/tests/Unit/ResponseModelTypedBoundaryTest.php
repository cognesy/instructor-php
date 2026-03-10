<?php

declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Events\Dispatchers\EventDispatcher;

it('exposes typed tool selection and response formatting objects', function () {
    $config = new StructuredOutputConfig(outputMode: OutputMode::Tools);
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        new EventDispatcher(),
    );

    $responseModel = $factory->fromAny(\stdClass::class);

    expect($responseModel->toolDefinitions())->toBeInstanceOf(ToolDefinitions::class)
        ->and($responseModel->toolDefinitions()->isEmpty())->toBeFalse()
        ->and($responseModel->toolChoice())->toBeInstanceOf(ToolChoice::class)
        ->and($responseModel->toolChoice()->isSpecific())->toBeTrue()
        ->and($responseModel->responseFormat())->toBeInstanceOf(ResponseFormat::class);
});

it('keeps json response format typed outside tools mode', function () {
    $config = new StructuredOutputConfig(outputMode: OutputMode::JsonSchema);
    $factory = new ResponseModelFactory(
        new StructuredOutputSchemaRenderer($config),
        $config,
        new EventDispatcher(),
    );

    $responseModel = $factory->fromAny(\stdClass::class);

    expect($responseModel->toolDefinitions())->toBeInstanceOf(ToolDefinitions::class)
        ->and($responseModel->toolDefinitions()->isEmpty())->toBeTrue()
        ->and($responseModel->toolChoice())->toBeInstanceOf(ToolChoice::class)
        ->and($responseModel->toolChoice()->isEmpty())->toBeTrue()
        ->and($responseModel->responseFormat())->toBeInstanceOf(ResponseFormat::class)
        ->and($responseModel->responseFormat()->isEmpty())->toBeFalse();
});
