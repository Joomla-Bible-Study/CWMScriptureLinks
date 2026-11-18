# CWM Scripture Links

Content plugin for automatic scripture linking in Joomla 5/6 articles.

## What it does

The `pkg_cwmscripture` package installs four extensions:

- **Content Plugin (`plg_content_scripturelinks`)** — Replaces `{scripture}` and `{bible}` tags in Joomla articles with scripture passages, tooltips or popups
- **Library (`lib_cwmscripture`)** — Included as a [git submodule](https://github.com/Joomla-Bible-Study/lib_cwmscripture) providing Bible text lookup, caching, and rendering
- **Task Plugin (`plg_task_cwmscripture`)** — Runs the library's deferred background jobs, such as downloading the core Bible translations after install
- **System Plugin (`plg_system_cwmscripture`)** — Protects downloaded Bible translations during library updates. Keep it enabled.

## Requirements

- PHP 8.3+
- Joomla 5.x or 6.x

## Installation

### Standalone
Download `pkg_cwmscripture-x.x.x.zip` from [Releases](https://github.com/Joomla-Bible-Study/CWMScriptureLinks/releases) and install via Joomla Extension Manager.

### With Proclaim
Included automatically when installing [Proclaim](https://github.com/Joomla-Bible-Study/Proclaim). The library is locked and cannot be disabled while Proclaim is installed.

### With CWM LivingWord
Bundled with [CWM LivingWord](https://github.com/Joomla-Bible-Study/CWMLivingWord), which installs it unless an equal or newer copy is already there.

## Usage

In any Joomla article:

```
{scripture}John 3:16{/scripture}
{scripture kjv}Genesis 1:1-3{/scripture}
{scripture version="web" display="tooltip"}Romans 8:28{/scripture}
{bible}Psalm 23{/bible}
```

Configure the display mode (tooltip, inline passage, popup), default Bible version, and providers in the plugin settings. A tag can override the version and the display mode.

## Development

```bash
git clone --recurse-submodules git@github.com:Joomla-Bible-Study/CWMScriptureLinks.git
cd CWMScriptureLinks
composer install
composer build    # Build the installable pkg_cwmscripture zip
composer test     # Run the plugin tests
```

## License

GNU General Public License v2 or later
