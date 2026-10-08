<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use Cognesy\Polyglot\BatchInference\Enums\GeminiBatchInputMode;
use InvalidArgumentException;

final readonly class GeminiBatchOptions implements BatchSubmissionOptions
{
    public function __construct(
        private GeminiBatchInputMode $inputMode = GeminiBatchInputMode::File,
        private string $displayName = 'polyglot-batch',
    ) {
        if ($displayName === '') {
            throw new InvalidArgumentException('Gemini batch display name is required.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'gemini';
    }
    public function inputMode(): GeminiBatchInputMode
    {
        return $this->inputMode;
    }
    public function displayName(): string
    {
        return $this->displayName;
    }
}
