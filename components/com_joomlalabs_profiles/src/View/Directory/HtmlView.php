<?php

declare(strict_types=1);

/**
 * @package     Joomla.Site
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Site\View\Directory;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    private ?CMSApplicationInterface $application = null;

    protected $items;

    protected $pagination;

    protected $state;

    protected $params;

    protected $categories;

    protected $filterSearch;

    protected $filterCategoryId;

    protected $showCategoryFilter;

    public function setApplication(CMSApplicationInterface $application): void
    {
        $this->application = $application;
    }

    private function getApplication(): CMSApplicationInterface
    {
        return $this->application ?? throw new \RuntimeException('Application not injected into Directory view');
    }

    public function display($tpl = null)
    {
        /** @var \JoomlaLabs\Component\Profiles\Site\Model\DirectoryModel $model */
        $model = $this->getModel();

        $this->state              = $this->get('State');
        $this->items              = $this->get('Items');
        $this->pagination         = $this->get('Pagination');
        $this->params             = $this->state->get('params');
        $this->categories         = $model->getAvailableCategories();
        $this->filterSearch       = (string) $this->state->get('filter.search');
        $this->filterCategoryId   = (int) $this->state->get('filter.category_id');
        $this->showCategoryFilter = \count($this->categories) > 1;

        $this->prepareDocument();

        parent::display($tpl);
    }

    private function prepareDocument(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app      = $this->getApplication();
        $menuItem = $app->getMenu()->getActive();
        $title    = $menuItem ? (string) $menuItem->title : Text::_('COM_JOOMLALABS_PROFILES_DIRECTORY_VIEW_DEFAULT_TITLE');

        $this->getDocument()->setTitle($title);
    }
}
