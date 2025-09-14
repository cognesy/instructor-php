<?php declare(strict_types=1);

namespace Cognesy\Utils\Context\Psr\Exceptions;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

final class ContainerError extends RuntimeException implements ContainerExceptionInterface {}

