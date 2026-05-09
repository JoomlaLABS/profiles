<?php

declare(strict_types=1);

/**
 * @package     Joomla.Site
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Site\View\Profile;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use JoomlaLabs\Component\Profiles\Site\Model\ProfileModel;

\defined('_JEXEC') or die;

class HtmlView extends BaseHtmlView
{
    private ?CMSApplicationInterface $application = null;

    protected $item;

    protected $params;

    protected $fieldGroups;

    protected $hasDetails;

    public function setApplication(CMSApplicationInterface $application): void
    {
        $this->application = $application;
    }

    private function getApplication(): CMSApplicationInterface
    {
        return $this->application ?? throw new \RuntimeException('Application not injected into Profile view');
    }

    public function display($tpl = null)
    {
        $model = $this->getModel();
        $input = $this->getApplication()->getInput();

        $this->item   = $this->get('Item');
        $this->params = $this->get('State')->get('params');

        if (!$this->item) {
            throw new \Exception(Text::_('COM_JOOMLALABS_PROFILES_PROFILE_NOT_FOUND'), 404);
        }

        if (!$model instanceof ProfileModel) {
            throw new \RuntimeException('Profile model not available for profile view rendering.');
        }

        /** @var ProfileModel $model */

        $this->item->event                       = new \stdClass();
        $this->item->event->afterDisplayTitle    = $model->renderFieldsByDisplay($this->item, 1);
        $this->item->event->beforeDisplayContent = '';
        $this->item->event->afterDisplayContent  = $model->renderFieldsByDisplay($this->item, 3);
        $this->fieldGroups                       = $model->getGroupedFields($this->item, 2);
        $this->hasDetails                        = $this->fieldGroups !== []
            || $this->item->event->afterDisplayTitle !== ''
            || $this->item->event->afterDisplayContent !== '';

        $requestedLayout = trim((string) $this->params->get('profile_layout', ''));

        if ($requestedLayout === '') {
            $requestedLayout = trim((string) $input->getCmd('layout', ''));
        }

        if ($requestedLayout === '') {
            $requestedLayout = 'default';
        }

        if ($requestedLayout !== '' && $requestedLayout !== 'default') {
            $this->setLayout($requestedLayout);
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    private function prepareDocument(): void
    {
        $this->preparePathway();
        $this->getDocument()->setTitle((string) $this->item->display_name);
    }

    private function preparePathway(): void
    {
        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $app      = $this->getApplication();
        $menuItem = $app->getMenu()->getActive();

        if (!$menuItem instanceof MenuItem) {
            return;
        }

        if ($this->isSingleProfileMenuItem($menuItem) && $this->menuTargetsCurrentProfile($menuItem)) {
            return;
        }

        if (!$this->isDirectoryMenuItem($menuItem)) {
            return;
        }

        $model = $this->getModel();

        if (!$model instanceof ProfileModel) {
            return;
        }

        $rootCategoryId = (int) ($menuItem->query['root_category_id'] ?? 0);
        $pathway        = $app->getPathway();

        foreach ($model->getCategoryPathwayItems((int) $this->item->catid, $rootCategoryId) as $category) {
            $pathway->addItem((string) $category->title, '');
        }

        $pathway->addItem((string) $this->item->display_name, '');
    }

    private function isDirectoryMenuItem(MenuItem $menuItem): bool
    {
        return ($menuItem->component ?? '') === 'com_joomlalabs_profiles'
            && ($menuItem->query['view'] ?? '') === 'directory';
    }

    private function isSingleProfileMenuItem(MenuItem $menuItem): bool
    {
        return ($menuItem->component ?? '') === 'com_joomlalabs_profiles'
            && ($menuItem->query['view'] ?? '') === 'profile';
    }

    private function menuTargetsCurrentProfile(MenuItem $menuItem): bool
    {
        $menuProfileId = (int) strtok((string) ($menuItem->query['id'] ?? ''), ':');

        return $menuProfileId > 0 && $menuProfileId === (int) $this->item->id;
    }
}
