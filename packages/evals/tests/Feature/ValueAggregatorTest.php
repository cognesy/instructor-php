<?php

use Cognesy\Evals\Enums\NumberAggregationMethod;
use Cognesy\Evals\Utils\NumberSeriesAggregator;

beforeEach(function () {
    $this->values = [1, 2, 3, 4, 5];
    $this->aggregator = new NumberSeriesAggregator($this->values);
});

test('constructor initializes with valid values', function () {
    expect($this->aggregator)->toBeInstanceOf(NumberSeriesAggregator::class);
});

test('constructor throws exception for empty values array', function () {
    expect(fn() => new NumberSeriesAggregator([]))->toThrow(InvalidArgumentException::class);
});

test('constructor throws exception for non-numeric values', function () {
    expect(fn() => new NumberSeriesAggregator([1, 2, 'a', 4, 5]))->toThrow(InvalidArgumentException::class);
});

test('setValues updates values correctly', function () {
    $newValues = [6, 7, 8, 9, 10];
    $result = $this->aggregator->withValues($newValues)->aggregate();
    expect($result)->toEqual(8); // Mean of new values
});

test('setMethod updates aggregation method', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Max);
    expect($this->aggregator->aggregate())->toEqual(5);
});

test('min calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Min);
    expect($this->aggregator->aggregate())->toEqual(1);
});

test('max calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Max);
    expect($this->aggregator->aggregate())->toEqual(5);
});

test('sum calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Sum);
    expect($this->aggregator->aggregate())->toEqual(15);
});

test('mean calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Mean);
    expect($this->aggregator->aggregate())->toEqual(3);
});

test('median calculation for odd number of values', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Median);
    expect($this->aggregator->aggregate())->toEqual(3);
});

test('median calculation for even number of values', function () {
    $this->aggregator->withValues([1, 2, 3, 4]);
    $this->aggregator->withMethod(NumberAggregationMethod::Median);
    expect($this->aggregator->aggregate())->toEqual(2.5);
});

test('variance calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Variance);
    expect($this->aggregator->aggregate())->toBeCloseTo(2, 2);
});

test('standard deviation calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::StandardDeviation);
    expect($this->aggregator->aggregate())->toBeCloseTo(1.4142, 4);
});

test('sum of squares calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::SumOfSquares);
    expect($this->aggregator->aggregate())->toEqual(55);
});

test('range calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::Range);
    expect($this->aggregator->aggregate())->toEqual(4);
});

test('geometric mean calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::GeometricMean);
    expect($this->aggregator->aggregate())->toBeCloseTo(2.6052, 4);
});

test('geometric mean throws exception for non-positive values', function () {
    $this->aggregator
        ->withValues([1, 2, 0, 4, 5])
        ->withMethod(NumberAggregationMethod::GeometricMean);
    expect(fn() => $this->aggregator->aggregate())->toThrow(RuntimeException::class);
});

test('harmonic mean calculation', function () {
    $this->aggregator->withMethod(NumberAggregationMethod::HarmonicMean);
    expect($this->aggregator->aggregate())->toBeCloseTo(2.1898, 4);
});

test('harmonic mean throws exception for zero values', function () {
    $this->aggregator
        ->withValues([1, 2, 0, 4, 5])
        ->withMethod(NumberAggregationMethod::HarmonicMean);
    expect(fn() => $this->aggregator->aggregate())->toThrow(RuntimeException::class);
});

test('percentile calculation', function () {
    $this->aggregator
        ->withMethod(NumberAggregationMethod::Percentile)
        ->withValues([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    expect($this->aggregator->aggregate())->toBeCloseTo(9.55, 4);
});

test('percentile calculation with custom percentile', function () {
    $aggregator = new NumberSeriesAggregator([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], ['percentile' => 75], NumberAggregationMethod::Percentile);
    expect($aggregator->aggregate())->toBe(7.75); // 75th percentile
});

test('percentile calculation throws exception for invalid percentile', function () {
    $aggregator = new NumberSeriesAggregator([1, 2, 3, 4, 5], ['percentile' => 101], NumberAggregationMethod::Percentile);
    expect(fn() => $aggregator->aggregate())->toThrow(InvalidArgumentException::class);
});
