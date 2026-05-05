# wp-blog-abilities

![License](https://img.shields.io/badge/license-MIT-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3)

A WordPress plugin that registers blog post *abilities* for use with the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter). AI agents like **Claude** can create, edit, list, and delete posts directly — no WP Admin needed.

---

## How It Works

```
Claude Code  →  mcp-adapter-execute-ability  →  WordPress REST API  →  Post saved
```

Once the plugin is active, you can tell Claude:

> *"Create a draft post titled 'My DevOps Tips' with the following content..."*

And Claude will post it directly to your WordPress site.

---

## Requirements

| Component | Version |
|---|---|
| WordPress | 6.8+ (6.9+ recommended) |
| PHP | 7.4+ |
| [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin | Installed & active |
| [Abilities API](https://github.com/WordPress/abilities-api) plugin | Only needed for WP 6.8 |

> On WordPress 6.9+, the Abilities API is built-in — no extra plugin required.

---

## Installation

This plugin requires **two plugins** to be installed on your WordPress site.

### Step 1 — Download both ZIPs from the [Releases page](https://github.com/afatyoo/wp-blog-abilities/releases/latest)

| File | Description |
|---|---|
| `mcp-adapter.zip` | WordPress MCP Adapter — bridges MCP protocol to WordPress REST API |
| `wp-blog-abilities.zip` | This plugin — registers blog post abilities for Claude |

### Step 2 — Install both plugins via WP Admin

For each ZIP file:
1. Go to **WP Admin → Plugins → Add New → Upload Plugin**
2. Upload the ZIP → **Install Now** → **Activate**

Install `mcp-adapter.zip` first, then `wp-blog-abilities.zip`.

### Option B: Clone via Git (VPS / SSH)

```bash
cd /var/www/html/wp-content/plugins
git clone https://github.com/WordPress/mcp-adapter.git
git clone https://github.com/afatyoo/wp-blog-abilities.git
```

Activate both plugins at **WP Admin → Plugins**.

---

## Claude Code Setup

### Step 1 — Create an Application Password in WordPress

1. Go to **WP Admin → Users → Profile**
2. Scroll to the **Application Passwords** section
3. Enter a name (e.g. `Claude MCP`) → click **Add New Application Password**
4. **Copy the generated password** — format: `xxxx xxxx xxxx xxxx xxxx xxxx`
   > The password is only shown once. Save it somewhere safe.

### Step 2 — Create `.mcp.json` in your Claude Code project

Create a `.mcp.json` file at the root of your Claude Code project:

```json
{
  "mcpServers": {
    "wordpress-blog": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yourdomain.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

Replace:
- `yourdomain.com` → your WordPress domain
- `your-wordpress-username` → your WordPress username
- `xxxx xxxx xxxx xxxx xxxx xxxx` → the Application Password you just created

### Step 3 — Restart Claude Code

> **Important:** Changes to `.mcp.json` require a **full restart** of Claude Code (quit the app, not just `/exit`). After reopening, the MCP server will connect automatically.

### Step 4 — Test the Connection

Ask Claude:

```
List all draft posts on my blog
```

If it works, Claude will return a list of posts from your WordPress site.

---

## Available Abilities

All abilities are registered with `meta.mcp.public = true` so they are automatically discovered by the MCP Adapter.

### `blog/create-post`
Create a new post.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `title` | string | ✅ | Post title |
| `content` | string | ✅ | Post content (HTML or plain text) |
| `status` | string | — | `publish`, `draft`, `pending` (default: `draft`) |
| `excerpt` | string | — | Short excerpt |
| `tags` | array of string | — | Tag names |
| `categories` | array of string | — | Category names (auto-created if they don't exist) |

**Permission:** `publish_posts`

---

### `blog/update-post`
Update an existing post.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to update |
| `title` | string | — | New title |
| `content` | string | — | New content |
| `status` | string | — | `publish`, `draft`, `pending`, `trash` |
| `excerpt` | string | — | New excerpt |

**Permission:** `edit_posts`

---

### `blog/list-posts`
Retrieve a list of posts with optional filters.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `status` | string | — | `publish`, `draft`, `pending`, `any` (default: `any`) |
| `numberposts` | integer | — | Number of posts (default: 10, max: 100) |
| `search` | string | — | Keyword search |

**Permission:** `edit_posts`

---

### `blog/delete-post`
Move a post to trash or permanently delete it.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to delete |
| `force` | boolean | — | `true` = permanent delete, `false` = move to trash (default: `false`) |

**Permission:** `delete_posts`

---

## Troubleshooting

**MCP not detected after updating `.mcp.json`**
→ Fully quit Claude Code (not just `/exit`), then reopen.

**Notice error on `wp_register_ability_category`**
→ Make sure category registration uses the `wp_abilities_api_categories_init` hook, not `wp_abilities_api_init`.

**Abilities not showing up on discover**
→ Verify the MCP Adapter plugin is active. Check that `meta.mcp.public = true` is set on each ability.

**Authentication failed**
→ Use an Application Password, not your regular login password. The format includes spaces: `xxxx xxxx xxxx xxxx xxxx xxxx`.

---

## Development Notes

### Hooks Used
- `wp_abilities_api_categories_init` — register the ability category (`content`)
- `wp_abilities_api_init` — register all abilities

> ⚠️ The category **must** be registered on `wp_abilities_api_categories_init`, not `wp_abilities_api_init`. Wrong hook causes a notice error even if the ability still registers.

### Security
- All input is sanitized: `sanitize_text_field()`, `wp_kses_post()` for HTML content
- Strict permission callbacks using standard WordPress capabilities
- No public endpoints — all access requires WordPress authentication (Application Password)

---

## License

[MIT License](https://github.com/afatyoo/wp-blog-abilities/blob/main/LICENSE)
