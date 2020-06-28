<?php

/**
 * TestCase class for the Profile Options Controller
 */
class TestProfileOptions extends ElkArteCommonSetupTest
{
	protected $backupGlobalsBlacklist = ['user_info'];

	/**
	 * Initialize or add whatever necessary for these tests
	 */
	public function setUp()
	{
		global $context, $cur_profile;

		parent::setUp();

		new ElkArte\Themes\ThemeLoader();
		theme()->getTemplates()->loadLanguageFile('Profile', 'english', true, true);

		// Some tricks, maybe
		require_once(SUBSDIR . '/Profile.subs.php');
		\ElkArte\MembersList::load('1', false, 'profile');
		$cur_profile = \ElkArte\MembersList::get('1');
		$context['user']['is_owner'] = true;
		$context['id_member'] = 1;

		$db = database();

		$db->insert('',
			'{db_prefix}notifications_pref',
			array(
				'id_member' => 'int',
				'notification_type' => 'string',
				'mention_type' => 'string',
			),
			array(
				array(
					'id_member' => 1,
					'notification_type' => 'notification',
					'mention_type' => 'likemsg'
				),
				array(
					'id_member' => 1,
					'notification_type' => 'email',
					'mention_type' => 'mentionmem'
				),
				array(
					'id_member' => 1,
					'notification_type' => 'email',
					'mention_type' => 'likemsg'
				),
				array(
					'id_member' => 2,
					'notification_type' => 'notification',
					'mention_type' => 'mentionmem'
				),
				array(
					'id_member' => 2,
					'notification_type' => 'email',
					'mentionmem'
				),
				array(
					'id_member' => 2,
					'notification_type' => 'email',
					'mention_type' => 'likemsg'
				),
			),
			array('id_member', 'mention_type')
		);
	}

	/**
	 * cleanup data we no longer need at the end of the tests in this class.
	 * tearDown() is run automatically by the testing framework after each test method.
	 */
	public function tearDown()
	{
		$db = database();
		$db->query('', '
			DELETE FROM {db_prefix}notifications_pref', []);
	}

	/**
	 * Test the settings for profile account
	 */
	public function testProfileAccount()
	{
		global $context;

		$controller = new \ElkArte\Controller\ProfileOptions(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_account();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertNotNull($context['profile_fields']);
		$this->assertEquals('test_admin', $context['profile_fields']['member_name']['value']);
	}

	/**
	 * Not run yet, the avatar data 'name' is not loading?
	 * @todo figure that out and change name to testForumProfile
	 */
	public function ForumProfile()
	{
		global $context;

		$controller = new \ElkArte\Controller\ProfileOptions(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_forumProfile();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertEquals('signature_modify', $context['profile_fields']['signature']['callback_func']);
	}

	public function testNotification()
	{
		global $context;

		$context['menu_item_selected'] = 'notification';
		$context['token_check'] = 'profile-u1';
		$context['profile-u1_token_var'] = 'profile-u1';
		$context['profile-u1_token'] = 'profile-u1';

		$controller = new \ElkArte\Controller\ProfileOptions(new \ElkArte\EventManager());
		$controller->setUser(\ElkArte\User::$info);
		$controller->pre_dispatch();
		$controller->action_notification();

		// Lets see some items loaded into $context['profile_fields']
		$this->assertEquals('0', $context['member']['notify_announcements']);
		$this->assertNotNull($context['topic_notification_list']['additional_rows']['bottom_of_list']);
	}
}
