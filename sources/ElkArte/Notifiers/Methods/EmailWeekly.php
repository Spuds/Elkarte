<?php

/**
 * This class takes care of sending a notification as weekly email digest
 *
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 *
 */

namespace ElkArte\Notifiers;

use ElkArte\Notifiers\NotifierInterface;

/**
 * Class Notifications
 *
 * Core area for notifications, defines the abstract model
 */
class EmailWeekly implements NotifierInterface
{
	/**
	 * Hash defining what is needed to build the message
	 *
	 * @var string[]
	 */
	public $lang_data;

	/**
	 * Notifications constructor.
	 *
	 * Registers the known notifications to the system, allows for integration to add more
	 *
	 * @param \ElkArte\Database\QueryInterface $db
	 * @param \ElkArte\UserInfo|null $user
	 * @throws \ElkArte\Exceptions\Exception
	 */
	public function __construct($db, $user)
	{
		parent::__construct($db, $user);
		require_once(SUBSDIR . '/Mail.subs.php');

		$this->lang_data = ['subject' => 'subject', 'body' => 'snippet', 'suffix' => true];
	}

	/**
	 * {@inheritdoc }
	 */
	public function send($obj, $task, $bodies)
	{
		$this->_send_weekly_email($obj, $task, $bodies);
	}

	/**
	 * Stores data in the database to send a weekly digest.
	 *
	 * @param \ElkArte\Mentions\MentionType\NotificationInterface $obj
	 * @param \ElkArte\NotificationsTask $task
	 * @param mixed[] $bodies
	 */
	protected function _send_weekly_email(NotificationInterface $obj, NotificationsTask $task, $bodies)
	{
		foreach ($bodies as $body)
		{
			if (!in_array($body['id_member_to'], [0, $this->user->id]))
			{
				$this->_insert_delayed([
					$task['notification_type'],
					$body['id_member_to'],
					$task['log_time'],
					'w',
					$body['body']
				]);
			}
		}
	}

	/**
	 * Do the insert into the database for daily and weekly digests.
	 *
	 * @param mixed[] $insert_array
	 */
	protected function _insert_delayed($insert_array)
	{
		$this->_db->insert('ignore',
			'{db_prefix}pending_notifications',
			[
				'notification_type' => 'string-10',
				'id_member' => 'int',
				'log_time' => 'int',
				'frequency' => 'string-1',
				'snippet' => 'string',
			],
			$insert_array,
			[]
		);
	}
}
