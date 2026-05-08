# wp-blog-abilities

![License](https://img.shields.io/badge/license-MIT-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3)
![Abilities](https://img.shields.io/badge/abilities-20-green)

A WordPress plugin that registers blog post *abilities* for use with the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter). AI agents like **Claude** can create, edit, schedule, and manage posts directly — no WP Admin needed.

---

## How It Works

```
Claude Code  →  mcp-adapter-execute-ability  →  WordPress REST API  →  Post saved
```

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
2. Scroll to **Application Passwords** → enter a name → click **Add New Application Password**
3. **Copy the generated password** — format: `xxxx xxxx xxxx xxxx xxxx xxxx`

### Step 2 — Create `.mcp.json` in your Claude Code project

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

### Step 3 — Restart Claude Code

> **Important:** Changes to `.mcp.json` require a **full restart** of Claude Code (quit the app, not just `/exit`).

---

## Available Abilities (20)

| Ability | Description |
|---|---|
| `blog/create-post` | Create a new post with title, content, status, tags, and categories |
| `blog/update-post` | Update title, content, status, excerpt, tags, and categories of an existing post |
| `blog/get-post` | Get full content and metadata of a post by ID, including tags and categories |
| `blog/list-posts` | List posts with optional filters (status, keyword, count) |
| `blog/delete-post` | Move a post to trash or permanently delete it |
| `blog/schedule-post` | Schedule a post to publish automatically at a future date and time |
| `blog/duplicate-post` | Duplicate a post as a new draft, preserving content, tags, and categories |
| `blog/create-tag` | Create a new post tag |
| `blog/create-category` | Create a new post category, with optional parent for nested categories |
| `blog/list-tags` | List all tags with ID, name, slug, and post count |
| `blog/list-categories` | List all categories with ID, name, slug, count, and parent |
| `blog/upload-media` | Upload a media file to the Media Library by fetching from a public URL |
| `blog/set-featured-image` | Set the featured image of a post using a media attachment ID |
| `blog/list-comments` | List comments filtered by post, status, and count |
| `blog/update-comment` | Approve, hold, spam, or trash a comment |
| `blog/reply-comment` | Post a reply to an existing comment as the current user |
| `blog/update-tag` | Edit the name, slug, or description of an existing tag |
| `blog/update-category` | Edit the name, slug, description, or parent of an existing category |
| `blog/delete-tag` | Permanently delete a tag |
| `blog/delete-category` | Permanently delete a category (posts reassigned to default) |

> See [docs/abilities.md](docs/abilities.md) for full parameter reference.

---

## Troubleshooting

**MCP not detected after updating `.mcp.json`**
→ Fully quit Claude Code (not just `/exit`), then reopen.

**Abilities not showing up on discover**
→ Verify the MCP Adapter plugin is active. Check that `meta.mcp.public = true` is set on each ability.

**Authentication failed**
→ Use an Application Password, not your regular login password. Format includes spaces: `xxxx xxxx xxxx xxxx xxxx xxxx`.

---

## License

[MIT License](https://github.com/afatyoo/wp-blog-abilities/blob/main/LICENSE)
