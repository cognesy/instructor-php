<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Tell\Operational\PlaneMap;
use Cognesy\Tell\Render\FieldSelection;
use Cognesy\Tell\Render\StructuredOutput;
use InvalidArgumentException;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PlanesCommand extends Command
{
    private const array FIELDS = [
        'plane',
        'command',
        'responsibility',
        'ownedState',
        'input',
        'output',
        'authority',
        'degradedBehavior',
    ];

    public function __construct(private readonly PlaneMap $planes)
    {
        parent::__construct('planes');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Describe Tell data, control, and management operations')
            ->setHelp(<<<'HELP'
Show Tell's logical operational-plane map. The planes remain collocated in one
binary; this view documents state ownership, authority, and degraded behavior.

Examples:
  tell planes
  tell planes --full
  tell planes --fields=plane,command,ownedState,authority
HELP)
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated plane-map fields', '')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Include every plane-map field')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requested = (string) $input->getOption('fields');
        if ((bool) $input->getOption('full') && $requested !== '') {
            throw new InvalidArgumentException('--full and --fields cannot be combined.');
        }
        $defaults = match ((bool) $input->getOption('full')) {
            true => self::FIELDS,
            false => ['plane', 'command', 'responsibility'],
        };
        $fields = FieldSelection::from($requested, $defaults, self::FIELDS);
        (new StructuredOutput($output))->write([
            'systemBoundary' => 'The local Tell agent runtime for one workspace.',
            'primaryValue' => 'Execute agent turns and expose their effective runtime safely to shell callers.',
            'separationLevel' => 'Logical contracts and state ownership; one collocated process.',
            'lastKnownGood' => 'The resolved AgentDefinition and AgentProfile are immutable for one invocation; no persisted control snapshot is reused.',
            'count' => $this->planes->count(),
            'planeCounts' => $this->planes->counts(),
            'operations' => $fields->project($this->planes->toArray()),
            'help' => [
                'Run `tell planes --full` for ownership, authority, contracts, and degraded behavior.',
                'Plane names describe operational roles, not separate services or code-layer trees.',
            ],
        ], json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }
}
