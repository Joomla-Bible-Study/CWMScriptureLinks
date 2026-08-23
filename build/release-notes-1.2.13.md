Updates the bundled CWM Scripture Library to 1.1.20, which fixes scripture fields failing to load on Joomla 7.

On Joomla 7, the scripture reference fields stopped working: typing a book name no longer offered suggestions, the chapter and verse hints did not appear, and the Bible version selector lost its search filter. Browsers reported `Joomla is not defined` in the developer console. Joomla 5 and Joomla 6 were unaffected.

The library's JavaScript uses Joomla's own core script, but never told Joomla it needed that file loaded first. On Joomla 5 and 6 the core script happened to load first for other reasons, so the omission never showed. Joomla 7 does not load it in that order, so the scripture fields stopped before they could start.

The scripture linking behaviour in this package is otherwise unchanged.

No action is needed after updating. If you are on Joomla 7 and have been seeing empty or unresponsive scripture fields, they will work again once this update is installed and your browser cache is refreshed.
