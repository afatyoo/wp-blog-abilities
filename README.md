# wp-blog-abilities

![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3)

Plugin WordPress yang mendaftarkan *abilities* untuk manajemen blog post melalui [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter). AI agent seperti **Claude** bisa membuat, mengedit, melihat daftar, dan menghapus post langsung — tanpa perlu buka WP-Admin.

---

## Demo Singkat

```
Claude Code  →  mcp-adapter-execute-ability  →  WordPress REST API  →  Post tersimpan
```

Setelah plugin ini aktif, kalian bisa ngomong ke Claude:

> *"Buatkan draft post berjudul 'Tips DevOps 2025' dengan konten berikut..."*

Dan Claude langsung posting ke WordPress kalian. _Boom._

---

## Persyaratan

| Komponen | Versi |
|---|---|
| WordPress | 6.8+ (6.9+ direkomendasikan) |
| PHP | 7.4+ |
| Plugin [MCP Adapter](https://github.com/WordPress/mcp-adapter) | Terinstall & aktif |
| Plugin [Abilities API](https://github.com/WordPress/abilities-api) | Hanya untuk WP 6.8 |

> Di WordPress 6.9+, Abilities API sudah built-in — tidak perlu plugin tambahan.

---

## Instalasi

### Opsi 1: Download ZIP (Shared Hosting / cPanel)

1. Klik tombol **Code → Download ZIP** di halaman repo ini
2. Login ke **WP Admin → Plugins → Add New → Upload Plugin**
3. Upload file ZIP → **Install Now** → **Activate**

### Opsi 2: Clone via Git (VPS / SSH)

```bash
cd /var/www/html/wp-content/plugins
git clone https://github.com/YOUR_USERNAME/wp-blog-abilities.git
```

Aktifkan di **WP Admin → Plugins → Blog Abilities for MCP → Activate**.

---

## Setup Claude Code

### Langkah 1 — Buat Application Password di WordPress

1. Login WP Admin → **Users → Profile**
2. Scroll ke bagian **Application Passwords**
3. Isi nama aplikasi (contoh: `Claude MCP`) → klik **Add New Application Password**
4. **Salin password yang muncul** — format: `xxxx xxxx xxxx xxxx xxxx xxxx`
   > Password hanya muncul sekali. Simpan baik-baik.

### Langkah 2 — Buat file `.mcp.json` di project Claude Code

Buat file `.mcp.json` di root folder project kalian:

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

Ganti:
- `yourdomain.com` → domain WordPress kalian
- `your-wordpress-username` → username WordPress kalian
- `xxxx xxxx xxxx xxxx xxxx xxxx` → Application Password yang baru dibuat

### Langkah 3 — Restart Claude Code

> **Penting:** Perubahan `.mcp.json` memerlukan **restart penuh** Claude Code (quit aplikasi, bukan sekadar `/exit`). Setelah dibuka kembali, MCP server akan otomatis terkoneksi.

### Langkah 4 — Test Koneksi

Di Claude Code, minta Claude untuk:

```
List semua draft post di blog saya
```

Kalau berhasil, Claude akan menampilkan daftar post dari WordPress kalian.

---

## Abilities yang Tersedia

Semua ability didaftarkan dengan `meta.mcp.public = true` sehingga otomatis terdeteksi MCP Adapter.

### `blog/create-post`
Membuat post baru.

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `title` | string | ✅ | Judul post |
| `content` | string | ✅ | Konten HTML atau plain text |
| `status` | string | — | `publish`, `draft`, `pending` (default: `draft`) |
| `excerpt` | string | — | Ringkasan singkat |
| `tags` | array of string | — | Tag names |
| `categories` | array of string | — | Category names (dibuat otomatis jika belum ada) |

**Permission:** `publish_posts`

---

### `blog/update-post`
Mengupdate post yang sudah ada.

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID post yang akan diupdate |
| `title` | string | — | Judul baru |
| `content` | string | — | Konten baru |
| `status` | string | — | `publish`, `draft`, `pending`, `trash` |
| `excerpt` | string | — | Excerpt baru |

**Permission:** `edit_posts`

---

### `blog/list-posts`
Mengambil daftar post dengan filter opsional.

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `status` | string | — | `publish`, `draft`, `pending`, `any` (default: `any`) |
| `numberposts` | integer | — | Jumlah post (default: 10, maks: 100) |
| `search` | string | — | Keyword pencarian |

**Permission:** `edit_posts`

---

### `blog/delete-post`
Memindahkan post ke trash atau menghapus permanen.

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `id` | integer | ✅ | ID post yang akan dihapus |
| `force` | boolean | — | `true` = hapus permanen, `false` = pindah ke trash (default: `false`) |

**Permission:** `delete_posts`

---

## Cara Kerja (Teknis)

```
Claude Code
    │
    ├─ mcp-adapter-discover-abilities  →  dapat daftar: blog/create-post, dll
    │
    ├─ mcp-adapter-execute-ability
    │       ability_name: "blog/create-post"
    │       parameters: { title, content, status, ... }
    │
    └─ WordPress REST API  →  post tersimpan di database
```

MCP Endpoint: `https://yourdomain.com/wp-json/mcp/mcp-adapter-default-server`

---

## Catatan Pengembangan

### Hook yang Digunakan
- `wp_abilities_api_categories_init` — registrasi kategori ability (`content`)
- `wp_abilities_api_init` — registrasi semua ability

> ⚠️ Kategori **harus** didaftarkan di `wp_abilities_api_categories_init`, bukan di `wp_abilities_api_init`. Salah hook → notice error (meski ability tetap terdaftar).

### Keamanan
- Semua input disanitasi: `sanitize_text_field()`, `wp_kses_post()` untuk konten HTML
- Permission callback ketat sesuai kapabilitas WordPress standar
- Tidak ada endpoint publik — semua melalui autentikasi WordPress (Application Password)

---

## Troubleshooting

**MCP tidak terdeteksi setelah update `.mcp.json`**
→ Quit Claude Code sepenuhnya (bukan `/exit`), buka ulang.

**Error: `wp_register_ability_category` notice**
→ Pastikan kategori didaftarkan di hook `wp_abilities_api_categories_init`, bukan `wp_abilities_api_init`.

**Abilities tidak muncul saat discover**
→ Cek apakah MCP Adapter plugin sudah aktif. Cek juga `meta.mcp.public = true` ada di setiap ability.

**Authentication failed**
→ Pastikan Application Password digunakan (bukan password login biasa). Format password: `xxxx xxxx xxxx xxxx xxxx xxxx` (dengan spasi).

---

## Lisensi

[GPL-2.0-or-later](LICENSE)
