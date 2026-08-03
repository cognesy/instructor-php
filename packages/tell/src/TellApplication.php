<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Tell\Command\AgentsCommand;
use Cognesy\Tell\Command\AuthCommand;
use Cognesy\Tell\Command\DescribeCommand;
use Cognesy\Tell\Command\PlanesCommand;
use Cognesy\Tell\Command\SessionsCommand;
use Cognesy\Tell\Command\ToolsCommand;
use Cognesy\Tell\Operational\PlaneMap;
use Cognesy\Tell\Render\StructuredOutput;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TellApplication extends Application
{
    private const string PACKAGE_NAME = 'cognesy/instructor-tell';

    private readonly InputDefinition $routingDefinition;

    public function __construct(?TellAgentFactory $agents = null)
    {
        parent::__construct('Instructor Tell', self::packageVersion());
        $this->setDefaultCommand('tell');

        $agents ??= TellAgentFactory::installed();
        $tell = new TellCommand($agents);
        $agentsCommand = new AgentsCommand($agents);
        $authCommand = new AuthCommand($agents);
        $describeCommand = new DescribeCommand($agents);
        $sessionsCommand = new SessionsCommand($agents);
        $toolsCommand = new ToolsCommand($agents);
        $planeMap = PlaneMap::fromCommands(
            $tell,
            $agentsCommand,
            $authCommand,
            $describeCommand,
            $sessionsCommand,
            $toolsCommand,
        );
        $commands = [
            $tell,
            $agentsCommand,
            $authCommand,
            $describeCommand,
            new PlanesCommand($planeMap),
            $sessionsCommand,
            $toolsCommand,
        ];
        $this->routingDefinition = $this->routingDefinition(...$commands);
        $this->addCommands($commands);
    }

    /** @param list<string>|null $argv */
    public function runArgv(?array $argv = null, ?OutputInterface $output = null): int
    {
        $arguments = $argv ?? array_values($_SERVER['argv'] ?? []);
        $routed = $this->route($arguments);
        $output ??= new ConsoleOutput;
        $this->setCatchExceptions(false);
        try {
            return parent::run(new ArgvInput($routed), $output);
        } catch (ConsoleException|InvalidArgumentException $error) {
            $this->writeError($output, $routed, $error->getMessage(), true);

            return Command::INVALID;
        } catch (Throwable $error) {
            $this->writeError($output, $routed, $error->getMessage(), false);

            return Command::FAILURE;
        } finally {
            $this->setCatchExceptions(true);
        }
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function route(array $arguments): array
    {
        $index = $this->commandIndex($this->routingArguments($arguments));

        if ($index !== null && $this->has($arguments[$index])) {
            return $this->hoistCommand($arguments, $index);
        }

        array_splice($arguments, 1, 0, ['tell']);

        return $arguments;
    }

    /**
     * Locates the first token that could name a command, skipping options and
     * the values they consume. Unknown options are treated as value-less, so a
     * mistyped option never hides the command name behind it: routing stays
     * correct and Symfony reports the error against the intended command.
     *
     * @param  list<string>  $arguments
     */
    private function commandIndex(array $arguments): ?int
    {
        $count = count($arguments);
        for ($index = 1; $index < $count; $index++) {
            $token = $arguments[$index];
            if ($token === '' || ! str_starts_with($token, '-')) {
                return $index;
            }
            if ($this->consumesNextToken($token)) {
                $index++;
            }
        }

        return null;
    }

    /**
     * Moves the command token directly after the script name. Symfony resolves
     * the command with its own definition-less scan, which would otherwise
     * mistake a preceding option's value for the command name.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function hoistCommand(array $arguments, int $index): array
    {
        if ($index === 1) {
            return $arguments;
        }
        $command = $arguments[$index];
        array_splice($arguments, $index, 1);
        array_splice($arguments, 1, 0, [$command]);

        return $arguments;
    }

    /**
     * Whether an option token takes its value from the following token, rather
     * than inline (`--option=value`, `-ovalue`) or not at all.
     */
    private function consumesNextToken(string $token): bool
    {
        if (str_starts_with($token, '--')) {
            $name = substr($token, 2);

            return ! str_contains($name, '=')
                && $this->routingDefinition->hasOption($name)
                && $this->routingDefinition->getOption($name)->acceptValue();
        }

        $shortcut = substr($token, 1);

        return strlen($shortcut) === 1
            && $this->routingDefinition->hasShortcut($shortcut)
            && $this->routingDefinition->getOptionForShortcut($shortcut)->acceptValue();
    }

    /**
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private function routingArguments(array $arguments): array
    {
        $separator = array_search('--', $arguments, true);

        return match ($separator) {
            false => $arguments,
            default => array_slice($arguments, 0, $separator),
        };
    }

    private function routingDefinition(Command ...$commands): InputDefinition
    {
        $definition = new InputDefinition;
        $definition->addOptions(array_values($this->getDefinition()->getOptions()));
        foreach ($commands as $command) {
            $definition->addOptions(array_values($command->getDefinition()->getOptions()));
        }

        return $definition;
    }

    /** @param list<string> $arguments */
    private function writeError(
        OutputInterface $output,
        array $arguments,
        string $message,
        bool $usage,
    ): void {
        $command = $this->addressedCommand($arguments);
        $payload = ['error' => $message];
        if ($usage) {
            $helpCommand = match ($command->getName()) {
                'tell' => 'tell --help',
                default => 'tell '.$command->getName().' --help',
            };
            $payload['help'] = [
                "Valid flags for `{$command->getName()}`: ".implode(', ', $this->validFlags($command)).'.',
                "Run `{$helpCommand}` for usage and examples.",
            ];
        }
        (new StructuredOutput($output))->write($payload, json: $this->requestsJson($arguments));
    }

    /** @param list<string> $arguments */
    private function addressedCommand(array $arguments): Command
    {
        $name = $arguments[1] ?? 'tell';

        return match (true) {
            is_string($name) && $this->has($name) => $this->get($name),
            default => $this->get('tell'),
        };
    }

    /** @return list<string> */
    private function validFlags(Command $command): array
    {
        $options = [
            ...array_values($this->getDefinition()->getOptions()),
            ...array_values($command->getDefinition()->getOptions()),
        ];
        $flags = array_map(static fn (InputOption $option): string => '--'.$option->getName(), $options);
        sort($flags);

        return array_values(array_unique($flags));
    }

    /** @param list<string> $arguments */
    private function requestsJson(array $arguments): bool
    {
        foreach ($arguments as $index => $argument) {
            if ($argument === '--json' || $argument === '--output=json' || $argument === '--output=events') {
                return true;
            }
            if ($argument === '--output' && in_array($arguments[$index + 1] ?? null, ['json', 'events'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function packageVersion(): string
    {
        if (InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? 'unknown';
        }

        return InstalledVersions::getRootPackage()['pretty_version'] ?? 'unknown';
    }
}
