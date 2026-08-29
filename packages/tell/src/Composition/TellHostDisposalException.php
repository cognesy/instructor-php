<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use RuntimeException;

final class TellHostDisposalException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors) {
        parent::__construct('Tell host cleanup failed: ' . implode('; ', $errors));
    }
}
