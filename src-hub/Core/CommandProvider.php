<?php

namespace Cognesy\InstructorHub\Core;

use Cognesy\Instructor\Container\Container;

class CommandProvider
{
    private Container $config;
    private array $commands;
    private array $commandInstances;

    public function __construct(
        Container $config,
        array     $commands
    ) {
        if (empty($commands)) {
            throw new \Exception('No commands defined in ' . get_class($this));
        }
        $this->config = $config;
        $this->commands = $commands;
    }

    public function getCommand(string $commandName) : Command {
        if (!$this->commandExists($commandName)) {
            throw new \Exception("Command `{$commandName}` not found.");
        }
        return $this->getCommandInstance($commandName);
    }

    public function listCommands() : array {
        return $this->getCommandInstances();
    }

    public function commandExists(string $commandName) : bool {
        return array_key_exists($commandName, $this->getCommandInstances());
    }

    private function getCommandInstance(string $commandName) : Command {
        return $this->getCommandInstances()[$commandName];
    }

    private function getCommandInstances() : array {
        // return if already instantiated
        if (!empty($this->commandInstances)) {
            return $this->commandInstances;
        }
        // instantiate all commands
        foreach ($this->commands as $commandClass) {
            [$name, $instance] = $this->createCommandInstance($commandClass);
            $this->commandInstances[$name] = $instance;
        }
        return $this->commandInstances;
    }

    private function createCommandInstance(string $commandClass) : array {
        /** @var Command $instance */
        $instance = $this->config->get($commandClass);
        return [$instance->name, $instance];
    }
}