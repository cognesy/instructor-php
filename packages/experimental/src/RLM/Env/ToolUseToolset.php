<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Env;

use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Experimental\RLM\Contracts\Toolset;
use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;
use Cognesy\Polyglot\Inference\Data\ToolCall;

final class ToolUseToolset implements Toolset
{
    public function __construct(
        private CanExecuteToolCalls $executor,
        private ToolUseState $state = new ToolUseState(),
    ) {}

    public static function fromTools(Tools $tools): self {
        $executor = new ToolExecutor($tools);
        return new self($executor, new ToolUseState());
    }

    /**
     * @param array<string,mixed> $args
     */
    public function call(string $name, array $args): ResultHandle {
        $toolCall = new ToolCall($name, $args);
        $execution = $this->executor->useTool($toolCall, $this->state);
        $summary = json_encode([
            'tool' => $execution->name(),
            'ok' => !$execution->hasError(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $hash = substr(sha1($summary ?? ''), 0, 12);
        return ResultHandle::from('artifact://tooluse/' . $execution->name() . '/' . $hash);
    }
}
