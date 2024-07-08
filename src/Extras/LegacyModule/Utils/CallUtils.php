<?php
namespace Cognesy\Instructor\Extras\Module\Utils;

use Cognesy\Instructor\Utils\Result;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: "Use not used - to be removed")]
class CallUtils
{
    static public function argsMatch(array $args, array $expectedNames) : Result {
        $argNames = array_keys($args);
        if (count($argNames) === 0) {
            return Result::failure("No input fields provided");
        }
        $areKeysStrings = array_reduce($argNames, fn($carry, $item) => $carry && is_string($item), true);
        if (!$areKeysStrings) {
            return Result::failure("Call must use named parameters: make(argName: argValue, ...)");
        }
        $diff = array_diff($argNames, $expectedNames);
        if (count($diff) > 0) {
            return Result::failure("Unexpected input fields: " . implode(', ', $diff));
        }
        return Result::success(true);
    }
}