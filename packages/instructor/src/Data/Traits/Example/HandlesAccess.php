<?php
namespace Cognesy\Instructor\Data\Traits\Example;

use Cognesy\Utils\Messages\Messages;

trait HandlesAccess
{
    public function input() : mixed {
        return $this->input;
    }

    public function output() : mixed {
        return $this->output;
    }

    public function inputString() : string {
        return trim(Messages::fromInput($this->input)->toString());
    }

    public function outputString() : string {
        return trim(Messages::fromInput($this->output)->toString());
    }
}