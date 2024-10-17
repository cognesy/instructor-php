<?php

namespace Cognesy\Instructor\Extras\Evals\Utils;

use Cognesy\Instructor\Extras\Evals\Enums\ValueAggregationMethod;
use InvalidArgumentException;
use RuntimeException;

class ValueAggregator
{
    private array $values;
    private array $params;
    private ValueAggregationMethod $method;

    /**
     * ValueAggregator constructor.
     *
     * @param array<float> $values The input values for aggregation.
     * @param array<string, mixed> $params Additional parameters for aggregation.
     * @param ValueAggregationMethod $method Aggregation method.
     */
    public function __construct(
        array $values = [],
        array $params = [],
        ValueAggregationMethod $method = ValueAggregationMethod::Mean
    ) {
        $this->setValues($values);
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * Sets the values for aggregation.
     *
     * @param array $values
     * @return void
     * @throws InvalidArgumentException
     */
    public function setValues(array $values): void
    {
        if (empty($values)) {
            throw new InvalidArgumentException("Values array cannot be empty.");
        }

        // Ensure all elements are numeric
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("All values must be numeric.");
            }
        }

        $this->values = $values;
    }

    /**
     * Sets the aggregation method.
     *
     * @param ValueAggregationMethod $method
     * @return void
     */
    public function setMethod(ValueAggregationMethod $method): void
    {
        $this->method = $method;
    }

    /**
     * Performs the aggregation based on the selected method.
     *
     * @return float|array
     * @throws RuntimeException
     */
    public function aggregate() : float {
        return match ($this->method) {
            ValueAggregationMethod::Min => $this->min(),
            ValueAggregationMethod::Max => $this->max(),
            ValueAggregationMethod::Sum => $this->sum(),
            ValueAggregationMethod::Mean => $this->mean(),
            ValueAggregationMethod::Median => $this->median(),
            ValueAggregationMethod::Variance => $this->variance(),
            ValueAggregationMethod::StandardDeviation => $this->standardDeviation(),
            ValueAggregationMethod::SumOfSquares => $this->sumOfSquares(),
            ValueAggregationMethod::Range => $this->range(),
            ValueAggregationMethod::GeometricMean => $this->geometricMean(),
            ValueAggregationMethod::HarmonicMean => $this->harmonicMean(),
            default => throw new RuntimeException("Unsupported aggregation method: {$this->method->value}"),
        };
    }

    /**
     * Calculates the minimum value.
     *
     * @return float
     */
    private function min(): float
    {
        return min($this->values);
    }

    /**
     * Calculates the maximum value.
     *
     * @return float
     */
    private function max(): float
    {
        return max($this->values);
    }

    /**
     * Calculates the sum of values.
     *
     * @return float
     */
    private function sum(): float
    {
        return array_sum($this->values);
    }

    /**
     * Calculates the mean (average) of values.
     *
     * @return float
     */
    private function mean(): float
    {
        return $this->sum() / count($this->values);
    }

    /**
     * Calculates the median of values.
     *
     * @return float
     */
    private function median(): float
    {
        $sorted = $this->values;
        sort($sorted);
        $count = count($sorted);
        $middle = intdiv($count, 2);

        if ($count % 2) {
            return (float) $sorted[$middle];
        }

        return ($sorted[$middle - 1] + $sorted[$middle]) / 2.0;
    }

    /**
     * Calculates the variance of values.
     *
     * @return float
     */
    private function variance(): float
    {
        $mean = $this->mean();
        $squaredDiffs = array_map(fn($value) => ($value - $mean) ** 2, $this->values);
        return array_sum($squaredDiffs) / count($this->values);
    }

    /**
     * Calculates the standard deviation of values.
     *
     * @return float
     */
    private function standardDeviation(): float
    {
        return sqrt($this->variance());
    }

    /**
     * Calculates the sum of squares of values.
     *
     * @return float
     */
    private function sumOfSquares(): float
    {
        return array_sum(array_map(fn($value) => $value ** 2, $this->values));
    }

    /**
     * Calculates the range of values.
     *
     * @return float
     */
    private function range(): float
    {
        return $this->max() - $this->min();
    }

    /**
     * Calculates the geometric mean of values.
     *
     * @return float
     * @throws RuntimeException
     */
    private function geometricMean(): float
    {
        foreach ($this->values as $value) {
            if ($value <= 0) {
                throw new RuntimeException("All values must be positive for Geometric Mean.");
            }
        }

        $product = array_product($this->values);
        return $product ** (1 / count($this->values));
    }

    /**
     * Calculates the harmonic mean of values.
     *
     * @return float
     * @throws RuntimeException
     */
    private function harmonicMean(): float
    {
        $reciprocals = [];
        foreach ($this->values as $value) {
            if ($value === 0) {
                throw new RuntimeException("Values must be non-zero for Harmonic Mean.");
            }
            $reciprocals[] = 1 / $value;
        }

        return count($this->values) / array_sum($reciprocals);
    }

    /**
     * Calculates the specified percentile of values.
     *
     * @return float
     * @throws InvalidArgumentException
     */
    private function percentile(): float
    {
        if (!($this->params['percentile'] ?? false)
            || $this->params['percentile'] < 0
            || $this->params['percentile'] > 100
        ) {
            throw new InvalidArgumentException("Invalid percentile value. Must be between 0 and 100.");
        }

        $sorted = $this->values;
        sort($sorted);
        $count = count($sorted);

        if ($count === 0) {
            throw new InvalidArgumentException("Cannot calculate percentile of an empty array.");
        }

        if ($count === 1) {
            return $sorted[0];
        }

        $index = ($this->params['percentile'] / 100) * ($count - 1);
        $floor = floor($index);
        $ceil = ceil($index);

        if ($floor == $ceil) {
            return $sorted[$floor];
        }

        $lower = $sorted[$floor];
        $upper = $sorted[$ceil];
        $fraction = $index - $floor;

        return $lower + ($upper - $lower) * $fraction;
    }
}
