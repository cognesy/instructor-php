<?php
namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\Enums\TaskStatus;
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
        $this->signature = $this->initSignature($signature);
        $this->data = $this->signature->data();
    }
}
