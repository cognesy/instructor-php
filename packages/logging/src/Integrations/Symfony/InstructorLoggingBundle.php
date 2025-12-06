<?php

declare(strict_types=1);

namespace Cognesy\Logging\Integrations\Symfony;

use Cognesy\Logging\Integrations\Symfony\DependencyInjection\InstructorLoggingExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle for Instructor Logging
 */
class InstructorLoggingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getContainerExtension(): InstructorLoggingExtension
    {
        return new InstructorLoggingExtension();
    }
}