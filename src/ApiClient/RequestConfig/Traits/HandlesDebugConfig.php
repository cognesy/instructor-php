<?php
namespace Cognesy\Instructor\ApiClient\RequestConfig\Traits;

use Cognesy\Instructor\ApiClient\RequestConfig\DebugConfig;

trait HandlesDebugConfig
{
    public DebugConfig $debugConfig;

    private function withDebugConfig(?DebugConfig $debugConfig) : void {
        $this->debugConfig = $debugConfig ?? new DebugConfig();
    }

    public function withDebug(bool $debug = true, bool $stopOnDebug = true) : static {
        $this->debugConfig = new DebugConfig($debug, $stopOnDebug);
        return $this;
    }

    public function isDebug() : bool {
        return $this->debugConfig->debug();
    }

    public function stopOnDebug() : bool {
        return $this->debugConfig->stopOnDebug();
    }
}
