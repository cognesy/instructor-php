<?php
namespace Cognesy\Instructor\Core;

class ResponseModel
{
    public mixed $instance; // calculated
    public ?string $class; // calculated
    public ?array $functionCall; // calculated

    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract data from provided content';

    public function __construct(
        string                 $class = null,
        mixed                  $instance = null,
        array                  $functionCall = null,
    )
    {
        $this->class = $class;
        $this->instance = $instance;
        $this->functionCall = $functionCall;
    }
}
