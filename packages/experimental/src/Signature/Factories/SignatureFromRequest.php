<?php declare(strict_types=1);

namespace Cognesy\Experimental\Signature\Factories;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Experimental\Signature\Signature;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;

class SignatureFromRequest
{
    public function make(
        StructuredOutputRequest $request,
        string $inputName = 'input',
        string $outputName = 'output',
    ) : Signature {
        return new Signature(
            input: $this->inputSchema($request)->withName($inputName),
            output: $this->responseModel($request)->schema()->withName($outputName),
            description: $request->prompt(),
        );
    }

    private function inputSchema(StructuredOutputRequest $request) : Schema {
        return Schema::string(name: 'input', description: 'Input data');
    }

    private function responseModel(StructuredOutputRequest $request) : ResponseModel {
        return $this->responseModelFactory()->fromAny($request->responseModel());
    }

    private function responseModelFactory() : ResponseModelFactory {
        $config = new StructuredOutputConfig();
        $events = new EventDispatcher();
        $schemaFactory = new SchemaFactory(useObjectReferences: $config->useObjectReferences());
        return new ResponseModelFactory(
            toolCallBuilder: new ToolCallBuilder($schemaFactory),
            schemaFactory: $schemaFactory,
            config: $config,
            events: $events,
        );
    }
}