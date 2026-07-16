# Visati — WordPress build

Isolated WordPress build for the Visati re-platform (Next.js → WordPress/Elementor).
**Nothing here touches the Next.js app at the repository root** (`src/`, `app/`, `package.json`, etc.).

Authoritative plan: [`../specs/002-wp-migration/plan.md`](../specs/002-wp-migration/plan.md).

## What lives here

Only the code **we author** is version-controlled. WordPress core, Elementor, EMCP, and
companion plugins are installed by Local/WP-CLI and are **not** in this repo.

```
wordpress/
├── README.md                          # this file
├── .gitignore
└── wp-content/
    ├── themes/
    │   └── visati/                    # child theme of Hello Elementor (global chrome + tokens)
    │       ├── style.css
    │       └── functions.php
    └── plugins/
        └── visati-core/               # custom plugin (CPTs, tables, REST, Stripe, R2, admin)
            └── visati-core.php
```

## Local development (Local by Flywheel)

1. Install **Local by Flywheel** (https://localwp.com). Create a site
   (PHP 8.2+, MySQL 8 / MariaDB 10.6). Note the **site path**
   (e.g. `C:\Users\K TECH\Local Sites\visati\app\public`) and admin user.
2. Symlink our theme + plugin into the Local site so edits are served live
   (run in an **Administrator** cmd, or enable Windows **Developer Mode** first so
   non-admin symlinks work):

   ```bat
   mklink /D "C:\Users\K TECH\Local Sites\visati\app\public\wp-content\themes\visati" "D:\GIAIC\Visati\wordpress\wp-content\themes\visati"
   mklink /D "C:\Users\K TECH\Local Sites\visati\app\public\wp-content\plugins\visati-core" "D:\GIAIC\Visati\wordpress\wp-content\plugins\visati-core"
   ```

   Fallback (no symlink): copy the two folders into the Local `wp-content` when you want
   to test, then copy changes back.
3. In WP admin: activate the **Hello Elementor** parent theme, then the **Visati** child
   theme, then the **Visati Core** plugin.

## EMCP (elementor-mcp) connection — WP-CLI stdio (primary, local)

Add to the **project root** `.mcp.json` (the EMCP server runs over Local's bundled WP-CLI;
no HTTP, no Application Password needed locally — the most reliable transport):

```json
{
  "mcpServers": {
    "emcp-tools": {
      "type": "stdio",
      "command": "wp",
      "args": [
        "mcp-adapter", "serve",
        "--server=emcp-tools-server",
        "--user=admin",
        "--path=C:/Users/K TECH/Local Sites/visati/app/public"
      ]
    }
  }
}
```

`wp` must resolve on PATH inside the Claude Code shell — use Local's "Open Site Shell"
environment, or point `command` at Local's bundled `wp-cli.phar` + PHP. (We confirm the
exact invocation after Local is installed.)

EMCP itself is installed as a plugin (release ZIP from
https://github.com/msrbuilds/elementor-mcp/releases) — see plan Phase 0.

## Deploy (later) — Hostinger subdomain

See plan **Phase 6**. Summary: push theme + plugin via git, migrate DB + media
(WP-CLI `search-replace` or a free migration plugin), restore Elementor content via EMCP,
then switch EMCP to the Node.js HTTPS proxy + Application Password.
