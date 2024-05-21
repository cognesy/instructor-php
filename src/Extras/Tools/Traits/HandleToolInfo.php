<?php

namespace Cognesy\Instructor\Extras\Tools\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Enums\Mode;

trait HandleToolInfo
{
    private string $name;
    private string $description;
    private ClientType $clientType;
    private Mode $callMode;

    protected function getName(): string {
        return $this->name;
    }

    protected function getDescription(): string {
        return $this->description;
    }

    public function getClientType(): ClientType {
        return $this->clientType;
    }

    public function getMode(): Mode {
        return $this->callMode;
    }
}