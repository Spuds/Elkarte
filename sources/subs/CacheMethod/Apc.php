<?php

/**
 * This file contains functions that deal with getting and setting cache values.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1 beta 2
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * Alternative PHP Cache or APC / APCu
 */
class Apc extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc }
	 */
	public function init()
	{
		return function_exists('apc_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function put($key, $value, $ttl = 120)
	{
		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			apc_delete($key . 'elkarte');
		else
			apc_store($key . 'elkarte', $value, $ttl);
	}

	/**
	 * {@inheritdoc }
	 */
	public function get($key, $ttl = 120)
	{
		$success = false;
		$result = apc_fetch($key . 'elkarte', $success);

		$this->is_miss = !$success;

		return $result;
	}

	/**
	 * {@inheritdoc }
	 */
	public function clean($type = '')
	{
		// If passed a type, clear that type out
		if ($type === '' || $type === 'data')
		{
			apc_clear_cache('user');
			apc_clear_cache('system');
		}
		elseif ($type === 'user')
			apc_clear_cache('user');
	}

	/**
	 * {@inheritdoc }
	 */
	public function isAvailable()
	{
		return function_exists('apc_store') || function_exists('apcu_store');
	}

	/**
	 * {@inheritdoc }
	 */
	public function details()
	{
		return array('title' => $this->title(), 'version' => phpversion('apc'));
	}

	/**
	 * {@inheritdoc }
	 */
	public function title()
	{
		return 'Alternative PHP Cache';
	}
}
