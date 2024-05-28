<?php
namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Extras\Tasks\Task\Data\ArrayTaskData;
use Cognesy\Instructor\Extras\Tasks\Task\Enums\TaskStatus;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use DateTimeImmutable;

class Task implements \Cognesy\Instructor\Extras\Tasks\Task\Contracts\CanProcessInput
{
    use \Cognesy\Instructor\Extras\Tasks\Task\Traits\HandlesTaskInfo;
    use \Cognesy\Instructor\Extras\Tasks\Task\Traits\HandlesTaskStatus;
    use \Cognesy\Instructor\Extras\Tasks\Task\Traits\HandlesTaskData;
    use \Cognesy\Instructor\Extras\Tasks\Task\Traits\HandlesSignature;
    use \Cognesy\Instructor\Extras\Tasks\Task\Traits\HandlesContext;

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
