<?php declare(strict_types=1);

namespace Cognesy\Agents\Serialization;

final readonly class SlimSerializationConfig
{
    public function __construct(
        public int $maxMessages = 50,
        public int $maxSteps = 20,
        public int $maxContentLength = 2000,
        public bool $includeToolResults = true,
        public bool $includeSteps = true,
        public bool $includeContinuationTrace = false,
        public bool $redactToolArgs = false,
    ) {}

    public static function minimal(): self {
        return new self(
            maxMessages: 20,
            maxSteps: 0,
            maxContentLength: 500,
            includeToolResults: false,
            includeSteps: false,
        );
    }

    public static function standard(): self {
        return new self();
    }

    public static function full(): self {
        return new self(
            maxMessages: 100,
            maxSteps: 50,
            maxContentLength: 5000,
            includeToolResults: true,
            includeSteps: true,
            includeContinuationTrace: true,
        );
    }
}
