<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;

abstract class AbstractBuilder
{
    protected string $functionName = 'extract_data';
    protected string $functionDescription = 'Extract data from provided content';
    protected SchemaFactory $schemaFactory;
    protected FunctionCallBuilder $functionCallBuilder;
    //
    protected TypeDetailsFactory $typeDetailsFactory;
    protected SchemaBuilder $schemaBuilder;

    public function __construct(
        FunctionCallBuilder $functionCallFactory,
        SchemaFactory       $schemaFactory,
    ) {
        $this->functionCallBuilder = $functionCallFactory;
        $this->schemaFactory = $schemaFactory;
        //
        $this->typeDetailsFactory = new TypeDetailsFactory;
        $this->schemaBuilder = new SchemaBuilder;
    }

    abstract public function build($requestedModel) : ResponseModel;
}