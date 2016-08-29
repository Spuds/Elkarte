<?php

/**
 * This Plugin file contains all the functions that allow for ElkArte to interface
 * with Bad Behavior.  Bad Behavior is
 * Copyright (C) 2005,2006,2007,2008,2009,2010,2011,2012 Michael Hampton
 * License: LGPLv3
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

// This die was left on purpose, because BB is special
if (!defined('ELK'))
{
	die('No access...');
}

define('BB2_CWD', dirname(__FILE__));

// Calls inward to Bad Behavior itself.
require_once(BB2_CWD . '/bad-behavior/core.inc.php');

/**
 * Return current time in the format preferred by your database.
 *
 * @return string
 */
function bb2_db_date()
{
	return time();
}

/**
 * Return affected rows from most recent query.
 *
 * @return int
 */
function bb2_db_affected_rows()
{
	$db = database();

	return $db->affected_rows();
}

/**
 * Escape a string for database usage
 *
 * @param string $string
 * @return string
 */
function bb2_db_escape($string)
{
	$db = database();

	return $db->escape_string($string);
}

/**
 * Return the number of rows in a particular query.
 *
 * @param object $result
 * @return int
 */
function bb2_db_num_rows($result)
{
	$db = database();

	return $db->num_rows($result);
}

/**
 * Run a query and return the results, if any.
 * Should return FALSE if an error occurred.
 * Bad Behavior will use the return value here in other callbacks.
 *
 * @param string $query
 * @return bool or int
 */
function bb2_db_query($query)
{
	$db = database();

	// First fix the horrors caused by bb's support of only mysql
	// ok they are right its my horror :P
	if (strpos($query, 'DATE_SUB') !== false)
		$query = 'DELETE FROM {db_prefix}log_badbehavior WHERE date < ' . (bb2_db_date() - 7 * 86400);
	elseif (strpos($query, 'OPTIMIZE TABLE') !== false)
		return true;
	elseif (strpos($query, '@@session.wait_timeout') !== false)
		return true;

	// Run the query, return success, failure or the actual results
	$result = $db->query('', $query, array());

	if (!$result)
		return false;
	elseif ($result === true)
		return (bb2_db_affected_rows() !== 0);
	elseif (bb2_db_num_rows($result) === 0)
		return false;

	return bb2_db_rows($result);
}

/**
 * Return all rows in a particular query.
 * Should contain an array of all rows generated by calling mysql_fetch_assoc()
 * or equivalent and appending the result of each call to an array.
 *
 * @param object $result
 * @return mixed[] associate array of query results
 */
function bb2_db_rows($result)
{
	$db = database();

	$temp = array();
	while ($row = $db->fetch_assoc($result))
		$temp[] = $row;
	$db->free_result($result);

	return $temp;
}

/**
 * Return emergency contact email address.
 *
 * @return string (email address)
 */
function bb2_email()
{
	global $webmaster_email;

	return $webmaster_email;
}

/**
 * Create the query for inserting a record in to the database.
 * This is the main logging function for logging and verbose levels.
 *
 * @param array $settings
 * @param array $package
 * @param string $key
 * @return string
 */
function bb2_insert($settings, $package, $key)
{
	global $user_info, $sc;

	// Logging not enabled
	if (!$settings['logging'])
		return '';

	// Clean the data that bb sent us
	$ip = bb2_db_escape($package['ip']);
	$date = (int) bb2_db_date();
	$request_method = bb2_db_escape($package['request_method']);
	$request_uri = bb2_db_escape($package['request_uri']);
	$server_protocol = bb2_db_escape($package['server_protocol']);
	$user_agent = bb2_db_escape($package['user_agent']);
	$member_id = (int) !empty($user_info['id']) ? $user_info['id'] : 0;
	$session = !empty($sc) ? (string) $sc : '';

	// Prepare the headers etc for db insertion
	// We are passed at least
	//	Host, User-Agent, Accept, Accept-Language, Accept-Encoding, DNT, Connection, Referer, Cookie, Authorization
	$headers = '';
	$length = 0;
	$skip = array('User-Agent', 'Accept-Encoding', 'DNT', 'X-Wap-Profile');
	foreach ($package['headers'] as $h => $v)
	{
		if (!in_array($h, $skip))
		{
			// Make sure this header it will fit in the db, if not move on to the next
			// @todo increase the db space to 512 or convert to text?
			$check = $length + Util::strlen($h) + Util::strlen($v) + 2;
			if ($check < 255)
			{
				$headers .= bb2_db_escape($h . ': ' .  $v . "\n");
				$length = $check;
			}
		}
	}

	$request_entity = '';
	if (!strcasecmp($request_method, "POST"))
	{
		foreach ($package['request_entity'] as $h => $v)
		{
			if (is_array($v))
				$v = bb2_multi_implode($v, ' | ');

			$request_entity .= bb2_db_escape("$h: $v\n");
		}

		// Only such much space in this column, so brutally cut it
		// @todo in 1.1 improve logging or drop this?
		$request_entity = substr($request_entity, 0, 254);

		// Make it safe for the db
		while (preg_match('~[\'\\\\]$~', substr($request_entity, -1)) === 1)
			$request_entity = substr($request_entity, 0, -1);
	}

	// Add it
	return "INSERT INTO {db_prefix}log_badbehavior
		(`ip`, `date`, `request_method`, `request_uri`, `server_protocol`, `http_headers`, `user_agent`, `request_entity`, `valid`, `id_member`, `session`) VALUES
		('$ip', '$date', '$request_method', '$request_uri', '$server_protocol', '$headers', '$user_agent', '$request_entity', '$key', '$member_id' , '$session')";
}

/**
 * Implode that is multi dimensional array aware
 *
 * - Recursively calls itself to return a single string from a multi dimensional
 * array
 *
 * @param mixed[] $array array to recursively implode
 * @param string $glue value that glues elements together
 * @param bool $trim_all trim ALL whitespace from string
 *
 * @return string
 */
function bb2_multi_implode($array, $glue = ',', $trim_all = false)
{
	if (!is_array($array))
		$array = array($array);

	foreach ($array as $key => $value)
	{
		if (is_array($value))
			$array[$key] = bb2_multi_implode($value, $glue, $trim_all);
	}

	if ($trim_all)
		$array = array_map('trim', $array);

	return implode($glue, $array);
}

/**
 * Retrieve whitelist
 *
 * @todo
 * @return type
 */
function bb2_read_whitelist()
{
	global $modSettings;

	// Current whitelist data
	$whitelist = array('badbehavior_ip_wl', 'badbehavior_useragent_wl', 'badbehavior_url_wl');
	foreach ($whitelist as $list)
	{
		$whitelist[$list] = array();
		if (!empty($modSettings[$list]))
		{
			$whitelist[$list] = Util::unserialize($modSettings[$list]);
			$whitelist[$list] = array_filter($whitelist[$list]);
		}
	}

	// Nothing in the whitelist
	if (empty($whitelist['badbehavior_ip_wl']) && empty($whitelist['badbehavior_useragent_wl']) && empty($whitelist['badbehavior_url_wl']))
		return false;

	// Build up the whitelist array so badbehavior can use it
	return array_merge(
		array('ip' => $whitelist['badbehavior_ip_wl']),
		array('url' => $whitelist['badbehavior_url_wl']),
		array('useragent' => $whitelist['badbehavior_useragent_wl'])
	);
}

/**
 * Retrieve bad behavior settings from database and supply them to
 * bad behavior so it knows to not behave badly
 *
 * @return mixed[]
 */
function bb2_read_settings()
{
	global $modSettings;

	$badbehavior_reverse_proxy = !empty($modSettings['badbehavior_reverse_proxy']);

	// Make sure that the proxy addresses are split into an array, and if it's empty - make sure reverse proxy is disabled
	if (!empty($modSettings['badbehavior_reverse_proxy_addresses']))
		$badbehavior_reverse_proxy_addresses = explode('|', trim($modSettings['badbehavior_reverse_proxy_addresses']));
	else
	{
		$badbehavior_reverse_proxy_addresses = array();
		$badbehavior_reverse_proxy = false;
	}

	// If they supplied a http:BL API Key lets see if it looks correct before we use it
	$invalid_badbehavior_httpbl_key = empty($modSettings['badbehavior_httpbl_key']) || (!empty($modSettings['badbehavior_httpbl_key']) && (strlen($modSettings['badbehavior_httpbl_key']) !== 12 || !ctype_lower($modSettings['badbehavior_httpbl_key'])));

	// Return the settings so BadBehavior can use them
	return array(
		'log_table' => '{db_prefix}log_badbehavior',
		'strict' => !empty($modSettings['badbehavior_strict']),
		'verbose' => !empty($modSettings['badbehavior_verbose']),
		'logging' => !empty($modSettings['badbehavior_logging']),
		'httpbl_key' => $invalid_badbehavior_httpbl_key ? '' : $modSettings['badbehavior_httpbl_key'],
		'httpbl_threat' => $modSettings['badbehavior_httpbl_threat'],
		'httpbl_maxage' => $modSettings['badbehavior_httpbl_maxage'],
		'eu_cookie' => !empty($modSettings['badbehavior_eucookie']),
		'offsite_forms' => !empty($modSettings['badbehavior_offsite_forms']),
		'reverse_proxy' => $badbehavior_reverse_proxy,
		'reverse_proxy_header' => $modSettings['badbehavior_reverse_proxy_header'],
		'reverse_proxy_addresses' => $badbehavior_reverse_proxy_addresses
	);
}

/**
 * Insert this into the <head> section of your HTML through a template call
 * or whatever is appropriate. This is optional we'll fall back to cookies
 * if you don't use it.
 */
function bb2_insert_head()
{
	global $bb2_javascript;

	// Prepare it so we can use addInlineJavascript by removing the script tags hats its pre wrapped in
	$temp = str_replace('<script type="text/javascript">' . "\n" . '<!--' . "\n", '', $bb2_javascript);
	$temp = str_replace('// --></script>', '', $temp);

	return "\n" . trim($temp);
}

/**
 * Display Statistics (default off)
 * Enabling this option will return a string to add a blurb to your site footer
 * advertising Bad Behavior’s presence and the number of recently blocked requests.
 *
 * This option is not available or has no effect when logging is not in use.
 *
 * @param bool $force
 */
function bb2_insert_stats($force = false)
{
	global $txt;

	$settings = bb2_read_settings();

	if ($force || $settings['display_stats'])
	{
		// Get the blocked count for the last 7 days ... cache this as well
		if (!Cache::instance()->getVar($bb2_blocked, 'bb2_blocked', 900))
		{
			$bb2_blocked = bb2_db_query('SELECT COUNT(*) FROM {db_prefix}log_badbehavior WHERE `valid` NOT LIKE \'00000000\'');
			Cache::instance()->put('bb2_blocked', $bb2_blocked, 900);
		}

		if ($bb2_blocked !== false)
			return sprintf($txt['badbehavior_blocked'], $bb2_blocked[0]['COUNT(*)']);
	}
}

/**
 * Return the top-level relative path of wherever we are (for cookies)
 *
 * @return string
 */
function bb2_relative_path()
{
	global $boardurl;

	return $boardurl;
}