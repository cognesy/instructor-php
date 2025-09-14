<?php declare(strict_types=1);

namespace Cognesy\Utils\Context\Psr\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class NotFound extends RuntimeException implements NotFoundExceptionInterface {}

