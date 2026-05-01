<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\MVC\Factory;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactory;

\defined('_JEXEC') or die;

final class ProfilesMVCFactory extends MVCFactory
{
    public function __construct(
        string $namespace,
        private readonly CMSApplicationInterface $application,
    ) {
        parent::__construct($namespace);
    }

    public function createModel($name, $prefix = '', array $config = [])
    {
        $model = parent::createModel($name, $prefix, $config);

        $this->injectApplication($model);

        return $model;
    }

    public function createView($name, $prefix = '', $type = '', array $config = [])
    {
        $view = parent::createView($name, $prefix, $type, $config);

        $this->injectApplication($view);

        return $view;
    }

    public function createTable($name, $prefix = '', array $config = [])
    {
        $table = parent::createTable($name, $prefix, $config);

        $this->injectApplication($table);

        return $table;
    }

    private function injectApplication(object|null $object): void
    {
        if ($object && method_exists($object, 'setApplication')) {
            $object->setApplication($this->application);
        }
    }
}
