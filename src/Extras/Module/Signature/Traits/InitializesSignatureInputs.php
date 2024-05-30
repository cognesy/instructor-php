<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Traits;

use Cognesy\Instructor\Extras\Module\Task\Utils\CallUtils;
use Exception;

trait InitializesSignatureInputs
{
    public function withArgs(mixed ...$inputs) : static {
        $result = CallUtils::argsMatch($inputs, $this->input->getPropertyNames());
        if ($result->isFailure()) {
            throw new Exception($result->error());
        }
        $this->input()->setValues($inputs);
        return $this;
    }
}