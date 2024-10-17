<?php

namespace Cognesy\Instructor\Extras\Evals\Enums;

enum ValueAggregationMethod : string
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
