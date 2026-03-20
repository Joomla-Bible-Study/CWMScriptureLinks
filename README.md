# CWM Scripture Links

Content plugin for automatic scripture linking in Joomla 5/6 articles.

## What it does

- **Content Plugin (`plg_content_scripturelinks`)** — Replaces `{scripture}` and `{bible}` tags in Joomla articles with scripture passages or links
- **Library (`lib_cwmscripture`)** — Included as a [git submodule](https://github.com/Joomla-Bible-Study/lib_cwmscripture) providing Bible text lookup, caching, and rendering

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
git clone --recurse-submodules git@github.com:Joomla-Bible-Study/CWMScriptureLinks.git
php build/build.php    # Build installable package
```

## License

GNU General Public License v2 or later
