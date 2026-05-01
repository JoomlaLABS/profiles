<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Categories\SectionNotFoundException;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Fields\FieldsFormServiceInterface;
use Joomla\CMS\Fields\FieldsServiceTrait;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Language\Text;
use Psr\Container\ContainerInterface;

\defined('_JEXEC') or die;

class ProfilesComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, FieldsFormServiceInterface, RouterServiceInterface
{
    use HTMLRegistryAwareTrait;

    use RouterServiceTrait;

    use CategoryServiceTrait, FieldsServiceTrait {
        CategoryServiceTrait::prepareForm insteadof FieldsServiceTrait;
    }

    public function __construct(
        ComponentDispatcherFactoryInterface $dispatcherFactory,
        private readonly CMSApplicationInterface $app,
    ) {
        parent::__construct($dispatcherFactory);
    }

    public function boot(ContainerInterface $container)
    {
    }

    public function getCategory(array $options = [], $section = ''): CategoryInterface
    {
        $section = (string) $section;

        if ($section !== '' && $this->validateSection($section) === null) {
            throw new SectionNotFoundException();
        }

        $options['table'] ??= '#__joomlalabs_profiles';
        $options['extension'] ??= 'com_joomlalabs_profiles';
        $options['statefield'] ??= 'published';

        return new Categories($options);
    }

    public function validateSection($section, $item = null)
    {
        if ($this->app->isClient('site') && ($section === 'form' || $section === 'profiles' || $section === 'profile')) {
            $section = 'record';
        }

        if ($section === 'profile') {
            $section = 'record';
        }

        if ($section !== 'record') {
            return null;
        }

        return $section;
    }

    public function getContexts(): array
    {
        $this->app->getLanguage()->load('com_joomlalabs_profiles', JPATH_ADMINISTRATOR);

        return [
            'com_joomlalabs_profiles.record'     => Text::_('COM_JOOMLALABS_PROFILES_FIELDS_CONTEXT_PROFILE'),
            'com_joomlalabs_profiles.categories' => Text::_('JCATEGORY'),
        ];
    }

    protected function getTableNameForSection(?string $section = null)
    {
        return $section === 'category' ? 'categories' : 'joomlalabs_profiles';
    }

    protected function getStateColumnForSection(?string $section = null)
    {
        return 'published';
    }
}
