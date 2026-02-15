<?php declare(strict_types=1);

namespace Cognesy\Utils\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class NotFoundException extends RuntimeException implements NotFoundExceptionInterface {}
