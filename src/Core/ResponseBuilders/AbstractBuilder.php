<?php

namespace Cognesy\Instructor\Core\ResponseBuilders;

use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;

abstract class AbstractBuilder
{
    protected string $functionName = 'extract_data';
    protected string $functionDescription = 'Extract data from provided content';
    protected SchemaFactory $schemaFactory;
    protected TypeDetailsFactory $typeDetailsFactory;
    protected FunctionCallBuilder $functionCallBuilder;
    protected SchemaBuilder $schemaBuilder;

    public function __construct(
        FunctionCallBuilder $functionCallFactory,
        SchemaFactory       $schemaFactory,
        SchemaBuilder       $schemaBuilder,
        TypeDetailsFactory  $typeDetailsFactory
    ) {
        $this->functionCallBuilder = $functionCallFactory;
        $this->schemaFactory = $schemaFactory;
        $this->schemaBuilder = $schemaBuilder;
        $this->typeDetailsFactory = $typeDetailsFactory;
    }

    abstract public function build($requestedModel) : ResponseModel;
}