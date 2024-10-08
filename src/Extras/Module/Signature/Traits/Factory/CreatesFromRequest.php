<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits\Factory;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Module\Signature\Signature;
use Cognesy\Instructor\Features\Core\Data\RequestInfo;
use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;

trait CreatesFromRequest
{
    public static function fromRequest(
        RequestInfo $request,
        string $inputName = 'input',
        string $outputName = 'output',
    ) : Signature {
        return new Signature(
            input: self::inputSchema($request)->withName($inputName),
            output: self::responseModel($request)->schema()->withName($outputName),
            description: $request->prompt,
        );
    }

    private static function inputSchema(RequestInfo $request) : Schema {
        return Schema::string(name: 'input', description: 'Input data');
    }

    private static function responseModel(RequestInfo $request) : ResponseModel {
        return self::responseModelFactory()->fromAny(
            requestedModel: $request->responseModel,
            toolName: $request->toolName,
            toolDescription: $request->toolDescription,
        );
    }

    private static function responseModelFactory() : ResponseModelFactory {
        return new ResponseModelFactory(
            toolCallBuilder: new ToolCallBuilder(
                new SchemaFactory(),
                new ReferenceQueue(),
            ),
            schemaFactory: new SchemaFactory(),
            events: new EventDispatcher(),
        );
    }
}