<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

interface BatchSubmissionOptions
{
    public function provider(): string;
}
