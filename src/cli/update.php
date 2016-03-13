<?php
/**
 * @package    Joomla.Cli
 *
 * @copyright  Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

if (php_sapi_name() != 'cli')
{
	exit(1);
}

// We are a valid entry point.
const _JEXEC = 1;

// Define core extension id
const CORE_EXTENSION_ID = 700;

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

// Load the JApplicationCli class
JLoader::import('joomla.application.cli');
JLoader::import('joomla.application.component.helper');

/**
 *
 * @since  3.5.1
 */
class JoomlaCliUpdate extends JApplicationCli
{
	/** @var InstallerModelUpdate */
	protected $installer = null;

	/**
	 * Entry point for the script
	 *
	 * @return  void
	 */
	public function doExecute()
	{
		$_SERVER['HTTP_HOST'] = 'localhost';
		JFactory::getApplication('site');

		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_installer/models');
		$this->installer = JModelLegacy::getInstance('Update', 'InstallerModel');

		if ($this->input->get('core', ''))
		{
			$this->updateCore();
		}

		if ($this->input->get('extension', ''))
		{
			$this->updateExtensions();
		}

		if ($this->input->get('info', ''))
		{
			$this->infoInstalledVersions();
		}

		if ($this->input->get('sitename', ''))
		{
			$this->getSiteInfo();
		}
	}

	/**
	 * Gives Information about all installed extensions
	 */
	public function infoInstalledVersions()
	{
		// Get All extensions
		$extensions = $this->getAllExtensions();

		$this->installer->purge();

		$this->findUpdates(0);

		$updates = $this->getUpdates();

		$toUpdate = [];
		$upToDate = [];

		foreach ($extensions as &$extension)
		{
			if (array_key_exists($extension['extension_id'], $updates))
			{
				$toUpdate                = $extension;
				$toUpdate['newVersion']  = $updates[$toUpdate['extension_id']]['version'];
				$toUpdate['needsUpdate'] = true;
			}
			else
			{
				$upToDate                   = $extension;
				$upToDate['currentVersion'] = json_decode($upToDate['manifest_cache'], true)['version'];
				$upToDate['newVersion']     = $upToDate['currentVersion'];
				$upToDate['needsUpdate']    = false;
			}
		}

		$result = array_merge($toUpdate, $upToDate);

		$this->out(json_encode($result));
	}

	/**
	 * Update Core Joomla
	 */
	public function updateCore()
	{
		return $this->updateExtension(CORE_EXTENSION_ID);
	}

	/**
	 * Update a single extension
	 */
	public function updateExtension($eid)
	{
		$this->installer->purge();

		$this->findUpdates($eid);

		// Joomla Core update
		$update_ids = $this->getUpdateIds($eid);

		$this->installer->update($update_ids);

		$result = $this->installer->getState('result');

		return $result;
	}

	/**
	 * Update Extensions
	 */
	public function updateExtensions()
	{
		$this->installer->purge();

		$this->findUpdates();

		// Joomla Core update
		$update_ids = $this->getUpdateIds();
		$result     = [];

		foreach ($update_ids as $update_id)
		{
			$this->installer->update([$update_id]);
			$result[$update_id] = $this->installer->getState('result');
		}

		return $result;
	}

	/**
	 * Find updates
	 *
	 * @param int $eid
	 */
	private function findUpdates($eid = 0)
	{
		$updater = JUpdater::getInstance();

		// Fills potential updates into the table '#__updates for ALL extensions
		$updater->findUpdates($eid);
	}

	/**
	 * Get the update
	 *
	 * @return array
	 */
	private function getUpdateIds($eid = null)
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('update_id')
			->from('#__updates')
			->where($db->qn('extension_id') . ' <> 0');

		if (!is_null($eid))
		{
			$query->where($db->qn('extension_id') . ' = ' . $db->q($eid));
		}

		$db->setQuery($query);

		return $db->loadColumn();
	}

	/**
	 * Get updates
	 *
	 * @return array
	 */
	private function getUpdates()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('*')
			->from('#__updates')
			->where($db->qn('extension_id') . ' <> 0');

		$db->setQuery($query);

		return $db->loadAssocList('extension_id');
	}

	/**
	 * Get all extensions
	 *
	 * @return   mixed
	 */
	private function getAllExtensions()
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('*')
			->from('#__extensions');

		$db->setQuery($query);

		return $db->loadAssocList('extension_id');
	}

	public function getSiteInfo()
	{
		$info = new stdClass();

		$info->sitename = JFactory::getApplication()->config->get('sitename');

		$this->out(json_encode($info));
	}
}

JApplicationCli::getInstance('JoomlaCliUpdate')->execute();
