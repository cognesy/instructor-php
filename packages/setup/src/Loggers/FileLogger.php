<?php declare(strict_types=1);

namespace Cognesy\Setup\Loggers;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class FileLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('[Y-m-d H:i:s]');
        $logMessage = sprintf('%s [%s] %s', $timestamp, strtoupper((string)$level), (string)$message);
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }

    #[\Override]
    public function emergency(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
    #[\Override]
    public function alert(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::ALERT, $message, $context); }
    #[\Override]
    public function critical(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::CRITICAL, $message, $context); }
    #[\Override]
    public function error(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::ERROR, $message, $context); }
    #[\Override]
    public function warning(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::WARNING, $message, $context); }
    #[\Override]
    public function notice(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::NOTICE, $message, $context); }
    #[\Override]
    public function info(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::INFO, $message, $context); }
    #[\Override]
    public function debug(string|\Stringable $message, array $context = []): void { $this->log(LogLevel::DEBUG, $message, $context); }
}
