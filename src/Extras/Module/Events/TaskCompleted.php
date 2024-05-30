<?php
namespace Cognesy\Instructor\Extras\Module\Events;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use DateTime;
use DateTimeImmutable;

class TaskCompleted extends Event
{
    public function __construct(
        public string $taskId,
        public string $taskName,
        public DateTimeImmutable $taskCreatedAt,
        public DateTime $taskUpdatedAt,
        public array $taskDetails,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'taskId' => $this->taskId,
            'taskName' => $this->taskName,
            'taskCreatedAt' => $this->taskCreatedAt->format('Y-m-d H:i:s'),
            'taskUpdatedAt' => $this->taskUpdatedAt->format('Y-m-d H:i:s'),
            'taskDetails' => $this->taskDetails,
        ]);
    }
}