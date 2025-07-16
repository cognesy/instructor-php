<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core\Traits;

use Exception;

trait HandlesResultTypecasting
{
    // TYPECASTING RESULTS //////////////////////////////////////

    /**
     * Returns the result as a boolean.
     *
     * @return bool
     * @throws Exception
     */
    public function getBoolean() : bool {
        $result = $this->get();
        if (!is_bool($result)) {
            throw new Exception('Result is not a boolean: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an integer.
     *
     * @return int
     * @throws Exception
     */
    public function getInt() : int {
        $result = $this->get();
        if (!is_int($result)) {
            throw new Exception('Result is not an integer: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as a float.
     *
     * @return float
     * @throws Exception
     */
    public function getFloat() : float {
        $result = $this->get();
        if (!is_float($result)) {
            throw new Exception('Result is not a float: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as a string.
     *
     * @return string
     * @throws Exception
     */
    public function getString() : string {
        $result = $this->get();
        if (!is_string($result)) {
            throw new Exception('Result is not a string: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an array.
     *
     * @return array
     * @throws Exception
     */
    public function getArray() : array {
        $result = $this->get();
        if (!is_array($result)) {
            throw new Exception('Result is not an array: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an object.
     *
     * @return object
     * @throws Exception
     */
    public function getObject() : object {
        $result = $this->get();
        if (!is_object($result)) {
            throw new Exception('Result is not an object: ' . gettype($result));
        }
        return $result;
    }

    /**
     * Returns the result as an instance of the specified class.
     *
     * @template T
     * @param class-string<T> $class The class name of the returned object
     * @return T
     * @psalm-return T
     * @throws Exception
     */
    public function getInstanceOf(string $class) : object {
        $result = $this->get();
        if (!is_object($result)) {
            throw new Exception('Result is not an object: ' . gettype($result));
        }
        if (!is_a($result, $class)) {
            throw new Exception('Cannot return type `' . gettype($result) . '` as an instance of: ' . $class);
        }
        return $result;
    }
}