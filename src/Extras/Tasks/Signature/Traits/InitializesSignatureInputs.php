<?php

namespace Cognesy\Instructor\Extras\Tasks\Signature\Traits;

use Cognesy\Instructor\Extras\Tasks\Task\CallUtils;
use Exception;

trait InitializesSignatureInputs
{
    public function withArgs(mixed ...$inputs) : static {
        $result = CallUtils::argsMatch($inputs, $this->data->getInputNames());
        if ($result->isFailure()) {
            throw new Exception($result->error());
        }
        $this->data->setInputValues($inputs);
        return $this;
    }
}