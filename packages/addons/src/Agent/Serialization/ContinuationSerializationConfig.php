<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Serialization;

final readonly class ContinuationSerializationConfig
{
    public function __construct(
        public int $maxMessagesPerSection = 50,
        public int $maxContentLength = 2000,
        public bool $includeToolResults = true,
        public bool $redactToolArgs = false,
    ) {}

    public static function standard(): self {
        return new self();
    }
}
