<?php

/**
 * Part of CWM ScriptureLinks Plugin
 *
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Plugin\Content\ScriptureLinks\Extension;

use CWM\Library\Scripture\Bible\AbstractBibleProvider;
use CWM\Library\Scripture\Bible\BibleProviderFactory;
use CWM\Library\Scripture\Helper\ScriptureHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ScriptureLinks Content Plugin.
 *
 * Replaces {bible}...{/bible} and {scripture}...{/scripture} tags in article
 * content with scripture passages from the CWM Scripture Library.
 *
 * @since  1.0.0
 */
class ScriptureLinks extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    /**
     * Plugin that replaces scripture tags with linked passages.
     *
     * @param   \Joomla\Event\Event  $event  The event object
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onContentPrepare(\Joomla\Event\Event $event): void
    {
        [$context, $row, $params, $page] = array_values($event->getArguments());

        if ($context === 'com_finder.indexer') {
            return;
        }

        if (empty($row->text)) {
            return;
        }

        // Check for tags first (fast bail-out)
        if (
            stripos($row->text, '{bible') === false
            && stripos($row->text, '{scripture') === false
        ) {
            // Auto-detect mode check
            $mode = $this->params->get('mode', 'tag');

            if ($mode !== 'auto') {
                return;
            }
        }

        AbstractBibleProvider::registerLogger();

        $mode = $this->params->get('mode', 'tag');

        // Always process explicit tags
        $row->text = $this->processTags($row->text);

        // Auto-detect mode: scan for untagged scripture references
        if ($mode === 'auto') {
            $row->text = $this->processAutoDetect($row->text);
        }
    }

    /**
     * Process {bible} and {scripture} tags in content.
     *
     * Supports:
     * - {scripture}John 3:16{/scripture}
     * - {scripture kjv}John 3:16{/scripture}
     * - {bible}John 3:16{/bible}
     * - {bible 9}John 3:16{/bible} (legacy numeric version)
     *
     * @param   string  $text  Article content
     *
     * @return  string  Content with tags replaced
     *
     * @since  1.0.0
     */
    private function processTags(string $text): string
    {
        $pattern = '#\{(?:bible|scripture)\s*([a-zA-Z0-9]*)\}(.+?)\{/(?:bible|scripture)\}#is';

        return preg_replace_callback($pattern, [$this, 'replaceTag'], $text);
    }

    /**
     * Callback for tag replacement.
     *
     * @param   array  $matches  Regex matches
     *
     * @return  string  Replacement HTML
     *
     * @since  1.0.0
     */
    private function replaceTag(array $matches): string
    {
        $versionOverride = trim($matches[1]);
        $referenceText   = trim($matches[2]);

        if (empty($referenceText)) {
            return '';
        }

        $version = !empty($versionOverride)
            ? $versionOverride
            : $this->params->get('default_version', 'kjv');

        return $this->buildScriptureLink($referenceText, $version);
    }

    /**
     * Auto-detect scripture references in content and replace them.
     *
     * @param   string  $text  Article content
     *
     * @return  string  Content with detected references replaced
     *
     * @since  1.0.0
     */
    private function processAutoDetect(string $text): string
    {
        // Build regex from known book names and abbreviations
        $abbreviations = ScriptureHelper::getAbbreviations();
        $bookNames     = array_keys($abbreviations);

        // Sort longest first to avoid partial matches
        usort($bookNames, static fn ($a, $b) => strlen($b) - strlen($a));

        $escapedBooks = array_map(static fn ($name) => preg_quote($name, '#'), $bookNames);
        $booksPattern = implode('|', $escapedBooks);

        // Match: "Book Chapter:Verse[-Verse]" patterns not already inside tags
        $pattern = '#(?<!\{(?:bible|scripture)[^}]*\})(?<!\w)\b(' . $booksPattern . ')\s+(\d{1,3})(?::(\d{1,3})(?:\s*-\s*(?:(\d{1,3}):)?(\d{1,3}))?)?\b(?!\s*\{/(?:bible|scripture)\})#i';

        $version = $this->params->get('default_version', 'kjv');

        return preg_replace_callback($pattern, function (array $matches) use ($version) {
            $fullMatch = $matches[0];

            // Verify it's a valid scripture reference
            $ref = ScriptureHelper::parseReference($fullMatch);

            if ($ref === null) {
                return $fullMatch;
            }

            return $this->buildScriptureLink($fullMatch, $version);
        }, $text);
    }

    /**
     * Build the HTML output for a scripture reference.
     *
     * @param   string  $referenceText  Human-readable reference (e.g. "John 3:16")
     * @param   string  $version        Bible version abbreviation
     *
     * @return  string  HTML output
     *
     * @since  1.0.0
     */
    private function buildScriptureLink(string $referenceText, string $version): string
    {
        $display = $this->params->get('display', 'link');

        // Parse the reference to build the API query
        $ref = ScriptureHelper::parseReference($referenceText);

        if ($ref === null) {
            return $referenceText;
        }

        // Build a + separated reference for the provider
        $formattedRef = ScriptureHelper::formatReference(
            $ref->booknumber,
            $ref->chapterBegin,
            $ref->verseBegin,
            $ref->chapterEnd,
            $ref->verseEnd
        );

        $queryRef = str_replace(' ', '+', $formattedRef);

        if ($display === 'link') {
            return '<a href="https://www.biblegateway.com/passage/?search='
                . urlencode($formattedRef)
                . '&version=' . urlencode(strtoupper($version))
                . '" target="_blank" rel="noopener noreferrer" class="scripture-link">'
                . htmlspecialchars($referenceText)
                . '</a>';
        }

        // For passage display modes, fetch the actual text
        $providerParams = new Registry([
            'provider_getbible'  => $this->params->get('provider_getbible', 1),
            'provider_api_bible' => $this->params->get('provider_api_bible', 0),
            'api_bible_api_key'  => $this->params->get('api_bible_api_key', ''),
            'gdpr_mode'          => $this->params->get('gdpr_mode', 0),
        ]);

        try {
            $provider = BibleProviderFactory::getProviderForTranslation($version, $providerParams);

            $cacheDays = (int) $this->params->get('cache_days', 30);

            if ($cacheDays > 0) {
                $provider->setCacheTtl($cacheDays * 86400);
            }

            $result = $provider->getPassage($queryRef, $version);

            if ($result->hasText()) {
                return $this->renderPassage($referenceText, $result->text, $result->copyright, $display);
            }
        } catch (\Throwable $e) {
            Log::add('ScriptureLinks: Error fetching "' . $referenceText . '": ' . $e->getMessage(), Log::ERROR, 'cwmscripture.bible');
        }

        // Fallback: return as a BibleGateway link
        return '<a href="https://www.biblegateway.com/passage/?search='
            . urlencode($formattedRef)
            . '&version=' . urlencode(strtoupper($version))
            . '" target="_blank" rel="noopener noreferrer" class="scripture-link">'
            . htmlspecialchars($referenceText)
            . '</a>';
    }

    /**
     * Render a scripture passage in the configured display mode.
     *
     * @param   string  $reference  Human-readable reference
     * @param   string  $text       Passage text (may contain HTML)
     * @param   string  $copyright  Copyright notice
     * @param   string  $display    Display mode: 'tooltip', 'inline', 'popup'
     *
     * @return  string  HTML output
     *
     * @since  1.0.0
     */
    private function renderPassage(string $reference, string $text, string $copyright, string $display): string
    {
        $copyrightHtml = '';

        if (!empty($copyright)) {
            $copyrightHtml = '<div class="scripture-copyright">' . htmlspecialchars($copyright) . '</div>';
        }

        switch ($display) {
            case 'tooltip':
                $tooltipText = strip_tags($text);

                if (strlen($tooltipText) > 500) {
                    $tooltipText = substr($tooltipText, 0, 497) . '...';
                }

                return '<span class="scripture-tooltip" title="' . htmlspecialchars($tooltipText) . '">'
                    . htmlspecialchars($reference)
                    . '</span>';

            case 'inline':
                return '<div class="scripture-container scripture-inline">'
                    . '<strong class="scripture-ref">' . htmlspecialchars($reference) . '</strong> '
                    . '<div class="scripture-text">'
                    . '<div class="scripture-body">' . $text . '</div>'
                    . $copyrightHtml
                    . '</div></div>';

            case 'popup':
                $id = 'scripture_' . md5($reference . $text);

                return '<a href="#" class="scripture-link scripture-popup-trigger" '
                    . 'onclick="var w=window.open(\'\',\'scripture\',\'width=700,height=500,scrollbars=yes\');'
                    . 'var t=document.getElementById(\'' . $id . '\');'
                    . 'if(w){w.document.open();w.document.write(t.innerHTML);w.document.close();}return false;">'
                    . htmlspecialchars($reference)
                    . '</a>'
                    . '<template id="' . $id . '">'
                    . '<!DOCTYPE html><html><head><meta charset="utf-8">'
                    . '<title>' . htmlspecialchars($reference) . '</title>'
                    . '<style>body{font-family:Georgia,serif;line-height:1.8;padding:2em;max-width:700px;margin:0 auto;color:#333;}'
                    . 'sup{font-size:0.65em;font-weight:700;color:#8b4513;margin-right:2px;}'
                    . '.scripture-copyright{margin-top:1em;padding-top:0.75em;border-top:1px solid #e0ddd5;font-size:0.8em;color:#888;font-style:italic;}</style>'
                    . '</head><body>'
                    . '<h3>' . htmlspecialchars($reference) . '</h3>'
                    . $text . $copyrightHtml
                    . '</body></html></template>';

            default:
                return htmlspecialchars($reference);
        }
    }
}
