<?php
namespace Cognesy\Instructor\Configs;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Container\Contracts\CanAddConfiguration;

class InstructorConfig implements CanAddConfiguration
{
    public function addConfiguration(Container $config): void {
        $config->fromConfigProviders([
            new RequestHandlingConfig(),
            new ResponseHandlingConfig(),
        ]);
    }
}
