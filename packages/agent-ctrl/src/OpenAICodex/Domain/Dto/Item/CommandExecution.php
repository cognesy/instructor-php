<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

/**
 * Shell command execution
 *
 * Example: {"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"in_progress"}
 * Completed: {"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"completed","output":"file1.txt\nfile2.txt","exit_code":0}
 */
final readonly class CommandExecution extends Item
{
    public function __construct(
        string $id,
        string $status,
        public string $command,
        public ?string $output = null,
        public ?int $exitCode = null,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'command_execution';
    }

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'in_progress'),
            command: (string)($data['command'] ?? ''),
            output: isset($data['output']) ? (string)$data['output'] : null,
            exitCode: isset($data['exit_code']) ? (int)$data['exit_code'] : null,
        );
    }
}
