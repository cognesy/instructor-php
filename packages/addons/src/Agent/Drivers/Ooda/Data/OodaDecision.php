<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\Ooda\Data;

use Cognesy\Addons\Agent\Drivers\ReAct\Contracts\Decision;
use Cognesy\Schema\Attributes\Description;

/**
 * Structured OODA loop decision.
 *
 * Each step captures all four phases:
 * - Observe: Current state assessment
 * - Orient: Analysis and options
 * - Decide: Chosen action
 * - Act: Tool call or final answer
 */
final class OodaDecision implements Decision
{
    // OBSERVE phase
    #[Description('Current goal we are trying to achieve')]
    public string $goal = '';
    #[Description('Current situation - what do we know now?')]
    public string $currentState = '';
    #[Description('Key findings from previous actions')]
    public string $previousResults = '';
    #[Description('Known obstacles or challenges')]
    public string $obstacles = '';
    #[Description('Progress toward goal (0-100)')]
    public int $progress = 0;

    // ORIENT phase
    #[Description('Analysis of the situation and available options')]
    public string $analysis = '';
    #[Description('Possible next actions considered')]
    /** @var string[] */
    public array $options = [];
    #[Description('Reasoning for the chosen approach')]
    public string $reasoning = '';

    // DECIDE phase
    #[Description('Decision type - call_tool or final_answer')]
    public string $type = '';
    #[Description('Confidence in this decision (0-100)')]
    public int $confidence = 0;

    // ACT phase
    #[Description('Tool name from the catalog (e.g. search_files, read_file). Required when type=call_tool.')]
    public ?string $tool = null;
    #[Description('Tool arguments object with parameter names as keys (e.g. {"pattern": "*.php"} or {"path": "src/file.php"})')]
    public array $args = [];
    #[Description('Complete answer text when type=final_answer')]
    public ?string $answer = null;

    #[\Override]
    public function thought(): string {
        return $this->reasoning;
    }

    public function type(): string {
        return $this->type;
    }

    #[\Override]
    public function isCall(): bool {
        return $this->type === 'call_tool';
    }

    public function isFinal(): bool {
        return $this->type === 'final_answer';
    }

    #[\Override]
    public function tool(): ?string {
        return $this->tool;
    }

    #[\Override]
    public function args(): array {
        return $this->args;
    }

    public function setArgs(array $args): void {
        $this->args = $args;
    }

    public function answer(): string {
        return $this->answer ?? '';
    }

    public function toFormattedOutput(): string {
        $lines = [
            "## OBSERVE",
            "**Goal:** {$this->goal}",
            "**Current State:** {$this->currentState}",
            "**Progress:** {$this->progress}%",
        ];

        if (!empty($this->previousResults)) {
            $lines[] = "**Previous Results:** {$this->previousResults}";
        }
        if (!empty($this->obstacles)) {
            $lines[] = "**Obstacles:** {$this->obstacles}";
        }

        $lines[] = "";
        $lines[] = "## ORIENT";
        $lines[] = "**Analysis:** {$this->analysis}";
        if (!empty($this->options)) {
            $lines[] = "**Options Considered:**";
            foreach ($this->options as $option) {
                $lines[] = "- {$option}";
            }
        }
        $lines[] = "**Reasoning:** {$this->reasoning}";

        $lines[] = "";
        $lines[] = "## DECIDE";
        $lines[] = "**Decision:** {$this->type} (confidence: {$this->confidence}%)";

        $lines[] = "";
        $lines[] = "## ACT";
        if ($this->isCall()) {
            $args = json_encode($this->args, JSON_UNESCAPED_SLASHES);
            $lines[] = "**Action:** Call {$this->tool}({$args})";
        } else {
            $lines[] = "**Action:** Final answer";
        }

        return implode("\n", $lines);
    }
}
