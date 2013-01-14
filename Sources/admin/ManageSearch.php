<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * The admin screen to change the search settings.
 *
 */

if (!defined('ELKARTE'))
	die('Hacking attempt...');

/**
 * Main entry point for the admin search settings screen.
 * It checks permissions, and it forwards to the appropriate function based on
 * the given sub-action.
 * Defaults to sub-action 'settings'.
 * Called by ?action=admin;area=managesearch.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template.
 * @uses Search language file.
 */
function ManageSearch()
{
	global $context, $txt, $scripturl;

	isAllowedTo('admin_forum');

	loadLanguage('Search');
	loadTemplate('ManageSearch');

	db_extend('search');

	$subActions = array(
		'settings' => 'EditSearchSettings',
		'weights' => 'EditWeights',
		'method' => 'EditSearchMethod',
		'createfulltext' => 'EditSearchMethod',
		'removecustom' => 'EditSearchMethod',
		'removefulltext' => 'EditSearchMethod',
		'createmsgindex' => 'CreateMessageIndex',
		'managesphinx' => 'EditSphinxSettings',
	);

	call_integration_hook('integrate_manage_search', array($subActions));

	// Default the sub-action to 'edit search settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'weights';

	$context['sub_action'] = $_REQUEST['sa'];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manage_search'],
		'help' => 'search',
		'description' => $txt['search_settings_desc'],
		'tabs' => array(
			'weights' => array(
				'description' => $txt['search_weights_desc'],
			),
			'method' => array(
				'description' => $txt['search_method_desc'],
			),
			'settings' => array(
				'description' => $txt['search_settings_desc'],
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

/**
 * Edit some general settings related to the search function.
 * Called by ?action=admin;area=managesearch;sa=settings.
 * Requires the admin_forum permission.
 *
 * @param $return_config
 * @uses ManageSearch template, 'modify_settings' sub-template.
 */
function EditSearchSettings($return_config = false)
{
	global $txt, $context, $scripturl, $sourcedir, $modSettings;

	// What are we editing anyway?
	$config_vars = array(
			// Permission...
			array('permissions', 'search_posts'),
			// Some simple settings.
			array('check', 'simpleSearch'),
			array('check', 'search_dropdown'),
			array('int', 'search_results_per_page'),
			array('int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']),
		'',
			// Some limitations.
			array('int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc'], 6, 'postinput' => $txt['seconds']),
	);

	call_integration_hook('integrate_modify_search_settings', array($config_vars));

	// Perhaps the search method wants to add some settings?
	require_once($sourcedir . '/Search.php');
	$searchAPI = findSearchAPI();
	if (is_callable(array($searchAPI, 'searchSettings')))
		call_user_func_array($searchAPI->searchSettings, array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['search_settings_title'];
	$context['sub_template'] = 'show_settings';

	// We'll need this for the settings.
	loadAdminClass ('ManageServer.php');

	// A form was submitted.
	if (isset($_REQUEST['save']))
	{
		checkSession();

		call_integration_hook('integrate_save_search_settings');

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Prep the template!
	$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
	$context['settings_title'] = $txt['search_settings_title'];

	// We need this for the in-line permissions
	createToken('admin-mp');

	prepareDBSettingContext($config_vars);
}

/**
 * Edit the relative weight of the search factors.
 * Called by ?action=admin;area=managesearch;sa=weights.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template, 'modify_weights' sub-template.
 */
function EditWeights()
{
	global $txt, $context, $modSettings;

	$context['page_title'] = $txt['search_weights_title'];
	$context['sub_template'] = 'modify_weights';

	$factors = array(
		'search_weight_frequency',
		'search_weight_age',
		'search_weight_length',
		'search_weight_subject',
		'search_weight_first_message',
		'search_weight_sticky',
	);

	call_integration_hook('integrate_modify_search_weights', array($factors));

	// A form was submitted.
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-msw');

		call_integration_hook('integrate_save_search_weights');

		$changes = array();
		foreach ($factors as $factor)
			$changes[$factor] = (int) $_POST[$factor];
		updateSettings($changes);
	}

	$context['relative_weights'] = array('total' => 0);
	foreach ($factors as $factor)
		$context['relative_weights']['total'] += isset($modSettings[$factor]) ? $modSettings[$factor] : 0;

	foreach ($factors as $factor)
		$context['relative_weights'][$factor] = round(100 * (isset($modSettings[$factor]) ? $modSettings[$factor] : 0) / $context['relative_weights']['total'], 1);

	createToken('admin-msw');
}

/**
 * Edit the search method and search index used.
 * Calculates the size of the current search indexes in use.
 * Allows to create and delete a fulltext index on the messages table.
 * Allows to delete a custom index (that CreateMessageIndex() created).
 * Called by ?action=admin;area=managesearch;sa=method.
 * Requires the admin_forum permission.
 *
 * @uses ManageSearch template, 'select_search_method' sub-template.
 */
function EditSearchMethod()
{
	global $txt, $context, $modSettings, $smcFunc, $db_type, $db_prefix;

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_method_title'];
	$context['sub_template'] = 'select_search_method';
	$context['supports_fulltext'] = $smcFunc['db_search_support']('fulltext');

	// Load any apis.
	$context['search_apis'] = loadSearchAPIs();

	// Detect whether a fulltext index is set.
	if ($context['supports_fulltext'])
		detectFulltextIndex();

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'createfulltext')
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		// Make sure it's gone before creating it.
		$smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}messages
			DROP INDEX body',
			array(
				'db_error_skip' => true,
			)
		);

		$smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}messages
			ADD FULLTEXT body (body)',
			array(
			)
		);

		$context['fulltext_index'] = 'body';
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty($context['fulltext_index']))
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		$smcFunc['db_query']('', '
			ALTER TABLE {db_prefix}messages
			DROP INDEX ' . implode(',
			DROP INDEX ', $context['fulltext_index']),
			array(
				'db_error_skip' => true,
			)
		);

		$context['fulltext_index'] = '';

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removecustom')
	{
		checkSession('get');
		validateToken('admin-msm', 'get');

		db_extend();
		$tables = $smcFunc['db_list_tables'](false, $db_prefix . 'log_search_words');
		if (!empty($tables))
		{
			$smcFunc['db_search_query']('drop_words_table', '
				DROP TABLE {db_prefix}log_search_words',
				array(
				)
			);
		}

		updateSettings(array(
			'search_custom_index_config' => '',
			'search_custom_index_resume' => '',
		));

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-msmpost');

		updateSettings(array(
			'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], array('fulltext', 'custom')) && !isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
			'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
			'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
		));
	}

	$context['table_info'] = array(
		'data_length' => 0,
		'index_length' => 0,
		'fulltext_length' => 0,
		'custom_index_length' => 0,
	);

	// Get some info about the messages table, to show its size and index size.
	if ($db_type == 'mysql')
	{
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);
		if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $smcFunc['db_fetch_assoc']($request);
			$context['table_info']['data_length'] = $row['Data_length'];
			$context['table_info']['index_length'] = $row['Index_length'];
			$context['table_info']['fulltext_length'] = $row['Index_length'];
			$smcFunc['db_free_result']($request);
		}

		// Now check the custom index table, if it exists at all.
		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
				)
			);
		else
			$request = $smcFunc['db_query']('', '
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
				)
			);
		if ($request !== false && $smcFunc['db_num_rows']($request) == 1)
		{
			// Only do this if the user has permission to execute this query.
			$row = $smcFunc['db_fetch_assoc']($request);
			$context['table_info']['index_length'] += $row['Data_length'] + $row['Index_length'];
			$context['table_info']['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
			$smcFunc['db_free_result']($request);
		}
	}
	elseif ($db_type == 'postgresql')
	{
		// In order to report the sizes correctly we need to perform vacuum (optimize) on the tables we will be using.
		db_extend();
		$temp_tables = $smcFunc['db_list_tables']();
		foreach ($temp_tables as $table)
			if ($table == $db_prefix. 'messages' || $table == $db_prefix. 'log_search_words')
				$smcFunc['db_optimize_table']($table);

		// PostGreSql has some hidden sizes.
		$request = $smcFunc['db_query']('', '
			SELECT relname, relpages * 8 *1024 AS "KB" FROM pg_class
			WHERE relname = {string:messages} OR relname = {string:log_search_words}
			ORDER BY relpages DESC',
			array(
				'messages' => $db_prefix. 'messages',
				'log_search_words' => $db_prefix. 'log_search_words',
			)
		);

		if ($request !== false && $smcFunc['db_num_rows']($request) > 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if ($row['relname'] == $db_prefix . 'messages')
				{
					$context['table_info']['data_length'] = (int) $row['KB'];
					$context['table_info']['index_length'] = (int) $row['KB'];

					// Doesn't support fulltext
					$context['table_info']['fulltext_length'] = $txt['not_applicable'];
				}
				elseif ($row['relname'] == $db_prefix. 'log_search_words')
				{
					$context['table_info']['index_length'] = (int) $row['KB'];
					$context['table_info']['custom_index_length'] = (int) $row['KB'];
				}
			}
			$smcFunc['db_free_result']($request);
		}
		else
			// Didn't work for some reason...
			$context['table_info'] = array(
				'data_length' => $txt['not_applicable'],
				'index_length' => $txt['not_applicable'],
				'fulltext_length' => $txt['not_applicable'],
				'custom_index_length' => $txt['not_applicable'],
			);
	}
	else
		$context['table_info'] = array(
			'data_length' => $txt['not_applicable'],
			'index_length' => $txt['not_applicable'],
			'fulltext_length' => $txt['not_applicable'],
			'custom_index_length' => $txt['not_applicable'],
		);

	// Format the data and index length in kilobytes.
	foreach ($context['table_info'] as $type => $size)
	{
		// If it's not numeric then just break.  This database engine doesn't support size.
		if (!is_numeric($size))
			break;

		$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}

	$context['custom_index'] = !empty($modSettings['search_custom_index_config']);
	$context['partial_custom_index'] = !empty($modSettings['search_custom_index_resume']) && empty($modSettings['search_custom_index_config']);
	$context['double_index'] = !empty($context['fulltext_index']) && $context['custom_index'];

	createToken('admin-msmpost');
	createToken('admin-msm', 'get');
}

/**
 * Create a custom search index for the messages table.
 * Called by ?action=admin;area=managesearch;sa=createmsgindex.
 * Linked from the EditSearchMethod screen.
 * Requires the admin_forum permission.
 * Depending on the size of the message table, the process is divided in steps.
 *
 * @uses ManageSearch template, 'create_index', 'create_index_progress', and 'create_index_done'
 *  sub-templates.
 */
function CreateMessageIndex()
{
	global $modSettings, $context, $smcFunc, $db_prefix, $txt;

	// Scotty, we need more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_index_custom'];

	$messages_per_batch = 50;

	$index_properties = array(
		2 => array(
			'column_definition' => 'small',
			'step_size' => 1000000,
		),
		4 => array(
			'column_definition' => 'medium',
			'step_size' => 1000000,
			'max_size' => 16777215,
		),
		5 => array(
			'column_definition' => 'large',
			'step_size' => 100000000,
			'max_size' => 2000000000,
		),
	);

	if (isset($_REQUEST['resume']) && !empty($modSettings['search_custom_index_resume']))
	{
		$context['index_settings'] = unserialize($modSettings['search_custom_index_resume']);
		$context['start'] = (int) $context['index_settings']['resume_at'];
		unset($context['index_settings']['resume_at']);
		$context['step'] = 1;
	}
	else
	{
		$context['index_settings'] = array(
			'bytes_per_word' => isset($_REQUEST['bytes_per_word']) && isset($index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
		);
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;

		// admin timeouts are painful when building these long indexes
		if ($_SESSION['admin_time'] + 3300 < time() && $context['step'] >= 1)
			$_SESSION['admin_time'] = time();
	}

	if ($context['step'] !== 0)
		checkSession('request');

	// Step 0: let the user determine how they like their index.
	if ($context['step'] === 0)
	{
		$context['sub_template'] = 'create_index';
	}

	// Step 1: insert all the words.
	if ($context['step'] === 1)
	{
		$context['sub_template'] = 'create_index_progress';

		if ($context['start'] === 0)
		{
			db_extend();
			$tables = $smcFunc['db_list_tables'](false, $db_prefix . 'log_search_words');
			if (!empty($tables))
			{
				$smcFunc['db_search_query']('drop_words_table', '
					DROP TABLE {db_prefix}log_search_words',
					array(
					)
				);
			}

			$smcFunc['db_create_word_search']($index_properties[$context['index_settings']['bytes_per_word']]['column_definition']);

			// Temporarily switch back to not using a search index.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
				updateSettings(array('search_index' => ''));

			// Don't let simultanious processes be updating the search index.
			if (!empty($modSettings['search_custom_index_config']))
				updateSettings(array('search_custom_index_config' => ''));
		}

		$num_messages = array(
			'done' => 0,
			'todo' => 0,
		);

		$request = $smcFunc['db_query']('', '
			SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
			FROM {db_prefix}messages
			GROUP BY todo',
			array(
				'starting_id' => $context['start'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];

		if (empty($num_messages['todo']))
		{
			$context['step'] = 2;
			$context['percentage'] = 80;
			$context['start'] = 0;
		}
		else
		{
			// Number of seconds before the next step.
			$stop = time() + 3;
			while (time() < $stop)
			{
				$inserts = array();
				$request = $smcFunc['db_query']('', '
					SELECT id_msg, body
					FROM {db_prefix}messages
					WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
					LIMIT {int:limit}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $messages_per_batch - 1,
						'limit' => $messages_per_batch,
					)
				);
				$forced_break = false;
				$number_processed = 0;
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					// In theory it's possible for one of these to take friggin ages so add more timeout protection.
					if ($stop < time())
					{
						$forced_break = true;
						break;
					}

					$number_processed++;
					foreach (text2words($row['body'], $context['index_settings']['bytes_per_word'], true) as $id_word)
					{
						$inserts[] = array($id_word, $row['id_msg']);
					}
				}
				$num_messages['done'] += $number_processed;
				$num_messages['todo'] -= $number_processed;
				$smcFunc['db_free_result']($request);

				$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

				if (!empty($inserts))
					$smcFunc['db_insert']('ignore',
						'{db_prefix}log_search_words',
						array('id_word' => 'int', 'id_msg' => 'int'),
						$inserts,
						array('id_word', 'id_msg')
					);
				if ($num_messages['todo'] === 0)
				{
					$context['step'] = 2;
					$context['start'] = 0;
					break;
				}
				else
					updateSettings(array('search_custom_index_resume' => serialize(array_merge($context['index_settings'], array('resume_at' => $context['start'])))));
			}

			// Since there are still two steps to go, 80% is the maximum here.
			$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
		}
	}

	// Step 2: removing the words that occur too often and are of no use.
	elseif ($context['step'] === 2)
	{
		if ($context['index_settings']['bytes_per_word'] < 4)
			$context['step'] = 3;
		else
		{
			$stop_words = $context['start'] === 0 || empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
			$stop = time() + 3;
			$context['sub_template'] = 'create_index_progress';
			$max_messages = ceil(60 * $modSettings['totalMessages'] / 100);

			while (time() < $stop)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_word, COUNT(id_word) AS num_words
					FROM {db_prefix}log_search_words
					WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
					GROUP BY id_word
					HAVING COUNT(id_word) > {int:minimum_messages}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $index_properties[$context['index_settings']['bytes_per_word']]['step_size'] - 1,
						'minimum_messages' => $max_messages,
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$stop_words[] = $row['id_word'];
				$smcFunc['db_free_result']($request);

				updateSettings(array('search_stopwords' => implode(',', $stop_words)));

				if (!empty($stop_words))
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}log_search_words
						WHERE id_word in ({array_int:stop_words})',
						array(
							'stop_words' => $stop_words,
						)
					);

				$context['start'] += $index_properties[$context['index_settings']['bytes_per_word']]['step_size'];
				if ($context['start'] > $index_properties[$context['index_settings']['bytes_per_word']]['max_size'])
				{
					$context['step'] = 3;
					break;
				}
			}
			$context['percentage'] = 80 + round($context['start'] / $index_properties[$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
		}
	}

	// Step 3: remove words not distinctive enough.
	if ($context['step'] === 3)
	{
		$context['sub_template'] = 'create_index_done';

		updateSettings(array('search_index' => 'custom', 'search_custom_index_config' => serialize($context['index_settings'])));
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:search_custom_index_resume}',
			array(
				'search_custom_index_resume' => 'search_custom_index_resume',
			)
		);
	}
}

/**
 * Get the installed Search API implementations.
 * This function checks for patterns in comments on top of the Search-API files!
 * In addition to filenames pattern.
 * It loads the search API classes if identified.
 * This function is used by EditSearchMethod to list all installed API implementations.
 */
function loadSearchAPIs()
{
	global $sourcedir, $txt;

	$apis = array();
	if ($dh = opendir($sourcedir))
	{
		while (($file = readdir($dh)) !== false)
		{
			if (is_file($sourcedir . '/' . $file) && preg_match('~^SearchAPI-([A-Za-z\d_]+)\.php$~', $file, $matches))
			{
				// Check that this is definitely a valid API!
				$fp = fopen($sourcedir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '* SearchAPI-' . $matches[1] . '.php') !== false)
				{
					require_once($sourcedir . '/' . $file);

					$index_name = strtolower($matches[1]);
					$search_class_name = $index_name . '_search';
					$searchAPI = new $search_class_name();

					// No Support?  NEXT!
					if (!$searchAPI->is_supported)
						continue;

					$apis[$index_name] = array(
						'filename' => $file,
						'setting_index' => $index_name,
						'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
						'label' => $index_name && isset($txt['search_index_' . $index_name]) ? $txt['search_index_' . $index_name] : '',
						'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? $txt['search_index_' . $index_name . '_desc'] : '',
					);
				}
			}
		}
	}
	closedir($dh);

	return $apis;
}

/**
 * Checks if the message table already has a fulltext index created and returns the key name
 * Determines if a db is capable of creating a fulltext index
 */
function detectFulltextIndex()
{
	global $smcFunc, $context, $db_prefix;

	$request = $smcFunc['db_query']('', '
		SHOW INDEX
		FROM {db_prefix}messages',
		array(
		)
	);
	$context['fulltext_index'] = '';
	if ($request !== false || $smcFunc['db_num_rows']($request) != 0)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
				$context['fulltext_index'][] = $row['Key_name'];
		$smcFunc['db_free_result']($request);

		if (is_array($context['fulltext_index']))
			$context['fulltext_index'] = array_unique($context['fulltext_index']);
	}

	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = $smcFunc['db_query']('', '
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
			)
		);
	else
		$request = $smcFunc['db_query']('', '
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
			)
		);

	if ($request !== false)
	{
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if ((isset($row['Type']) && strtolower($row['Type']) != 'myisam') || (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam'))
				$context['cannot_create_fulltext'] = true;
		$smcFunc['db_free_result']($request);
	}
}

/**
 * Edit settings related to the sphinx or sphinxQL search function.
 * Called by ?action=admin;area=managesearch;sa=sphinx.
 */
function EditSphinxSettings()
{
	global $txt, $context, $modSettings, $sourcedir;

	// saving the settings
	if (isset($_POST['save']))
	{
		checkSession();
		validateToken('admin-mssphinx');

		updateSettings(array(
			'sphinx_data_path' => rtrim($_POST['sphinx_data_path'], '/'),
			'sphinx_log_path' => rtrim($_POST['sphinx_log_path'], '/'),
			'sphinx_stopword_path' => $_POST['sphinx_stopword_path'],
			'sphinx_indexer_mem' => (int) $_POST['sphinx_indexer_mem'],
			'sphinx_searchd_server' => $_POST['sphinx_searchd_server'],
			'sphinx_searchd_port' => (int) $_POST['sphinx_searchd_port'],
			'sphinxql_searchd_port' => (int) $_POST['sphinxql_searchd_port'],
			'sphinx_max_results' => (int) $_POST['sphinx_max_results'],
		));
	}
	// checking if we can connect?
	elseif (isset($_POST['checkconnect']))
	{
		checkSession();
		validateToken('admin-mssphinx');

		// If they have not picked sphinx yet, let them know, but we can still check connections
		if (empty($modSettings['search_index']) || ($modSettings['search_index'] !== 'sphinx' && $modSettings['search_index'] !== 'sphinxql'))
		{
			$context['settings_message'][] = $txt['sphinx_test_not_selected'];
			$context['error_type'] = 'notice';
		}

		// try to connect via Sphinx API?
		if ($modSettings['search_index'] === 'sphinx' || empty($modSettings['search_index']))
		{
			if (@file_exists($sourcedir . '/sphinxapi.php'))
			{
				include_once($sourcedir . '/sphinxapi.php');
				$mySphinx = new SphinxClient();
				$mySphinx->SetServer($modSettings['sphinx_searchd_server'], (int) $modSettings['sphinx_searchd_port']);
				$mySphinx->SetLimits(0, (int) $modSettings['sphinx_max_results']);
				$mySphinx->SetMatchMode(SPH_MATCH_BOOLEAN);
				$mySphinx->SetSortMode(SPH_SORT_ATTR_ASC, 'id_topic');

				$request = $mySphinx->Query('test', 'elkarte_index');
				if ($request === false)
				{
					$context['settings_message'][] = $txt['sphinx_test_connect_failed'];
					$context['error_type'] = 'serious';
				}
				else
					$context['settings_message'][] = $txt['sphinx_test_passed'];
			}
			else
			{
				$context['settings_message'][] = $txt['sphinx_test_api_missing'];
				$context['error_type'] = 'serious';
			}
		}

		// try to connect via SphinxQL
		if ($modSettings['search_index'] === 'sphinxql' || empty($modSettings['search_index']))
		{
			if (!empty($modSettings['sphinx_searchd_server']) && !empty($modSettings['sphinxql_searchd_port']))
			{
				$result = mysql_connect(($modSettings['sphinx_searchd_server'] === 'localhost' ? '127.0.0.1' : $modSettings['sphinx_searchd_server']) . ':' . (int) $modSettings['sphinxql_searchd_port']);
				if ($result === false)
				{
					$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
					$context['error_type'] = 'serious';
				}
				else
					$context['settings_message'][] = $txt['sphinxql_test_passed'];
			}
			else
			{
				$context['settings_message'][] = $txt['sphinxql_test_connect_failed'];
				$context['error_type'] = 'serious';
			}
		}
	}
	elseif (isset($_POST['createconfig']))
	{
		checkSession();
		validateToken('admin-mssphinx');

		CreateSphinxConfig();
	}

	// Setup for the template
	$context['page_title'] = $txt['search_sphinx'];
	$context['page_description'] = $txt['sphinx_description'];
	$context['sub_template'] = 'manage_sphinx';
	createToken('admin-mssphinx');
}

function CreateSphinxConfig()
{
	global $context, $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_character_set, $modSettings;

	$humungousTopicPosts = 200;

	// set up to ouput a file to the users browser
	ob_end_clean();
	header('Pragma: ');
	if (!$context['browser']['is_gecko'])
		header('Content-Transfer-Encoding: binary');
	header('Connection: close');
	header('Content-Disposition: attachment; filename="sphinx.conf"');
	header('Content-Type: application/octet-stream');

	$weight_factors = array(
		'age',
		'length',
		'first_message',
		'sticky',
	);

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor)
	{
		$weight[$weight_factor] = empty($modSettings['search_weight_' . $weight_factor]) ? 0 : (int) $modSettings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// weightless, then use defaults
	if ($weight_total === 0)
	{
		$weight = array(
			'age' => 25,
			'length' => 25,
			'first_message' => 25,
			'sticky' => 25,
		);
		$weight_total = 100;
	}

	// check paths are set, if not use some defaults
	$modSettings['sphinx_data_path'] = empty($modSettings['sphinx_data_path']) ? '/var/sphinx/data' : $modSettings['sphinx_data_path'];
	$modSettings['sphinx_log_path'] = empty($modSettings['sphinx_log_path']) ? '/var/sphinx/log' : $modSettings['sphinx_log_path'];

	// output our minimal configuration file to get them started
	echo '#
# Sphinx configuration file (sphinx.conf), configured for Elkarte
#
# This is the minimum needed clean, simple, functional
#
# By default the location of this file would probably be:
# /usr/local/etc/sphinx.conf
#

source elkarte_source
{
	type				= mysql
	sql_host 			= ', $db_server, '
	sql_user			= ', $db_user, '
	sql_pass			= ', $db_passwd, '
	sql_db				= ', $db_name, '
	sql_port			= 3306', empty($db_character_set) ? '' : '
	sql_query_pre		= SET NAMES ' . $db_character_set, '
	sql_query_pre		=	\
		REPLACE INTO ', $db_prefix, 'settings (variable, value) \
		SELECT \'sphinx_indexed_msg_until\', MAX(id_msg) \
		FROM ', $db_prefix, 'messages
	sql_query_range		= \
		SELECT 1, value \
		FROM ', $db_prefix, 'settings \
		WHERE variable = \'sphinx_indexed_msg_until\'
	sql_range_step		= 1000
	sql_query			= \
		SELECT \
			m.id_msg, m.id_topic, m.id_board, IF(m.id_member = 0, 4294967295, m.id_member) AS id_member, m.poster_time, m.body, m.subject, \
			t.num_replies + 1 AS num_replies, CEILING(1000000 * ( \
				IF(m.id_msg < 0.7 * s.value, 0, (m.id_msg - 0.7 * s.value) / (0.3 * s.value)) * ' . $weight['age'] . ' + \
				IF(t.num_replies < 200, t.num_replies / 200, 1) * ' . $weight['length'] . ' + \
				IF(m.id_msg = t.id_first_msg, 1, 0) * ' . $weight['first_message'] . ' + \
				IF(t.is_sticky = 0, 0, 1) * ' . $weight['sticky'] . ' \
			) / ' . $weight_total . ') AS relevance \
		FROM ', $db_prefix, 'messages AS m, ', $db_prefix, 'topics AS t, ', $db_prefix, 'settings AS s \
		WHERE t.id_topic = m.id_topic \
			AND s.variable = \'maxMsgID\' \
			AND m.id_msg BETWEEN $start AND $end
	sql_attr_uint		= id_topic
	sql_attr_uint		= id_board
	sql_attr_uint		= id_member
	sql_attr_timestamp	= poster_time
	sql_attr_timestamp	= relevance
	sql_attr_timestamp	= num_replies
	sql_query_info		= \
		SELECT * \
		FROM ', $db_prefix, 'messages \
		WHERE id_msg = $id
}

source elkarte_delta_source : elkarte_source
{
	sql_query_pre	= ', isset($db_character_set) ? 'SET NAMES ' . $db_character_set : '', '
	sql_query_range	= \
		SELECT s1.value, s2.value \
		FROM ', $db_prefix, 'settings AS s1, ', $db_prefix, 'settings AS s2 \
		WHERE s1.variable = \'sphinx_indexed_msg_until\' \
			AND s2.variable = \'maxMsgID\'
}

index elkarte_base_index
{
	html_strip 		= 1
	source 			= elkarte_source
	path 			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_base.index', empty($modSettings['sphinx_stopword_path']) ? '' : '
	stopwords 		= ' . $modSettings['sphinx_stopword_path'], '
	min_word_len 	= 2
	charset_type 	= ', isset($db_character_set) && $db_character_set === 'utf8' ? 'utf-8' : 'sbcs', '
	charset_table 	= 0..9, A..Z->a..z, _, a..z
}

index elkarte_delta_index : elkarte_base_index
{
	source 			= elkarte_delta_source
	path 			= ', $modSettings['sphinx_data_path'], '/elkarte_sphinx_delta.index
}

index elkarte_index
{
	type			= distributed
	local			= elkarte_base_index
	local			= elkarte_delta_index
}

indexer
{
	mem_limit 		= ', (empty($modSettings['sphinx_indexer_mem']) ? 32 : (int) $modSettings['sphinx_indexer_mem']), 'M
}

searchd
{
	listen 			= ', (empty($modSettings['sphinx_searchd_port']) ? 3312 : (int) $modSettings['sphinx_searchd_port']), '
	listen 			= ', (empty($modSettings['sphinxql_searchd_port']) ? 3313 : (int) $modSettings['sphinxql_searchd_port']), ':mysql41
	log 			= ', $modSettings['sphinx_log_path'], '/searchd.log
	query_log 		= ', $modSettings['sphinx_log_path'], '/query.log
	read_timeout 	= 5
	max_children 	= 30
	pid_file 		= ', $modSettings['sphinx_data_path'], '/searchd.pid
	max_matches 	= ', (empty($modSettings['sphinx_max_results']) ? 3312 : (int) $modSettings['sphinx_max_results']), '
}
';
	obExit(false, false);
}