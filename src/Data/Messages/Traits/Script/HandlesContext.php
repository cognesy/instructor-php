<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Script;

trait HandlesContext
{
    private ?array $context = null;

    public function context() : array {
        return $this->context;
    }

    public function withContext(array $context) : static {
        $this->context = $context;
        return $this;
    }
}
