<?php
namespace Cognesy\Instructor\Extras\Module\Call;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Module\Call\Contracts\CanBeProcessed;
use Cognesy\Instructor\Extras\Module\Call\Contracts\HasErrorData;
use Cognesy\Instructor\Extras\Module\Call\Enums\CallStatus;
use Cognesy\Instructor\Extras\Module\CallData\Contracts\HasInputOutputData;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use DateTimeImmutable;
use Exception;

// Calls are used to track execution of modules

class CallWithSignature implements CanBeProcessed, HasErrorData
{
    use Traits\HandlesContext;
    use Traits\HandlesErrors;
    use Traits\HandlesCallDataAccess;
    use Traits\HandlesCallInfo;
    use Traits\HandlesCallStatus;

    protected string $caller;
    protected string $callerSignature;
    protected HasInputOutputData $data;

    public function __construct(
        HasInputOutputData $data,
        string $caller = '',
        string $callerSignature = ''
    ) {
        $this->id = Uuid::uuid4();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTime();
        $this->status = CallStatus::Created;
        $this->context = [];
        $this->caller = $caller;
        $this->callerSignature = $callerSignature;
        $this->data = $data;
    }

    public function data(): HasInputOutputData {
        if (!isset($this->data)) {
            throw new Exception('Call data is not set');
        }
        return $this->data;
    }

    public function signature() : Signature {
        return $this->data->signature();
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(DateTime::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTime::ATOM),
            'signature' => $this->signature()->toShortSignature(),
            'signatureDescription' => $this->signature()->getDescription(),
            'signatureDetails' => $this->signature()->toSignatureString(),
            'inputs' => Json::encode($this->inputs()),
            'outputs' => Json::encode($this->outputs()),
            'context' => Json::encode($this->context),
        ];
    }
}
