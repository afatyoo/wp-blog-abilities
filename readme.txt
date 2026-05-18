=== Blog Abilities for MCP ===
Contributors: afatyoo
Tags: mcp, claude, ai, blog, automation
Requires at least: 6.8
Tested up to: 6.8
Stable tag: 1.6.1
Requires PHP: 7.4
License: MIT
License URI: https://opensource.org/licenses/MIT

Registers blog post abilities for use with the WordPress MCP Adapter. AI agents like Claude can create, edit, schedule, and manage posts directly.

== Description ==

Blog Abilities for MCP registers blog post abilities for use with the WordPress MCP Adapter plugin. AI agents such as Claude can create, edit, schedule, and manage posts directly without needing WP Admin access.

Requires the MCP Adapter plugin (WordPress/mcp-adapter). On WordPress 6.9+, the Abilities API is built-in. On 6.8, install the Abilities API plugin separately.

== Installation ==

1. Install and activate the MCP Adapter plugin.
2. Upload wp-blog-abilities.zip via Plugins > Add New > Upload Plugin.
3. Activate the plugin.
4. Configure your MCP client to connect to your WordPress REST API endpoint.

== Changelog ==

= 1.6.1 =
* Fix: schedule-post now correctly retains future status instead of publishing immediately.

= 1.6.0 =
* Initial public release with 20 blog post abilities.
