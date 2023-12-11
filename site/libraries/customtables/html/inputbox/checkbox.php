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
if (!defined('_JEXEC') and !defined('WPINC')) {
	die('Restricted access');
}

class InputBox_checkbox extends BaseInputBox
{
	function __construct(CT &$ct, Field $field, ?array $row, array $option_list = [], array $attributes = [])
	{
		parent::__construct($ct, $field, $row, $option_list, $attributes);
	}

	function render(?string $value, ?string $defaultValue): string
	{
		if ($value === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = (int)$defaultValue;
		} else {
			$value = (int)$value;
		}

		$format = '';

		if (isset($this->option_list[2]) and $this->option_list[2] == 'yesno')
			$format = "yesno";

		$element_id = $this->attributes['id'];

		if ($format == "yesno") {

			$this->attributes['type'] = 'radio';

			if ($this->ct->Env->version < 4) {

				if ($value == 1)
					$this->attributes['checked'] = 'checked';

				$this->attributes['class'] = null;
				$this->attributes['id'] = $element_id . '0';
				$this->attributes['value'] = '1';

				$input1 = '<div style="position: absolute;visibility:hidden !important; display:none !important;">'
					. '<input ' . self::attributes2String($this->attributes) . ' />'
					. '</div>'
					. '<label class="btn' . ($value == 1 ? ' active btn-success' : '') . '" for="' . $element_id . '0" id="' . $element_id . '0_label" >' . common::translate('COM_CUSTOMTABLES_YES') . '</label>';

				$this->attributes['id'] = $element_id . '1';
				$this->attributes['value'] = '0';

				$input2 = '<div style="position: absolute;visibility:hidden !important; display:none !important;">'
					. '<input ' . self::attributes2String($this->attributes) . ' />'
					. '</div>'
					. '<label class="btn' . ($value == 0 ? ' active btn-danger' : '') . '" for="' . $element_id . '1" id="' . $element_id . '1_label">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>';

				return '<fieldset id="' . $element_id . '" class="' . $this->attributes['class'] . ' btn-group radio btn-group-yesno" '
					. 'style="border:none !important;background:none !important;">' . $input1 . $input2 . '</fieldset>';
			} else {

				if ($value == 1)
					$this->attributes['checked'] = 'checked';

				$this->attributes['id'] = $element_id . '1';
				$this->attributes['value'] = '1';
				$this->attributes['class'] = 'active';

				$input1 = '<input ' . self::attributes2String($this->attributes) . ' />'
					. '<label for="' . $element_id . '0">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>';

				$input2 = '<input ' . self::attributes2String($this->attributes) . ' />'
					. '<label for="' . $element_id . '1">' . common::translate('COM_CUSTOMTABLES_YES') . '</label>';

				return '<div class="switcher">' . $input1 . $input2 . '<span class="toggle-outside"><span class="toggle-inside"></span></span></div>';
			}
		} else {
			if ($this->ct->Env->version < 4) {
				$onchange = $element_id . '_off.value=(this.checked === true ? 0 : 1);';// this is to save unchecked value as well.
				$this->attributes['onchange'] = (($this->attributes['onchange'] ?? '') == '' ? '' : $this->attributes['onchange'] . ' ') . $onchange;

				if ($value == 1)
					$this->attributes['checked'] = 'checked';

				$this->attributes['type'] = 'checkbox';

				$input = '<input ' . self::attributes2String($this->attributes) . ' />';

				$hidden = '<input type="hidden"'
					. ' id="' . $element_id . '_off" '
					. ' name="' . $element_id . '_off" '
					. ($value == 1 ? ' value="0" ' : 'value="1"')
					. ' >';

				return $input . $hidden;
			} else {

				$customClass = $this->attributes['class'];
				$this->attributes['type'] = 'radio';

				$this->attributes['id'] = $element_id . '0';
				$this->attributes['value'] = '0';
				$this->attributes['class'] = 'active';

				if ($value == 0)
					$this->attributes['checked'] = 'checked';

				$input1 = '<input ' . self::attributes2String($this->attributes) . ' />'
					. '<label for="' . $this->attributes['id'] . '">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>';

				$this->attributes['id'] = $element_id . '1';
				$this->attributes['value'] = '1';
				$this->attributes['class'] = null;

				if ($value == 1)
					$this->attributes['checked'] = 'checked';

				$input2 = '<input ' . self::attributes2String($this->attributes) . ' />'
					. '<label for="' . $this->attributes['id'] . '">' . common::translate('COM_CUSTOMTABLES_YES') . '</label>';

				$span = '<span class="toggle-outside"><span class="toggle-inside"></span></span>';
				$hidden = '<input type="hidden"'
					. ' id="' . $element_id . '_off" '
					. ' name="' . $element_id . '_off" '
					. ($value == 1 ? ' value="0" ' : 'value="1"')
					. ' >';

				return '<div class="switcher' . ($customClass != '' ? ' ' . $customClass : '') . '">' . $input1 . $input2 . $span . $hidden . '</div><script>
							document.getElementById("' . $element_id . '0").onchange = function(){if(this.checked === true)' . $element_id . '_off.value=1;' . $this->attributes['onchange'] . '};
							document.getElementById("' . $element_id . '1").onchange = function(){if(this.checked === true)' . $element_id . '_off.value=0;' . $this->attributes['onchange'] . '};
						</script>';
			}
		}
	}
}