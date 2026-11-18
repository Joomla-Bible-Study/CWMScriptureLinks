# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CWMScriptureLinks is the **canonical scripture library** for the CWM (Christian Web Ministries) ecosystem. It provides Bible text lookup, caching, and rendering as a shared Joomla 5/6 library, plus a content plugin for automatic scripture linking in articles.

**PHP Requirement:** 8.4+ for the shipped code. Development and CI run on PHP 8.5, so don't use 8.5-only syntax or functions such as the pipe operator (`|>`), `clone()` with properties, or `array_first()` / `array_last()`.
**Joomla:** 5.x / 6.x

The project packages four Joomla extensions together:

1. **`lib_cwmscripture`** — A Joomla library extension (included as a **git submodule** from [Joomla-Bible-Study/lib_cwmscripture](https://github.com/Joomla-Bible-Study/lib_cwmscripture)) that owns the Bible provider system, scripture parsing, and 3 shared database tables.
2. **`plg_content_scripturelinks`** (repo root) — A content plugin that replaces `{scripture}` and `{bible}` tags in article content with scripture passages, tooltips or popups, and answers the tooltip's AJAX requests.
3. **`plg_task_cwmscripture`** — A task plugin that runs the library's deferred background jobs, such as downloading the core Bible translations scheduled during install.
4. **`plg_system_cwmscripture`** — A system plugin that protects downloaded Bible translations from the uninstall SQL that library versions up to 1.1.4 declared, which Joomla ran on every update (see "Package scriptfile" below).

All four are distributed as a single Joomla package (`pkg_cwmscripture`).

**Submodule note:** After cloning, run `git submodule update --init --recursive` to fetch the library code.

**Proclaim dependency:** The Proclaim component (CWM Proclaim, `../Proclaim/`) includes this as a git submodule and depends on the library. Proclaim's install script installs both extensions and locks the library (`#__extensions.locked = 1`) to prevent disabling.

## Architecture

### Library (`libraries/lib_cwmscripture/` — git submodule)

The library lives in its own repository: [Joomla-Bible-Study/lib_cwmscripture](https://github.com/Joomla-Bible-Study/lib_cwmscripture). See that repo's CLAUDE.md for full architecture details.

Namespace: `CWM\Library\Scripture`

Key components: Bible provider system (Local, GetBible, API.Bible), scripture parsing helpers, Bible importer, scripture renderer, and 3 database tables (`#__bsms_bible_translations`, `#__bsms_bible_verses`, `#__bsms_scripture_cache`).

### Content Plugin (repo root)

Namespace: `CWM\Plugin\Content\ScriptureLinks`

- **`src/Extension/ScriptureLinks.php`** — Main plugin class. Subscribes to `onContentPrepare` and `onAjaxScripturelinks`.
  - **Tag mode** (default): Replaces `{scripture}John 3:16{/scripture}` and `{bible}John 3:16{/bible}` tags. A tag can override the version and the display mode: `{scripture kjv}...{/scripture}` or `{scripture version="web" display="tooltip"}...{/scripture}`.
  - **Auto-detect mode**: Regex scan for untagged scripture references using the library's `ScriptureHelper::getAbbreviations()`.
  - **Display modes**: tooltip, inline passage text, popup window.
  - If no provider returns text, the reference renders as plain text (`<span class="scripture-ref scripture-unavailable">`). Nothing links to an external Bible site.
  - **AJAX** goes through `com_ajax` (`plugin=scripturelinks&group=content`) and is dispatched on `action`, never `task`.
    - `getPassage` answers the scripture tooltip. Site visitors call it, so it is public and read-only, and takes no token. The reference must parse. The version must be the plugin's default or one in the translation catalog.
    - The other actions manage the site's Bible translations, for the translations manager in Proclaim's Admin Center and in this plugin's settings.
      - Before any of them runs, the dispatcher requires the administrator application and a user who can open and edit the plugin's settings: `core.manage` and `core.edit` on `com_plugins`. `core.edit` alone would admit Joomla's default Manager group, which inherits it site-wide. See `mayManageTranslations()`.
      - They also take a CSRF token, but a token is no permission check: Joomla gives one to every visitor. New actions are refused by default unless they're `getPassage`.
    - `tests/unit/Content/AjaxDispatcherContractTest.php` checks that every action resolves to a handler and that every URL the plugin registers names an action it handles.
    - `tests/unit/Content/AjaxAccessTest.php` sends every action, read from the source, through the real dispatcher. It checks that site visitors (even with the page's token), site users and administrators without the right are refused, that permitted administrators get through to the token check, and that `getPassage` stays public.
- **`services/provider.php`** — Joomla DI service provider registration.

### Package Manifest (`build/pkg_cwmscripture.xml`)

Wraps all four extensions. The library installs first (order in `<files>` matters).

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
- **CWM LivingWord** bundles `pkg_cwmscripture` and installs it on sites that may not have Proclaim. Those sites are why `plg_system_cwmscripture` exists, and why the plugin answers its own tooltips instead of relying on Proclaim's endpoint.
- **Study-specific logic** (getScripturesForStudy, saveScriptures, syncLegacyColumns) stays in Proclaim — only generic parsing/formatting is in this library.
- The logger category is `cwmscripture.bible` (log file: `cwmscripture.bible.php`).
- WebAsset names use prefix `lib_cwmscripture.*`.

## Build

Run `composer build` to create the distributable `pkg_cwmscripture-{version}.zip`. It runs `cwm-package` from [cwm-build-tools](https://github.com/Joomla-Bible-Study/cwm-build-tools), configured in `cwm-build.config.json`. The library is built from the `libraries/lib_cwmscripture` submodule, and the three plugins from this repo.

The library's tests live in the `lib_cwmscripture` repo. This repo's PHPUnit suite (`tests/unit/`) covers the three plugins: run `composer test`, or `composer check` for the syntax check, php-cs-fixer and the tests together.

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
