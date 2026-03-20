# CWM Scripture Library

Shared Bible provider library and content plugin for Joomla 5/6.

## What it does

- **Library (`lib_cwmscripture`)** — Bible text lookup via multiple providers (GetBible.net, API.Bible, local DB), scripture reference parsing, caching, and rendering
- **Content Plugin (`plg_content_scripturelinks`)** — Replaces `{scripture}` and `{bible}` tags in Joomla articles with scripture passages or links

## Requirements

- PHP 8.3+
- Joomla 5.x or 6.x

## Installation

### Standalone
Download `pkg_cwmscripture-x.x.x.zip` from [Releases](https://github.com/Joomla-Bible-Study/CWMScriptureLinks/releases) and install via Joomla Extension Manager.

### With Proclaim
Included automatically when installing [Proclaim](https://github.com/Joomla-Bible-Study/Proclaim). The library is locked and cannot be disabled while Proclaim is installed.

## Usage

In any Joomla article:

```
{scripture}John 3:16{/scripture}
{scripture kjv}Genesis 1:1-3{/scripture}
{bible}Psalm 23{/bible}
```

Configure display mode (link, tooltip, inline passage, popup), default Bible version, and providers in the plugin settings.

## Development

```bash
composer install
composer test          # Run PHPUnit tests
php build/build.php    # Build installable package
```

## License

GNU General Public License v2 or later
