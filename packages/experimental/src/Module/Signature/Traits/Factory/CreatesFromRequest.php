<?php

namespace Cognesy\Experimental\Module\Signature\Traits\Factory;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Utils\ReferenceQueue;
use Cognesy\Utils\Events\EventDispatcher;

trait CreatesFromRequest
{
    public static function fromRequest(
        StructuredOutputRequest     $request,
        string                      $inputName = 'input',
        string                      $outputName = 'output',
    ) : Signature {
        return new Signature(
            input: self::inputSchema($request)->withName($inputName),
            output: self::responseModel($request)->schema()->withName($outputName),
            description: $request->prompt(),
        );
    }

    private static function inputSchema(StructuredOutputRequest $request) : Schema {
        // TODO: needs to be implmeneted
        return Schema::string(name: 'input', description: 'Input data');
    }

    private static function responseModel(StructuredOutputRequest $request) : ResponseModel {
        return self::responseModelFactory()->fromAny(
            requestedModel: $request->responseModel(),
            toolName: $request->config()?->toolName(),
            toolDescription: $request->config()?->toolDescription(),
        );
    }

    private static function responseModelFactory() : ResponseModelFactory {
        $config = StructuredOutputConfig::default();
        $events = new EventDispatcher();
        $schemaFactory = new SchemaFactory($config->useObjectReferences());
        return new ResponseModelFactory(
            toolCallBuilder: new ToolCallBuilder(
                $schemaFactory,
                new ReferenceQueue(),
            ),
            schemaFactory: $schemaFactory,
            config: $config,
            events: $events,
            listener: $events,
        );
    }
}