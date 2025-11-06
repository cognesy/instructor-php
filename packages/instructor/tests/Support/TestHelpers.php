<?php declare(strict_types=1);

// Shared test helpers for Instructor package

if (!function_exists('makeAnyResponseModel')) {
    function makeAnyResponseModel(mixed $any): \Cognesy\Instructor\Data\ResponseModel {
        $config = new \Cognesy\Instructor\Config\StructuredOutputConfig();
        $schemaFactory = new \Cognesy\Schema\Factories\SchemaFactory(useObjectReferences: $config->useObjectReferences());
        $events = new \Cognesy\Events\Dispatchers\EventDispatcher();
        $factory = new \Cognesy\Instructor\Creation\ResponseModelFactory(
            new \Cognesy\Schema\Factories\ToolCallBuilder($schemaFactory),
            $schemaFactory,
            $config,
            $events,
        );
        return $factory->fromAny($any);
    }
}

