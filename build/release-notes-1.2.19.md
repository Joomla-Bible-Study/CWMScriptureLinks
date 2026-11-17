Updates the bundled CWM Scripture Library to 1.1.26, which fixes whole chapters and multi-chapter passages from GetBible.net.

These problems affected translations your site gets from GetBible.net, rather than ones stored on your own site:

- A scripture tag for a whole chapter, such as {scripture}Psalm 23{/scripture}, showed only its first verse.
- A passage crossing a chapter boundary, such as John 3:16-4:2, showed only its reference, with no text.
- Readings that span several chapters, the form CWM LivingWord's reading plans use, came back as a single verse.

Whole chapters and passages that cross chapters now come from GetBible's chapter service. Passages within one chapter are fetched exactly as before. Answers cached by the old lookup are no longer used, so the fix shows up straight away, not when your scripture cache expires.

The bundled library now also installs on Joomla 5.0 and 5.1; it used to require Joomla 5.2.

Scripture linking in articles otherwise works as before.

No action is needed after updating.
