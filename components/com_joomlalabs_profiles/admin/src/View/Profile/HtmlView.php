<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\View\Profile;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\View\FormView;
use Joomla\CMS\Toolbar\Toolbar;

\defined('_JEXEC') or die;

class HtmlView extends FormView
{
    private const VERSIONS_ALIAS = 'com_joomlalabs_profiles.record';

    protected $categorySection = 'com_joomlalabs_profiles';

    public function __construct(array $config)
    {
        if (empty($config['option'])) {
            $config['option'] = 'com_joomlalabs_profiles';
        }

        $config['toolbar_icon'] = 'address-book';

        parent::__construct($config);
    }

    protected function initializeView()
    {
        parent::initializeView();

        $this->canDo = ContentHelper::getActions('com_joomlalabs_profiles', 'category', (int) ($this->item->catid ?? 0));

        if (\count($this->getCurrentUser()->getAuthorisedCategories('com_joomlalabs_profiles', 'core.create')) > 0) {
            $this->canDo->set('core.create', true);
        }
    }

    protected function addToolbar()
    {
        $params           = $this->state->get('params');
        $saveHistoryValue = null;

        // Prevent FormView from adding its default "profile" versions alias.
        if ($params) {
            $saveHistoryValue = $params->get('save_history', 0);
            $params->set('save_history', 0);
            $this->state->set('params', $params);
        }

        parent::addToolbar();

        if ($params && $saveHistoryValue !== null) {
            $params->set('save_history', $saveHistoryValue);
            $this->state->set('params', $params);
        }

        if (!$this->item || empty($this->item->{$this->keyName})) {
            return;
        }

        if (!ComponentHelper::isEnabled('com_contenthistory') || !(bool) $saveHistoryValue) {
            return;
        }

        $user         = $this->getCurrentUser();
        $itemEditable = (bool) ($this->canDo->get('core.edit') ?? false);

        if (!$itemEditable && property_exists($this->item, 'created_by')) {
            $itemEditable = (bool) ($this->canDo->get('core.edit.own') ?? false)
                && (int) $this->item->created_by === (int) $user->id;
        }

        if (!$itemEditable) {
            return;
        }

        Toolbar::getInstance('toolbar')->versions(self::VERSIONS_ALIAS, (int) $this->item->{$this->keyName});
    }
}
