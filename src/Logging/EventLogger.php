<?php

namespace Cognesy\Instructor\Logging;

use Cognesy\Instructor\Events\Event;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class EventLogger implements LoggerAwareInterface
{
    private LoggerInterface $logger;
    private string $level;

    public function __construct(
        LoggerInterface $logger,
        string $level = 'info',
    ) {
        $this->logger = $logger;
        $this->level = $level;
    }

    public function setLogger(LoggerInterface $logger): void {
        $this->logger = $logger;
    }

    public function eventListener(Event $event) : void {
        if (!isset($this->logger)) {
            return;
        }
        if ($this->levelOrder($event->logLevel) > $this->levelOrder($this->level)) {
            return;
        }
        $this->logger->log(
            $event->logLevel,
            $event->name(),
            [
                'event' => $event->name(),
                'details' => (string) $event
            ]
        );
    }

    private function levelOrder(string $level) : int {
        $levels = [
            'emergency' => 0,
            'alert' => 1,
            'critical' => 2,
            'error' => 3,
            'warning' => 4,
            'notice' => 5,
            'info' => 6,
            'debug' => 7,
        ];
        return $levels[$level];
    }
}