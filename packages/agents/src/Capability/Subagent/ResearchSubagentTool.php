<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\File\ReadFileTool;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Messages\Messages;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class ResearchSubagentTool extends SimpleTool
{
    private string $baseDir;

    public function __construct(string $baseDir) {
        parent::__construct(new ResearchSubagentToolDescriptor());
        $this->baseDir = rtrim($baseDir, '/');
    }

    public static function inDirectory(string $baseDir): self {
        return new self($baseDir);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $task = $this->arg($args, 'task', 0, '');
        $files = $this->arg($args, 'files', 1, []);

        if ($task === '') {
            return "Error: task is required";
        }

        // Create subagent with read-only file access
        $subagentTools = new Tools(
            ReadFileTool::inDirectory($this->baseDir),
        );

        $subagent = AgentBuilder::base()
            ->withCapability(new UseTools(...$subagentTools->all()))
            ->build();

        // Build context with file list
        $fileList = is_array($files) ? implode(', ', $files) : $files;
        $prompt = "You are a research assistant. {$task}\n";
        if ($fileList !== '') {
            $prompt .= "Relevant files to examine: {$fileList}\n";
        }
        $prompt .= "Provide a concise summary of your findings.";

        $subState = AgentState::empty()->withMessages(
            Messages::fromString($prompt)
        );

        // Run subagent to completion
        $finalState = $subagent->execute($subState);

        return $finalState->currentStep()?->outputMessages()->toString() ?? 'No findings';
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('task', 'The research task to perform'),
                    JsonSchema::array('files', JsonSchema::string(), 'List of file paths to examine'),
                ])
                ->withRequiredProperties(['task'])
        )->toArray();
    }
}
