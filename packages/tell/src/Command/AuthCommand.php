<?php

declare(strict_types=1);

namespace Cognesy\Tell\Command;

use Closure;
use Cognesy\Tell\Operational\CanDescribeOperationalPlane;
use Cognesy\Tell\Operational\OperationalPlane;
use Cognesy\Tell\Operational\PlaneOperation;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellCredentialNames;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class AuthCommand extends Command implements CanDescribeOperationalPlane
{
    /** @var Closure(): string */
    private readonly Closure $readInput;

    /** @param callable(): string|null $readInput */
    public function __construct(
        private readonly TellAgentFactory $agents,
        ?callable $readInput = null,
    ) {
        $this->readInput = match ($readInput) {
            null => static function (): string {
                $content = file_get_contents('php://stdin');

                return is_string($content) ? $content : '';
            },
            default => Closure::fromCallable($readInput),
        };
        parent::__construct('auth');
    }

    #[Override]
    protected function configure(): void
    {
        $this->setDescription('Inspect or manage Tell credentials')
            ->setHelp(<<<'HELP'
Inspect credential provenance or explicitly manage Tell's private credential file.
Secret values are accepted only through standard input and are never rendered.

Resolution order:
  process environment > workspace .env > Tell credential store

Examples:
  tell auth status
  tell auth status openai --json
  printf '%s' "$OPENAI_API_KEY" | tell auth set openai --stdin
  tell auth remove openai
HELP)
            ->addArgument('action', InputArgument::OPTIONAL, 'status, set, or remove', 'status')
            ->addArgument('provider', InputArgument::OPTIONAL, 'Provider or connection name')
            ->addOption('stdin', null, InputOption::VALUE_NONE, 'Read the credential value from standard input')
            ->addOption('variable', null, InputOption::VALUE_REQUIRED, 'Override the provider credential variable', '')
            ->addOption('dir', 'C', InputOption::VALUE_REQUIRED, 'Workspace directory used for .env resolution', '')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            return match ((string) $input->getArgument('action')) {
                'status' => $this->status($input, $output),
                'set' => $this->set($input, $output),
                'remove' => $this->remove($input, $output),
                default => throw new InvalidArgumentException('Unknown auth action. Valid actions: status, set, remove.'),
            };
        } catch (InvalidArgumentException $error) {
            $this->error($input, $output, $error->getMessage());

            return Command::INVALID;
        } catch (RuntimeException $error) {
            $this->error($input, $output, $error->getMessage());

            return Command::FAILURE;
        }
    }

    #[Override]
    public function planeOperation(): PlaneOperation
    {
        return new PlaneOperation(
            plane: OperationalPlane::Management,
            command: 'auth',
            responsibility: 'Inspect credential provenance and explicitly manage Tell-owned credential persistence.',
            ownedState: 'Only ~/.tell/config/credentials.env; ambient environment and workspace .env remain externally owned.',
            input: 'Provider identity plus an explicit status, stdin set, or remove operation.',
            output: 'Configured state and source provenance without secret values.',
            authority: 'Read credential availability and mutate only the named Tell-owned credential.',
            degradedBehavior: 'Data-plane execution may use higher-priority ambient sources; missing credentials fail before inference.',
        );
    }

    private function status(InputInterface $input, OutputInterface $output): int
    {
        if ((bool) $input->getOption('stdin')) {
            throw new InvalidArgumentException('--stdin is valid only for auth set.');
        }
        $provider = $this->optionalProvider($input);
        $resolver = $this->agents->secretResolver($this->workspace($input));
        if ($provider !== null) {
            $variable = $this->variable($input, $provider);
            $resolved = $resolver->resolve($variable);
            $this->write($input, $output, [
                'provider' => $provider,
                'variable' => $variable,
                'configured' => $resolved !== null,
                'source' => $resolved?->source,
                'help' => $resolved === null
                    ? ["Pipe a credential to `tell auth set {$provider} --stdin`."]
                    : ['Secret values are never displayed.'],
            ]);

            return Command::SUCCESS;
        }
        if ((string) $input->getOption('variable') !== '') {
            throw new InvalidArgumentException('--variable requires a provider.');
        }
        $variables = array_values(array_unique([
            ...TellCredentialNames::known(),
            ...$this->agents->credentials()->variables(),
        ]));
        sort($variables);
        $credentials = [];
        foreach ($variables as $variable) {
            $resolved = $resolver->resolve($variable);
            if ($resolved !== null) {
                $credentials[] = $resolved->toArray();
            }
        }
        $this->write($input, $output, [
            'count' => count($credentials),
            'credentials' => $credentials,
            'message' => $credentials === [] ? 'No known provider credentials are configured.' : 'Credential values are hidden.',
            'help' => ['Run `tell auth status <provider>` for one provider.'],
        ]);

        return Command::SUCCESS;
    }

    private function set(InputInterface $input, OutputInterface $output): int
    {
        $provider = $this->requiredProvider($input);
        if (! (bool) $input->getOption('stdin')) {
            throw new InvalidArgumentException('auth set requires --stdin; credential values are never accepted as arguments.');
        }
        $variable = $this->variable($input, $provider);
        $value = rtrim(($this->readInput)(), "\r\n");
        $changed = $this->agents->credentials()->set($variable, $value);
        $this->write($input, $output, [
            'provider' => $provider,
            'variable' => $variable,
            'configured' => true,
            'changed' => $changed,
            'source' => 'tell-credentials',
            'message' => $changed ? 'Credential stored.' : 'Credential was already current.',
        ]);

        return Command::SUCCESS;
    }

    private function remove(InputInterface $input, OutputInterface $output): int
    {
        $provider = $this->requiredProvider($input);
        if ((bool) $input->getOption('stdin')) {
            throw new InvalidArgumentException('--stdin is valid only for auth set.');
        }
        $variable = $this->variable($input, $provider);
        $removed = $this->agents->credentials()->remove($variable);
        $this->write($input, $output, [
            'provider' => $provider,
            'variable' => $variable,
            'removed' => $removed,
            'message' => $removed ? 'Credential removed.' : 'Credential was not present in the Tell store.',
        ]);

        return Command::SUCCESS;
    }

    private function workspace(InputInterface $input): string
    {
        $directory = (string) $input->getOption('dir');
        $cwd = getcwd();
        $workspace = match (true) {
            $directory !== '' => $directory,
            is_string($cwd) => $cwd,
            default => '.',
        };
        if (! is_dir($workspace)) {
            throw new InvalidArgumentException("Working directory does not exist: {$workspace}");
        }

        return $workspace;
    }

    private function requiredProvider(InputInterface $input): string
    {
        $provider = $this->optionalProvider($input);
        if ($provider === null) {
            throw new InvalidArgumentException('Provider is required for auth set and remove.');
        }

        return $provider;
    }

    private function optionalProvider(InputInterface $input): ?string
    {
        $provider = $input->getArgument('provider');

        return match (true) {
            is_string($provider) && $provider !== '' => $provider,
            default => null,
        };
    }

    private function variable(InputInterface $input, string $provider): string
    {
        $variable = (string) $input->getOption('variable');
        $resolved = match ($variable) {
            '' => TellCredentialNames::forProvider($provider),
            default => $variable,
        };
        TellCredentialNames::assertVariable($resolved);

        return $resolved;
    }

    /** @param array<string, mixed> $payload */
    private function write(InputInterface $input, OutputInterface $output, array $payload): void
    {
        (new StructuredOutput($output))->write($payload, json: (bool) $input->getOption('json'));
    }

    private function error(InputInterface $input, OutputInterface $output, string $message): void
    {
        $this->write($input, $output, [
            'error' => $message,
            'help' => ['Run `tell auth --help` for safe credential-management examples.'],
        ]);
    }
}
