<?php declare(strict_types=1);

namespace Cognesy\Evals\Enums;

enum NumberAggregationMethod : string
{
    case Min = 'min';
    case Max = 'max';
    case Sum = 'sum';
    case Mean = 'mean';
    case Median = 'median';
    case Variance = 'variance';
    case StandardDeviation = 'standard_deviation';
    case SumOfSquares = 'weighted_sum_of_squares';
    case Range = 'range';
    case GeometricMean = 'geometric_mean';
    case HarmonicMean = 'harmonic_mean';
    case Percentile = 'percentile';
}
