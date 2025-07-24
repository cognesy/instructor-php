<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Exceptions;

final class MetadataConflictException extends \Exception
{
    public function __construct(string $key, mixed $fenceValue, mixed $doctestValue)
    {
        $fenceValueStr = is_string($fenceValue) ? $fenceValue : json_encode($fenceValue);
        $doctestValueStr = is_string($doctestValue) ? $doctestValue : json_encode($doctestValue);
        
        parent::__construct(
            "Metadata key conflict: '{$key}' is defined in both fence line ({$fenceValueStr}) and @doctest annotation ({$doctestValueStr})"
        );
    }
}