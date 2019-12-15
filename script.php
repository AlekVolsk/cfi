<?php defined('_JEXEC') or die;
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) 2019 Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\CMS\Language\Text;

class plgSystemCfiInstallerScript
{
    function preflight($type, $parent)
    {
        if (strtolower($type) === 'uninstall') {
            return true;
        }

		$minJoomlaVersion = $parent->get('manifest')->attributes()->version[0];

		if (!class_exists('Joomla\CMS\Version')) {
			JFactory::getApplication()->enqueueMessage(JText::sprintf('J_JOOMLA_COMPATIBLE', JText::_($parent->get('manifest')->name[0]), $minJoomlaVersion), 'error');
			return false;
        }
        
        $msg = '';
        $ver = new Version();
        $name = Text::_($parent->get('manifest')->name[0]);
        $minPhpVersion = $parent->get('manifest')->php_minimum[0];

        if (version_compare($ver->getShortVersion(), $minJoomlaVersion, 'lt')) {
            $msg .= Text::sprintf('J_JOOMLA_COMPATIBLE', $name, $minJoomlaVersion);
        }

        if (version_compare(phpversion(), $minPhpVersion, 'lt')) {
            $msg .= Text::sprintf('J_PHP_COMPATIBLE', $name, $minPhpVersion);
        }

        if ($msg) {
            Factory::getApplication()->enqueueMessage($msg, 'error');
            return false;
        }
    }

    public function postflight($type, $parent)
    {
        if (strtolower($type) === 'uninstall') {
            return true;
        }
        
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update('#__extensions')
            ->set('enabled = 1')
            ->where('element = ' . $db->quote('cfi'))
            ->where('type = ' . $db->quote('plugin'))
            ->where('folder = ' . $db->quote('system'));
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (Exception $e) { }
    }
}
