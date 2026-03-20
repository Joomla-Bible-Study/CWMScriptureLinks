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
        $params = ScriptureParamsHelper::getParams();
        $token  = Session::getFormToken();
        $ajaxUrl = Uri::base() . 'index.php?option=com_ajax&group=content&plugin=scripturelinks&format=json';
        $mediaBase = Uri::root(true) . '/media/lib_cwmscripture';

        // Detect field name prefix from the form (e.g. "jform[params]")
        $prefix = $this->formControl ? $this->formControl . '[' . $this->group . ']' : 'jform[params]';

        $providerGetbible = (int) $params->get('provider_getbible', 1);
        $providerApiBible = (int) $params->get('provider_api_bible', 0);
        $apiBibleKey      = (string) $params->get('api_bible_api_key', '');
        $gdprMode         = (int) $params->get('gdpr_mode', 0);
        $defaultVersion   = (string) $params->get('default_version', 'kjv');
        $cacheDays        = (int) $params->get('cache_days', 30);

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');

        // Inject CSS/JS inline — WebAssetManager doesn't work for form fields
        // rendered after <head> is already output
        $html = '<link rel="stylesheet" href="' . $esc($mediaBase . '/css/translations-manager.css') . '" />';
        $html .= '<script src="' . $esc($mediaBase . '/js/cwm-fetch.js') . '" defer></script>';
        $html .= '<script src="' . $esc($mediaBase . '/js/bible-translations.js') . '" defer></script>';

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
        $esc      = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
        $adminLang = 'en-GB';

        try {
            $adminLang = Factory::getApplication()->getLanguage()->getTag();
        } catch (\Throwable) {
        }

        // Same HTML structure as Proclaim's admin/tmpl/cwmadmin/edit.php Scripture tab
        $html = '<div class="row"><div class="col-12"><div class="cwmadmin-panel mb-4">';

        // Header with title and action buttons
        $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h3 class="tab-description mb-0" id="translations-card-header">'
            . Text::_('PLG_CONTENT_SCRIPTURELINKS_LOCAL_TRANSLATIONS') . '</h3>';
        $html .= '<div class="btn-group btn-group-sm">';
        $html .= '<button type="button" class="btn btn-primary d-none" id="btn-update-all-translations">'
            . '<i class="icon-download" aria-hidden="true"></i> Update All</button>';
        $html .= '<button type="button" class="btn btn-danger d-none" id="btn-remove-all-translations">'
            . '<i class="icon-trash" aria-hidden="true"></i> Remove All</button>';
        $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-refresh-translations">'
            . '<i class="icon-refresh" aria-hidden="true"></i></button>';
        $html .= '</div></div>';

        $html .= '<p class="text-muted">' . Text::_('PLG_CONTENT_SCRIPTURELINKS_LOCAL_TRANSLATIONS_DESC') . '</p>';

        // Translations list container — populated by bible-translations.js
        $html .= '<div id="translations-list">';
        $html .= '<div class="text-center py-3">'
            . '<span class="spinner-border spinner-border-sm" role="status"></span> Loading...</div>';
        $html .= '</div>';

        $html .= '</div></div></div>';

        // Config div with all translated strings for bible-translations.js
        // Uses the same element IDs and data-str-* attributes as Proclaim's template
        $html .= '<div id="bible-translations-config" class="d-none"'
            . ' data-gdpr-mode="' . $gdprMode . '"'
            . ' data-token="' . $token . '"'
            . ' data-str-loading="Loading..."'
            . ' data-str-no-translations="No translations available."'
            . ' data-str-load-error="Unknown"'
            . ' data-str-title="Title"'
            . ' data-str-abbreviation="Abbreviation"'
            . ' data-str-source="Source"'
            . ' data-str-status="Status"'
            . ' data-str-verses="Verses"'
            . ' data-str-installed="Installed"'
            . ' data-str-not-installed="Not Installed"'
            . ' data-str-download="Download"'
            . ' data-str-downloading="Downloading..."'
            . ' data-str-remove="Remove"'
            . ' data-str-download-failed="Download failed"'
            . ' data-str-confirm-remove="Are you sure you want to remove this translation?"'
            . ' data-str-bundled-done="Bundled translations auto-downloaded"'
            . ' data-str-status-ready="Ready"'
            . ' data-str-status-installed="translations imported"'
            . ' data-str-status-none="None installed"'
            . ' data-str-status-unknown="Unknown"'
            . ' data-str-remove-all="Remove All"'
            . ' data-str-confirm-remove-all="Remove ALL installed translations? This cannot be undone."'
            . ' data-str-size="Size"'
            . ' data-str-total-size="Total Size"'
            . ' data-str-syncing="Syncing..."'
            . ' data-str-sync-complete="%s translations synced"'
            . ' data-str-sync-failed="Sync failed"'
            . ' data-str-gdpr-disabled="Online providers disabled (GDPR mode)"'
            . ' data-str-online="Online"'
            . ' data-str-language="Language"'
            . ' data-str-all-languages="All Languages"'
            . ' data-str-filter-all="All"'
            . ' data-str-filter-installed="Installed"'
            . ' data-str-filter-not-installed="Not Installed"'
            . ' data-str-filter-in-use="In Use"'
            . ' data-str-search-placeholder="Search by name or abbreviation..."'
            . ' data-str-usage-count="Usage Count"'
            . ' data-str-usage-badge="used in %s messages"'
            . ' data-str-suggested="Suggested"'
            . ' data-str-showing-count="Showing %s of %s translations"'
            . ' data-admin-language="' . $esc($adminLang) . '"'
            . ' data-str-core-translation="Core"'
            . ' data-str-core-cannot-remove="Core translations cannot be removed"'
            . ' data-str-suggested-desc="Suggested for your language"'
            . ' data-str-online-only="Online Only"'
            . ' data-str-online-only-desc="Available via online provider only"'
            . ' data-str-provider-disable-confirm="Disable this provider? Non-installed catalog entries will be removed."'
            . ' data-str-provider-cleanup-done="%s catalog entries removed"'
            . ' data-str-bible-refresh="Refresh"'
            . ' data-str-bible-refreshing="Refreshing..."'
            . ' data-str-bible-update-all="Update All"'
            . ' data-str-bible-update-all-desc="Re-download all installed translations"'
            . ' data-str-bible-updating-all="Updating all..."'
            . ' data-str-bible-update-all-complete="Updated %s, failed %s"'
            . ' data-str-bible-downloaded-at="Downloaded: %s"'
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
