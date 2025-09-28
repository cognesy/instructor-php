<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\ModPredict\Optimize\Contracts\Redactor;

final class NoopRedactor implements Redactor
{
    public function redactInput(string $signatureId, mixed $input): mixed {
        return $input;
    }

    public function redactOutput(string $signatureId, mixed $output): mixed {
        return $output;
    }
}