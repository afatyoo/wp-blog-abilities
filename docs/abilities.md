# Ability Reference

Full parameter reference for all 15 abilities in `postnova-for-mcp`.

---

## Posts

### `blog/create-post`
Create a new WordPress post.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `title` | string | ✅ | Post title |
| `content` | string | ✅ | Post content (HTML or plain text) |
| `status` | string | | `publish`, `draft`, `pending` (default: `draft`) |
| `excerpt` | string | | Short excerpt |
| `tags` | array of string | | Tag names (auto-created if they don't exist) |
| `categories` | array of string | | Category names (auto-created if they don't exist) |

**Returns:** `id`, `title`, `status`, `permalink`

**Permission:** `publish_posts`

---

### `blog/update-post`
Update an existing post, including its taxonomy.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to update |
| `title` | string | | New title |
| `content` | string | | New content |
| `status` | string | | `publish`, `draft`, `pending`, `trash` |
| `excerpt` | string | | New excerpt |
| `tags` | array of string | | Tag names to set (replaces existing tags) |
| `tag_ids` | array of integer | | Tag IDs to set directly (replaces existing tags) |
| `categories` | array of string | | Category names to set (replaces existing categories) |
| `category_ids` | array of integer | | Category IDs to set directly (replaces existing categories) |

**Returns:** `id`, `title`, `status`, `permalink`, `tags`, `categories`

**Permission:** `edit_posts`

---

### `blog/get-post`
Retrieve full content and metadata of a single post by ID.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to retrieve |

**Returns:** `id`, `title`, `content`, `excerpt`, `status`, `date`, `permalink`, `tags`, `tag_ids`, `categories`, `category_ids`

**Permission:** `edit_posts`

---

### `blog/list-posts`
List posts with optional filters.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `status` | string | | `publish`, `draft`, `pending`, `any` (default: `any`) |
| `numberposts` | integer | | Number of posts (default: 10, max: 100) |
| `search` | string | | Keyword search |

**Returns:** array of `{ id, title, status, date, permalink }`

**Permission:** `edit_posts`

---

### `blog/delete-post`
Move a post to trash or permanently delete it.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to delete |
| `force` | boolean | | `true` = permanent delete, `false` = trash (default: `false`) |

**Returns:** `success`, `message`

**Permission:** `delete_posts`

---

### `blog/schedule-post`
Schedule a post to publish automatically at a future date and time.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to schedule |
| `date` | string | ✅ | Publish date in `YYYY-MM-DD HH:MM:SS` format (site timezone) |

**Returns:** `id`, `title`, `status`, `scheduled_date`, `permalink`

**Notes:** Returns an error if the date is not in the future.

**Permission:** `edit_posts`

---

### `blog/duplicate-post`
Duplicate an existing post as a new draft, preserving content, tags, and categories.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Post ID to duplicate |
| `title` | string | | Title for the duplicate (defaults to original title + `" (Copy)"`) |

**Returns:** `id`, `title`, `status`, `permalink`

**Permission:** `publish_posts`

---

## Taxonomy

### `blog/create-tag`
Create a new post tag.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `name` | string | ✅ | Tag name |
| `slug` | string | | Custom slug (auto-generated if omitted) |
| `description` | string | | Tag description |

**Returns:** `id`, `name`, `slug`, `description`

**Notes:** Returns an error if a tag with the same name already exists.

**Permission:** `manage_categories`

---

### `blog/create-category`
Create a new post category.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `name` | string | ✅ | Category name |
| `slug` | string | | Custom slug (auto-generated if omitted) |
| `description` | string | | Category description |
| `parent` | integer | | Parent category ID for nested categories |

**Returns:** `id`, `name`, `slug`, `description`, `parent`

**Notes:** Returns an error if a category with the same name already exists.

**Permission:** `manage_categories`

---

### `blog/list-tags`
List all post tags.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `search` | string | | Filter tags by name |
| `hide_empty` | boolean | | Exclude tags with no posts (default: `false`) |

**Returns:** array of `{ id, name, slug, count }`

**Permission:** `edit_posts`

---

### `blog/list-categories`
List all post categories.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `search` | string | | Filter categories by name |
| `hide_empty` | boolean | | Exclude categories with no posts (default: `false`) |

**Returns:** array of `{ id, name, slug, count, parent }`

**Permission:** `edit_posts`

---

## Media

### `blog/upload-media`
Upload a media file to the WordPress Media Library by fetching it from a public URL.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `url` | string | ✅ | Publicly accessible URL of the file to upload |
| `title` | string | | Media title |
| `alt_text` | string | | Alt text for images |
| `post_id` | integer | | Attach to this post ID |

**Returns:** `id`, `url`, `filename`, `title`, `alt_text`

**Permission:** `upload_files`

---

### `blog/set-featured-image`
Set the featured image of a post using a media attachment ID.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `post_id` | integer | ✅ | Post ID |
| `attachment_id` | integer | ✅ | Media attachment ID to use as featured image |

**Returns:** `post_id`, `attachment_id`, `featured_image_url`

**Tip:** Use `blog/upload-media` first to get an `attachment_id`, then pass it here.

**Permission:** `edit_posts`

---

## Comments

### `blog/list-comments`
List comments, optionally filtered by post or status.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `post_id` | integer | | Filter by post ID |
| `status` | string | | `approve`, `hold`, `spam`, `trash`, `all` (default: `approve`) |
| `number` | integer | | Number of comments (default: 20, max: 100) |

**Returns:** array of `{ id, post_id, author, email, date, content, status }`

**Permission:** `edit_posts`

---

### `blog/update-comment`
Approve, hold, spam, or trash a comment.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Comment ID |
| `status` | string | ✅ | `approve`, `hold`, `spam`, `trash` |

**Returns:** `id`, `status`, `success`

**Permission:** `edit_posts`

---

### `blog/reply-comment`
Post a reply to an existing comment as the current logged-in user.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `comment_id` | integer | ✅ | Parent comment ID to reply to |
| `content` | string | ✅ | Reply content |

**Returns:** `id`, `content`, `date`, `status`

**Notes:** Reply is automatically approved and attributed to the authenticated WordPress user.

**Permission:** `edit_posts`

---

## Taxonomy Management

### `blog/update-tag`
Edit the name, slug, or description of an existing tag.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Tag ID to update |
| `name` | string | | New tag name |
| `slug` | string | | New tag slug |
| `description` | string | | New tag description |

**Returns:** `id`, `name`, `slug`, `description`

**Permission:** `manage_categories`

---

### `blog/update-category`
Edit the name, slug, description, or parent of an existing category.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Category ID to update |
| `name` | string | | New category name |
| `slug` | string | | New category slug |
| `description` | string | | New category description |
| `parent` | integer | | New parent category ID (`0` to remove parent) |

**Returns:** `id`, `name`, `slug`, `description`, `parent`

**Permission:** `manage_categories`

---

### `blog/delete-tag`
Permanently delete a tag. Posts using this tag will have it removed automatically.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Tag ID to delete |

**Returns:** `success`, `message`

**Permission:** `manage_categories`

---

### `blog/delete-category`
Permanently delete a category. Posts will be reassigned to the default category.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `id` | integer | ✅ | Category ID to delete |

**Returns:** `success`, `message`

**Notes:** Cannot delete the default category.

**Permission:** `manage_categories`
