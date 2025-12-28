# Enterprise admin guide

This guide is for **ChatGPT Enterprise Admins** looking to set up Codex for their workspace. If you’re a developer, check out our [docs](https://developers.openai.com/codex).

## Enterprise-grade security and privacy

Codex automatically supports all ChatGPT Enterprise security features, including:

- No training on enterprise data
- Zero data retention for the CLI and IDE
- Residency and retention follow ChatGPT Enterprise policies
- Granular user access controls
- Data encryption at rest (AES 256) and in transit (TLS 1.2+)

To learn more, refer to our security [page](https://developers.openai.com/codex/security).


## Local vs. Cloud Setup
Codex operates in two environments: local and cloud. 
1. Local usage of Codex includes the CLI and IDE extension. The agent works locally in a sandbox on the developer's laptop.
2. Cloud usage of Codex includes Codex Cloud, iOS, Code Review, and tasks created by the [Slack integration](https://developers.openai.com/integrations/slack). The agent works remotely in a hosted cloud container containing your codebase.

Access to Codex local and cloud can be configured through separate permissions, governed by role-based access control (RBAC). Using RBAC, you can enable only local, cloud, or both for all users or just specific user groups.

## Codex Local Setup

### Enable Codex CLI and IDE extension in workspace settings

To enable your workspace members to leverage Codex locally, go to [Workspace Settings \> Settings and Permissions](https://chatgpt.com/admin/settings). Toggle on **Allow members to use Codex Local** for your organization. Note that this setting does not require the GitHub connector.

Once enabled, users can sign in to use the CLI and IDE extension with their ChatGPT account. If this toggle is off, users who attempt to use the CLI or IDE will see the following error: "403 - Unauthorized. Contact your ChatGPT administrator for access."

## Codex Cloud Setup

### Prerequisites

Codex Cloud requires **GitHub (cloud-hosted) repositories** for use. If your codebase is on-prem or not on GitHub, you can use the Codex SDK to build many of the same functionalities of Codex Cloud in your own on-prem compute.
<DocsTip>
  Note: To set up Codex as an admin, you must have GitHub access to the
  repositories commonly used across your organization. If you don’t have the
  necessary access, you’ll need to collaborate with someone on your Engineering
  team who does.
</DocsTip>

### Enable Codex Cloud in workspace settings
Start by turning on the ChatGPT Github Connector in the Codex section of [Workspace Settings \> Settings and Permissions](https://chatgpt.com/admin/settings).

To enable Codex Cloud for your workspace, toggle **Allow members to use Codex Cloud** ON.

Once enabled, users can access Codex directly from the left-hand navigation panel in ChatGPT.

<div class="max-w-lg mx-auto py-1">
  <img
    src="/images/codex/enterprise/cloud-toggle-config.png"
    alt="Codex Cloud toggle"
    class="block w-full mx-auto rounded-lg"
  />
</div>

<DocsTip>
  Note: After you toggle Codex to ON in your Enterprise workspace
  settings, it may take up to 10 mins for the Codex UI element to populate in
  ChatGPT.
</DocsTip>

### Configure the Codex Github Connector with an IP Allow List
To control the list of IPs that can connect to your ChatGPT GitHub connector, configure the following two IP ranges:

* [ChatGPT Egress IPs](https://openai.com/chatgpt-actions.json)
* [Codex Container Egress IPs](https://openai.com/chatgpt-agents.json)

These IP ranges may change in the future, so we recommend automatically checking them and updating your allow list based on the contents of these lists.

### Allow Members to Administer Codex
This toggle provides Codex users the ability to view Codex workspace analytics and manage environments (edit and delete).

Codex supports role based user access (see below for more details), therefore this toggle can be turned on for only a specific subset of users.

### Enable Codex Slack app to post answers on task completion
Codex integrates with Slack. When a user mentions @Codex in Slack, Codex kicks off a cloud task, gets context from the Slack thread, and responds with a link to a PR to review in the thread.

To allow the Slack app to post answers on task completion, toggle **Allow Codex Slack app to post answers on task completion** ON. When enabled, Codex posts its full answer back to Slack upon task completion. Otherwise, Codex posts only a link to the task.

To learn more, refer to our guide on [using Codex in Slack](/codex/integrations/slack).

### Enable Codex agent to access the internet
By default, Codex Cloud agents have no internet access during runtime to protect from security and safety risks like prompt injection.

As an admin, you can toggle on the ability for users to enable agent internet access in their environments. To enable, toggle **Allow Codex agent to access the internet** ON.

When this setting is on, users can whitelist access to common software dependencies add additional domains and trusted sites, and specify allowed HTTP methods.

### Enable code review with Codex Cloud
To allow Codex to do code reviews, go to [Settings → Code review](https://chatgpt.com/codex/settings/code-review).

Users can specify their personal preferences on whether they want Codex to reviews all of their pull requests. Users can also configure whether code review runs for all contributors to a repository.

There are two types of code reviews:

1. Auto-triggered code reviews when a user opens a PR for review
2. Reactive code reviews when a user mentions @Codex to look at issues. For example, “@Codex fix this CI failure” or “@Codex address that feedback”

## Role-based-user-access (RBAC)

We support role based user access for Codex. RBAC is a security and permissions model used to control access to systems or resources based on a user’s role assignments. 

To enable RBAC for Codex, navigate to Settings & Permissions → Custom Roles in [ChatGPT's admin page](https://chatgpt.com/admin/settings) and assign roles to Groups created in the Groups tab.

This simplifies permission management for Codex and improves security in your ChatGPT workspace. To learn more, refer to our help center [article](https://help.openai.com/en/articles/11750701-rbac).

## Set up your first Codex cloud environment

1. Navigate to Codex Cloud and click Get Started to begin onboarding.
2. Click Connect to GitHub to start installation of the ChatGPT GitHub Connector if you have not already connected to GitHub with ChatGPT.
   - Authorize the ChatGPT Connector for your user
   - Choose your installation target for the ChatGPT Connector (typically your main organization)
   - Authorize the repositories you’d like to enable to connect to Codex (may require a GitHub admin to approve).
3. Create your first environment by selecting the repository most relevant to your developers. Don’t worry, you can always add more later. Then click Create Environment
   - Add the emails of any environment collaborator to enable edit access for them
4. Codex will suggest starter tasks (e.g. writing tests, fixing bugs, exploring code) that can run concurrently; click Start Tasks button to kick them off.

You have now created your first environment. Individuals who connect to GitHub will now be able to create tasks using this environment and users who are authorized for the relevant repository will have the ability to push pull requests generated from their tasks.

### Environment management
As a ChatGPT workspace administrator, you have the ability to edit and delete Codex environments in your workspace.

### Connect additional GitHub repositories with Codex Cloud

1. Click the **Environments** button or open the **environment selector** and click **Manage Environments**.
2. Click the **Create Environment** button
3. **Select the environment** you’d like to connect to this environment
4. Give the environment an recognizable **name and description**.
5. Select the **environment visibility**
6. Click the **Create Environment** button

Note: Codex automatically optimizes your environment setup by reviewing your codebase. We recommend against performing advanced environment configuration until you observe specific performance issues. View our [docs](https://developers.openai.com/codex/cloud) to learn more.

### User Facing Setup Instructions

The following are instructions you can share with your end users on how to get started using Codex:

1. Navigate to [Codex](https://chatgpt.com/codex) in the left-hand panel of ChatGPT.
2. Click the Connect to GitHub button inside of the prompt composer if not already connected
   - Authenticate into GitHub
3. You are now able to use shared environments with your workspace or create your own environment.
4. Try getting started with a task using both Ask and Code mode, here is something you can try:
   - Ask: Can you find some bugs in my codebase?
   - Write code: Improve test coverage in my codebase following our existing test pattern.

## Tracking Codex Utilization
* For workspaces with rate limits, navigate to [Settings → Usage](https://chatgpt.com/codex/settings/usage) dashboard to view workspace metrics for Codex.
* For enterprise workspaces with flexible pricing, you can see credit usage in the ChatGPT workspace billing console.

## Codex Analytics
<div class="max-w-1xl mx-auto">
  <img
    src="/images/codex/enterprise/analytics.png"
    alt="Slack workflow diagram"
    class="block w-full mx-auto rounded-lg"
  />
</div>

### Dashboards
Codex's Analytics dashboard allows ChatGPT workspace administrators to track user adoption of different features. Codex offers the following analytics dashboards:
* Daily users by product (CLI, IDE, Cloud, Code Review)
* Daily code review users
* Daily code reviews
* Code reviews by priority level
* Daily code reviews by feedback sentiment
* Daily cloud tasks
* Daily cloud users
* Daily VS Code extension users
* Daily CLI users

### Data Export
Administrators can also export Codex analytics data in CSV or JSON format. Codex offers the following options for export:
* Code review users and reviews (Daily unique users and total reviews completed in Code Review)
* Code review findings and feedback (Daily counts of comments, reactions, replies, and priority-level findings)
* Cloud users and tasks (Daily unique cloud users and tasks completed)
* CLI and VS Code users (Daily unique users for the Codex CLI and VS Code extension)
* Sessions and messages per user (Daily session starts and user message counts for each Codex user across surfaces)