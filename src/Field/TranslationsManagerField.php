<?php

/**
 * Part of CWM ScriptureLinks Plugin
 *
 * @package    CWM.Plugin.Content.ScriptureLinks
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Plugin\Content\ScriptureLinks\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Custom form field that renders the Bible translations download manager.
 *
 * @since  1.0.0
 */
class TranslationsManagerField extends FormField
{
    /**
     * @var  string
     * @since  1.0.0
     */
    protected $type = 'TranslationsManager';

    /**
     * @inheritDoc
     */
    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useStyle('lib_cwmscripture.translations-manager');
        $wa->useScript('lib_cwmscripture.translations-manager');

        $ajaxUrl = Uri::base() . 'index.php?option=com_ajax&group=content&plugin=scripturelinks&format=json';
        $token   = Session::getFormToken();

        Factory::getApplication()->getDocument()->addScriptOptions('cwmscripture.manager', [
            'ajaxUrl' => $ajaxUrl,
            'token'   => $token,
        ]);

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__bsms_bible_translations'))
            ->order($db->quoteName('language') . ' ASC, ' . $db->quoteName('name') . ' ASC');
        $db->setQuery($query);

        try {
            $translations = $db->loadObjectList();
        } catch (\Throwable) {
            return '<div class="alert alert-warning">'
                . Text::_('PLG_CONTENT_SCRIPTURELINKS_TRANSLATIONS_TABLE_MISSING')
                . '</div>';
        }

        if (empty($translations)) {
            return '<div class="alert alert-info">'
                . Text::_('PLG_CONTENT_SCRIPTURELINKS_TRANSLATIONS_NONE')
                . '</div>';
        }

        $html = '<div id="cwm-translations-manager">';

        // Status message area
        $html .= '<div id="cwm-tm-status" class="alert" style="display:none;"></div>';

        $html .= '<table class="table table-striped" id="cwm-translations-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_NAME') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_ABBREVIATION') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_LANGUAGE') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_SOURCE') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_STATUS') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_VERSES') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_SIZE') . '</th>';
        $html .= '<th>' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_ACTIONS') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($translations as $t) {
            $rowId     = 'cwm-tm-row-' . htmlspecialchars($t->abbreviation);
            $installed = (int) $t->installed === 1;
            $statusCls = $installed ? 'badge bg-success' : 'badge bg-secondary';
            $statusTxt = $installed
                ? Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_INSTALLED')
                : Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_AVAILABLE');

            $sizeDisplay = $installed && $t->data_size > 0
                ? $this->formatBytes((int) $t->data_size)
                : ($t->estimated_size > 0 ? '~' . $this->formatBytes((int) $t->estimated_size) : '—');

            $verseDisplay = $installed && $t->verse_count > 0
                ? number_format((int) $t->verse_count)
                : '—';

            $html .= '<tr id="' . $rowId . '" data-abbreviation="' . htmlspecialchars($t->abbreviation) . '">';
            $html .= '<td><strong>' . htmlspecialchars($t->name) . '</strong>';

            if (!empty($t->downloaded_at)) {
                $html .= '<br><small class="text-muted">'
                    . Text::sprintf('PLG_CONTENT_SCRIPTURELINKS_TM_DOWNLOADED_AT', htmlspecialchars($t->downloaded_at))
                    . '</small>';
            }

            $html .= '</td>';
            $html .= '<td><code>' . htmlspecialchars(strtoupper($t->abbreviation)) . '</code></td>';
            $html .= '<td>' . htmlspecialchars(strtoupper($t->language)) . '</td>';
            $html .= '<td>' . htmlspecialchars($t->source) . '</td>';
            $html .= '<td><span class="cwm-tm-status-badge ' . $statusCls . '">' . $statusTxt . '</span></td>';
            $html .= '<td class="cwm-tm-verses">' . $verseDisplay . '</td>';
            $html .= '<td class="cwm-tm-size">' . $sizeDisplay . '</td>';
            $html .= '<td class="cwm-tm-actions">';

            if ($installed) {
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary cwm-tm-btn-refresh" '
                    . 'data-abbreviation="' . htmlspecialchars($t->abbreviation) . '">'
                    . '<span class="icon-loop" aria-hidden="true"></span> '
                    . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_REFRESH')
                    . '</button> ';

                if (!\in_array(strtolower($t->abbreviation), ['kjv', 'web'], true)) {
                    $html .= '<button type="button" class="btn btn-sm btn-outline-danger cwm-tm-btn-remove" '
                        . 'data-abbreviation="' . htmlspecialchars($t->abbreviation) . '">'
                        . '<span class="icon-trash" aria-hidden="true"></span> '
                        . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_REMOVE')
                        . '</button>';
                }
            } else {
                $html .= '<button type="button" class="btn btn-sm btn-success cwm-tm-btn-download" '
                    . 'data-abbreviation="' . htmlspecialchars($t->abbreviation) . '">'
                    . '<span class="icon-download" aria-hidden="true"></span> '
                    . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_DOWNLOAD')
                    . '</button>';
            }

            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @inheritDoc
     */
    protected function getLabel(): string
    {
        return '';
    }

    /**
     * Format bytes into a human-readable string.
     *
     * @param   int  $bytes  Size in bytes
     *
     * @return  string
     *
     * @since  1.0.0
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }
}
