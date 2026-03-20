<?php

/**
 * Part of CWM Scripture Library
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Renderer;

use CWM\Library\Scripture\Bible\BiblePassageResult;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Generic scripture passage renderer.
 *
 * Provides display methods that are independent of any specific component.
 * Proclaim's Cwmshowscripture delegates to these methods for actual HTML generation.
 *
 * @since  1.0.0
 */
class ScriptureRenderer
{
    /**
     * Display mode constants.
     *
     * @since  1.0.0
     */
    public const MODE_HIDDEN  = 0;
    public const MODE_TOGGLE  = 1;
    public const MODE_VISIBLE = 2;
    public const MODE_POPUP   = 3;

    /**
     * Render a text-based scripture passage.
     *
     * @param   BiblePassageResult  $result       The passage result
     * @param   int                 $mode         Display mode (0=hidden, 1=toggle, 2=always, 3=popup)
     * @param   bool                $showIcon     Whether to show Bible icon
     * @param   string              $switcherHtml Optional version switcher HTML
     *
     * @return  string  HTML output
     *
     * @since  1.0.0
     */
    public function renderTextPassage(
        BiblePassageResult $result,
        int $mode = self::MODE_VISIBLE,
        bool $showIcon = true,
        string $switcherHtml = ''
    ): string {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useStyle('lib_cwmscripture.scripture-text');

        $copyrightHtml = '';

        if (!empty($result->copyright)) {
            $copyrightHtml = '<div class="scripture-copyright">'
                . htmlspecialchars($result->copyright) . '</div>';
        }

        $passage = '';

        switch ($mode) {
            case self::MODE_TOGGLE:
                $id       = 'scripture_' . uniqid('', true);
                $passage  = '<div class="scripture-container">';
                $passage .= '<a class="scripture-toggle heading" href="#" role="button" '
                    . 'aria-expanded="false" aria-controls="' . $id . '" '
                    . 'onclick="var e = document.getElementById(\'' . $id . '\'); '
                    . 'var isHidden = e.style.display == \'none\'; '
                    . 'e.style.display = (isHidden ? \'block\' : \'none\'); '
                    . 'this.setAttribute(\'aria-expanded\', isHidden); return false;">';

                if ($showIcon) {
                    $passage .= '<i class="fas fa-bible fa-3x" aria-hidden="true" '
                        . 'style="display: flex; margin-right: 10px;"></i>';
                }

                $passage .= Text::_('LIB_CWMSCRIPTURE_SHOW_HIDE') . '</a>';
                $passage .= '<div id="' . $id . '" class="scripture-text" style="display: none;">';
                $passage .= '<div class="scripture-body">' . $result->text . '</div>';
                $passage .= $copyrightHtml;
                $passage .= $switcherHtml;
                $passage .= '</div></div>';
                break;

            case self::MODE_VISIBLE:
                $passage  = '<div class="scripture-container scripture-visible">';
                $passage .= '<div class="scripture-text">';
                $passage .= '<div class="scripture-body">' . $result->text . '</div>';
                $passage .= $copyrightHtml;
                $passage .= $switcherHtml;
                $passage .= '</div></div>';
                break;

            case self::MODE_POPUP:
                $popupId  = 'scripture_popup_' . uniqid('', true);
                $passage  = '<div class="scripture-container">';
                $passage .= '<a href="#" class="scripture-popup-trigger" '
                    . 'onclick="var t=document.getElementById(\'' . $popupId . '\');'
                    . 'var w=window.open(\'\',\'scripture_popup\','
                    . '\'width=700,height=500,scrollbars=yes,resizable=yes\');'
                    . 'if(w){w.document.open();w.document.write(t.innerHTML);w.document.close();}'
                    . 'return false;" '
                    . 'title="' . Text::_('LIB_CWMSCRIPTURE_OPEN_PASSAGE') . '">';

                if ($showIcon) {
                    $passage .= '<i class="fas fa-bible fa-3x" aria-hidden="true" '
                        . 'style="display: flex; margin-right: 10px;"></i>';
                } else {
                    $passage .= Text::_('LIB_CWMSCRIPTURE_OPEN_PASSAGE');
                }

                $passage .= '</a>';

                $lang     = Factory::getApplication()->getLanguage()->getTag();
                $passage .= '<template id="' . $popupId . '">';
                $passage .= '<!DOCTYPE html><html lang="' . $lang . '">';
                $passage .= '<head><meta charset="utf-8">';
                $passage .= '<title>' . Text::_('LIB_CWMSCRIPTURE_OPEN_PASSAGE') . '</title>';
                $passage .= '<style>'
                    . 'body{font-family:Georgia,"Times New Roman",serif;line-height:1.8;'
                    . 'padding:2em;margin:0;max-width:700px;margin:0 auto;color:#333;background:#fafaf8;}'
                    . 'sup{font-size:0.65em;font-weight:700;color:#8b4513;margin-right:2px;}'
                    . '.scripture-copyright{margin-top:1em;padding-top:0.75em;'
                    . 'border-top:1px solid #e0ddd5;font-size:0.8em;color:#888;font-style:italic;}'
                    . '</style></head><body>';
                $passage .= $result->text;
                $passage .= $copyrightHtml;
                $passage .= '</body></html>';
                $passage .= '</template></div>';
                break;

            default:
                // MODE_HIDDEN
                break;
        }

        return $passage;
    }

    /**
     * Render an "unavailable" notice with a retry button.
     *
     * @param   string  $reference  Scripture reference
     * @param   string  $version    Bible version abbreviation
     *
     * @return  string  HTML notice
     *
     * @since  1.0.0
     */
    public function renderUnavailableNotice(string $reference, string $version): string
    {
        $uid = uniqid('retry_', true);

        $html  = '<div class="scripture-container scripture-unavailable" '
            . 'data-reference="' . htmlspecialchars($reference) . '" '
            . 'data-version="' . htmlspecialchars($version) . '">';
        $html .= '<div class="scripture-text">';
        $html .= '<div class="scripture-body">';
        $html .= '<p class="text-muted"><em>' . Text::_('LIB_CWMSCRIPTURE_UNAVAILABLE') . '</em></p>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-secondary scripture-retry-btn" '
            . 'id="' . $uid . '">'
            . '<i class="fas fa-redo" aria-hidden="true"></i> '
            . Text::_('LIB_CWMSCRIPTURE_RETRY') . '</button>';
        $html .= '</div></div></div>';

        return $html;
    }
}
