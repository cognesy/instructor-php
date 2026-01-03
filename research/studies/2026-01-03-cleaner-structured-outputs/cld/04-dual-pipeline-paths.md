# P1: Dual Pipeline Paths in ResponseGenerator

## Problem Statement

`ResponseGenerator` maintains two separate processing pipelines:

1. **Legacy pipeline**: JSON string → deserialize → validate → transform
2. **Array-first pipeline**: extract → array → deserialize → validate → transform

This creates branching logic that's hard to follow and maintain.

## Evidence

### 1. Branching in `makeResponse()`

```php
public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result {
    // Fast-path: if a fully processed value is already present...
    if ($response->hasValue() && $responseModel->outputFormat() === null) {
        return Result::success($response->value());
    }

    // Use array-first pipeline when an extractor is available
    if ($this->extractor !== null) {
        return $this->makeArrayFirstResponse($response, $responseModel, $mode);
    }

    // Legacy pipeline: JSON string → deserialize → validate → transform
    $pipeline = $this->makeResponsePipeline($responseModel);
    $json = $response->findJsonData($mode)->toString();
    return $pipeline->executeWith(ProcessingState::with($json))->result();
}
```

### 2. Duplicated Pipeline Construction

```php
// Legacy pipeline
private function makeResponsePipeline(ResponseModel $responseModel) : Pipeline {
    return Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn($responseContent) => match(true) {...})  // Empty check
        ->through(fn($responseContent) => Result::try(...))   // JSON decode
        ->through(fn($data) => $this->responseDeserializer->deserialize(...))
        ->through(fn($responseObject) => $this->responseValidator->validate(...))
        ->through(fn($responseObject) => $this->responseTransformer->transform(...))
        // ...
}

// Array-first pipeline
private function makeArrayFirstPipeline(ResponseModel $responseModel): Pipeline {
    return Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn(array $data) => match(true) {...})  // Empty check
        // No JSON decode step (already array)
        ->through(fn(array $data) => $this->responseDeserializer->deserialize(...))
        ->through(fn($response) => match(true) {...})   // Conditional validation
        ->through(fn($response) => match(true) {...})   // Conditional transform
        // ...
}
```

### 3. Conditional Logic Scattered

The choice between pipelines depends on:
- `$response->hasValue()` - pre-processed value exists
- `$responseModel->outputFormat()` - output format configuration
- `$this->extractor !== null` - extractor availability

## Impact

- **Two code paths** to test and maintain
- **Subtle bugs** from pipeline differences
- **Cognitive load** - must understand when each path is taken
- **Inconsistent behavior** - array-first skips validation for arrays

## Root Cause

Array-first pipeline was added as an enhancement, but legacy path wasn't removed. The `extractor` presence acts as a runtime switch.

## Proposed Solution

### Unify on Array-First Pipeline

The array-first pipeline is more general:

```php
public function makeResponse(InferenceResponse $response, ResponseModel $responseModel, OutputMode $mode) : Result {
    // Fast-path for pre-processed values
    if ($response->hasValue() && $responseModel->outputFormat() === null) {
        return Result::success($response->value());
    }

    // ALWAYS use array-first pipeline
    $data = $this->extractToArray($response, $mode);
    if ($data->isFailure()) {
        return $data;
    }

    return $this->processingPipeline($responseModel)
        ->executeWith(ProcessingState::with($data->unwrap()))
        ->result();
}

private function extractToArray(InferenceResponse $response, OutputMode $mode): Result {
    // If extractor is configured, use it
    if ($this->extractor !== null) {
        return $this->extractor->extract($response, $mode);
    }

    // Default extraction: get JSON and decode
    $json = $response->findJsonData($mode)->toString();
    if ($json === '') {
        return Result::failure('No JSON found in response');
    }
    return Result::try(fn() => Json::decode($json));
}

private function processingPipeline(ResponseModel $responseModel): Pipeline {
    // Single pipeline definition
    $skipObjectProcessing = $responseModel->shouldReturnArray();

    return Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn(array $data) => match(true) {
            empty($data) => Result::failure('No data extracted'),
            default => Result::success($data)
        })
        ->through(fn(array $data) => $this->responseDeserializer->deserialize($data, $responseModel))
        ->when(!$skipObjectProcessing, fn($p) => $p
            ->through(fn($obj) => $this->responseValidator->validate($obj, $responseModel))
            ->through(fn($obj) => $this->responseTransformer->transform($obj, $responseModel))
        )
        ->tap(fn($response) => $this->events->dispatch(new ResponseConvertedToObject([...])))
        ->onFailure(fn($state) => $this->events->dispatch(new ResponseGenerationFailed([...])))
        ->finally(fn(CanCarryState $state) => match(true) {
            $state->isSuccess() => $state->result(),
            default => Result::failure(implode('; ', $this->extractErrors($state)))
        })
        ->create();
}
```

### Benefits

1. **Single pipeline** - One code path to understand
2. **Extractor as optional enhancement** - Default extraction when not configured
3. **Cleaner separation** - Extraction → Processing are distinct phases
4. **Easier testing** - Test one pipeline, not two

## File Changes

### Modify

```
Core/ResponseGenerator.php
- Remove makeResponsePipeline() (legacy)
- Remove makeArrayFirstPipeline()
- Add unified extractToArray()
- Add unified processingPipeline()
```

### Consider

```
ResponseIteratorFactory.php
- Always inject a default extractor (ResponseExtractor)
- Remove null check branching
```

## Migration Steps

1. Extract `extractToArray()` method that handles both cases
2. Create single `processingPipeline()` method
3. Update `makeResponse()` to use unified flow
4. Remove old pipeline methods
5. Update tests

## Risk Assessment

- **Medium risk** - Core processing logic
- **Good test coverage needed** - Both pipeline paths should have tests
- **Behavioral change** - Legacy path users may see different extraction behavior

## Estimated Effort

- Implementation: 4 hours
- Testing: 4 hours
- **Total: 8 hours**

## Success Metrics

- Remove `makeResponsePipeline()` method
- Remove `makeArrayFirstPipeline()` method
- Single processing path in `ResponseGenerator`
- All existing tests pass
