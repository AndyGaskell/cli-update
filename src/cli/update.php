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
JLoader::import('joomla.filesystem.folder');

/**
 *
 * @since  3.5.1
 */
class JoomlaCliUpdate extends JApplicationCli
{
	/** @var InstallerModelUpdate */
	protected $updater = null;

	/** @var JApplicationSite */
	protected $app = null;

	/**
	 * Entry point for the script
	 *
	 * @return  void
	 */
	public function doExecute()
	{
		$_SERVER['HTTP_HOST'] = 'localhost';
		$this->app = JFactory::getApplication('site');

		JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_installer/models');
		$this->updater = JModelLegacy::getInstance('Update', 'InstallerModel');

		if ($this->input->get('core', ''))
		{
			return $this->updateCore();
		}

		$param = $this->input->get('extension', '');
		if ($param == '')
		{
			return $this->updateExtensions();
		}

		if ($param != '')
		{
			$eid = (int) $param;
			
			return $this->updateExtension($eid);
		}


		if ($this->input->get('info', ''))
		{
			return $this->infoInstalledVersions();
		}

		if ($this->input->get('sitename', ''))
		{
			return $this->getSiteInfo();
		}

		$param = $this->input->get('install', '', 'raw');

		if ($param != '')
		{
			return $this->installExtension($param);
		}

		$param = $this->input->get('remove', '');

		if ($param != '')
		{
			return $this->removeExtension($param);
		}
	}

	/**
	 * Remove an extension
	 *
	 * @param   int  $param  Extention id
	 *
	 * @return  bool
	 */
	protected function removeExtension($param)
	{
		$id = (int) $param;

		$result = true;

		$installer = JInstaller::getInstance();
		$row = JTable::getInstance('extension');

		$row->load($id);

		if ($row->type && $row->type != 'language')
		{
			$result = $installer->uninstall($row->type, $id);
		}

		return $result;
	}

	/**
	 * @param $param
	 *
	 * @return bool
	 */
	public function installExtension($param)
	{
		$method = $this->getMethod($param);

		if ($method == 'url')
		{
			$packagefile = JInstallerHelper::downloadPackage($param);
			$packagefile = JPATH_BASE . '/tmp/' . basename($packagefile);
		}

		if ($method == 'folder')
		{
			$packagefile = $param;
		}

		$package = JInstallerHelper::unpack($packagefile, true);

		if ($package['type'] === false)
		{
			return false;
		}

		$jInstaller = JInstaller::getInstance();

		$jInstaller->install($package['extractdir']);

		if ($method == 'url')
		{
			JInstallerHelper::cleanupInstall($packagefile, $package['extractdir']);
		}

		return true;
	}
	
	/**
	 * Gives Information about all installed extensions
     */
	public function infoInstalledVersions()
	{
		$lang = JFactory::getLanguage();

		$langFiles = $this->getLanguageFiles();
		foreach ($langFiles as $file)
		{
			$file = str_replace(array('en-GB.', '.ini'), '', $file);
			$lang->load($file, JPATH_ADMINISTRATOR, 'en-GB', true, false);
		}

		// Get All extensions
		$extensions = $this->getAllExtensions();

		$this->updater->purge();

		$this->findUpdates(0);

		$updates = $this->getUpdates();

		$toUpdate = array();
		$upToDate = array();

		foreach($extensions as &$extension)
		{
			$extension['name'] = JText::_($extension['name']);

			if (array_key_exists($extension['extension_id'],$updates))
			{
				$tmp = $extension;
				$tmp['currentVersion'] = json_decode($tmp['manifest_cache'], true)['version'];
				$tmp['newVersion']     = $updates[$tmp['extension_id']]['version'];
				$tmp['needsUpdate']    = true;

				$toUpdatearray() = $tmp;
			}
			else
			{
				$tmp = $extension;
				$tmp['currentVersion'] = json_decode($tmp['manifest_cache'], true)['version'];
				$tmp['newVersion']     = $tmp['currentVersion'];
				$tmp['needsUpdate']    = false;

				$upToDatearray() = $tmp;
			}
		}

		$result = array_merge($toUpdate, $upToDate);

		$this->out(json_encode($result));
	}


	private function getLanguageFiles()
	{
		return JFolder::files(JPATH_ADMINISTRATOR . '/language/en-GB/', '\.sys\.ini');
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
		$this->updater->purge();

		$this->findUpdates($eid);

		// Joomla Core update
		$update_ids = $this->getUpdateIds($eid);

		$this->updater->update($update_ids);

		$result = $this->updater->getState('result');

		return $result;
	}

	/**
	 * Update Extensions
	 */
	public function updateExtensions()
	{
		$this->updater->purge();

		$this->findUpdates();

		// Joomla Core update
		$update_ids = $this->getUpdateIds();
		$result     = array();

		foreach ($update_ids as $update_id)
		{
			$this->updater->update([$update_id]);
			$result[$update_id] = $this->updater->getState('result');
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
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('update_id')
				->from('#__updates')
				->where($db->qn('extension_id') . ' <> 0');

		if (! is_null($eid))
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
		$db = JFactory::getDbo();
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
		$db = JFactory::getDbo();
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

	private function getMethod($param)
	{
		if (is_file($param))
		{
			return 'folder';
		}

		return 'url';
	}
}

JApplicationCli::getInstance('JoomlaCliUpdate')->execute();
