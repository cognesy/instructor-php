# Tool Use

Naive way to use tools is to fully rely on tool calling APIs - you provide the list of tools with signatures and model returns selected tool with arguments extracted based on the processed content. This is OK in case the toolset is small.

But when the toolset consists of dozens / hundreds of tools, it becomes impractical:

 - Model has to process a very large tool definition list with their arguments
 - It costs tokens and time to process large specification of toolset
 - It may lead to suboptimal inference - wrong tool selection
 - It reduces steerability - the choice is fully dictated by the model, developer has very little control over it (limited to prompts, tool naming and descriptions)
 - It's hard to improve selection without retraining the model
 - It is hard to monitor and debug (very large, complex API calls)

Better alternative it to make tool selection a separate step before arguments extraction, followed by usual tool use sequence (i.e. feeding the results of tool use to the model).

In naive cases it slows down the processing, as we need an additional API call to select the tool before extracting arguments for it, etc. But this is a small price to pay for the benefits:

 - modularized & more controllable tool selection process 
 - we can improve / customize tool selection without retraining the model
 - we can handle arbitrarily large toolsets - e.g. via multistep tool selection
 - it's easier to monitor and debug

Providing the model with a list of available tools and getting tool selected with extracted arguments may still be one of the available strategies, but it should not be the only one. Question: is it worth the effort, does the increased code complexity justify the benefit of having access to such approach?
