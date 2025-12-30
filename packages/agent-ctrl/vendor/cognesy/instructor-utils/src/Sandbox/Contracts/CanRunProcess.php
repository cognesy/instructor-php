<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Contracts;

use Cognesy\Utils\Sandbox\Data\ExecResult;

interface CanRunProcess
{
    /**
     * Execute a command given as argv with specified cwd, env and optional stdin.
     *
     * @param list<string> $argv
     * @param array<string,string> $env
     */
    public function run(array $argv, string $cwd, array $env, ?string $stdin): ExecResult;

    /**
     * Execute a command with real-time output streaming.
     *
     * @param list<string> $argv
     * @param array<string,string> $env
     * @param callable(string $type, string $chunk): void|null $onOutput Callback for streaming output
     */
    public function runStreaming(array $argv, string $cwd, array $env, ?string $stdin, ?callable $onOutput): ExecResult;
}

