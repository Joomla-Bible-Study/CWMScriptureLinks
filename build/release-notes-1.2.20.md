**Security fix: please update as soon as you can.**

The translation tools in this plugin's settings, and on the Scripture tab of CWM Proclaim's Admin Center, could be used by anyone visiting your site, without logging in. A visitor could delete your downloaded Bible translations (all except KJV and WEB), or keep starting large downloads. These tools now work only in your site's administrator, for users who can manage and edit plugins; Super Users can. Details are in [GHSA-g2v4-h5q3-xcwh](https://github.com/Joomla-Bible-Study/CWMScriptureLinks/security/advisories/GHSA-g2v4-h5q3-xcwh).

Scripture tooltips now work on sites without CWM Proclaim. In tooltip display mode, every reference showed "Passage unavailable" unless Proclaim was installed and enabled. The plugin now answers its own tooltips.

A tooltip now shows the translation its tag names. A tooltip tag with version="web" showed your default translation in its popup, not the World English Bible. This happened with or without Proclaim.

Other changes to tooltips:

- On sites with Proclaim, tooltips in articles are now answered by this plugin, not by Proclaim. Proclaim's own pages work as before.
- A tooltip shows only translations your site offers: your default, or one in your list of translations. For any other translation, the tooltip says the passage is unavailable.

No action is needed after updating. If you turned off the "Content - ScriptureLinks" plugin as a precaution, you can turn it back on.
