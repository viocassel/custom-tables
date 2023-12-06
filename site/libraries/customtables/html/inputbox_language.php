<?php
/**
 * CustomTables Joomla! 3.x/4.x/5.x Component and WordPress 6.x Plugin
 * @package Custom Tables
 * @author Ivan Komlev <support@joomlaboat.com>
 * @link https://joomlaboat.com
 * @copyright (C) 2018-2023 Ivan Komlev
 * @license GNU/GPL Version 2 or later - https://www.gnu.org/licenses/gpl-2.0.html
 **/

namespace CustomTables;

// no direct access
use Joomla\CMS\Factory;

if (!defined('_JEXEC') and !defined('WPINC')) {
	die('Restricted access');
}

class InputBox_Language extends BaseInputBox
{
	function __construct(CT &$ct, Field $field, ?array $row, array $option_list = [], array $attributes = [])
	{
		parent::__construct($ct, $field, $row, $option_list, $attributes);
	}

	function render_language(?string $value, ?string $defaultValue): string
	{
		if ($value === null or $value === '') {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname);
			if ($value === null) {
				if ($defaultValue === null or $defaultValue === '') {
					//If it's a new record then current language will be used.
					$langObj = Factory::getLanguage();
					$value = $langObj->getTag();
				} else
					$value = $defaultValue;
			}
		}

		$this->selectBoxAddCSSClass();
		$lang = new Languages();

		// Start building the select element with attributes
		$select = '<select ' . $this->attributes2String() . '>';

		// Optional default option
		$selected = (0 === $value) ? ' selected' : '';
		$select .= '<option value=""' . $selected . '> - ' . common::translate('COM_CUSTOMTABLES_SELECT_LANGUAGE') . '</option>';

		// Generate options for each file in the folder
		foreach ($lang->LanguageList as $language) {
			$selected = ($language->language == $value) ? ' selected' : '';
			$select .= '<option value="' . $language->language . '" ' . $selected . '>' . $language->caption . '</option>';

		}
		$select .= '</select>';
		return $select;
	}
}