<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Describes the capabilities of an inference driver.
 *
 * This value object is used to query what features a driver supports,
 * enabling capability-based filtering of test cases and runtime decisions.
 */
readonly class DriverCapabilities
{
    /**
     * @param OutputMode[] $outputModes Native supported output modes (empty = all modes supported)
     * @param bool $streaming Whether streaming responses are supported
     * @param bool $toolCalling Whether tool/function calling is supported
     * @param bool $jsonSchema Whether native JSON schema mode is supported
     * @param bool $responseFormatWithTools Whether response_format works alongside tools
     */
    public function __construct(
        public array $outputModes = [],
        public bool $streaming = true,
        public bool $toolCalling = true,
        public bool $jsonSchema = true,
        public bool $responseFormatWithTools = true,
    ) {}

    /**
     * Check if a specific output mode is supported.
     * If outputModes is empty, all modes are considered supported.
     */
    public function supportsOutputMode(OutputMode $mode): bool {
        return empty($this->outputModes) || in_array($mode, $this->outputModes, true);
    }

    /**
     * Check if streaming responses are supported.
     */
    public function supportsStreaming(): bool {
        return $this->streaming;
    }

    /**
     * Check if tool/function calling is supported.
     */
    public function supportsToolCalling(): bool {
        return $this->toolCalling;
    }

    /**
     * Check if native JSON schema mode is supported.
     */
    public function supportsJsonSchema(): bool {
        return $this->jsonSchema;
    }

    /**
     * Check if response_format can be used alongside tools.
     */
    public function supportsResponseFormatWithTools(): bool {
        return $this->responseFormatWithTools;
    }

    /**
     * Check if a specific mode + streaming combination is supported.
     */
    public function supports(OutputMode $mode, bool $streaming): bool {
        return $this->supportsOutputMode($mode)
            && (!$streaming || $this->supportsStreaming());
    }

    /**
     * Get the list of explicitly supported output modes.
     * Returns empty array if all modes are supported.
     *
     * @return OutputMode[]
     */
    public function getOutputModes(): array {
        return $this->outputModes;
    }
}
