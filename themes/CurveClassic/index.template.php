<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 */

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $context, $settings, $options, $txt;

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* What document type definition is being used? (for font size and other issues.)
		'xhtml' for an XHTML 1.0 document type definition.
		'html' for an HTML 4.01 document type definition. */
	$settings['doctype'] = 'xhtml';

	// The version this template/theme is for. This should probably be the version it was created for.
	$settings['theme_version'] = '2.0';

	// Use plain buttons - as opposed to text buttons?
	$settings['use_buttons'] = true;

	// Show sticky and lock status separate from topic icons?
	$settings['separate_sticky_lock'] = true;

	// Does this theme use the strict doctype?
	$settings['strict_doctype'] = false;

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = false;
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>';

	// load in any css from mods or themes so they can overwrite if wanted
	template_css();

	// load in any javascript files from mods and themes
	template_javascript();

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
	{
		echo '
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/rtl.css?alp21" />';

	if (!empty($context['theme_variant']))
		echo '
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/rtl', $context['theme_variant'], '.css?alp21" />';
	}

	echo '
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '', '
	<title>', $context['page_title_html_safe'], '</title>';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help" />
	<link rel="contents" href="', $scripturl, '" />', ($context['allow_search'] ? '
	<link rel="search" href="' . $scripturl . '?action=search" />' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?type=rss2;action=.xml" />
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $scripturl, '?type=atom;action=.xml" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['links']['next']))
		echo '<link rel="next" href="', $context['links']['next'], '" />';
	else if (!empty($context['current_topic']))
		echo '<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';
	if (!empty($context['links']['prev']))
		echo '<link rel="prev" href="', $context['links']['prev'], '" />';
	else if (!empty($context['current_topic']))
		echo '<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0" />';

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body id="', $context['browser_body_id'], '" class="action_', !empty($context['current_action']) ? htmlspecialchars($context['current_action']) : (!empty($context['current_board']) ?
		'messageindex' : (!empty($context['current_topic']) ? 'display' : 'home')), !empty($context['current_board']) ? ' board_' . htmlspecialchars($context['current_board']) : '', '">';
}

function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Wrapper div now echoes permanently for better layout options. h1 a is now target for "Go up" links.
	echo '
<div id="wrapper" ', !empty($settings['forum_width']) ? 'style="width: ' . $settings['forum_width'] . '"' : '', '>
	<div id="header">
		<div class="frame">
			<div id="top_section">
				<h1 class="forumtitle">
					<a href="', $scripturl, '" id="title">', empty($context['header_logo_url_html_safe']) ? $context['forum_name'] : '<img src="' . $context['header_logo_url_html_safe'] . '" alt="' . $context['forum_name'] . '" />', '</a>
				</h1>';

	// the upshrink image, right-floated
	echo '
				<img id="upshrink" src="', $settings['images_url'], '/upshrink.png" alt="*" title="', $txt['upshrink_description'], '" style="display: none;" />';

	echo empty($settings['site_slogan']) ? '
				<img id="logo" src="' . $settings['images_url'] . '/logo_elk.png" alt="Elkarte Community" title="Elkarte Community" />' : '
				<div id="siteslogan" class="floatright">' . $settings['site_slogan'] . '</div>', '
			</div>
			<div id="upper_wrap">
				<div id="upper_section" ', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
					<div class="user">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		if (!empty($context['user']['avatar']))
			echo '
						<p class="avatar"><a href="', $scripturl, '?action=profile">', $context['user']['avatar']['image'], '</a></p>';

			echo '
						<ul class="reset">
							<li class="greeting">', $txt['hello_member_ndt'], ' <span>', $context['user']['name'], '</span></li>
							<li><a href="', $scripturl, '?action=unread">', $txt['unread_since_visit'], '</a></li>
							<li><a href="', $scripturl, '?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '
							<li class="notice">', $txt['maintain_mode_on'], '</li>';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
							<li>', $context['unapproved_members_text'], '</li>';

		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '
							<li><a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a></li>';

		echo '
							<li>', $context['current_time'], '</li>
						</ul>';
	}
	// Otherwise they're a guest - this time ask them to either register or login - lazy bums...
	elseif (!empty($context['show_login_bar']))
	{
		echo '
						<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
						<form id="guest_form" action="', $scripturl, '?action=login2;quicklogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
							<div class="info">', sprintf($txt[$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest'], $txt['guest_title'], $scripturl . '?action=login'), '</div>
							<input type="text" name="user" size="10" class="input_text" />
							<input type="password" name="passwrd" size="10" class="input_password" />
							<select name="cookielength">
								<option value="60">', $txt['one_hour'], '</option>
								<option value="1440">', $txt['one_day'], '</option>
								<option value="10080">', $txt['one_week'], '</option>
								<option value="43200">', $txt['one_month'], '</option>
								<option value="-1" selected="selected">', $txt['forever'], '</option>
							</select>
							<input type="submit" value="', $txt['login'], '" class="button_submit" /><br />
							<div class="info">', $txt['quick_login_dec'], '</div>';

		if (!empty($modSettings['enableOpenID']))
			echo '
							<br /><input type="text" name="openid_identifier" size="25" class="input_text openid_login" />';

		echo '
							<input type="hidden" name="hash_passwrd" value="" />
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
							<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
						</form>';
	}

	echo '
					</div>
					<div class="news">';

	if ($context['allow_search'])
	{
		echo '
						<form id="search_form" action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8">
							<input type="text" name="search" value="" class="input_text" />&nbsp;';

		// Using the quick search dropdown?
		if (!empty($modSettings['search_dropdown']))
		{
			$selected = !empty($context['current_topic']) ? 'current_topic' : (!empty($context['current_board']) ? 'current_board' : 'all');

			echo '
							<select name="search_selection">
								<option value="all"', ($selected == 'all' ? ' selected="selected"' : ''), '>', $txt['search_entireforum'], ' </option>';

		// Can't limit it to a specific topic if we are not in one
		if (!empty($context['current_topic']))
			echo '
								<option value="topic"', ($selected == 'current_topic' ? ' selected="selected"' : ''), '>', $txt['search_thistopic'], '</option>';

		// Can't limit it to a specific board if we are not in one
		if (!empty($context['current_board']))
			echo '
								<option value="board"', ($selected == 'current_board' ? ' selected="selected"' : ''), '>', $txt['search_thisbrd'], '</option>';
			echo '
								<option value="members"', ($selected == 'members' ? ' selected="selected"' : ''), '>', $txt['search_members'], ' </option>
							</select>';
		}

		// Search within current topic?
		if (!empty($context['current_topic']))
			echo '
							<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_topic' : 'topic'), '" value="', $context['current_topic'], '" />';
		// If we're on a certain board, limit it to this board ;).
		elseif (!empty($context['current_board']))
			echo '
							<input type="hidden" name="', (!empty($modSettings['search_dropdown']) ? 'sd_brd[' : 'brd['), $context['current_board'], ']"', ' value="', $context['current_board'], '" />';

		echo '
							<input type="submit" name="search2" value="', $txt['search'], '" class="button_submit" />
							<input type="hidden" name="advanced" value="0" />
						</form>';
	}

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']) && !empty($context['random_news_line']))
		echo '
						<h2>', $txt['news'], ': </h2>
						<p>', $context['random_news_line'], '</p>';

	echo '
					</div>
				</div>';

	// Define the upper_section toggle in JavaScript.
	echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				var oMainHeaderToggle = new smc_Toggle({
					bToggleEnabled: true,
					bCurrentlyCollapsed: ', empty($options['collapse_header']) ? 'false' : 'true', ',
					aSwappableContainers: [
						\'upper_section\'
					],
					aSwapImages: [
						{
							sId: \'upshrink\',
							srcExpanded: smf_images_url + \'/upshrink.png\',
							altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
							srcCollapsed: smf_images_url + \'/upshrink2.png\',
							altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
						}
					],
					oThemeOptions: {
						bUseThemeSettings: smf_member_id == 0 ? false : true,
						sOptionName: \'collapse_header\',
						sSessionVar: smf_session_var,
						sSessionId: smf_session_id
					},
					oCookieOptions: {
						bUseCookie: smf_member_id == 0 ? true : false,
						sCookieName: \'upshrink\'
					}
				});
			// ]]></script>';

	// Show the menu here, according to the menu sub template.
	template_menu();

	// Custom banners and shoutboxes should be placed here, before the linktree.

	// Show the navigation tree.
	theme_linktree();
	echo '
			</div>
		</div>
	</div>';

	// The main content should go here.
	echo '
	<div id="content_section">
		<div id="main_content_section">';
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
		</div>
	</div>';

	// This markup gives the looks of the Curve theme without huge images.
	// The new "Go Down" target is here. Any added content will display globally.
	echo '
	<div id="lower_section">
		<div class="frame"><a id="bot"></a></div>
	</div>
</div>';

	// Show the "Powered by" and "Valid" logos, as well as the copyright. Remember, the copyright must be somewhere!
	// Footer is now full-width by default. Frame inside it will match theme wrapper width automatically.
	echo '
	<div id="footer_section">
		<div class="frame" ', !empty($settings['forum_width']) ? 'style="width: ' . $settings['forum_width'] . '"' : '', '>';

	// There is now a global "Go to top" link above the copyright.
	echo '
			<a href="#top" id="footer_uplink"><img src="', $settings['images_url'], '/upshrink.png" alt="*" title="', $txt['go_up'], '" /></a>
			<ul class="reset">
				<li class="copyright">', theme_copyright(), '</li>
				<li><a id="button_xhtml" href="http://validator.w3.org/check?uri=referer" target="_blank" class="new_win" title="', $txt['valid_xhtml'], '"><span>', $txt['xhtml'], '</span></a></li>
				', !empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']) && !empty($modSettings['rss_limit']) ? '<li><a id="button_rss" href="' . $scripturl . '?action=.xml;type=rss;limit=' . $modSettings['rss_limit'] . '" class="new_win"><span>' . $txt['rss'] . '</span></a></li>' : '', '
			</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
			<p>', sprintf($txt['page_created_full'], $context['load_time'], $context['load_queries']), '</p>';

	echo '
		</div>
	</div>';
}

function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// load in any javascipt that could be defered to the end of the page
	template_javascript(true);

	echo '
</body>
</html>';
}

/**
 * Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
 *
 * @param bool $force_show = false
 */
function theme_linktree($force_show = false)
{
	global $context, $settings, $options, $shown_linktree, $scripturl, $txt;

	// If linktree is empty, just return - also allow an override.
	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;

	echo '
	<div class="navigate_section">
		<ul>';

	// Each tree item has a URL and name. Some may have extra_before and extra_after.
	foreach ($context['linktree'] as $link_num => $tree)
	{
		echo '
			<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		// Show the link, including a URL if it should have one.
		echo $settings['linktree_link'] && isset($tree['url']) ? '
				<a href="' . $tree['url'] . '"><span>' . $tree['name'] . '</span></a>' : '<span>' . $tree['name'] . '</span>';

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		// Don't show a separator for the last one.
		if ($link_num != count($context['linktree']) - 1)
			echo ' &#187;';

		echo '
			</li>';
	}

	if ($context['user']['is_logged'])
	echo '
		<li class="unread_links">
			<a href="', $scripturl, '?action=unread" title="', $txt['unread_since_visit'], '">', $txt['view_unread_category'], '</a> -
			<a href="', $scripturl, '?action=unreadreplies" title="', $txt['show_unread_replies'], '">', $txt['unread_replies'], '</a>
		</li>';

	echo '
		</ul>
	</div>';

	$shown_linktree = true;
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<div id="main_menu">
			<ul class="dropmenu" id="menu_nav">';

	// Note: Menu markup has been cleaned up to remove unnecessary spans and classes.
	foreach ($context['menu_buttons'] as $act => $button)
	{
		echo '
				<li id="button_', $act, '" ', !empty($button['sub_buttons']) ? 'class="subsections"' :'', '>
					<a class="', $button['active_button'] ? 'active' : '', '" href="', $button['href'], '" ', isset($button['target']) ? 'target="' . $button['target'] . '"' : '', '>
						', $button['title'], '
					</a>';
		if (!empty($button['sub_buttons']))
		{
			echo '
					<ul>';

			foreach ($button['sub_buttons'] as $childbutton)
			{
				echo '
						<li ', !empty($childbutton['sub_buttons']) ? 'class="subsections"' :'', '>
							<a href="', $childbutton['href'], '" ' , isset($childbutton['target']) ? 'target="' . $childbutton['target'] . '"' : '', '>
								', $childbutton['title'], '
							</a>';
				// 3rd level menus :)
				if (!empty($childbutton['sub_buttons']))
				{
					echo '
							<ul>';

					foreach ($childbutton['sub_buttons'] as $grandchildbutton)
						echo '
								<li>
									<a href="', $grandchildbutton['href'], '" ' , isset($grandchildbutton['target']) ? 'target="' . $grandchildbutton['target'] . '"' : '', '>
										', $grandchildbutton['title'], '
									</a>
								</li>';

					echo '
							</ul>';
				}

				echo '
						</li>';
			}
				echo '
					</ul>';
		}
		echo '
				</li>';
	}

	echo '
			</ul>
		</div>';
}

/**
 * Generate a strip of buttons.
 * @param array $button_strip
 * @param string $direction = ''
 * @param array $strip_options = array()
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	if (!is_array($strip_options))
		$strip_options = array();

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// @todo this check here doesn't make much sense now (from 2.1 on), it should be moved to where the button array is generated
		// Kept for backward compatibility
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="button_strip_' . $key . (isset($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '><span>' . $txt[$value['text']] . '</span></a></li>';
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>
			<ul>',
				implode('', $buttons), '
			</ul>
		</div>';
}

function template_show_error($error_id)
{
	global $context;

	if (empty($error_id))
		return;

	$error = !empty($context[$error_id]) ? $context[$error_id] : null;

	echo '
					<div class="', (!isset($error['type']) ? 'infobox' : ($error['type'] !== 'serious' ? 'noticebox' : 'errorbox')), '" ', empty($error['errors']) ? ' style="display: none"' : '', ' id="', $error_id, '">';
	if (!empty($error['title']))
		echo '
						<dl>
							<dt>
								<strong id="', $error_id, '_title">', $error['title'], '</strong>
							</dt>
							<dd>';
	if (!empty($error['errors']))
	{
		echo '
								<ul class="error" id="', $error_id, '_list">';

		foreach ($error['errors'] as $key => $error)
			echo '
									<li id="', $error_id, '_', $key, '">', $error, '</li>';
		echo '
								</ul>';
	}
	if (!empty($error['title']))
		echo '
							</dd>
						</dl>';
	echo '
					</div>';
}