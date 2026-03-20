--
-- CWM Scripture Library - Uninstall SQL
-- Only runs when the library is uninstalled standalone (not locked by Proclaim).
--

DROP TABLE IF EXISTS `#__bsms_scripture_cache`;
DROP TABLE IF EXISTS `#__bsms_bible_verses`;
DROP TABLE IF EXISTS `#__bsms_bible_translations`;
