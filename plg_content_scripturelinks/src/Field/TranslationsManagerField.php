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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use CWM\Library\Scripture\Field\TranslationsmanagerField as LibraryField;

/**
 * Backward-compatible wrapper — delegates to the library field.
 *
 * @since    1.0.0
 * @deprecated  1.1.0  Use CWM\Library\Scripture\Field\TranslationsmanagerField directly.
 */
class TranslationsManagerField extends LibraryField
{
}
