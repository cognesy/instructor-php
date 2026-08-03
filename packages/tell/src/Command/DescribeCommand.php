<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DescribeCommand extends Command implements CanDescribeOperationalPlane
{
    private readonly TellAgentFactory $agents;

    public function __construct(?TellAgentFactory $agents = null)
    {
        $this->agents = $agents ?? TellAgentFactory::installed();
        parent::__construct('describe');
    }

    #[Override]
    protected function configure(): void
    {
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = $this->options($input);
        $definition = $this->agents->definition($options);
        $loop = $this->agents->build($options, $definition);
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

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Control,
            command: 'describe',
            responsibility: 'Compile and inspect the effective execution profile for one agent.',
            ownedState: 'Immutable AgentProfile and optionally composed system prompt for one invocation.',
            input: 'Selected AgentDefinition plus current capability, tool, hook, and LLM configuration.',
            output: 'Version-local execution-policy snapshot consumed by the data plane.',
            authority: 'Resolve and inspect active policy; no inference, tool execution, or persistent mutation.',
            degradedBehavior: 'Returns no partial profile when resolution fails; existing persisted sessions remain untouched.',
        );
    }

    private function options(InputInterface $input): TellOptions
    {
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

    private function systemPrompt(AgentLoop $loop, AgentDefinition $definition): string
    {
        $state = (new DefinitionStateFactory)->instantiateAgentState($definition);
        $interceptor = $loop->interceptor();
        $state = match (true) {
            $interceptor === null => $state,
            default => $interceptor->intercept(HookContext::beforeStep($state))->state(),
        };

        return $state->context()->systemPrompt();
    }
}
