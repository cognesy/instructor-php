<?php declare(strict_types=1);

namespace Cognesy\Evals\Utils;

use Cognesy\Evals\Enums\NumberAggregationMethod;
use InvalidArgumentException;
use RuntimeException;

class NumberSeriesAggregator
{
    private array $values;
    private array $params;
    private NumberAggregationMethod $method;

    /**
     * ValueAggregator constructor.
     *
     * @param array<float> $values The input values for aggregation.
     * @param array<string, mixed> $params Additional parameters for aggregation.
     * @param \Cognesy\Evals\Enums\NumberAggregationMethod $method Aggregation method.
     */
    public function __construct(
        array $values = [],
        array $params = [],
        NumberAggregationMethod $method = NumberAggregationMethod::Mean
    ) {
        if (empty($values)) {
            throw new InvalidArgumentException("Values array cannot be empty.");
        }
        $this->withValues($values);
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * Sets the values for aggregation.
     *
     * @param array $values
     * @return NumberSeriesAggregator
     * @throws InvalidArgumentException
     */
    public function withValues(array $values): self {
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
        return $this;
    }

    /**
     * Sets the aggregation method.
     *
     * @param NumberAggregationMethod $method
     * @return NumberSeriesAggregator
     */
    public function withMethod(NumberAggregationMethod $method): self {
        $this->method = $method;
        return $this;
    }

    /**
     * Performs the aggregation based on the selected method.
     *
     * @return float|int
     * @throws RuntimeException
     */
    public function aggregate() : float {
        return match ($this->method) {
            NumberAggregationMethod::Min => $this->min(),
            NumberAggregationMethod::Max => $this->max(),
            NumberAggregationMethod::Sum => $this->sum(),
            NumberAggregationMethod::Mean => $this->mean(),
            NumberAggregationMethod::Median => $this->median(),
            NumberAggregationMethod::Variance => $this->variance(),
            NumberAggregationMethod::StandardDeviation => $this->standardDeviation(),
            NumberAggregationMethod::SumOfSquares => $this->sumOfSquares(),
            NumberAggregationMethod::Range => $this->range(),
            NumberAggregationMethod::GeometricMean => $this->geometricMean(),
            NumberAggregationMethod::HarmonicMean => $this->harmonicMean(),
            NumberAggregationMethod::Percentile => $this->percentile(),
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
    private function median(): float|int
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
        // Set the default percentile to 95 if not already provided
        $this->params['percentile'] = $this->params['percentile'] ?? 95;

        // Validate percentile range
        if ($this->params['percentile'] < 0 || $this->params['percentile'] > 100) {
            throw new InvalidArgumentException("Invalid percentile value. Must be between 0 and 100.");
        }

        // Ensure values are sorted
        $sorted = $this->values;
        sort($sorted);
        $count = count($sorted);

        // Handle edge cases for empty or single-element arrays
        if ($count === 0) {
            throw new InvalidArgumentException("Cannot calculate percentile of an empty array.");
        }

        if ($count === 1) {
            return $sorted[0];
        }

        // Calculate the index based on the percentile
        $index = ($this->params['percentile'] / 100) * ($count - 1);
        $floorIndex = (int) floor($index);
        $ceilIndex = (int) ceil($index);

        // If the floor and ceil index are the same, return the exact value
        if ($floorIndex === $ceilIndex) {
            return $sorted[$floorIndex];
        }

        // Interpolate between the two surrounding values
        $lowerValue = $sorted[$floorIndex];
        $upperValue = $sorted[$ceilIndex];
        $fraction = $index - $floorIndex;

        // Return the interpolated value
        return $lowerValue + ($upperValue - $lowerValue) * $fraction;
    }
}
