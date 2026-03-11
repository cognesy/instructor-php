<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

/**
 * Preprocesses skill body content by executing shell commands.
 *
 * Finds !`command` patterns in the skill body, executes each command,
 * and replaces the pattern with the command output. This runs before
 * argument substitution.
 */
final class SkillPreprocessor
{
    private const PATTERN = '/!\x60([^\x60]+)\x60/';

    private ?string $workingDirectory;
    private int $timeoutSeconds;

    public function __construct(
        ?string $workingDirectory = null,
        int $timeoutSeconds = 10,
    ) {
        $this->workingDirectory = $workingDirectory;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function process(string $body): string {
        return preg_replace_callback(self::PATTERN, function (array $matches) {
            $command = $matches[1];
            return $this->executeCommand($command);
        }, $body);
    }

    public function hasCommands(string $body): bool {
        return (bool) preg_match(self::PATTERN, $body);
    }

    private function executeCommand(string $command): string {
        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $cwd = $this->workingDirectory ?? getcwd() ?: null;
        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);

        if (!is_resource($process)) {
            return "[error: failed to execute '{$command}']";
        }

        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $startTime = time();

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            if ($out !== false) $stdout .= $out;
            if ($err !== false) $stderr .= $err;

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Read any remaining output
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                if ($out !== false) $stdout .= $out;
                if ($err !== false) $stderr .= $err;
                break;
            }

            if ((time() - $startTime) >= $this->timeoutSeconds) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return "[error: command '{$command}' timed out after {$this->timeoutSeconds}s]";
            }

            usleep(10000); // 10ms
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = $status['exitcode'] ?? proc_close($process);

        if ($exitCode !== 0 && $exitCode !== -1) {
            $errorMsg = trim($stderr) ?: trim($stdout);
            return "[error: command '{$command}' failed (exit {$exitCode}): {$errorMsg}]";
        }

        proc_close($process);
        return trim($stdout);
    }
}
