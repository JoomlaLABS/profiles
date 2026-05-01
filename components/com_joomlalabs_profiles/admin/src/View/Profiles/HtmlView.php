<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\View\Profiles;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\View\ListView;

\defined('_JEXEC') or die;

class HtmlView extends ListView
{
    public $filterForm;

    public $activeFilters;

    protected $items;

    protected $pagination;

    protected $state;

    public function __construct(array $config)
    {
        if (empty($config['option'])) {
            $config['option'] = 'com_joomlalabs_profiles';
        }

        $config['toolbar_icon']   = 'address-book profiles';
        $config['supports_batch'] = true;
        $config['category']       = 'com_joomlalabs_profiles';

        parent::__construct($config);
    }

    public function display($tpl = null)
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        parent::display($tpl);
    }

    protected function initializeView()
    {
        parent::initializeView();

        $user = $this->getCurrentUser();

        $this->canDo = ContentHelper::getActions('com_joomlalabs_profiles', 'category', $this->state->get('filter.category_id'));

        if (\count($user->getAuthorisedCategories('com_joomlalabs_profiles', 'core.create')) > 0) {
            $this->canDo->set('core.create', true);
        }
    }
}
