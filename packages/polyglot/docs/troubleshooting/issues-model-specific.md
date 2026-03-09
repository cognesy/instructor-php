---
title: Model-Specific Issues
description: Capabilities vary by model even within the same provider.
---

Common examples:

- one model supports tools while another does not
- one model supports JSON schema while another only supports plain text or JSON object output
- streaming support differs by model

When debugging, reduce the request to plain text first, then add response shaping features back one by one.
