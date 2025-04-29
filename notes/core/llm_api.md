# More flexibility in LLM API integration

## Document creation of custom client


## Full payload customization

Allow custom creation of LLM call payload from Request data.


## Support any client - e.g. Nuno Maduro's OpenAI client

 - Allow arbitrary client
   - Get JSON Schema from requested response model
   - Construction of LLM API request - user code
   - Deserialize returned JSON to requested model
   - Run validations and return errors (if any)
   - Retries
 
