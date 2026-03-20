--
-- CWM Scripture Library - Install SQL
-- These tables are shared between ScriptureLinks and Proclaim.
-- Uses CREATE TABLE IF NOT EXISTS for safe coexistence with existing Proclaim installs.
--

--
-- Table structure for table `#__bsms_bible_translations`
--

CREATE TABLE IF NOT EXISTS `#__bsms_bible_translations` (
    `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `abbreviation` VARCHAR(20)      NOT NULL COMMENT 'Short code e.g. kjv, web, nlt',
    `name`         VARCHAR(255)     NOT NULL COMMENT 'Full name e.g. King James Version',
    `language`     VARCHAR(10)      NOT NULL DEFAULT 'en' COMMENT 'ISO language code',
    `source`       VARCHAR(50)      NOT NULL DEFAULT 'getbible' COMMENT 'Origin: getbible, api_bible, manual',
    `provider_id`  VARCHAR(100)     DEFAULT NULL COMMENT 'Provider-specific Bible ID (e.g. api.bible UUID)',
    `installed`    TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 if verses are stored locally',
    `bundled`      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 if shipped with the component',
    `verse_count`  INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of verses stored locally',
    `estimated_size` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Estimated text size in bytes before download',
    `data_size`    BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Actual stored size in bytes',
    `downloaded_at` DATETIME NULL DEFAULT NULL COMMENT 'When the translation was last downloaded/refreshed',
    `copyright`    TEXT COMMENT 'Copyright notice for this translation',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_abbreviation` (`abbreviation`),
    KEY `idx_installed` (`installed`),
    KEY `idx_language` (`language`)
) ENGINE InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `#__bsms_bible_verses`
--

CREATE TABLE IF NOT EXISTS `#__bsms_bible_verses` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `translation` VARCHAR(20)     NOT NULL COMMENT 'FK to bible_translations.abbreviation',
    `book`        TINYINT UNSIGNED NOT NULL COMMENT 'Standard book number 1-66',
    `chapter`     SMALLINT UNSIGNED NOT NULL,
    `verse`       SMALLINT UNSIGNED NOT NULL,
    `text`        TEXT             NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_translation_book_chapter_verse` (`translation`, `book`, `chapter`, `verse`),
    KEY `idx_translation_book` (`translation`, `book`)
) ENGINE InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `#__bsms_scripture_cache`
--

CREATE TABLE IF NOT EXISTS `#__bsms_scripture_cache` (
    `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider`    VARCHAR(50)      NOT NULL COMMENT 'Provider name: getbible, api_bible, etc.',
    `translation` VARCHAR(20)      NOT NULL,
    `reference`   VARCHAR(255)     NOT NULL COMMENT 'Normalized reference string',
    `text`        MEDIUMTEXT       NOT NULL,
    `copyright`   TEXT COMMENT 'Copyright text returned by provider',
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`  DATETIME         NOT NULL COMMENT 'Cache expiry time',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_provider_translation_reference` (`provider`, `translation`, `reference`),
    KEY `idx_expires` (`expires_at`)
) ENGINE InnoDB
  DEFAULT CHARSET = utf8mb4
  DEFAULT COLLATE = utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Seed common translations (installed=0 means available for download, not yet local)
INSERT IGNORE INTO `#__bsms_bible_translations` (`abbreviation`, `name`, `language`, `source`, `installed`, `bundled`, `estimated_size`)
VALUES
    ('kjv', 'King James Version', 'en', 'getbible', 0, 1, 4000000),
    ('akjv', 'American King James Version', 'en', 'getbible', 0, 0, 4000000),
    ('web', 'World English Bible', 'en', 'getbible', 0, 1, 4300000),
    ('asv', 'American Standard Version', 'en', 'getbible', 0, 0, 4100000),
    ('ylt', 'Young''s Literal Translation', 'en', 'getbible', 0, 0, 4000000),
    ('basicenglish', 'Bible in Basic English', 'en', 'getbible', 0, 0, 3500000),
    ('douayrheims', 'Douay-Rheims Bible', 'en', 'getbible', 0, 0, 4200000),
    ('wb', 'Webster Bible', 'en', 'getbible', 0, 0, 4000000),
    ('darby', 'Darby Translation', 'en', 'getbible', 0, 0, 4000000),
    ('vulgate', 'Vulgata Clementina', 'la', 'getbible', 0, 0, 3800000),
    ('almeida', 'Almeida Atualizada', 'pt', 'getbible', 0, 0, 4000000),
    ('luther1545', 'Luther (1545)', 'de', 'getbible', 0, 0, 4200000),
    ('ls1910', 'Louis Segond 1910', 'fr', 'getbible', 0, 0, 4100000),
    ('synodal', 'Synodal Translation', 'ru', 'getbible', 0, 0, 4500000),
    ('valera', 'Reina Valera (1909)', 'es', 'getbible', 0, 0, 4100000),
    ('karoli', 'Károli Bible', 'hu', 'getbible', 0, 0, 4000000),
    ('giovanni', 'Giovanni Diodati Bible', 'it', 'getbible', 0, 0, 4100000),
    ('cornilescu', 'Cornilescu Bible', 'ro', 'getbible', 0, 0, 3900000),
    ('korean', 'Korean Bible', 'ko', 'getbible', 0, 0, 3800000),
    ('cus', 'Chinese Union Simplified', 'zh', 'getbible', 0, 0, 2500000);
