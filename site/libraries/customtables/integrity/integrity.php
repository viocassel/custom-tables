<?php
/**
 * CustomTables Joomla! 3.x/4.x/5.x Native Component and WordPress 6.x Plugin
 * @package Custom Tables
 * @subpackage libraries/_checktable.php
 * @author Ivan Komlev <support@joomlaboat.com>
 * @link https://joomlaboat.com
 * @copyright (C) 2018-2023. Ivan Komlev
 * @license GNU/GPL Version 2 or later - https://www.gnu.org/licenses/gpl-2.0.html
 **/

namespace CustomTables;

if (!defined('_JEXEC') and !defined('WPINC')) {
	die('Restricted access');
}

use CustomTables\Integrity\IntegrityCoreTables;
use CustomTables\Integrity\IntegrityTables;

class IntegrityChecks
{
	public static function check(CT &$ct, $check_core_tables = true, $check_custom_tables = true): array
	{
		$result = []; //Status array

		if ($check_core_tables)
			IntegrityCoreTables::checkCoreTables($ct);

		if ($check_custom_tables)
			$result = IntegrityTables::checkTables($ct);

		return $result;
	}
}
