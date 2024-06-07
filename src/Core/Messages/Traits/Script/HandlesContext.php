<?php
namespace Cognesy\Instructor\Core\Messages\Traits\Script;

trait HandlesContext
{
    private ?array $context = null;

    public function getContext() : array {
        return $this->context;
    }

    public function setContext(array $context) : void {
        $this->context = $context;
    }
}
