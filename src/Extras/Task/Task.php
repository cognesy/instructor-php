<?php
namespace Cognesy\Instructor\Extras\Task;

use Cognesy\Instructor\Extras\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Task\Data\ArrayTaskData;
use Cognesy\Instructor\Extras\Task\Enums\TaskStatus;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use DateTimeImmutable;

class Task implements Contracts\CanProcessInput
{
    use Traits\HandlesTaskInfo;
    use Traits\HandlesTaskStatus;
    use Traits\HandlesTaskData;
    use Traits\HandlesSignature;
    use Traits\HandlesContext;

    public function __construct(string|Signature $signature) {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTime();
        $this->status = TaskStatus::Created;
        $this->context = [];
        $this->setSignature($signature);
        $this->data = ArrayTaskData::fromSignature($this->signature);
    }
}
