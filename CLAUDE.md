# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CWMScriptureLinks is the **canonical scripture library** for the CWM (Christian Web Ministries) ecosystem. It provides Bible text lookup, caching, and rendering as a shared Joomla 5/6 library, plus a content plugin for automatic scripture linking in articles.

**PHP Requirement:** 8.3+
**Joomla:** 5.x / 6.x

The project packages two Joomla extensions together:

1. **`lib_cwmscripture`** — A Joomla library extension in its own repo ([lib_cwmscripture](https://github.com/Joomla-Bible-Study/lib_cwmscripture)), included here as a git submodule. Owns the Bible provider system, scripture parsing, form fields, and 3 shared database tables.
2. **`plg_content_scripturelinks`** — A content plugin that replaces `{scripture}` and `{bible}` tags in article content with scripture passages.

Both are distributed as a single Joomla package (`pkg_cwmscripture`).

**Consumers:** Proclaim, CWMLivingWord, and any CWM addon can reference the library repo independently. The plugin depends on the library but not vice versa.

## Architecture

### Library (`lib_cwmscripture/`)

Namespace: `CWM\Library\Scripture`

- **`src/Bible/`** — Provider system (extracted from Proclaim):
  - `BibleProviderInterface.php` — Contract: `getPassage()`, `getAvailableTranslations()`, `returnsText()`, `isOfflineCapable()`
  - `AbstractBibleProvider.php` — Base class: book name constants, Proclaim-to-standard book mapping, DB cache read/write, HTTP GET with retry/backoff/DDoS detection
  - `BiblePassageResult.php` — Value object for passage results
  - `BibleProviderFactory.php` — Factory with priority chain: local → API.Bible → GetBible → fallback. Accepts a `Registry` of params for provider config.
  - `Provider/LocalProvider.php` — Reads from `#__bsms_bible_verses` table
  - `Provider/GetBibleProvider.php` — GetBible.net v2 API
  - `Provider/ApiBibleProvider.php` — API.Bible (American Bible Society) with OSIS codes, FUMS tracking
- **`src/Helper/`** — Scripture parsing (generic, no Proclaim coupling):
  - `ScriptureHelper.php` — `ABBREVIATIONS` map (140+ entries), `parseReference()`, `formatReference()`, `getBookNumber()`, `getBookName()`, `getAllBooks()`
  - `ScriptureReference.php` — Value object: booknumber, chapter/verse begin/end, bibleVersion
- **`sql/`** — Owns 3 tables (keeping `#__bsms_` prefix for Proclaim compatibility):
  - `#__bsms_bible_translations` — Translation catalog
  - `#__bsms_bible_verses` — Local verse storage
  - `#__bsms_scripture_cache` — API response cache

### Content Plugin (`plg_content_scripturelinks/`)

Namespace: `CWM\Plugin\Content\ScriptureLinks`

- **`src/Extension/ScriptureLinks.php`** — Main plugin class. Subscribes to `onContentPrepare`.
  - **Tag mode** (default): Replaces `{scripture}John 3:16{/scripture}` and `{bible}John 3:16{/bible}` tags. Supports version override: `{scripture kjv}...{/scripture}`.
  - **Auto-detect mode**: Regex scan for untagged scripture references using the library's `ScriptureHelper::getAbbreviations()`.
  - **Display modes**: inline link (BibleGateway), tooltip, inline passage text, popup window.
  - Falls back to BibleGateway link if provider lookup fails.
- **`services/provider.php`** — Joomla DI service provider registration.

### Package Manifest (`pkg_cwmscripture.xml`)

Wraps both extensions. Library installs first (order in `<files>` matters).

### Legacy Code (`Plugin/`)

The old Joomla 3 plugin by Mike Leeper. Dead code — kept for reference only. Not used by the new system.

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

## No Build System Yet

Currently no build tools, package manager, or CI. The distributable package will be assembled manually or via a future build script in `build/`.
