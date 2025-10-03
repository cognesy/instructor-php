## Consistency Based Self Adaptive Prompting (COSP)

COSP is a technique that aims to improve few-shot learning by selecting high-quality examples based on the consistency and confidence of model responses. This approach helps create more effective prompts by identifying examples that the model can process reliably.

### Overview

The COSP process involves two main stages:

1. Example Generation:
 - Generate multiple responses for potential examples
 - Run each example through the model multiple times
 - Collect responses and confidence scores

2. Example Selection
 - Select the best examples based on entropy and repetitiveness
 - Calculate entropy of responses to measure consistency
 - Evaluate repetitiveness to ensure reliability

### How COSP Works

#### Stage 1: Example Generation

For each potential example in your dataset:

- Generate multiple responses (typically 3-5)
- Calculate the entropy of these responses
- Measure the repetitiveness across responses

```python
from typing import List
from pydantic import BaseModel, Field
import instructor
from openai import OpenAI

class Response(BaseModel):
    content: str = Field(description="The model's response to the prompt")
    confidence: float = Field(description="Confidence score between 0 and 1")

client = instructor.from_openai(OpenAI())

def generate_responses(prompt: str, n: int = 3) -> List[Response]:
    responses = []
    for _ in range(n):
        response = client.chat.completions.create(
            model="gpt-4",
            messages=[{"role": "user", "content": prompt}],
            response_model=Response
        )
        responses.append(response)
    return responses
```

#### Stage 2: Example Selection

Calculate metrics for each example:

- Entropy: Measure response variability
- Repetitiveness: Check response consistency

```python
import numpy as np
from scipy.stats import entropy

def calculate_metrics(responses: List[Response]) -> tuple[float, float]:
    # Calculate entropy
    confidences = [r.confidence for r in responses]
    entropy_score = entropy(confidences)

    # Calculate repetitiveness
    unique_responses = len(set(r.content for r in responses))
    repetitiveness = 1 - (unique_responses / len(responses))

    return entropy_score, repetitiveness
```

## Implementation Example

Here's a complete example of COSP implementation:

```python
from typing import List, Tuple
from pydantic import BaseModel, Field
import instructor
from openai import OpenAI
import numpy as np
from scipy.stats import entropy

class Example(BaseModel):
    text: str
    score: float = Field(description="Combined quality score")
    entropy: float = Field(description="Entropy of responses")
    repetitiveness: float = Field(description="Repetitiveness of responses")

class COSPSelector:
    def __init__(self, client: OpenAI, n_samples: int = 3):
        self.client = instructor.from_openai(client)
        self.n_samples = n_samples

    def generate_responses(self, prompt: str) -> List[Response]:
        return [
            self.client.chat.completions.create(
                model="gpt-4",
                messages=[{"role": "user", "content": prompt}],
                response_model=Response
            )
            for _ in range(self.n_samples)
        ]

    def calculate_metrics(self, responses: List[Response]) -> Tuple[float, float]:
        confidences = [r.confidence for r in responses]
        entropy_score = entropy(confidences)

        unique_responses = len(set(r.content for r in responses))
        repetitiveness = 1 - (unique_responses / len(responses))

        return entropy_score, repetitiveness

    def select_examples(self, candidates: List[str], k: int) -> List[Example]:
        examples = []

        for text in candidates:
            responses = self.generate_responses(text)
            entropy_score, repetitiveness = self.calculate_metrics(responses)

            # Combined score (lower is better)
            score = entropy_score - repetitiveness

            examples.append(Example(
                text=text,
                score=score,
                entropy=entropy_score,
                repetitiveness=repetitiveness
            ))

        # Sort by score (lower is better) and select top k
        return sorted(examples, key=lambda x: x.score)[:k]
```

### Usage Example

```python
# Initialize COSP selector
client = OpenAI()
selector = COSPSelector(client)

# Candidate examples
candidates = [
    "The quick brown fox jumps over the lazy dog",
    "Machine learning is a subset of artificial intelligence",
    "Python is a high-level programming language",
    # ... more examples
]

# Select best examples
best_examples = selector.select_examples(candidates, k=3)

# Use selected examples in your prompt
selected_texts = [ex.text for ex in best_examples]
prompt = f"""Use these examples to guide your response:

Examples:
{chr(10).join(f'- {text}' for text in selected_texts)}

Now, please respond to: [your query here]
"""
```

### Benefits of COSP

- Improved Consistency: By selecting examples with low entropy and high repetitiveness
- Better Performance: More reliable few-shot learning
- Automated Selection: No manual example curation needed
- Quality Metrics: Quantifiable measure of example quality

### Limitations

- Computational Cost: Requires multiple API calls per example
- Time Overhead: Selection process can be slow for large candidate sets
- Model Dependency: Performance may vary across different models

### Related Techniques

- Universal Self Prompting (USP)
- Chain of Thought Prompting
- Self-Consistency

### References

1) Original COSP Paper (https://arxiv.org/abs/2305.14121)
2) Related Work: Self-Consistency Improves Chain of Thought Reasoning in Language Models (https://arxiv.org/abs/2203.11171)
