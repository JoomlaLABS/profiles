<?php

declare(strict_types=1);

/**
 * @package     Joomla.Administrator
 * @subpackage  com_joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Component\Profiles\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\User\CurrentUserTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

\defined('_JEXEC') or die;

class ProfileTable extends Table implements CurrentUserInterface
{
    use CurrentUserTrait;

    protected $_supportNullValue = true;

    protected $_jsonEncode = ['params', 'metadata'];

    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_joomlalabs_profiles.record';

        parent::__construct('#__joomlalabs_profiles', 'id', $db, $dispatcher);

        $this->setColumnAlias('title', 'display_name');
    }

    public function store($updateNulls = true)
    {
        $date   = (new Date())->toSql();
        $userId = $this->getCurrentUser()->id;

        if (empty($this->created)) {
            $this->created = $date;
        }

        if ($this->id) {
            $this->modified_by = $userId;
            $this->modified    = $date;
        } else {
            if (empty($this->created_by)) {
                $this->created_by = $userId;
            }

            if (empty($this->modified)) {
                $this->modified = $date;
            }

            if (empty($this->modified_by)) {
                $this->modified_by = $userId;
            }
        }

        $table = new self($this->getDatabase(), $this->getDispatcher());

        if ($table->load(['alias' => $this->alias, 'catid' => $this->catid]) && ($table->id != $this->id || $this->id == 0)) {
            $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_UNIQUE_ALIAS'));

            return false;
        }

        return parent::store($updateNulls);
    }

    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        if (!(int) $this->catid) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_CATEGORY_REQUIRED'));

            return false;
        }

        if (trim((string) $this->display_name) === '') {
            $this->setError(Text::_('COM_JOOMLALABS_PROFILES_ERROR_DISPLAY_NAME_REQUIRED'));

            return false;
        }

        $this->generateAlias();

        if (empty($this->params)) {
            $this->params = '{}';
        }

        if (empty($this->metadata)) {
            $this->metadata = '{}';
        }

        if (empty($this->modified)) {
            $this->modified = $this->created;
        }

        if (empty($this->modified_by)) {
            $this->modified_by = $this->created_by;
        }

        return true;
    }

    public function generateAlias()
    {
        if (empty($this->alias)) {
            $this->alias = $this->display_name;
        }

        $this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language ?: '*');

        if (trim(str_replace('-', '', $this->alias)) === '') {
            $this->alias = (new Date())->format('Y-m-d-H-i-s');
        }

        return $this->alias;
    }

    public function getTypeAlias()
    {
        return $this->typeAlias;
    }
}
