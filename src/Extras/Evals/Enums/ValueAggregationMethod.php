<?php

namespace Cognesy\Instructor\Extras\Evals\Enums;

enum ValueAggregationMethod : string
{
    case Mean = 'mean';
    case WeightedMean = 'weighted_mean';
    case Sum = 'sum';
    case Max = 'max';
    case Min = 'min';
}
