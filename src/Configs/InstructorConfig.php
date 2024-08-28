<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;

class InstructorConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {
        $config->fromConfigProviders([
            new RequestHandlingConfig(),
            new ResponseHandlingConfig(),
        ]);
    }
}
