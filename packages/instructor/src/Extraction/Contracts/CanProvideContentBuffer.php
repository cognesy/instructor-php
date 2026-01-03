<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Contract for extractors that can provide streaming content buffers.
 *
 * This interface allows ResponseExtractor to delegate buffer creation
 * for streaming pipelines, ensuring consistent extraction behavior
 * between sync and streaming modes.
 */
interface CanProvideContentBuffer
{
    /**
     * Create a content buffer appropriate for the given output mode.
     *
     * @param OutputMode $mode The output mode (Json, Tools, Text, etc.)
     * @return CanBufferContent Buffer instance for accumulating streaming content
     */
    public function makeContentBuffer(OutputMode $mode): CanBufferContent;
}
