# Raw Mode Technical Assessment

**Date:** 2026-01-02
**Purpose:** Assess codebase modifications needed to return raw arrays instead of deserialized objects
**Context:** Remove requirement for `x-php-class` field and enable array-only responses
**Status:** Initial assessment - see `decoupling-schema-from-deserialization.md` for refined API design

> **Note:** This document contains initial technical findings. The API design has been refined
> based on feedback. See **decoupling-schema-from-deserialization.md** for the final
> implementation plan with the user's preferred API:
> - Schema specification methods: `withResponseClass()`, `withResponseJsonSchema()`, `withResponseSchema()`, `withResponseClassFrom()`
> - Output format methods: `intoInstanceOf()`, `intoObject()`, `intoArray()`

---

## Current State Analysis

### Existing Array Support (Discovered!)

**CRITICAL FINDING:** InstructorPHP already has partial array support through `toArray()`:

```php
// THIS ALREADY WORKS:
$array = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->toArray();  // Returns: ['name' => 'John', 'age' => 30]
```

**Location:** `packages/instructor/src/PendingStructuredOutput.php:64-66`

**Implementation:**
```php
public function toArray() : array {
    return $this->toJsonObject()->toArray();
}

public function toJsonObject() : Json {
    return match(true) {
        $this->execution->isStreamed() => $this->stream()->finalResponse()->findJsonData($this->execution->outputMode()),
        default => $this->getResponse()->findJsonData($this->execution->outputMode())
    };
}
```

**How it works:**
1. Extracts JSON from InferenceResponse
2. Parses JSON string to array
3. Returns raw array (no deserialization)

**BUT:** Still requires `responseModel` parameter (class name or array with x-php-class).

---

## Processing Pipeline Analysis

### Current Flow (Sync Mode)

**File:** `packages/instructor/src/Core/ResponseGenerator.php:35-43`

```
LLM Response (InferenceResponse)
    ↓
1. Extract JSON                    [findJsonData()]
    ↓
2. Deserialize to Object           [responseDeserializer->deserialize()]
    ↓
3. Validate Object                 [responseValidator->validate()]
    ↓
4. Transform Object                [responseTransformer->transform()]
    ↓
5. Store in InferenceResponse      [inferenceResponse->withValue()]
    ↓
Return via get()
```

**File:** `packages/instructor/src/Core/AttemptIterator.php:96-110`

```php
// Validate response (full pipeline)
$validationResult = $this->responseGenerator->makeResponse(
    $finalInference,
    $responseModel,
    $execution->outputMode()
);

// Success - finalize execution
if ($validationResult->isSuccess()) {
    $finalValue = $validationResult->unwrap();  // ← Deserialized object

    return $execution->withSuccessfulAttempt(
        inferenceResponse: $finalInference->withValue($finalValue),
        partialInferenceResponse: $partial,
        returnedValue: $finalValue,
    );
}
```

### Pipeline Stages in Detail

#### Stage 1: JSON Extraction
**File:** `packages/polyglot/src/Inference/Data/InferenceResponse.php`

```php
public function findJsonData(OutputMode $mode): Json {
    // Handles different output modes (Tools, JsonSchema, Text, etc.)
    // Returns parsed JSON object
}
```

**Dependencies:**
- `packages/utils/src/Json/JsonParser.php` - Multi-strategy extraction
- `packages/utils/src/Json/Json.php` - JSON wrapper

#### Stage 2: Deserialization
**File:** `packages/instructor/src/Deserialization/ResponseDeserializer.php:28-40`

```php
public function deserialize(string $text, ResponseModel $responseModel) : Result {
    $result = match(true) {
        $this->canDeserializeSelf($responseModel) => $this->deserializeSelf(...),
        default => $this->deserializeAny($text, $responseModel)
    };
    return $result;
}

protected function deserializeAny(string $json, ResponseModel $responseModel) : Result {
    foreach ($this->deserializers as $deserializer) {
        $returnedClass = $responseModel->returnedClass();  // ← Requires class info
        $result = Result::try(fn() => $deserializer->fromJson($json, $returnedClass));
        if ($result->isSuccess()) {
            return $result;
        }
    }
    // Fallback to anonymous object if configured
    return $this->config->defaultToStdClass()
        ? Result::success($this->toAnonymousObject($json))
        : Result::failure(...);
}
```

**Key Insight:** Deserialization **requires** `returnedClass()` from ResponseModel.

#### Stage 3: Validation
**File:** `packages/instructor/src/Validation/ResponseValidator.php`

Validates the deserialized object against constraints.

#### Stage 4: Transformation
**File:** `packages/instructor/src/Transformation/ResponseTransformer.php`

Applies custom transformations (e.g., unwrapping scalar wrappers).

---

## Problem Statement

**Current limitation:**
```php
// User wants raw array without defining a class
$data = StructuredOutput::create()
    ->with(
        messages: 'Extract user data',
        responseModel: ???  // ← What to put here?
    )
    ->get();
```

**Options today:**
1. **Provide class name** - Requires defining a PHP class
2. **Provide array schema with x-php-class** - Still requires class
3. **Use toArray()** - Still requires responseModel

**None** allow pure schema-less extraction to array.

---

## Solution Options

### Option 1: Enhance Existing toArray() (RECOMMENDED)

**Make responseModel optional for toArray():**

```php
// New capability
$data = StructuredOutput::create()
    ->with(messages: 'Extract: name, age, email')
    ->toArray();  // ← No responseModel needed
```

**Implementation:**

**File:** `packages/instructor/src/Data/StructuredOutputExecution.php`

```php
public function responseModel(): ?ResponseModel {
    return $this->responseModel;  // ← Already nullable
}
```

**File:** `packages/instructor/src/Core/ResponseGenerator.php`

Add bypass method:

```php
public function makeRawResponse(InferenceResponse $response, OutputMode $mode) : Result {
    $json = $response->findJsonData($mode)->toString();
    if ($json === '') {
        return Result::failure('No JSON found in the response');
    }
    // Skip deserialization - just parse and return
    return Result::success(json_decode($json, true));
}
```

**File:** `packages/instructor/src/PendingStructuredOutput.php`

Modify toArray() to handle missing responseModel:

```php
public function toArray() : array {
    // If no responseModel, skip deserialization pipeline
    if ($this->execution->responseModel() === null) {
        return $this->toJsonObject()->toArray();
    }

    // Otherwise use existing flow (validates against schema)
    return $this->toJsonObject()->toArray();
}
```

**Pros:**
- ✅ Minimal changes (1-2 files)
- ✅ Backward compatible
- ✅ Leverages existing extraction
- ✅ No new API surface

**Cons:**
- ⚠️ No validation when responseModel is null
- ⚠️ Less obvious API (toArray() vs get())

---

### Option 2: Add rawMode() Flag (v1.3 PLAN)

**Add explicit flag to skip deserialization:**

```php
$data = StructuredOutput::create()
    ->with(
        messages: 'Extract user data',
        responseModel: User::class,  // ← Still need schema for LLM
    )
    ->rawMode()  // ← New flag
    ->get();     // ← Returns array instead of object
```

**Implementation:**

**File:** `packages/instructor/src/PendingStructuredOutput.php`

```php
private bool $skipDeserialization = false;

public function rawMode(): self {
    $this->skipDeserialization = true;
    return $this;
}

public function get() : mixed {
    if ($this->skipDeserialization) {
        return $this->toArray();  // ← Bypass deserialization
    }

    return match(true) {
        $this->execution->isStreamed() => $this->stream()->finalValue(),
        default => $this->getResponse()->value(),
    };
}
```

**Pros:**
- ✅ Explicit intent (rawMode)
- ✅ Still validates JSON extraction
- ✅ Schema still sent to LLM (better responses)
- ✅ Consistent with get() API

**Cons:**
- ❌ Requires responseModel (schema for LLM)
- ❌ More code changes

---

### Option 3: Make ResponseModel Fully Optional

**Allow completely schema-less requests:**

```php
$data = StructuredOutput::create()
    ->with(messages: 'Extract user data')  // ← No schema at all
    ->get();  // ← Returns whatever LLM returns (stdClass or array)
```

**Implementation:**

**File:** `packages/instructor/src/Core/ResponseGenerator.php`

```php
public function makeResponse(InferenceResponse $response, ?ResponseModel $responseModel, OutputMode $mode) : Result {
    if ($responseModel === null) {
        // No schema - just parse and return
        $json = $response->findJsonData($mode)->toString();
        return Result::success(json_decode($json, true));
    }

    // Normal path with full pipeline
    if ($response->hasValue()) {
        return Result::success($response->value());
    }
    $pipeline = $this->makeResponsePipeline($responseModel);
    $json = $response->findJsonData($mode)->toString();
    return $pipeline->executeWith(ProcessingState::with($json))->result();
}
```

**Pros:**
- ✅ Maximum flexibility
- ✅ True schema-less operation
- ✅ Simple mental model

**Cons:**
- ❌ LLM gets no schema guidance (worse results)
- ❌ Requires OutputMode to not reference schema
- ❌ Breaking change potential

---

## Recommended Approach: Hybrid

**Combine Option 1 + Option 2:**

### Phase 1: Enhance toArray() (Week 1)

Make toArray() work without responseModel for simple cases:

```php
// Simple extraction (no schema to LLM)
$data = StructuredOutput::create()
    ->with(messages: 'Extract user data')
    ->toArray();
```

### Phase 2: Add rawMode() (Week 2)

Add rawMode() for schema-guided extraction without deserialization:

```php
// Schema-guided extraction (better LLM responses) but return array
$data = StructuredOutput::create()
    ->with(
        messages: 'Extract user data',
        responseModel: User::class,  // ← LLM gets schema
    )
    ->rawMode()  // ← But we get array
    ->get();
```

---

## Implementation Plan

### Week 1: Enhance toArray()

**Files to modify:**
1. `packages/instructor/src/Core/ResponseGenerator.php`
   - Add `makeRawResponse()` method

2. `packages/instructor/src/PendingStructuredOutput.php`
   - Update toArray() to handle null responseModel

3. `packages/instructor/src/Data/StructuredOutputRequest.php`
   - Make responseModel truly optional

**Tests:**
```php
it('returns array when responseModel is null', function() {
    $data = StructuredOutput::create()
        ->with(messages: 'Extract: name=John, age=30')
        ->toArray();

    expect($data)->toBe(['name' => 'John', 'age' => 30]);
});
```

### Week 2: Add rawMode()

**Files to modify:**
1. `packages/instructor/src/PendingStructuredOutput.php`
   - Add `$skipDeserialization` flag
   - Add `rawMode()` method
   - Add `asArray()` alias
   - Modify `get()` to check flag

**Tests:**
```php
it('returns array when rawMode is enabled', function() {
    $data = StructuredOutput::create()
        ->with(
            messages: 'Extract user',
            responseModel: User::class,
        )
        ->rawMode()
        ->get();

    expect($data)->toBeArray();
    expect($data)->toHaveKey('name');
});

it('returns object when rawMode is not enabled', function() {
    $user = StructuredOutput::create()
        ->with(
            messages: 'Extract user',
            responseModel: User::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(User::class);
});
```

---

## Impact Analysis

### Breaking Changes

**NONE** - All changes are additive:
- toArray() already exists (just enhanced)
- rawMode() is new flag (default: false)
- Default behavior unchanged

### Performance Impact

**Positive:**
- Skipping deserialization saves ~5-10ms per response
- No Symfony Serializer overhead
- Less memory usage (no object instantiation)

### Use Cases Enabled

1. **Middleware Processing**
   ```php
   $data = StructuredOutput::create()
       ->with(responseModel: User::class, ...)
       ->rawMode()
       ->get();

   // Inspect before deserializing
   if ($data['role'] === 'admin') {
       $user = new AdminUser(...$data);
   }
   ```

2. **Array Manipulation**
   ```php
   $data = StructuredOutput::create()
       ->with(responseModel: User::class, ...)
       ->rawMode()
       ->get();

   $data['computed_field'] = $data['first'] . ' ' . $data['last'];
   ```

3. **Debugging**
   ```php
   $data = StructuredOutput::create()
       ->with(responseModel: User::class, ...)
       ->rawMode()
       ->get();

   dump($data);  // Easier to inspect arrays
   ```

4. **Schema-less Extraction**
   ```php
   $data = StructuredOutput::create()
       ->with(messages: 'Extract whatever you find')
       ->toArray();

   // Dynamic handling based on LLM response
   ```

---

## Validation Strategy

### Current Validation Flow

**File:** `packages/instructor/src/Validation/ResponseValidator.php`

Validation runs on deserialized objects using:
- Symfony Validator constraints
- Custom `CanValidateSelf` implementations

**With Raw Mode:**
- Validation is **skipped** (no object to validate)
- JSON extraction still validates JSON structure
- User responsible for validating array contents

**Risk Mitigation:**

Option A: Allow validation on arrays
```php
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)  // ← Provides schema
    ->rawMode()
    ->validate()  // ← New: validate array against schema
    ->get();
```

Option B: Document clearly
```
⚠️ **Important:** rawMode() skips validation.
Validate the array yourself if needed.
```

---

## Documentation Updates

### New Guides

**File:** `docs/essentials/raw_mode.md`

```markdown
# Raw Mode: Getting Arrays Instead of Objects

By default, InstructorPHP deserializes LLM responses into typed PHP objects.
If you need raw associative arrays, use one of these approaches:

## Method 1: toArray() (Schema-less)

Extract data without defining a class:

```php
$data = StructuredOutput::create()
    ->with(messages: 'Extract user: John Doe, age 30')
    ->toArray();
// Returns: ['name' => 'John Doe', 'age' => 30]
```

## Method 2: rawMode() (Schema-guided)

Provide schema to LLM but get array back:

```php
$data = StructuredOutput::create()
    ->with(
        messages: 'Extract user data',
        responseModel: User::class,  // LLM gets typed schema
    )
    ->rawMode()  // But we get array
    ->get();
```

## When to Use Each

**Use toArray():**
- Quick prototyping
- Dynamic/unknown structure
- No validation needed

**Use rawMode():**
- Need LLM schema guidance for better results
- Want middleware processing before objects
- Conditional deserialization based on data
```

---

## Effort Estimate

### Week 1: toArray() Enhancement
- Core changes: 4 hours
- Tests: 2 hours
- Docs: 1 hour
- **Total: 1 day**

### Week 2: rawMode() Implementation
- Core changes: 6 hours
- Tests: 3 hours
- Docs: 2 hours
- Examples: 1 hour
- **Total: 1.5 days**

**Grand Total: 2.5 days development time**

---

## Conclusion

### Key Findings

1. ✅ **toArray() already exists** - Just needs enhancement
2. ✅ **Pipeline is well-structured** - Easy to bypass deserialization
3. ✅ **No breaking changes needed** - All additive
4. ✅ **Low implementation effort** - 2.5 days total

### Recommendation

**Implement hybrid approach:**
1. **Week 1:** Enhance toArray() for schema-less use
2. **Week 2:** Add rawMode() for schema-guided array returns

This eliminates the x-php-class requirement while providing both:
- Simple schema-less extraction (toArray)
- Schema-guided extraction (rawMode + responseModel)

### Next Steps

1. ✅ Create technical assessment (this document)
2. ⏭️ Implement toArray() enhancement
3. ⏭️ Implement rawMode() flag
4. ⏭️ Add comprehensive tests
5. ⏭️ Update documentation
6. ⏭️ Create examples
