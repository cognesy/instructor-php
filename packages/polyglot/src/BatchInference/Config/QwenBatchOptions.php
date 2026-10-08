<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class QwenBatchOptions implements BatchSubmissionOptions
{
    public function __construct(
        private string $completionWindow = '24h',
        private ?string $taskName = null,
        private ?string $taskDescription = null,
    ) {
        if (!preg_match('/^([1-9][0-9]*)(h|d)$/', $completionWindow, $matches)) {
            throw new InvalidArgumentException('Qwen batch window must be an hour or day duration.');
        }
        $hours = (int) $matches[1] * ($matches[2] === 'd' ? 24 : 1);
        if ($hours < 24 || $hours > 336) {
            throw new InvalidArgumentException('Qwen batch window must be between 24 hours and 14 days.');
        }
        if (($taskName !== null && strlen($taskName) > 100)
            || ($taskDescription !== null && strlen($taskDescription) > 200)) {
            throw new InvalidArgumentException('Qwen task metadata exceeds documented limits.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'qwen';
    }
    public function completionWindow(): string
    {
        return $this->completionWindow;
    }

    /** @return array<string, string> */
    public function metadata(): array
    {
        $metadata = [];
        if ($this->taskName !== null) {
            $metadata['ds_name'] = $this->taskName;
        }
        if ($this->taskDescription !== null) {
            $metadata['ds_description'] = $this->taskDescription;
        }
        return $metadata;
    }
}
