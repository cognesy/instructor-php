<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Parsers;

use Cognesy\Instructor\Extraction\Contracts\CanParseContent;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

/** @internal */
final class JsonParser implements CanParseContent
{
    #[\Override]
    public function parse(string $content): Result
    {
        return Result::try(function () use ($content): array {
            $decoded = Json::decode($content);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Parsed JSON must be an object/array.');
            }
            return $decoded;
        });
    }
}
