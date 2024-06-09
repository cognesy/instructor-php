<?php
namespace Cognesy\Instructor\Traits\Instructor;

trait HandlesDebug
{
    public function debug() : bool {
        return $this->apiRequestConfig->isDebug();
    }

    public function withDebug(bool $debug = true, bool $stopOnDebug = true) : static {
        $this->apiRequestConfig->withDebug($debug, $stopOnDebug);
        return $this;
    }
}
