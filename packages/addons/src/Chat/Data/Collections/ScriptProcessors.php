<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessScript;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Script\Script;

final class ScriptProcessors
{
    /** @var CanProcessScript[] */
    private array $processors = [];

    public function add(CanProcessScript ...$processors): self {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }
        return $this;
    }

    public function isEmpty(): bool {
        return $this->processors === [];
    }

    public function apply(Script $script, ChatState $state): Script {
        $currentScript = $script;
        foreach ($this->processors as $processor) {
            if (!$processor->shouldProcess($currentScript, $state)) {
                continue;
            }
            $currentScript = $processor->process($currentScript, $state);
        }
        return $currentScript;
    }

    /** @return CanProcessScript[] */
    public function all(): array {
        return $this->processors;
    }
}
