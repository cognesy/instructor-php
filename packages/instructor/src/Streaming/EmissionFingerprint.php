<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Enums\OutputMode;

final class EmissionFingerprint
{
    private int $lastEmittedContentLength = -1;

    private string $lastEmittedToolKey = '';
    private int $lastEmittedToolSnapshotLength = -1;

    private string $lastEmittedFinishReason = '';
    private mixed $lastEmittedValue = null;
    private bool $hasLastEmittedValue = false;

    private function __construct() {}

    public static function fresh(): self {
        return new self();
    }

    public function hasChanged(EmissionSnapshot $snapshot, OutputMode $mode): bool {
        $contentChanged = $this->contentChanged($snapshot, $mode);
        $finishChanged = $snapshot->finishReason !== '' && $snapshot->finishReason !== $this->lastEmittedFinishReason;
        $valueChanged = $snapshot->hasValue() && !$this->valuesEqual($snapshot->value, $this->hasLastEmittedValue, $this->lastEmittedValue);

        return $contentChanged || $finishChanged || $valueChanged;
    }

    public function remember(EmissionSnapshot $snapshot, OutputMode $mode): void {
        $this->lastEmittedFinishReason = $snapshot->finishReason;
        $this->lastEmittedValue = $snapshot->value;
        $this->hasLastEmittedValue = $snapshot->hasValue();

        if ($mode === OutputMode::Tools) {
            $this->lastEmittedToolKey = $snapshot->toolKey;
            $this->lastEmittedToolSnapshotLength = strlen($snapshot->toolArgsSnapshot);
            return;
        }

        $this->lastEmittedContentLength = strlen($snapshot->content);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function contentChanged(EmissionSnapshot $snapshot, OutputMode $mode): bool {
        if ($mode === OutputMode::Tools) {
            return $this->toolSnapshotChanged($snapshot);
        }

        return strlen($snapshot->content) > $this->lastEmittedContentLength;
    }

    private function toolSnapshotChanged(EmissionSnapshot $snapshot): bool {
        $length = strlen($snapshot->toolArgsSnapshot);
        $key = $snapshot->toolKey;

        return match (true) {
            $length === 0 => false,
            $key !== $this->lastEmittedToolKey => true,
            $length !== $this->lastEmittedToolSnapshotLength => true,
            default => false,
        };
    }

    private function valuesEqual(mixed $current, bool $hasPrevious, mixed $previous): bool {
        return match (true) {
            !$hasPrevious => false,
            is_scalar($current) || $current === null => $current === $previous,
            default => $current == $previous,
        };
    }
}
