<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\ReadFileTool;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Tools\BaseTool;
use Cognesy\Messages\Messages;

class ResearchSubagentTool extends BaseTool
{
    private string $baseDir;

    public function __construct(Agent $parentAgent, string $baseDir) {
        parent::__construct(
            name: 'research_subagent',
            description: 'Spawn a subagent to research files and return a summary. Use for reading and analyzing file contents.',
        );
        $this->baseDir = rtrim($baseDir, '/');
    }

    public static function withParent(Agent $parentAgent, string $baseDir): self {
        return new self($parentAgent, $baseDir);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $task = $args['task'] ?? $args[0] ?? '';
        $files = $args['files'] ?? $args[1] ?? [];

        if (empty($task)) {
            return "Error: task is required";
        }

        // Create subagent with read-only file access
        $subagentTools = new Tools(
            ReadFileTool::inDirectory($this->baseDir),
        );

        $subagent = AgentBuilder::new()->withTools($subagentTools)->build();

        // Build context with file list
        $fileList = is_array($files) ? implode(', ', $files) : $files;
        $prompt = "You are a research assistant. {$task}\n";
        if (!empty($fileList)) {
            $prompt .= "Relevant files to examine: {$fileList}\n";
        }
        $prompt .= "Provide a concise summary of your findings.";

        $subState = AgentState::empty()->withMessages(
            Messages::fromString($prompt)
        );

        // Run subagent to completion
        $finalState = $subagent->finalStep($subState);

        return $finalState->currentStep()?->outputMessages()->toString() ?? 'No findings';
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task' => [
                            'type' => 'string',
                            'description' => 'The research task to perform',
                        ],
                        'files' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of file paths to examine',
                        ],
                    ],
                    'required' => ['task'],
                ],
            ],
        ];
    }
}
