<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Polyglot\Inference\LLMProvider;

interface SubagentDefinition
{
    public function name(): string;
    public function description(): string;
    public function systemPrompt(): string;

    /**
     * @return array<int, string>|null
     */
    public function tools(): ?array;

    public function inheritsAllTools(): bool;
    public function filterTools(Tools $parentTools): Tools;
    public function resolveLlmProvider(?LLMProvider $parentProvider = null): LLMProvider;
    public function hasSkills(): bool;

    /**
     * @return array<int, string>|null
     */
    public function skills(): ?array;
}
