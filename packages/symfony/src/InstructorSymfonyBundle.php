<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony;

use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class InstructorSymfonyBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new InstructorSymfonyExtension;
    }
}
