<?php

/**
 * Part of CWM ScriptureLinks Plugin
 *
 * @package    CWM.Library.Scripture
 * @copyright  (C) 2026 CWM Team All rights reserved
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       https://www.christianwebministries.org
 */

namespace CWM\Library\Scripture\Field;

use CWM\Library\Scripture\Helper\ScriptureParamsHelper;
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
 * Custom form field that renders the full Scripture management UI.
 *
 * Renders the same paneled layout as Proclaim's Admin Center Scripture tab:
 * - Left column: Scripture Providers (GetBible, API.Bible toggles, API key, sync)
 * - Right column: Scripture Settings (default version, cache days)
 * - Full width below: Local Translations table with download/remove/refresh
 *
 * Form inputs use the parent form's naming convention so Joomla saves
 * them as plugin params alongside the hidden fields in the XML.
 *
 * @since  1.0.0
 */
class TranslationsmanagerField extends FormField
{
    /**
     * @var  string
     * @since  1.0.0
     */
    protected $type = 'Translationsmanager';

    /**
     * @inheritDoc
     */
    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'lib_cwmscripture.translations-manager',
            'lib_cwmscripture/css/translations-manager.css'
        );
        $wa->registerAndUseScript(
            'lib_cwmscripture.translations-manager',
            'lib_cwmscripture/js/translations-manager.js',
            [],
            [],
            ['core']
        );

        $params = ScriptureParamsHelper::getParams();
        $token  = Session::getFormToken();
        $ajaxUrl = Uri::base() . 'index.php?option=com_ajax&group=content&plugin=scripturelinks&format=json';

        Factory::getApplication()->getDocument()->addScriptOptions('cwmscripture.manager', [
            'ajaxUrl' => $ajaxUrl,
            'token'   => $token,
        ]);

        // Detect field name prefix from the form (e.g. "jform[params]")
        $prefix = $this->formControl ? $this->formControl . '[' . $this->group . ']' : 'jform[params]';

        $providerGetbible = (int) $params->get('provider_getbible', 1);
        $providerApiBible = (int) $params->get('provider_api_bible', 0);
        $apiBibleKey      = (string) $params->get('api_bible_api_key', '');
        $gdprMode         = (int) $params->get('gdpr_mode', 0);
        $defaultVersion   = (string) $params->get('default_version', 'kjv');
        $cacheDays        = (int) $params->get('cache_days', 30);

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');

        $html = '';

        // ── Provider & Settings panels (two-column) ──
        $html .= '<div class="row" id="scripture-settings">';

        // Left column: Providers
        $html .= '<div class="col-12 col-lg-6">';
        $html .= '<div class="cwmadmin-panel mb-4">';
        $html .= '<h3 class="tab-description">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_PROVIDERS_TITLE') . '</h3>';
        $html .= '<p class="text-muted">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_PROVIDERS_DESC') . '</p>';

        // GetBible toggle
        $html .= $this->renderSwitcher(
            $prefix . '[provider_getbible]',
            'cwm_provider_getbible',
            Text::_('PLG_CONTENT_SCRIPTURELINKS_GETBIBLE_LABEL'),
            Text::_('PLG_CONTENT_SCRIPTURELINKS_GETBIBLE_DESC'),
            $providerGetbible
        );

        // GDPR mode
        $html .= $this->renderSwitcher(
            $prefix . '[gdpr_mode]',
            'cwm_gdpr_mode',
            Text::_('PLG_CONTENT_SCRIPTURELINKS_GDPR_LABEL'),
            Text::_('PLG_CONTENT_SCRIPTURELINKS_GDPR_DESC'),
            $gdprMode
        );

        // API.Bible toggle
        $html .= $this->renderSwitcher(
            $prefix . '[provider_api_bible]',
            'cwm_provider_api_bible',
            Text::_('PLG_CONTENT_SCRIPTURELINKS_APIBIBLE_LABEL'),
            Text::_('PLG_CONTENT_SCRIPTURELINKS_APIBIBLE_DESC'),
            $providerApiBible
        );

        // API.Bible API Key
        $maskedKey = '';

        if ($apiBibleKey !== '') {
            $maskedKey = str_repeat("\u{2022}", 20) . substr($apiBibleKey, -4);
        }

        $html .= '<div class="control-group" id="cwm-api-key-group">';
        $html .= '<div class="control-label"><label for="cwm_api_bible_api_key">'
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_APIKEY_LABEL') . '</label></div>';
        $html .= '<div class="controls"><div class="input-group">';
        $html .= '<input type="password" name="' . $esc($prefix . '[api_bible_api_key]') . '" '
            . 'id="cwm_api_bible_api_key" '
            . 'value="' . $esc($apiBibleKey) . '" '
            . 'placeholder="' . $esc($maskedKey) . '" '
            . 'class="form-control" />';
        $html .= '<button type="button" class="btn btn-secondary" id="cwm_api_key_toggle" aria-label="Toggle visibility">'
            . '<span class="icon-eye" aria-hidden="true"></span></button>';
        $html .= '</div>';
        $html .= '<div class="form-text">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_APIKEY_DESC') . '</div>';
        $html .= '</div></div>';

        // Get API Key button
        $html .= '<div id="cwm-api-bible-key-row" class="mb-3">';
        $html .= '<a href="https://api.bible/sign-in" target="_blank" rel="noopener noreferrer" '
            . 'class="btn btn-sm btn-outline-secondary">'
            . '<i class="icon-key" aria-hidden="true"></i> '
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_GET_API_KEY')
            . '</a>';
        $html .= '</div>';

        // Sync button
        $html .= '<div id="cwm-api-bible-sync-row" class="mb-3">';
        $html .= '<button type="button" class="btn btn-sm btn-primary" id="cwm-btn-sync-api-bible">'
            . '<i class="icon-refresh" aria-hidden="true"></i> '
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_SYNC_TRANSLATIONS')
            . '</button>';
        $html .= '<span id="cwm-api-bible-sync-status" class="ms-2 small"></span>';
        $html .= '</div>';

        $html .= '</div></div>'; // end panel, end left column

        // Right column: Settings
        $html .= '<div class="col-12 col-lg-6">';
        $html .= '<div class="cwmadmin-panel mb-4">';
        $html .= '<h3 class="tab-description">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_SETTINGS_TITLE') . '</h3>';

        // Default Bible Version — render as grouped select from DB
        $html .= '<div class="control-group">';
        $html .= '<div class="control-label"><label for="cwm_default_version">'
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_VERSION_LABEL') . '</label></div>';
        $html .= '<div class="controls">';
        $html .= $this->renderVersionSelect($prefix . '[default_version]', 'cwm_default_version', $defaultVersion);
        $html .= '<div class="form-text">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_VERSION_DESC') . '</div>';
        $html .= '</div></div>';

        // Cache Days
        $html .= '<div class="control-group">';
        $html .= '<div class="control-label"><label for="cwm_cache_days">'
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_CACHE_LABEL') . '</label></div>';
        $html .= '<div class="controls">';
        $html .= '<input type="number" name="' . $esc($prefix . '[cache_days]') . '" '
            . 'id="cwm_cache_days" value="' . $cacheDays . '" '
            . 'min="1" max="365" class="form-control" />';
        $html .= '<div class="form-text">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_CACHE_DESC') . '</div>';
        $html .= '</div></div>';

        $html .= '</div></div>'; // end panel, end right column
        $html .= '</div>'; // end row

        // ── Local Translations table ──
        $html .= $this->renderTranslationsTable($token, $gdprMode);

        // ── Inline JS for field interactions ──
        $html .= $this->renderInlineJs();

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
     * Render a Joomla-style radio switcher.
     *
     * @param   string  $name   Form input name
     * @param   string  $id     Element ID
     * @param   string  $label  Label text
     * @param   string  $desc   Description text
     * @param   int     $value  Current value (0 or 1)
     *
     * @return  string
     *
     * @since  1.1.0
     */
    private function renderSwitcher(string $name, string $id, string $label, string $desc, int $value): string
    {
        $esc     = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
        $yesChk  = $value ? ' checked' : '';
        $noChk   = !$value ? ' checked' : '';

        $html = '<div class="control-group">';
        $html .= '<div class="control-label"><label>' . $esc($label) . '</label></div>';
        $html .= '<div class="controls">';
        $html .= '<fieldset class="switcher" id="' . $id . '">';
        $html .= '<input type="radio" name="' . $esc($name) . '" id="' . $id . '0" value="0"' . $noChk . ' />';
        $html .= '<label for="' . $id . '0">' . Text::_('JNO') . '</label>';
        $html .= '<input type="radio" name="' . $esc($name) . '" id="' . $id . '1" value="1"' . $yesChk . ' />';
        $html .= '<label for="' . $id . '1">' . Text::_('JYES') . '</label>';
        $html .= '<span class="toggle-outside"><span class="toggle-inside"></span></span>';
        $html .= '</fieldset>';

        if ($desc) {
            $html .= '<div class="form-text">' . $esc($desc) . '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render the default version select dropdown.
     *
     * @param   string  $name     Form input name
     * @param   string  $id       Element ID
     * @param   string  $current  Current selected abbreviation
     *
     * @return  string
     *
     * @since  1.1.0
     */
    private function renderVersionSelect(string $name, string $id, string $current): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');

        $options = [];

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['abbreviation', 'name', 'language', 'installed']))
                ->from($db->quoteName('#__bsms_bible_translations'))
                ->order($db->quoteName('name') . ' ASC');
            $db->setQuery($query);
            $rows = $db->loadObjectList();

            foreach ($rows as $row) {
                $label = $row->name . ' (' . strtoupper($row->abbreviation) . ')';

                if ((int) $row->installed === 1) {
                    $label .= ' ✓';
                }

                $options[] = (object) ['value' => $row->abbreviation, 'text' => $label];
            }
        } catch (\Throwable) {
            // Fall back to basic defaults
        }

        if (empty($options)) {
            $options = [
                (object) ['value' => 'kjv', 'text' => 'King James Version (KJV)'],
                (object) ['value' => 'web', 'text' => 'World English Bible (WEB)'],
            ];
        }

        $html = '<select name="' . $esc($name) . '" id="' . $id . '" class="form-select">';

        foreach ($options as $opt) {
            $sel = $opt->value === $current ? ' selected' : '';
            $html .= '<option value="' . $esc($opt->value) . '"' . $sel . '>' . $esc($opt->text) . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Render the Local Translations table with AJAX controls.
     *
     * @param   string  $token     Form token name
     * @param   int     $gdprMode  Current GDPR mode setting
     *
     * @return  string
     *
     * @since  1.1.0
     */
    private function renderTranslationsTable(string $token, int $gdprMode): string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Count installed translations
        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__bsms_bible_translations'))
                ->where($db->quoteName('installed') . ' = 1');
            $db->setQuery($query);
            $installedCount = (int) $db->loadResult();
        } catch (\Throwable) {
            $installedCount = 0;
        }

        $html = '<div class="row"><div class="col-12"><div class="cwmadmin-panel mb-4">';

        // Header with title badge and action buttons
        $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h3 class="tab-description mb-0" id="cwm-translations-header">'
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_LOCAL_TRANSLATIONS');

        if ($installedCount > 0) {
            $html .= ' <span class="badge bg-success ms-2"><i class="icon-checkmark-circle" aria-hidden="true"></i> '
                . $installedCount . ' ' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_INSTALLED') . '</span>';
        }

        $html .= '</h3>';

        $html .= '<div class="btn-group btn-group-sm">';
        $html .= '<button type="button" class="btn btn-primary d-none" id="cwm-btn-update-all" '
            . 'title="' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_UPDATE_ALL_DESC') . '">'
            . '<i class="icon-download" aria-hidden="true"></i> ' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_UPDATE_ALL')
            . '</button>';
        $html .= '<button type="button" class="btn btn-danger d-none" id="cwm-btn-remove-all" '
            . 'title="' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_REMOVE_ALL') . '">'
            . '<i class="icon-trash" aria-hidden="true"></i> ' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_REMOVE_ALL')
            . '</button>';
        $html .= '<button type="button" class="btn btn-outline-secondary" id="cwm-btn-refresh-list" '
            . 'title="' . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_REFRESH') . '">'
            . '<i class="icon-refresh" aria-hidden="true"></i></button>';
        $html .= '</div></div>';

        $html .= '<p class="text-muted">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_LOCAL_TRANSLATIONS_DESC') . '</p>';

        // Translations list container (populated by JS)
        $html .= '<div id="cwm-translations-list">';
        $html .= '<div class="text-center py-3">'
            . '<span class="spinner-border spinner-border-sm" role="status"></span> '
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_TM_LOADING')
            . '</div>';
        $html .= '</div>';

        $html .= '</div></div></div>'; // end panel, col, row

        // Config div for JS (same pattern as Proclaim's bible-translations-config)
        $html .= '<div id="bible-translations-config" class="d-none" '
            . 'data-gdpr-mode="' . $gdprMode . '" '
            . 'data-token="' . $token . '"'
            . '></div>';

        return $html;
    }

    /**
     * Render inline JS for field interactions (eye toggle, provider show/hide).
     *
     * @return  string
     *
     * @since  1.1.0
     */
    private function renderInlineJs(): string
    {
        return '<script>
document.addEventListener("DOMContentLoaded", function() {
    // API key eye toggle
    var toggleBtn = document.getElementById("cwm_api_key_toggle");
    if (toggleBtn) {
        toggleBtn.addEventListener("click", function() {
            var input = document.getElementById("cwm_api_bible_api_key");
            var icon = this.querySelector("span");
            if (input.type === "password") {
                input.type = "text";
                icon.className = "icon-eye-close";
            } else {
                input.type = "password";
                icon.className = "icon-eye";
            }
        });
    }

    // Sync visible controls to hidden form fields so Joomla saves them
    var syncFields = [
        ["cwm_provider_getbible", "jform_params_provider_getbible"],
        ["cwm_provider_api_bible", "jform_params_provider_api_bible"],
        ["cwm_gdpr_mode", "jform_params_gdpr_mode"],
        ["cwm_api_bible_api_key", "jform_params_api_bible_api_key"],
        ["cwm_default_version", "jform_params_default_version"],
        ["cwm_cache_days", "jform_params_cache_days"]
    ];

    // On form submit, copy values from visible controls to hidden fields
    var form = document.getElementById("style-form") || document.querySelector("form[name=adminForm]");
    if (form) {
        form.addEventListener("submit", function() {
            syncFields.forEach(function(pair) {
                var visible = pair[0];
                var hidden = pair[1];
                var hiddenEl = document.getElementById(hidden);
                if (!hiddenEl) return;

                // Radio switcher: find checked input
                var fieldset = document.getElementById(visible);
                if (fieldset && fieldset.tagName === "FIELDSET") {
                    var checked = fieldset.querySelector("input:checked");
                    if (checked) hiddenEl.value = checked.value;
                    return;
                }

                // Regular input/select
                var el = document.getElementById(visible);
                if (el) hiddenEl.value = el.value;
            });
        });
    }
});
</script>';
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
