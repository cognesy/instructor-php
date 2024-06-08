<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesApiRequestBody
{
    protected function toApiRequestBody() : array {
        return array_filter(array_merge(
            ['model' => $this->modelName()],
            $this->options(),
        ));
    }
}
