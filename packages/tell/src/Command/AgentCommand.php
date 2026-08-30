<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Tell\Contracts\CanRunTellProtocol;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Protocol\TellAgentProtocolException;
use Cognesy\Tell\Protocol\TellAgentProtocolRequest;
use Cognesy\Tell\Protocol\TellAgentProtocolWriter;
use Cognesy\Tell\Runtime\TellSignalCancellationSource;
use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/** A bounded one-request/one-run JSONL process boundary for non-PHP controllers. */
final class AgentCommand extends Command implements CanDescribeOperationalPlane
{
    private CanProvideCancellationSignal $cancellation;

    public function __construct(
        private readonly CanRunTellProtocol $protocolRunner,
        ?CanProvideCancellationSignal $cancellation = null,
    ) {
        parent::__construct('agent');
        $this->cancellation = $cancellation ?? new TellSignalCancellationSource();
    }

    #[Override]
    protected function configure(): void {
        $this->setDescription('Run one versioned Tell request over a bounded JSONL protocol')
            ->addOption('rpc', null, InputOption::VALUE_NONE, 'Read one tell.agent.request.v1 object from stdin')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory', '');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $writer = new TellAgentProtocolWriter($output);
        $diagnostics = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            if (!(bool) $input->getOption('rpc')) {
                throw new TellAgentProtocolException('invalid_request', 'The agent command requires --rpc.');
            }
            $directory = (string) $input->getOption('dir');
            if ($directory === '') {
                $cwd = getcwd();
                $directory = is_string($cwd) ? $cwd : '.';
            }
            $raw = stream_get_contents(STDIN, TellAgentProtocolRequest::MAX_INPUT_BYTES + 1);
            if (!is_string($raw)) {
                throw new TellAgentProtocolException('invalid_request', 'Request could not be read from stdin.');
            }
            $protocol = TellAgentProtocolRequest::decode($raw, $directory);
            $writer->identify($protocol->id);
            if ($this->cancellation instanceof TellSignalCancellationSource) {
                $this->cancellation->install();
            }

            return $this->protocolRunner->run($protocol, $writer, $this->cancellation);
        } catch (TellAgentProtocolException $error) {
            if (!$writer->hasTerminalFrame()) {
                $writer->error($error->protocolCode, $error->getMessage());
            }

            return Command::INVALID;
        } catch (Throwable $error) {
            $diagnostics->writeln('Tell agent run failed (' . get_debug_type($error) . ').', OutputInterface::OUTPUT_RAW);
            if (!$writer->hasTerminalFrame()) {
                $writer->error('runtime_error', 'The Tell run failed.');
            }

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation {
        return new PlaneOperation(
            plane: OperationalPlane::Data,
            command: 'agent --rpc',
            responsibility: 'Execute one bounded, versioned request for an external controller.',
            ownedState: 'No protocol daemon state; only the execution mode requested by the caller.',
            input: 'One tell.agent.request.v1 JSON object on stdin.',
            output: 'Ordered tell.agent.frame.v1 JSONL progress plus exactly one terminal frame.',
            authority: 'Run one Tell invocation with public request controls and cooperative cancellation.',
            degradedBehavior: 'Return one bounded error or cancellation frame with a distinct exit status.',
        );
    }
}
