<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\File\ReadFileTool;
use Cognesy\Messages\Messages;

class ResearchSubagentTool extends BaseTool
{
    private string $baseDir;

    public function __construct(string $baseDir) {
        parent::__construct(
            name: 'research_subagent',
            description: 'Spawn a subagent to research files and return a summary. Use for reading and analyzing file contents.',
        );
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

        $subagent = AgentBuilder::base()->withTools($subagentTools)->build();

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
