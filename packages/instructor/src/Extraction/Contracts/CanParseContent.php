<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Utils\Result\Result;
use Throwable;

/** @internal */
interface CanParseContent
{
    /** @return Result<array<array-key, mixed>, Throwable> */
    public function parse(string $content): Result;
}
