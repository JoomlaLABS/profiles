<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  Privacy.joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Plugin\Privacy\Profiles\Extension;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\Privacy\CanRemoveDataEvent;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Component\Privacy\Administrator\Removal\Status;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class Profiles extends PrivacyPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPrivacyCanRemoveData' => 'onPrivacyCanRemoveData',
            'onPrivacyRemoveData'    => 'onPrivacyRemoveData',
            'onPrivacyExportRequest' => 'onPrivacyExportRequest',
        ];
    }

    public function onPrivacyCanRemoveData(CanRemoveDataEvent $event)
    {
        $event->addResult(new Status());
    }

    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $db     = $this->getDatabase();
        $domain = $this->createDomain('profiles', 'joomlalabs_profiles_data');

        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->bind(':user_id', $user->id, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        $items = $db->setQuery($query)->loadObjectList();

        foreach ($items as $item) {
            $domain->addItem($this->createItemFromArray((array) $item, (int) $item->id));
        }

        $event->addResult([
            $domain,
            $this->createCustomFieldsDomain('com_joomlalabs_profiles.record', $items),
        ]);
    }

    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $db       = $this->getDatabase();
        $modified = (new Date())->toSql();
        $query    = $db->createQuery()
            ->update($db->quoteName('#__joomlalabs_profiles'))
            ->set($db->quoteName('user_id') . ' = NULL')
            ->set($db->quoteName('modified') . ' = :modified')
            ->where($db->quoteName('user_id') . ' = :user_id')
            ->bind(':modified', $modified)
            ->bind(':user_id', $user->id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }
}
