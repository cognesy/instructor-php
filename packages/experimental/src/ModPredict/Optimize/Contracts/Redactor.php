<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Contracts;

interface Redactor
{
    public function redactInput(string $signatureId, mixed $input): mixed;

    public function redactOutput(string $signatureId, mixed $output): mixed;
}

