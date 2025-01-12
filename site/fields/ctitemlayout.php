<?php
/**
 * CustomTables Joomla! 3.x/4.x/5.x Component
 * @package Custom Tables
 * @author Ivan Komlev <support@joomlaboat.com>
 * @link https://joomlaboat.com
 * @copyright (C) 2018-2024. Ivan Komlev
 * @license GNU/GPL Version 2 or later - https://www.gnu.org/licenses/gpl-2.0.html
 **/

// no direct access
if (!defined('_JEXEC')) die('Restricted access');

use CustomTables\database;
use CustomTables\MySQLWhereClause;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;

$versionObject = new Version;
$version = (int)$versionObject->getShortVersion();

trait JFormFieldCTItemLayoutCommon
{
	protected static function getOptionList(): array
	{
		require_once(JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_customtables' . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'ct-database-joomla.php');
		$whereClause = new MySQLWhereClause();
		$whereClause->addCondition('published', 1);
		$whereClause->addOrCondition('layouttype', 6);

		$layouts = database::loadObjectList('#__customtables_layouts AS a',
			['id', 'layoutname', 'TABLE_NAME'], $whereClause, 'TABLE_NAME,layoutname');

		$options = ['' => ' - ' . Text::_('COM_CUSTOMTABLES_DEFAULT')];

		if ($layouts) {
			foreach ($layouts as $layout)
				$options[] = HTMLHelper::_('select.option', $layout->layoutname, $layout->TABLE_NAME . ' - ' . $layout->layoutname);
		}
		return $options;
	}
}

if ($version < 4) {

	JFormHelper::loadFieldClass('list');

	class JFormFieldCTItemLayout extends JFormFieldList
	{
		use JFormFieldCTItemLayoutCommon;

		protected $type = 'CTItemLayout';

		protected function getOptions(): array
		{
			return self::getOptionList();
		}
	}
} else {

	class JFormFieldCTItemLayout extends FormField
	{
		use JFormFieldCTItemLayoutCommon;

		protected $type = 'CTItemLayout';
		protected $layout = 'joomla.form.field.list'; //Needed for Joomla 5

		protected function getInput()
		{
			$data = $this->getLayoutData();
			$data['options'] = self::getOptionList();
			return $this->getRenderer($this->layout)->render($data);
		}
	}
}