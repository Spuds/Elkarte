<?php

/**
 * TestCase class for profile summary info actions
 *
 * Functional testing of web pages requests
 *
 * @backupGlobals disabled
 */
class ProfileInfoController extends ElkArteWebSupport
{
	public function setUpPage($url = '', $login = false)
	{
		$this->url = 'index.php?action=profile;area=summary';
		$this->login = true;
		parent::setUpPage();
	}

	/**
	 * Just cruise around the info area
	 */
	public function testShowPosts()
	{
		// First cruise the post tabs
		$this->bylinkText("Show Posts")->click();
		$this->assertStringContainsString('Messages', $this->byCssSelector("#recentposts > .category_header")->text(), $this->source());

   		$this->bylinkText("Topics")->click();
		$this->assertStringContainsString('Topics', $this->byCssSelector("#recentposts > .category_header")->text(), $this->source());

	  	$this->bylinkText("Attachments")->click();
		$this->assertStringContainsString('Attachments', $this->byCssSelector("#wrapper_profile_attachments > .category_header")->text(), $this->source());

		// On to stats
		$this->bylinkText('Profile Info')->click();
		$this->bylinkText("Show Stats")->click();
		$this->assertStringContainsString('User statistics', $this->title(), $this->source());
	}

	/**
	 * Look at the permissions, your the admin so you have them all
	 */
	public function testPerms()
	{
		$this->bylinkText("Profile Info")->click();
		$this->bylinkText("Show Permissions")->click();
		$this->assertStringContainsString('As an administrator', $this->byCssSelector(".description")->text());
	}
}
