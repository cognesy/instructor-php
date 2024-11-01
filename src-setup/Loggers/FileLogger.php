<?php

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

    public function log($level, $message, array $context = []): void
    {
        $timestamp = date('[Y-m-d H:i:s]');
        $logMessage = sprintf('%s [%s] %s', $timestamp, strtoupper($level), $message);
        file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }

    public function emergency($message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
    public function alert($message, array $context = []): void { $this->log(LogLevel::ALERT, $message, $context); }
    public function critical($message, array $context = []): void { $this->log(LogLevel::CRITICAL, $message, $context); }
    public function error($message, array $context = []): void { $this->log(LogLevel::ERROR, $message, $context); }
    public function warning($message, array $context = []): void { $this->log(LogLevel::WARNING, $message, $context); }
    public function notice($message, array $context = []): void { $this->log(LogLevel::NOTICE, $message, $context); }
    public function info($message, array $context = []): void { $this->log(LogLevel::INFO, $message, $context); }
    public function debug($message, array $context = []): void { $this->log(LogLevel::DEBUG, $message, $context); }
}
