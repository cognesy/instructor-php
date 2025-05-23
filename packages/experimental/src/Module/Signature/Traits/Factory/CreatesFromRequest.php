<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputRequestInfo;
use Cognesy\Instructor\Features\Core\ResponseModelFactory;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Utils\ReferenceQueue;
use Cognesy\Utils\Events\EventDispatcher;

trait CreatesFromRequest
{
    public static function fromRequest(
        StructuredOutputRequestInfo $request,
        string                      $inputName = 'input',
        string                      $outputName = 'output',
    ) : Signature {
        return new Signature(
            input: self::inputSchema($request)->withName($inputName),
            output: self::responseModel($request)->schema()->withName($outputName),
            description: $request->prompt(),
        );
    }

    private static function inputSchema(StructuredOutputRequestInfo $request) : Schema {
        // TODO: needs to be implmeneted
        return Schema::string(name: 'input', description: 'Input data');
    }

    private static function responseModel(StructuredOutputRequestInfo $request) : ResponseModel {
        return self::responseModelFactory()->fromAny(
            requestedModel: $request->responseModel(),
            toolName: $request->config()?->toolName(),
            toolDescription: $request->config()?->toolDescription(),
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