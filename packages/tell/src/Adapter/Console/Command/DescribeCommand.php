<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Command;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Adapter\Console\Symfony\TellOptions;
use Cognesy\Tell\Adapter\Console\Render\StructuredOutput;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Data\TellRequest;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DescribeCommand extends Command
{
    public function __construct(private readonly CanBuildTellAgent $agents) {
        parent::__construct('describe');
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Describe a built agent')
            ->setHelp(<<<'HELP'
Describe the effective runtime assembled for an agent definition.

Examples:
  tell describe
  tell describe --agent reviewer --prompt
  tell describe --json
HELP)
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent definition name', 'default')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Tool working directory', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON')
            ->addOption('prompt', null, InputOption::VALUE_NONE, 'Include composed system prompt');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $options = $this->options($input);
        $request = $options->request();
        $definition = $this->agents->definition($request);
        $loop = $this->agents->build($request, definition: $definition);
        $description = $loop->describe()->toArray();
        if ((bool) $input->getOption('prompt')) {
            $description['systemPrompt'] = $this->systemPrompt($loop, $definition);
        }

        $description['help'] = [
            'Run `tell tools --agent <name>` for a compact tool list.',
            'Add `--prompt` to include the effective system prompt.',
        ];
        (new StructuredOutput($output))->write($description, json: (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }


    private function options(InputInterface $input): TellOptions {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $project = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };

        return new TellOptions(
            prompt: 'Describe this agent.',
            agent: (string) $input->getOption('agent'),
            directory: $project,
        );
    }

    private function systemPrompt(AgentLoop $loop, AgentDefinition $definition): string {
        $state = (new DefinitionStateFactory())->instantiateAgentState($definition);
        $interceptor = $loop->interceptor();
        $state = match (true) {
            $interceptor === null => $state,
            default => $interceptor->intercept(HookContext::beforeStep($state))->state(),
        };

        return $state->context()->systemPrompt();
    }
}
