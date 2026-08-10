# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CWMScriptureLinks is the **canonical scripture library** for the CWM (Christian Web Ministries) ecosystem. It provides Bible text lookup, caching, and rendering as a shared Joomla 5/6 library, plus a content plugin for automatic scripture linking in articles.

**PHP Requirement:** 8.3+
**Joomla:** 5.x / 6.x

The project packages two Joomla extensions together:

1. **`lib_cwmscripture`** — A Joomla library extension (included as a **git submodule** from [Joomla-Bible-Study/lib_cwmscripture](https://github.com/Joomla-Bible-Study/lib_cwmscripture)) that owns the Bible provider system, scripture parsing, and 3 shared database tables.
2. **`plg_content_scripturelinks`** — A content plugin that replaces `{scripture}` and `{bible}` tags in article content with scripture passages/links.

Both are distributed as a single Joomla package (`pkg_cwmscripture`).

**Submodule note:** After cloning, run `git submodule update --init --recursive` to fetch the library code.

**Proclaim dependency:** The Proclaim component (CWM Proclaim, `../Proclaim/`) includes this as a git submodule and depends on the library. Proclaim's install script installs both extensions and locks the library (`#__extensions.locked = 1`) to prevent disabling.

## Architecture

### Library (`libraries/lib_cwmscripture/` — git submodule)

The library lives in its own repository: [Joomla-Bible-Study/lib_cwmscripture](https://github.com/Joomla-Bible-Study/lib_cwmscripture). See that repo's CLAUDE.md for full architecture details.

Namespace: `CWM\Library\Scripture`

Key components: Bible provider system (Local, GetBible, API.Bible), scripture parsing helpers, Bible importer, scripture renderer, and 3 database tables (`#__bsms_bible_translations`, `#__bsms_bible_verses`, `#__bsms_scripture_cache`).

### Content Plugin (repo root)

Namespace: `CWM\Plugin\Content\ScriptureLinks`

- **`src/Extension/ScriptureLinks.php`** — Main plugin class. Subscribes to `onContentPrepare`.
  - **Tag mode** (default): Replaces `{scripture}John 3:16{/scripture}` and `{bible}John 3:16{/bible}` tags. Supports version override: `{scripture kjv}...{/scripture}`.
  - **Auto-detect mode**: Regex scan for untagged scripture references using the library's `ScriptureHelper::getAbbreviations()`.
  - **Display modes**: inline link (BibleGateway), tooltip, inline passage text, popup window.
  - Falls back to BibleGateway link if provider lookup fails.
- **`services/provider.php`** — Joomla DI service provider registration.

### Package Manifest (`pkg_cwmscripture.xml`)

Wraps both extensions. Library installs first (order in `<files>` matters).

### Provenance

This extension **succeeds** the Joomla 3 ScriptureLinks plugin by Mike Leeper
(`MLWebTechnologies/ScriptureLinks`, last commit 2015, JED listing last updated
June 2016). It does not derive from it: the legacy `Plugin/` tree was removed in
`a28a693`, and **no code from the original ships today** — the scripture engine,
the tag handling and the provider system were all written here.

That matters when describing the extension. It is a different codebase under a
different name, targeting Joomla 5/6, with its own update server. Credit for the
idea belongs upstream; the implementation does not come from there.

## Database Tables

All use `CREATE TABLE IF NOT EXISTS` for safe coexistence with existing Proclaim installs.

| Table | Purpose |
|---|---|
| `#__bsms_bible_translations` | Translation catalog (abbreviation, name, language, source, provider_id, installed flag) |
| `#__bsms_bible_verses` | Local verse text (translation, book 1-66, chapter, verse, text) |
| `#__bsms_scripture_cache` | Cached API responses with TTL (provider, translation, reference, text, expires_at) |

## Key Relationships

- **Proclaim** depends on this library via git submodule. Proclaim's `proclaim.script.php` installs and locks both extensions.
- **Study-specific logic** (getScripturesForStudy, saveScriptures, syncLegacyColumns) stays in Proclaim — only generic parsing/formatting is in this library.
- The logger category is `cwmscripture.bible` (log file: `cwmscripture.bible.php`).
- WebAsset names use prefix `lib_cwmscripture.*`.

## Build

Run `php build/build.php` to create the distributable `pkg_cwmscripture-{version}.zip`. The build script pulls the library from the submodule directory. Tests live in the `lib_cwmscripture` repo.

### Package scriptfile

`build/script.install.php` is the package's install script (wired via `<scriptfile>`
in `build/pkg_cwmscripture.xml` and `package.installer` in `cwm-build.config.json`).

Its one job is to blank `JPATH_LIBRARIES/cwmscripture/sql/uninstall.mysql.utf8.sql`
in `preflight()`, before the library child installs. lib_cwmscripture ≤ 1.1.4
declared `<uninstall><sql>` pointing at `DROP TABLE` statements, and Joomla's
`LibraryAdapter` uninstalls the installed library on every update — so those DROPs
ran on upgrades and wiped every locally downloaded Bible translation. The library
no longer declares uninstall SQL, but Joomla reads the *installed* manifest and SQL
file during that uninstall, so only a package-level preflight can save an existing
site on the transition upgrade.

Bump the nested `libraries/lib_cwmscripture` submodule to a version ≥ the fix, or
this package keeps reinstalling the destructive manifest on disk.
