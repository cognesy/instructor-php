<?php

declare(strict_types=1);

$command = $argv[1] ?? '';
if ($command === '') {
    fwrite(STDERR, "Tell shell job worker requires a command.\n");
    exit(125);
}

if (function_exists('posix_setsid')) {
    @posix_setsid();
}

if (function_exists('pcntl_exec')) {
    pcntl_exec('/bin/sh', ['-lc', $command]);
    fwrite(STDERR, "Tell shell job worker could not exec /bin/sh.\n");
    exit(126);
}

passthru('/bin/sh -lc '.escapeshellarg($command), $exitCode);
exit($exitCode);
