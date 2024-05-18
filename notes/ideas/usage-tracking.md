# Usage tracking

 - Token usage tracking.
 - Using response headers to check the remaining tokens, rate limits, etc. 
 - Tracking costs.

## Usage data for streamed responses

Usage for streamed responses is available via events, but not via rawResponse().
Should we provide some general way to handle usage data across LLM drivers?
