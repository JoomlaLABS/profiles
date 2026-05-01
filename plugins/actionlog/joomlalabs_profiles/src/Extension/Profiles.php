<?php

declare(strict_types=1);

/**
 * @package     Joomla.Plugin
 * @subpackage  Actionlog.joomlalabs_profiles
 *
 * @copyright   (C) 2026 Joomla!LABS. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaLabs\Plugin\Actionlog\Profiles\Extension;

use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper;
use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Utilities\ArrayHelper;

\defined('_JEXEC') or die;

final class Profiles extends ActionLogPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private const COMPONENT_CONTEXT_PATTERN = '/^com_joomlalabs_profiles\.(profile|record)(?:\.|$)/';

    private const TYPE_LANGUAGE_KEY = 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_TYPE_PROFILE';

    private const CONTENT_TYPE = 'profile';

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave'   => 'onContentAfterSave',
            'onContentAfterDelete' => 'onContentAfterDelete',
            'onContentChangeState' => 'onContentChangeState',
        ];
    }

    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        $context = $event->getContext();

        if (!$this->supportsContext($context)) {
            return;
        }

        $item  = $event->getItem();
        $isNew = $event->getIsNew();
        $task  = strtolower((string) $this->getApplication()->getInput()->getCmd('task', ''));

        $isRestore = !$isNew && str_ends_with($task, '.loadhistory');

        $id = (int) ($item->id ?? 0);

        $message = [
            'action'   => $isRestore ? 'restore' : ($isNew ? 'add' : 'update'),
            'type'     => self::TYPE_LANGUAGE_KEY,
            'id'       => $id,
            'title'    => (string) ($item->display_name ?? ''),
            'itemlink' => $this->getProfileItemLink($id),
        ];

        if ($isRestore) {
            $versionId = $this->getApplication()->getInput()->getInt('version_id', 0);

            if ($versionId > 0) {
                $message['version_id'] = $versionId;
            }
        }

        $this->addLog(
            [$message],
            $isNew
                ? 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_ADDED'
                : ($isRestore
                    ? 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_RESTORED'
                    : 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_UPDATED'),
            $context
        );
    }

    public function onContentAfterDelete(AfterDeleteEvent $event): void
    {
        $context = $event->getContext();

        if (!$this->supportsContext($context)) {
            return;
        }

        $item = $event->getItem();

        $message = [
            'action' => 'delete',
            'type'   => self::TYPE_LANGUAGE_KEY,
            'id'     => (int) ($item->id ?? 0),
            'title'  => (string) ($item->display_name ?? ''),
        ];

        $this->addLog([$message], 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_DELETED', $context);
    }

    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        $context = $event->getContext();

        if (!$this->supportsContext($context)) {
            return;
        }

        $state = (int) $event->getValue();
        $map   = $this->mapStateToLogData($state);

        if ($map === null) {
            return;
        }

        $pks = array_values(array_filter(array_map('intval', (array) $event->getPks()), static fn (int $id): bool => $id > 0));

        if ($pks === []) {
            return;
        }

        $titles   = $this->loadProfileTitlesByIds($pks);
        $messages = [];

        foreach ($pks as $id) {
            $messages[] = [
                'action'   => $map['action'],
                'type'     => self::TYPE_LANGUAGE_KEY,
                'id'       => $id,
                'title'    => $titles[$id] ?? ('#' . $id),
                'itemlink' => $this->getProfileItemLink($id),
            ];
        }

        $this->addLog($messages, $map['language_key'], $context);
    }

    private function supportsContext(string $context): bool
    {
        $context = strtolower(trim($context));

        if ($context === '') {
            return false;
        }

        return (bool) preg_match(self::COMPONENT_CONTEXT_PATTERN, $context);
    }

    private function mapStateToLogData(int $state): ?array
    {
        return match ($state) {
            1       => ['action' => 'publish', 'language_key' => 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_PUBLISHED'],
            0       => ['action' => 'unpublish', 'language_key' => 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_UNPUBLISHED'],
            2       => ['action' => 'archive', 'language_key' => 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_ARCHIVED'],
            -2      => ['action' => 'trash', 'language_key' => 'PLG_ACTIONLOG_JOOMLALABS_PROFILES_PROFILE_TRASHED'],
            default => null,
        };
    }

    private function loadProfileTitlesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([$db->quoteName('id'), $db->quoteName('display_name')])
            ->from($db->quoteName('#__joomlalabs_profiles'))
            ->whereIn($db->quoteName('id'), ArrayHelper::toInteger($ids));

        $db->setQuery($query);

        try {
            $rows = (array) $db->loadObjectList();
        } catch (\RuntimeException) {
            return [];
        }

        $titles = [];

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);

            if ($id > 0) {
                $titles[$id] = (string) ($row->display_name ?? '');
            }
        }

        return $titles;
    }

    private function getProfileItemLink(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        return ActionlogsHelper::getContentTypeLink('com_joomlalabs_profiles', self::CONTENT_TYPE, $id, 'id', null);
    }
}
