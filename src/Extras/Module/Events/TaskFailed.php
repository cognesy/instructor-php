<?php
namespace Cognesy\Instructor\Extras\Module\Events;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use DateTime;
use DateTimeImmutable;

class CallFailed extends Event
{
    public function __construct(
        public string $callId,
        public string $callName,
        public DateTimeImmutable $callCreatedAt,
        public DateTime $callUpdatedAt,
        public array $callDetails,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'callId' => $this->callId,
            'callName' => $this->callName,
            'callCreatedAt' => $this->callCreatedAt->format('Y-m-d H:i:s'),
            'callUpdatedAt' => $this->callUpdatedAt->format('Y-m-d H:i:s'),
            'callDetails' => $this->callDetails,
        ]);
    }
}