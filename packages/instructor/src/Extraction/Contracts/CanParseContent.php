<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Utils\Result\Result;

/** @internal */
interface CanParseContent
{
    /** @return Result<array<string, mixed>, string> */
    public function parse(string $content): Result;
}
