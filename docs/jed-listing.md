# JED listing — CWM Scripture Links

Submission copy for the Joomla Extensions Directory, kept here so it is versioned
and reusable rather than retyped into a web form each time.

## Why a new listing rather than the existing one

JED already carries **ScriptureLinks** by Mike Leeper — version 3.0.0, last
updated June 2016, Joomla 3 only, flagged as not implementing the Joomla Update
System.

This is not that extension. The legacy `Plugin/` tree was removed in `a28a693`
and **no code from the original ships here**; the scripture engine, tag handling
and provider system were written for this project. It targets Joomla 5/6, ships
as a package, and updates through its own update server.

A separate listing is therefore the accurate filing, not a workaround: two
different programs, two entries. The lineage is credited below because it is
true and costs nothing, not because anything is owed.

---

## Listing fields

**Name:** CWM Scripture Links

**Short description**

> Turn Bible references in your Joomla articles into linked, looked-up passages —
> inline links, hover tooltips, full passage text or popups — backed by local,
> GetBible.net or API.Bible sources.

**Long description**

> CWM Scripture Links recognises `{scripture}` and `{bible}` tags in your content
> and renders the passage the way you choose. Auto-detect mode finds untagged
> references such as "Luke 7:36-38" on its own, so existing articles need no
> editing. A translation can be named per tag: `{scripture kjv}Romans 8:28{/scripture}`.
>
> Passages come from whichever provider you configure — a translation downloaded
> into your own database, the free GetBible.net API, or API.Bible. Results are
> cached, so a reference repeated across your site is fetched once. GDPR mode
> disables the external providers entirely and serves only locally stored text.
>
> The package installs the shared CWM Scripture Library, which is also used by
> Proclaim and Living Word, so sites running more than one CWM extension share a
> single scripture engine and one copy of any downloaded Bible.
>
> Succeeds the Joomla 3 ScriptureLinks plugin by Mike Leeper. Rewritten from
> scratch for Joomla 5 and 6; no code is shared with the original.

**Display modes:** inline link · hover tooltip · inline passage text · popup window

**Included extensions**

- `lib_cwmscripture` — shared scripture library (providers, parsing, rendering, translation management)
- `plg_content_scripturelinks` — processes the tags in article content
- `plg_task_cwmscripture` — downloads Bible translations in the background
- `plg_system_cwmscripture` — shared setup and compatibility checks

**Joomla versions:** 5.x, 6.x
**PHP:** 8.3 or newer
**Licence:** GNU GPL v2 or later
**Implements the Joomla Update System:** yes — update stream 4 on christianwebministries.org
**Price:** free

**URLs**

| Field | Value |
|---|---|
| Download | https://www.christianwebministries.org/downloads/latest-releases.html |
| Source | https://github.com/Joomla-Bible-Study/CWMScriptureLinks |
| Support | https://github.com/Joomla-Bible-Study/CWMScriptureLinks/issues |
| Homepage | https://www.christianwebministries.org |

> Check before submitting: the download URL should point at a page that shows the
> current version. As of writing, `/downloads/latest-releases.html` lists 1.1.5
> while the current package is 1.2.2.

---

## Courtesy note to the original author

Not required — nothing is retained from the original and GPL asks nothing further
— but worth sending as a matter of manners, and it leaves a public, dated record
that contact was attempted.

Post as an issue on `MLWebTechnologies/ScriptureLinks`:

> **Subject:** A Joomla 5/6 successor to ScriptureLinks
>
> Hello — thank you for ScriptureLinks. It solved a real problem for Joomla 3
> sites, and it is where the idea we build on came from.
>
> Christian Web Ministries now publishes **CWM Scripture Links**, a Joomla 5/6
> extension covering the same ground: scripture tags in article content, rendered
> as links, tooltips, inline text or popups. It is a fresh implementation and
> shares no code with this project, but the lineage is real and we credit it in
> our listing and documentation.
>
> Two things, both entirely at your discretion:
>
> 1. If you would like this repository's README or the JED listing to point at the
>    successor, we are glad for you to link it:
>    https://github.com/Joomla-Bible-Study/CWMScriptureLinks
> 2. If you would rather we describe the relationship differently, tell us and we
>    will.
>
> No reply needed — we are not asking for anything, only letting you know rather
> than letting you find out.
