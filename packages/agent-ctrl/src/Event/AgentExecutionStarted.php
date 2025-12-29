<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Event;

use Cognesy\AgentCtrl\Enum\AgentType;
use Psr\Log\LogLevel;

/**
 * Emitted when agent execution begins.
 */
final class AgentExecutionStarted extends AgentEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        AgentType $agentType,
        public readonly string $prompt,
        public readonly ?string $model = null,
        public readonly ?string $workingDirectory = null,
    ) {
        parent::__construct($agentType, [
            'prompt' => $this->truncatePrompt($prompt),
            'model' => $model,
            'workingDirectory' => $workingDirectory,
        ]);
    }

    #[\Override]
    public function __toString(): string
    {
        return sprintf(
            'Agent %s started%s',
            $this->agentType->value,
            $this->model ? " (model: {$this->model})" : '',
        );
    }

    private function truncatePrompt(string $prompt, int $maxLength = 100): string
    {
        if (strlen($prompt) <= $maxLength) {
            return $prompt;
        }
        return substr($prompt, 0, $maxLength) . '...';
    }
}
