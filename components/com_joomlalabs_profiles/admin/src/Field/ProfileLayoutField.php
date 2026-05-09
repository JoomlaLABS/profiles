<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

\defined('_JEXEC') or die;

class ProfileLayoutField extends ListField
{
    protected $type = 'ProfileLayout';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $path    = Path::clean(JPATH_SITE . '/components/com_joomlalabs_profiles/tmpl/profile');

        if (!is_dir($path)) {
            return $options;
        }

        $files = Folder::files($path, '^[^_].*\.php$', false, true);
        $names = [];

        foreach ($files as $file) {
            $layout = basename($file, '.php');

            if (!\in_array($layout, $names, true)) {
                $names[] = $layout;
            }
        }

        usort($names, static function (string $left, string $right): int {
            if ($left === 'default') {
                return -1;
            }

            if ($right === 'default') {
                return 1;
            }

            return strcasecmp($left, $right);
        });

        foreach ($names as $layout) {
            $text = $this->getLayoutLabel($layout);

            $options[] = HTMLHelper::_('select.option', $layout, $text);
        }

        return $options;
    }

    private function getLayoutLabel(string $layout): string
    {
        if ($layout === 'default') {
            return Text::_('COM_JOOMLALABS_PROFILES_PROFILE_LAYOUT_OPTION_DEFAULT');
        }

        $key   = 'COM_JOOMLALABS_PROFILES_PROFILE_LAYOUT_OPTION_' . strtoupper($layout);
        $label = Text::_($key);

        if ($label !== $key) {
            return $label;
        }

        $humanized = preg_replace('/(?<!^)([A-Z])/u', ' $1', $layout) ?? $layout;
        $humanized = str_replace(['-', '_'], ' ', $humanized);
        $humanized = preg_replace('/\s+/u', ' ', trim($humanized)) ?? $layout;

        return ucwords($humanized);
    }
}
