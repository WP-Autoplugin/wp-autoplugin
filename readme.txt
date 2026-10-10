=== WP-Autoplugin ===
Contributors: balazspiller
Donate link: https://wp-autoplugin.com
Tags: ai, plugin generator, development, wordpress, automation
Requires at least: 6.6
Tested up to: 7.0
Stable tag: 2.0.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A local-first AI workspace for planning, coding, reviewing, and deploying WordPress plugin and theme changes.

== Description ==

WP-Autoplugin brings an agentic coding workflow into WordPress. It can explore installed code, prepare a Plan, generate or revise code, review one exact revision, and release the result as a new plugin, a fork, or an approved plugin or theme change.

WP-Autoplugin is free and bring-your-own-key. Connect OpenAI, Anthropic, Google Gemini, xAI, or a compatible API endpoint and choose the models used at each stage. An optional experimental ChatGPT Subscription connection can use the connected account's Codex allowance instead of an OpenAI API key.

Use WP-Autoplugin to:

* Create a complete WordPress plugin from a description.
* Diagnose, explain, fix, or extend an installed plugin or theme.
* Build a separate extension plugin around real actions and filters without editing the target.
* Continue Plan, Code, Review, and Explain conversations across reloads.
* Inspect staged changes, edit files, compare diffs, and restore earlier revisions.
* Package, install, fork, apply, or roll back supported revisions.

Version 2 is a complete rewrite for WordPress 6.6+ and PHP 8.0+. It replaces the separate v1 workflows with one durable Plan -> Code -> Review workspace.

= Workspace and revisions =

Supported models explore existing plugins and themes through bounded, read-only tools for metadata, file trees, line ranges, source search, and hook discovery. Child-theme work can inspect its installed parent separately as read-only context.

Focused operations can change exact source ranges instead of rewriting whole files. Generated results are validated and stored as immutable staged revisions; manual edits and restores create successors rather than overwriting history.

Projects retain their tabs, jobs, Plans, conversations, revisions, Reviews, usage, and releases across reloads. Closing a tab does not delete the project or cancel work. Vision-capable models also accept verified JPEG, PNG, and WebP prompt images.

AI Review binds a verdict, manual tests, and actionable P0-P3 findings to one revision. Findings can be discussed, dismissed, reopened, fixed, and verified; the Review becomes stale when code changes.

= Safe release choices =

AI output never writes directly to a target. Code must first pass validation and enter a staged revision. Depending on the project, an administrator can then:

* Build an exact-revision ZIP or install a new plugin inactive.
* Install an inactive plugin fork, then switch to it separately.
* Build replacement plugin/theme ZIPs or install an inactive theme copy.
* Directly modify a plugin or inactive theme after confirmation.
* Roll back the latest supported direct change if affected files have not drifted.

Release rechecks paths, baselines, fingerprints, headers, requirements, and destination identity. Direct changes store complete before-and-after records before writing and attempt immediate restoration on failure. Upstream updates remain enabled and may overwrite them.

Review is advisory but strongly recommended. Release without a current all-clear Review requires an explicit recorded override.

Theme releases never convert a parent theme into a child theme, activate or switch themes, or copy Customizer, Site Editor, global-style, template, or other database-held settings. Direct theme modification and rollback are blocked while the theme is active or is the parent of the active child theme.

= Models and instructions =

Settings provides Default, Planner, Coder, and Reviewer model roles with supported reasoning controls. Capabilities vary by model. Installed plugins may provide bounded root-level AGENTS.md guidance, while site-wide Custom instructions define persistent conventions. Safety and response contracts take precedence, followed by the current request, AGENTS.md, Custom instructions, and built-in defaults. Usage is recorded by provider, model, and job.

The v1 flow-specific admin pages, AJAX workers, and simple/complex mode switch have been removed. AI Response Language is not supported by v2 at this time.

== Installation ==

1. Upload and activate WP-Autoplugin.
2. Open WP-Autoplugin > Settings.
3. Configure at least one provider and select a default model, or connect the experimental ChatGPT Subscription provider.
4. Open WP-Autoplugin > Workspace and create a project.

== Frequently Asked Questions ==

= Do I need an API key? =

Configure a supported provider such as OpenAI, Anthropic, Google Gemini, or xAI, add a compatible custom endpoint, or connect the experimental ChatGPT Subscription provider. Provider charges or subscription limits may apply.

= Does AI write directly to installed plugins or themes? =

No. AI source is validated and staged first. Releases require administrator approval, appropriate capabilities, and conflict checks. Direct changes also store before-and-after files for supported rollback; ZIPs, installations, activations, and fork switches are recorded but are not file-rollback points.

= Is generated code production-ready? =

AI-generated code should be reviewed and tested on a staging site before production use. Consider a professional security review for critical applications.

= Can I use different models for different tasks? =

Yes. Settings provides a Default model plus optional Planner, Coder, and Reviewer overrides. Models with supported reasoning controls also expose an effort selector.

== Privacy and Data ==

WP-Autoplugin needs no WP-Autoplugin account. Credentials, job snapshots, revisions, Reviews, releases, and project history remain on the WordPress site.

AI providers receive only required request content, which may include instructions, relevant source, images, AGENTS.md, Custom instructions, conversation, and staged artifacts. Connection tests and model discovery send the configured credential.

ChatGPT OAuth tokens are encrypted at rest using WordPress salts. Secrets are not returned through v2 REST resources or stored in jobs, revisions, usage records, or browser bootstrap data.

Uninstall cleanup is enabled by default and can be disabled for later reinstall. It removes WP-Autoplugin data, credentials, history, and temporary packages, but never deletes or reverts created or modified plugins/themes.

== External Services ==

External AI services are used only after administrator configuration. Tests and discovery send credentials; AI jobs send the content described above. Provider charges, policies, and limits apply.

Custom instructions are sent with every future AI-producing job and are not intended for secrets.

**OpenAI**

* API-key connection tests and model requests use `https://api.openai.com`.
* Experimental ChatGPT authorization uses `https://auth.openai.com`; model discovery and AI use `https://chatgpt.com/backend-api/codex`.
* [OpenAI API service](https://platform.openai.com/)
* [OpenAI Services Agreement](https://openai.com/policies/services-agreement/)
* [ChatGPT Terms of Use](https://openai.com/policies/terms-of-use/)
* [Privacy Policy](https://openai.com/policies/privacy-policy/)

**Anthropic**

* Connection tests and model requests use `https://api.anthropic.com`.
* [Claude API service](https://console.anthropic.com/)
* [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms)
* [Privacy Policy](https://www.anthropic.com/legal/privacy)

**Google Generative Language API**

* Connection tests and model requests use `https://generativelanguage.googleapis.com`.
* [Gemini API service](https://ai.google.dev/gemini-api)
* [Gemini API Additional Terms of Service](https://ai.google.dev/gemini-api/terms)
* [Privacy Policy](https://policies.google.com/privacy)

**xAI**

* Connection tests and model requests use `https://api.x.ai`.
* [xAI API service](https://x.ai/api)
* [Enterprise Terms of Service](https://x.ai/legal/terms-of-service-enterprise)
* [Data Processing Addendum](https://x.ai/legal/data-processing-addendum)

**Custom OpenAI-compatible endpoints**

AI jobs send credentials and request content to the administrator-supplied HTTPS endpoint. Its operator's policies apply.

**GitHub updates**

Update and plugin-information checks query `https://api.github.com` and read headers/readme from `https://raw.githubusercontent.com`. The User-Agent contains the plugin version and site home URL. Installing an update downloads its verified commit archive from `https://github.com`.

* [WP-Autoplugin repository on GitHub](https://github.com/WP-Autoplugin/wp-autoplugin)
* [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
* [GitHub General Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)

== Changelog ==

= 2.0.2 =

* Added GPT-6 Astra, Sol, Luna, and GPT-6.1 Sol for OpenAI.
* Added Claude Opus, Sonnet, and Haiku 5.5 for Anthropic.
* Added matching model effort controls, output-token limits, and image-input support.

= 2.0.1 =

* Lowered the minimum PHP requirement from 8.2 to 8.0.

= 2.0.0 =

* Replaced v1 with the native durable Plan, Code, Review, and Explain workspace.
* Added bounded source agents, focused edits, image prompts, immutable revisions, conversations, usage, and finding workflows.
* Added revision-exact plugin/theme ZIPs, inactive installs/copies, plugin activation/fork switching, and conflict-safe direct modification/rollback.
* Added root AGENTS.md and site-wide Custom instructions with explicit precedence and immutable job snapshots.
* Added model roles, reasoning effort, custom endpoints, uninstall cleanup, and experimental ChatGPT Subscription support.

== License ==

This plugin is licensed under the GPLv2 or later.
