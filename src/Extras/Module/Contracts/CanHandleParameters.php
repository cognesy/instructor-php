<?php
namespace Cognesy\Instructor\Extras\Module\Contracts;

use Cognesy\Experimental\Module\Parameters\PredictorParameters;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Not used')]
interface CanHandleParameters {
    public function withParameters(PredictorParameters $parameters) : static;
    public function getParameters() : PredictorParameters;
}
