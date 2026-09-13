<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/Pest.php';

use Cognesy\Tell\Adapter\Console\Command\BranchCommand;
use Cognesy\Tell\Core\Contract\Workspace\CanAccessTellConversations;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellBranches;
use Cognesy\Tell\Data\TellBranchInfo;
use Cognesy\Tell\Data\TellBranchSelection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('delegates branch listing only through the conversation operation contract', function (): void {
    $project = __DIR__;
    $branches = new class implements CanManageTellBranches
    {
        public int $currentCalls = 0;

        public int $listCalls = 0;

        public function list(bool $full = false): array
        {
            $this->listCalls++;
            if ($this->listCalls !== 1 || ! $full) {
                throw new LogicException('Expected exactly one list(true) call.');
            }

            return [new TellBranchInfo(
                name: 'review',
                head: 'abc123',
                empty: false,
                turnCount: 2,
                current: true,
                configuration: ['status' => 'configured', 'version' => 3],
                created: ['source' => 'current', 'branch' => 'main', 'head' => 'abc123'],
            )];
        }

        public function current(): TellBranchSelection
        {
            $this->currentCalls++;
            if ($this->currentCalls !== 1) {
                throw new LogicException('Expected exactly one current() call.');
            }

            return new TellBranchSelection('review', 'current');
        }

        public function show(string $name): never
        {
            throw new LogicException('Unexpected show() call.');
        }

        public function create(string $name, ?string $from = null, bool $empty = false): never
        {
            throw new LogicException('Unexpected create() call.');
        }

        public function checkout(string $name): never
        {
            throw new LogicException('Unexpected checkout() call.');
        }

        public function reset(string $name, int $steps): never
        {
            throw new LogicException('Unexpected reset() call.');
        }

        public function resetTo(string $name, string $hash): never
        {
            throw new LogicException('Unexpected resetTo() call.');
        }
    };
    $conversations = new class($project, $branches) implements CanAccessTellConversations
    {
        public int $branchesCalls = 0;

        public function __construct(
            private readonly string $expectedDirectory,
            private readonly CanManageTellBranches $branches,
        ) {}

        public function branches(string $directory): CanManageTellBranches
        {
            $this->branchesCalls++;
            if ($this->branchesCalls !== 1 || $directory !== $this->expectedDirectory) {
                throw new LogicException('Expected exactly one branches() call for the requested directory.');
            }

            return $this->branches;
        }

        public function main(string $directory): never
        {
            throw new LogicException('Unexpected main() call.');
        }

        public function conversation(string $directory, string $name): never
        {
            throw new LogicException('Unexpected conversation() call.');
        }

        public function current(string $directory): never
        {
            throw new LogicException('Unexpected current() call.');
        }

        public function branch(string $directory, string $name): never
        {
            throw new LogicException('Unexpected branch() call.');
        }

        public function ref(string $directory, string $hash): never
        {
            throw new LogicException('Unexpected ref() call.');
        }

        public function configuration(string $directory, ?string $branch = null): never
        {
            throw new LogicException('Unexpected configuration() call.');
        }

        public function sessions(string $directory): never
        {
            throw new LogicException('Unexpected sessions() call.');
        }
    };
    $tester = new CommandTester(new BranchCommand($conversations));

    $status = $tester->execute([
        'action' => 'list',
        '--dir' => $project,
        '--full' => true,
        '--json' => true,
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::SUCCESS)
        ->and($conversations->branchesCalls)->toBe(1)
        ->and($branches->currentCalls)->toBe(1)
        ->and($branches->listCalls)->toBe(1)
        ->and($payload)->toBe([
            'current' => ['name' => 'review', 'source' => 'current'],
            'count' => 1,
            'branches' => [[
                'name' => 'review',
                'head' => 'abc123',
                'empty' => false,
                'turnCount' => 2,
                'configuration' => ['status' => 'configured', 'version' => 3],
                'current' => true,
                'created' => ['source' => 'current', 'branch' => 'main', 'head' => 'abc123'],
            ]],
        ]);
});
