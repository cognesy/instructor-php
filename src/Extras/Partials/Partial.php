<?php

namespace Cognesy\Instructor\Extras\Partials;

class Partial
{
    private mixed $targetModel;
    private string $functionName;
    private string $functionDescription;

    public function __construct(mixed $targetModel, string $functionName, string $functionDescription)
    {
        $this->targetModel = $targetModel;
        $this->functionName = $functionName;
        $this->functionDescription = $functionDescription;
    }

    public function getTargetModel() : mixed {
        return $this->targetModel;
    }

    public function getFunctionName() : string {
        return $this->functionName;
    }

    public function getFunctionDescription() : string {
        return $this->functionDescription;
    }
}