<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Throwable;

trait HandlesStepErrors
{
    /** @var Throwable[] */
    private readonly array $errors;

    public function hasErrors(): bool {
        return $this->errors !== [];
    }

    /** @return Throwable[] */
    public function errors(): array {
        return $this->errors;
    }

    public function errorsAsString(): string {
        if ($this->errors === []) {
            return '';
        }

        return implode("\n", array_map(
            fn(Throwable $error): string => $error->getMessage(),
            $this->errors,
        ));
    }
}