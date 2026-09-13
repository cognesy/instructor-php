<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Tell\Adapter\Console\Symfony\TellOptions;
use Cognesy\Tell\Adapter\Console\Render\FieldSelection;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Data\TellRequest;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ToolsCommand extends Command
{
    public function __construct(private readonly CanBuildTellAgent $agents) {
        parent::__construct('tools');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('List tools resolved for a built agent')
            ->setHelp(<<<'HELP'
Build an agent and list the tools available to its runtime.

Examples:
  tell tools
  tell tools --agent reviewer --fields=name,description,deferred
  tell tools --json
HELP)
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated tool fields', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        $options = new TellOptions(
            prompt: 'List tools.',
            agent: (string) $input->getOption('agent'),
            directory: $project,
        );
        $tools = $this->agents->build($options->request())->profile()->tools->toArray();
        $rows = array_map(static fn (array $tool): array => [
            ...$tool,
            'promptVisible' => $tool['promptSnippet'] !== null,
        ], $tools);
        $fields = FieldSelection::from(
            (string) $input->getOption('fields'),
            ['name', 'description', 'promptVisible'],
            ['name', 'description', 'promptVisible', 'promptSnippet', 'promptGuidelines', 'metadata', 'instructions', 'deferred'],
        );
        $payload = [
            'agent' => $options->agent,
            'count' => count($rows),
            'tools' => $fields->project($rows),
            'help' => [
                'Run `tell describe --agent <name>` for the complete runtime profile.',
                'Use `--fields=name,description,deferred` to select another schema.',
            ],
        ];
        if ($rows === []) {
            $payload['message'] = "Agent {$options->agent} resolved zero tools.";
        }
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

}
