<?php
namespace Cognesy\InstructorHub\Core;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Utils\Color;

abstract class CliApp {
    private CommandProvider $commandProvider;
    public string $name = "<app name>";
    public string $description = "<app description>";

    public function __construct(Configuration $config) {
        $this->commandProvider = $config->get(CommandProvider::class);
    }

    public function run(int $argc, array $argv) : void {
        Cli::outln();
        Cli::outln($this->name, [Color::WHITE, Color::BOLD]);
        Cli::outln($this->description, Color::DARK_GRAY);
        Cli::outln();
        if ($argc < 2) {
            $this->commandNotSpecified();
            return;
        }
        list($command, $args) = $this->parseArgs($argv);
        $this->onCommand($command, $args);
        Cli::outln();
    }

    private function parseArgs(array $argv) : array {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);
        return [$command, $args];
    }

    private function onCommand(string $command, array $args) : void {
        // if command help - display commands
        if ($command === 'help') {
            $this->showHelp();
            return;
        }

        // check if the command exists
        if (!$this->commandProvider->commandExists($command)) {
            $this->commandUnknown($command);
            return;
        }

        // execute the command
        $commandInstance = $this->commandProvider->getCommand($command);
        $commandInstance->execute($args);
    }

    private function commandUnknown(string $command) : void {
        Cli::outln("Command `{$command}` not found.");
        Cli::outln("Use `help` to list available commands.");
    }

    private function commandNotSpecified() : void {
        Cli::outln("No command provided.");
        Cli::outln("Use `help` to list available commands.");
    }

    public function showHelp() {
        Cli::outln("Available commands:", Color::YELLOW);
        foreach ($this->commandProvider->listCommands() as $command) {
            Cli::grid([
                [2, ">", STR_PAD_LEFT, Color::DARK_YELLOW],
                [3, "hub", STR_PAD_LEFT, Color::DARK_GRAY],
                [10, $command->name, STR_PAD_RIGHT, Color::GREEN],
                [3, "-"],
                [30, $command->description, STR_PAD_RIGHT, Color::GRAY],
            ]);
            Cli::outln();
        }
    }
}
