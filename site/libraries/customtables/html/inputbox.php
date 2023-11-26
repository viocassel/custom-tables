<?php
/**
 * CustomTables Joomla! 3.x/4.x/5.x Native Component and WordPress 6.x Plugin
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

use Joomla\Registry\Registry;
use tagProcessor_General;
use tagProcessor_Item;
use tagProcessor_If;
use tagProcessor_Page;
use tagProcessor_Value;
use CT_FieldTypeTag_image;
use CT_FieldTypeTag_file;
use CT_FieldTypeTag_imagegallery;
use CT_FieldTypeTag_FileBox;

use CustomTables\DataTypes\Tree;

use Joomla\CMS\Factory;
use JoomlaBasicMisc;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Editor\Editor;
use JHTML;

use CTTypes;

if (defined('_JEXEC'))
	JHTML::addIncludePath(JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_customtables' . DIRECTORY_SEPARATOR . 'helpers');

class Inputbox
{
	var CT $ct;
	var Field $field;
	var ?array $row;

	var string $cssclass;
	var string $attributes;
	var string $onchange;
	var array $option_list;
	var string $place_holder;
	var string $prefix;
	var bool $isTwig;
	var ?string $defaultValue;

	protected string $cssStyle;

	function __construct(CT &$ct, $fieldRow, array $option_list = [], $isTwig = true, string $onchange = '')
	{
		$this->ct = &$ct;
		$this->isTwig = $isTwig;

		$this->cssclass = $option_list[0] ?? '';
		$this->attributes = str_replace('****quote****', '"', $option_list[1] ?? '');//Optional Parameter

		preg_match('/onchange="([^"]*)"/', $this->attributes, $matches);
		$onchange_value = $matches[1] ?? '';

		$this->attributes = str_replace($onchange_value, '', $this->attributes);
		$this->attributes = str_replace('onchange=""', '', $this->attributes);
		$this->attributes = str_replace("onchange=''", '', $this->attributes);
		$this->cssStyle = '';
		$this->onchange = ($onchange_value != '' and $onchange_value[strlen($onchange_value) - 1] != ';') ? $onchange_value . ';' . $onchange : $onchange_value . $onchange;

		if (str_contains($this->cssclass, ':'))//it's a style, change it to attribute
		{
			$this->cssStyle = $this->cssclass;
			$this->cssclass = '';
		}

		if (str_contains($this->attributes, 'onchange="') and $this->onchange != '') {
			//if the attributes already contain "onchange" parameter then add onchange value to the attributes parameter
			$this->attributes = str_replace('onchange="', 'onchange="' . $this->onchange, $this->attributes);
		} elseif ($this->attributes != '')
			$this->attributes .= ' onchange="' . $onchange . '"';
		else
			$this->attributes = 'onchange="' . $onchange . '"';

		$this->field = new Field($this->ct, $fieldRow);

		if ($this->field->type != "records")
			$this->cssclass .= ($this->ct->Env->version < 4 ? ' inputbox' : ' form-control');

		if ($this->field->isrequired == 1)
			$this->cssclass .= ' required';

		$this->option_list = $option_list;
		$this->place_holder = $this->field->title;
	}

	static public function renderTableJoinSelectorJSON(CT &$ct, $key, $obEndClean = true): ?string
	{
		$index = common::inputGetInt('index');

		$selectors = (array)$ct->app->getUserState($key);

		if ($index < 0 or $index >= count($selectors))
			die(json_encode(['error' => 'Index out of range.' . $key]));

		$additional_filter = common::inputGetCmd('filter', '');
		$subFilter = common::inputGetCmd('subfilter');
		$search = common::inputGetString('search');

		return self::renderTableJoinSelectorJSON_Process($ct, $selectors, $index, $additional_filter, $subFilter, $search, $obEndClean);
	}

	static public function renderTableJoinSelectorJSON_Process(CT &$ct, $selectors, $index, $additional_filter, $subFilter, ?string $search = null, $obEndClean = true): ?string
	{
		$selector = $selectors[$index];

		$tableName = $selector[0];
		if ($tableName === null) {
			if ($obEndClean)
				die(json_encode(['error' => 'Table not selected']));
			else
				return 'Table not selected';
		}

		$ct->getTable($tableName);
		if (is_null($ct->Table->tablename))
			die(json_encode(['error' => 'Table "' . $tableName . '"not found']));

		$fieldName_or_layout = $selector[1];
		if ($fieldName_or_layout === null or $fieldName_or_layout == '')
			$fieldName_or_layout = $ct->Table->fields[0]['fieldname'];//Get first field if not specified

		//$showPublished = 0 - show published
		//$showPublished = 1 - show unpublished
		//$showPublished = 2 - show any
		$showPublishedString = $selector[2] ?? '';
		$showPublished = ($showPublishedString == 'true' ? 2 : 0); //$selector[2] can be "" or "true" or "false"

		$filter = $selector[3] ?? '';
		$filterOverride = common::inputGetString('where');
		if ($filterOverride !== null) {
			if ($filter != '')
				$filter .= ' and ';

			$filter .= base64_decode($filterOverride);
		}

		$additional_where = '';
		//Find the field name that has a join to the parent (index-1) table
		foreach ($ct->Table->fields as $fld) {
			if ($fld['type'] == 'sqljoin' or $fld['type'] == 'records') {
				$type_params = JoomlaBasicMisc::csv_explode(',', $fld['typeparams']);

				$join_tableName = $type_params[0];
				$join_to_tableName = $selector[5];

				if ($additional_filter != '') {
					if ($join_tableName == $join_to_tableName)
						$filter .= ' and ' . $fld['fieldname'] . '=' . $additional_filter;

				} else {
					//Check if this table has self-parent field - the TableJoin field linked with the same table.
					if ($join_tableName == $tableName) {
						if ($subFilter == '')
							$additional_where = '(' . $fld['realfieldname'] . ' IS NULL OR ' . $fld['realfieldname'] . '="")';
						else
							$additional_where = $fld['realfieldname'] . '=' . database::quote($subFilter);
					}
				}
			}
		}

		$ct->setFilter($filter, $showPublished);
		if ($additional_where != '')
			$ct->Filter->where[] = $additional_where;

		if ($search !== null and $search != '') {
			foreach ($ct->Table->fields as $fld) {
				if ($fieldName_or_layout == $fld['fieldname']) {
					$ct->Filter->where[] = 'INSTR(' . $fld['realfieldname'] . ',' . database::quote($search) . ')';
				}
			}
		}
		/*
		if (count($selectors) > $index + 1) {
			$currentFilter = $selectors[$index];
			$nextFilter = $selectors[$index + 1];
			$ct->Filter->where[] = '(SELECT COUNT(id) FROM #__customtables_table_' . $nextFilter[0]
				. ' WHERE #__customtables_table_' . $nextFilter[0] . '.es_' . $nextFilter[4] . '=#__customtables_table_' . $currentFilter[0] . '.id) >0';
		}
		*/

		$orderBy = $selector[4] ?? '';

		//sorting
		$ct->Ordering->ordering_processed_string = $orderBy;
		$ct->Ordering->parseOrderByString();

		$ct->getRecords();

		if (!str_contains($fieldName_or_layout, '{{') and !str_contains($fieldName_or_layout, 'layout')) {
			$fieldName_or_layout_tag = '{{ ' . $fieldName_or_layout . ' }}';
		} else {
			$pair = explode(':', $fieldName_or_layout);

			if (count($pair) == 2) {
				$layout_mode = true;
				if ($pair[0] != 'layout' and $pair[0] != 'tablelesslayout')
					die(json_encode(['error' => common::translate('COM_CUSTOMTABLES_ERROR_UNKNOWN_FIELD_LAYOUT') . ' "' . $fieldName_or_layout . '"']));

				$Layouts = new Layouts($ct);
				$fieldName_or_layout_tag = $Layouts->getLayout($pair[1]);

				if (!isset($fieldName_or_layout_tag) or $fieldName_or_layout_tag == '') {
					$result_js = ['error' => common::translate(
							'COM_CUSTOMTABLES_ERROR_LAYOUT_NOT_FOUND') . ' "' . $pair[1] . '"'];
					return json_encode($result_js);
				}
			} else
				$fieldName_or_layout_tag = $fieldName_or_layout;
		}

		$selector1 = JoomlaBasicMisc::generateRandomString();
		$selector2 = JoomlaBasicMisc::generateRandomString() . '*';

		$itemLayout = '{{ record.id }}' . $selector1 . $fieldName_or_layout_tag . $selector2;
		$pageLayoutContent = '{% block record %}' . $itemLayout . '{% endblock %}';

		$paramsArray['establename'] = $tableName;

		$params = new Registry;
		$params->loadArray($paramsArray);
		$ct->setParams($params);

		$pathViews = CUSTOMTABLES_LIBRARIES_PATH
			. DIRECTORY_SEPARATOR . 'customtables' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;

		require_once($pathViews . 'json.php');

		$jsonOutput = new ViewJSON($ct);
		$output = $jsonOutput->render($pageLayoutContent, false);
		$outputList = JoomlaBasicMisc::csv_explode($selector2, $output, '"', false);
		$outputArray = [];
		foreach ($outputList as $outputListItems) {
			$items = JoomlaBasicMisc::csv_explode($selector1, $outputListItems, '"', false);
			if ($items[0] != '')
				$outputArray[] = ["value" => $items[0], "label" => $items[1]];
		}

		$outputString = json_encode($outputArray);

		if ($obEndClean) {
			if (ob_get_contents()) ob_end_clean();
			header('Content-Type: application/json; charset=utf-8');
			header("Pragma: no-cache");
			header("Expires: 0");

			die($outputString);
		}

		return $outputString;
	}

	function render(?string $value, ?array $row)
	{
		$this->row = $row;
		$this->field = new Field($this->ct, $this->field->fieldrow, $this->row);
		$this->prefix = $this->ct->Env->field_input_prefix . (!$this->ct->isEditForm ? $this->row[$this->ct->Table->realidfieldname] . '_' : '');

		if ($this->field->defaultvalue !== '' and $value === null) {
			$twig = new TwigProcessor($this->ct, $this->field->defaultvalue);
			$this->defaultValue = $twig->process($this->row);
		} else
			$this->defaultValue = null;

		switch ($this->field->type) {
			case 'radio':
				return $this->render_radio($value);

			case 'ordering':
			case 'int':
				return $this->render_int($value);

			case 'float':
				return $this->render_float($value);

			case 'phponadd':
			case 'phponchange':

				if ($value === null) {
					$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
					$value = preg_replace("/[^A-Za-z\d\-]/", '', $value);
					if ($value == '')
						$value = $this->defaultValue;
				}

				return $value . '<input type="hidden" '
					. 'name="' . $this->prefix . $this->field->fieldname . '" '
					. 'id="' . $this->prefix . $this->field->fieldname . '" '
					. 'data-type="' . $this->field->type . '" '
					. 'value="' . htmlspecialchars($value ?? '') . '" />';

			case 'phponview':
				return $value;

			case 'string':
				return $this->getTextBox($value);

			case 'alias':
				return $this->render_alias($value);

			case 'multilangstring':
				return $this->getMultilingualString();

			case 'text':
				return $this->render_text($value);

			case 'multilangtext'://dok
				require_once(CUSTOMTABLES_LIBRARIES_PATH . DIRECTORY_SEPARATOR . 'fieldtypes' . DIRECTORY_SEPARATOR . 'multilangtext.php');
				return $this->render_multilangtext();

			case 'checkbox':
				return $this->render_checkbox($value);

			case 'image': //Default value cannot be used with this data type.
				$image_type_file = CUSTOMTABLES_LIBRARIES_PATH . DIRECTORY_SEPARATOR . 'fieldtypes' . DIRECTORY_SEPARATOR . '_type_image.php';
				require_once($image_type_file);

				return CT_FieldTypeTag_image::renderImageFieldBox($this->ct, $this->field, $this->prefix, $this->row);

			case 'signature': //Default value cannot be used with this data type.
				return $this->render_signature();

			case 'blob': //Default value cannot be used with this data type.
			case 'file':
				$file_type_file = CUSTOMTABLES_LIBRARIES_PATH . DIRECTORY_SEPARATOR . 'fieldtypes' . DIRECTORY_SEPARATOR . '_type_file.php';
				require_once($file_type_file);
				return CT_FieldTypeTag_file::renderFileFieldBox($this->ct, $this->field, $this->row);

			case 'userid':
			case 'user':
				return $this->getUserBox($value);

			case 'usergroup':
				return $this->getUserGroupBox($value);

			case 'usergroups':
				if ($value === null) {
					$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
					$value = preg_replace('/[^\0-9]/u', '', $value);
					if ($value == '')
						$value = $this->defaultValue;
				}

				return JHTML::_('ESUserGroups.render',
					$this->prefix . $this->field->fieldname,
					$value,
					$this->field->params
				);

			case 'language':
				if ($value === null) {
					$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
					if ($value == '') {
						if ($this->defaultValue === null or $this->defaultValue === '') {
							//If it's a new record then current language will be used.
							$langObj = Factory::getLanguage();
							$value = $langObj->getTag();
						} else
							$value = $this->defaultValue;
					}
				}

				$lang_attributes = array(
					'name' => $this->prefix . $this->field->fieldname,
					'id' => $this->prefix . $this->field->fieldname,
					'data-type' => "language",
					'label' => $this->field->title, 'readonly' => false);

				return CTTypes::getField('language', $lang_attributes, $value)->input;

			case 'color':
				return $this->render_color($value);

			case 'filelink':
				if ($value === null) {
					$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
					if ($value == '')
						$value = $this->defaultValue;
				}

				return JHTML::_('ESFileLink.render', $this->prefix . $this->field->fieldname, $value, $this->cssStyle, $this->cssclass, $this->field->params[0], $this->attributes);

			case 'customtables':
				return $this->render_customtables($value);

			case 'sqljoin':
				return $this->render_tablejoin($value);

			case 'records':
				return $this->render_records($value);

			case 'googlemapcoordinates'://dok
				if ($value === null) {
					$value = common::inputGetCmd($this->ct->Env->field_prefix . $this->field->fieldname, '');
					if ($value == '')
						$value = $this->defaultValue;
				}
				return JHTML::_('GoogleMapCoordinates.render', $this->prefix . $this->field->fieldname, $value);

			case 'email'://dok
				if ($value === null) {
					$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
					//https://stackoverflow.com/questions/58265286/remove-all-special-characters-from-string-to-make-it-a-valid-email-but-keep-%C3%A4%C3%B6%C3%BC
					$value = preg_replace('/[^\p{L}\d\-.;@_]/u', '', $value);

					if ($value == '')
						$value = $this->defaultValue;
				}

				return '<input '
					. 'type="text" '
					. 'name="' . $this->prefix . $this->field->fieldname . '" '
					. 'id="' . $this->prefix . $this->field->fieldname . '" '
					. 'class="' . $this->cssclass . '" '
					. 'value="' . htmlspecialchars($value ?? '') . '" maxlength="255" '
					. $this->attributes . ' '
					. 'data-type="email" '
					. 'data-filters="email" '
					. 'data-label="' . $this->field->title . '"'
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ' />';

			case 'url':
				return $this->render_url($value);

			case 'date':
				return $this->render_date($value);

			case 'time':
				return $this->render_time($value);

			case 'article':
				if ($value === null) {
					$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname);
					if ($value === null)
						$value = (int)$this->defaultValue;
				}

				return JHTML::_('CTArticle.render',
					$this->prefix . $this->field->fieldname,
					$value,
					$this->cssclass,
					$this->field->params
				);

			case 'imagegallery': //Default value cannot be used with this data type.
				if (!$this->ct->isRecordNull($this->row))
					return $this->getImageGallery($this->row[$this->ct->Table->realidfieldname]);
				break;

			case 'filebox': //Default value cannot be used with this data type.
				if (!$this->ct->isRecordNull($this->row))
					return $this->getFileBox($this->row[$this->ct->Table->realidfieldname]);
				break;

			case 'multilangarticle': //Default value cannot be used with this data type.
				if (!$this->ct->isRecordNull($this->row))
					return $this->renderMultilingualArticle();
				break;
		}
		return '';
	}

	protected function render_radio(?string $value): string
	{
		$result = '<ul>';
		$i = 0;

		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			$value = preg_replace("/[^A-Za-z\d\-]/", '', $value);
			if ($value == '')
				$value = $this->defaultValue;
		}

		foreach ($this->field->params as $radioValue) {
			$v = trim($radioValue);
			$result .= '<li><input type="radio"'
				. ' data-type="radio" '
				. ' name="' . $this->prefix . $this->field->fieldname . '"'
				. ' id="' . $this->prefix . $this->field->fieldname . '_' . $i . '"'
				. ' value="' . $v . '" '
				. ($value == $v ? ' checked="checked"' : '')
				. ' />'
				. '<label for="' . $this->prefix . $this->field->fieldname . '_' . $i . '">' . $v . '</label></li>';
			$i++;
		}
		$result .= '</ul>';

		return $result;
	}

	protected function render_int(?string $value): string
	{
		$result = '';

		if ($value === null) {
			$value = common::inputGetAlnum($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		if ($value == '')
			$value = (int)$this->field->defaultvalue;
		else
			$value = (int)$value;

		$result .= '<input '
			. 'type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . '" '
			. 'label="' . $this->field->fieldname . '" '
			. 'class="' . $this->cssclass . '" '
			. $this->attributes . ' '
			. 'data-type="' . $this->field->type . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
			. 'value="' . htmlspecialchars($value ?? '') . '" />';

		return $result;
	}

	protected function render_float(?string $value): string
	{
		$result = '';

		if ($value === null) {
			$value = common::inputGetCmd($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = (float)$this->defaultValue;
		}

		$result .= '<input '
			. 'type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . '" '
			. 'class="' . $this->cssclass . '" '
			. 'data-type="float" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
			. $this->attributes . ' ';

		$decimals = intval($this->field->params[0]);
		if ($decimals < 0)
			$decimals = 0;

		if (isset($values[2]) and $values[2] == 'smart')
			$result .= 'onkeypress="ESsmart_float(this,event,' . $decimals . ')" ';

		$result .= 'value="' . htmlspecialchars($value ?? '') . '" />';
		return $result;
	}

	protected function getTextBox($value): string
	{
		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		$autocomplete = false;
		if (isset($this->option_list[2]) and $this->option_list[2] == 'autocomplete')
			$autocomplete = true;

		$result = '<input type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . '" '
			. 'label="' . $this->field->fieldname . '" '
			. ($autocomplete ? 'list="' . $this->prefix . $this->field->fieldname . '_datalist" ' : '')
			. 'class="' . $this->cssclass . '" '
			. 'title="' . $this->place_holder . '" '
			. 'data-type="' . $this->field->type . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" ';

		if ($this->row === null)
			$result .= 'placeholder="' . $this->place_holder . '" ';

		$result .= 'value="' . htmlspecialchars($value ?? '') . '" ' . ((int)$this->field->params[0] > 0 ? 'maxlength="' . (int)$this->field->params[0] . '"' : 'maxlength="255"') . ' ' . $this->attributes . ' />';

		if ($autocomplete) {

			$query = 'SELECT ' . $this->field->realfieldname . ' FROM ' . $this->ct->Table->realtablename . ' GROUP BY ' . $this->field->realfieldname
				. ' ORDER BY ' . $this->field->realfieldname;

			$records = database::loadObjectList($query);

			$result .= '<datalist id="' . $this->prefix . $this->field->fieldname . '_datalist">'
				. (count($records) > 0 ? '<option value="' . implode('"><option value="', $records) . '">' : '')
				. '</datalist>';
		}

		return $result;
	}

	protected function render_alias($value): string
	{
		$maxlength = 0;
		if ($this->field->params !== null and count($this->field->params) > 0)
			$maxlength = (int)$this->field->params[0];

		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		$result = '<input type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . '" '
			. 'label="' . $this->field->fieldname . '" '
			. 'class="' . $this->cssclass . '" '
			. ' ' . $this->attributes
			. 'data-type="' . $this->field->type . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" ';

		if ($this->row === null)
			$result .= 'placeholder="' . $this->place_holder . '" ';

		$result .= 'value="' . htmlspecialchars($value ?? '') . '" ' . ($maxlength > 0 ? 'maxlength="' . $maxlength . '"' : 'maxlength="255"') . ' ' . $this->attributes . ' />';

		return $result;
	}

	protected function getMultilingualString(): string
	{
		$result = '';
		//Specific language selected
		if (isset($this->option_list[4])) {
			$language = $this->option_list[4];

			$firstLanguage = true;
			foreach ($this->ct->Languages->LanguageList as $lang) {
				if ($firstLanguage) {
					$postfix = '';
					$firstLanguage = false;
				} else
					$postfix = '_' . $lang->sef;

				if ($language == $lang->sef) {
					//show single edit box
					return $this->getMultilingualStringItem($postfix, $lang->sef);
				}
			}
		}

		//show all languages
		$result .= '<div class="form-horizontal">';

		$firstLanguage = true;
		foreach ($this->ct->Languages->LanguageList as $lang) {
			if ($firstLanguage) {
				$postfix = '';
				$firstLanguage = false;
			} else
				$postfix = '_' . $lang->sef;

			$result .= '
			<div class="control-group">
				<div class="control-label">' . $lang->caption . '</div>
				<div class="controls">' . $this->getMultilingualStringItem($postfix, $lang->sef) . '</div>
			</div>';
		}
		$result .= '</div>';
		return $result;
	}

	protected function getMultilingualStringItem(string $postfix, string $langSEF): string
	{
		$attributes_ = '';
		$addDynamicEvent = false;

		if (str_contains($this->attributes, 'onchange="ct_UpdateSingleValue('))//its like a keyword
		{
			$addDynamicEvent = true;
		} else
			$attributes_ = $this->attributes;

		$value = $this->row[$this->field->realfieldname . $postfix] ?? null;
		if ($value === null) {
			$value = common::inputGetString($this->prefix . $this->field->fieldname . $postfix, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		if ($addDynamicEvent) {
			$href = 'onchange="ct_UpdateSingleValue(\'' . $this->ct->Env->WebsiteRoot . '\','
				. $this->ct->Params->ItemId . ',\'' . $this->field->fieldname . $postfix . '\','
				. $this->row[$this->ct->Table->realidfieldname] . ',\'' . $langSEF . '\',' . (int)$this->ct->Params->ModuleId . ')"';

			$attributes_ = ' ' . $href;
		}

		return '<input type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . $postfix . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . $postfix . '" '
			. 'class="' . $this->cssclass . '" '
			. 'value="' . htmlspecialchars($value ?? '') . '" '
			. 'data-type="' . $this->field->type . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
			. ((int)$this->field->params[0] > 0 ? 'maxlength="' . (int)$this->field->params[0] . '" ' : 'maxlength="255" ')
			. $attributes_ . ' />';
	}

	protected function render_text(?string $value): string
	{
		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		$result = '';
		$fullFieldName = $this->prefix . $this->field->fieldname;

		if (in_array('rich', $this->field->params)) {
			$w = $this->option_list[2] ?? '100%';
			$h = $this->option_list[3] ?? '300';
			$c = 0;
			$l = 0;
			$editor_name = $this->ct->app->get('editor');
			$editor = Editor::getInstance($editor_name);
			$result .= '<div>' . $editor->display($fullFieldName, $value, $w, $h, $c, $l) . '</div>';
		} else {
			$result .= '<textarea name="' . $fullFieldName . '" '
				. 'id="' . $fullFieldName . '" '
				. 'class="' . $this->cssclass . '" '
				. $this->attributes . ' '
				. 'data-type="' . $this->field->type . '" '
				. 'data-label="' . $this->field->title . '" '
				. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
				. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
				. '>' . htmlspecialchars($value ?? '') . '</textarea>';
		}

		if (in_array('spellcheck', $this->field->params)) {
			$file_path = JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_customtables' . DIRECTORY_SEPARATOR . 'thirdparty'
				. DIRECTORY_SEPARATOR . 'jsc' . DIRECTORY_SEPARATOR . 'include.js';

			if (file_exists($file_path)) {
				$this->ct->document->addCustomTag('<script src="' . URI::root(true) . '/components/com_customtables/thirdparty/jsc/include.js"></script>');
				$this->ct->document->addCustomTag('<script>$Spelling.SpellCheckAsYouType("' . $fullFieldName . '");</script>');
				$this->ct->document->addCustomTag('<script>$Spelling.DefaultDictionary = "English";</script>');
			}
		}
		return $result;
	}

	protected function render_multilangtext(): string
	{
		$RequiredLabel = 'Field is required';
		$result = '';
		$firstLanguage = true;
		foreach ($this->ct->Languages->LanguageList as $lang) {
			if ($firstLanguage) {
				$postfix = '';
				$firstLanguage = false;
			} else
				$postfix = '_' . $lang->sef;

			$fieldname = $this->field->fieldname . $postfix;

			$value = null;
			if (isset($this->row) and array_key_exists($this->ct->Env->field_prefix . $fieldname, $this->row)) {
				$value = $this->row[$this->ct->Env->field_prefix . $fieldname];
			} else {
				Fields::addLanguageField($this->ct->Table->realtablename, $this->ct->Env->field_prefix . $this->field->fieldname, $this->ct->Env->field_prefix . $fieldname);
				$this->ct->errors[] = 'Field "' . $this->ct->Env->field_prefix . $fieldname . '" not yet created. Go to /Custom Tables/Database schema/Checks to create that field.';
				$value = '';
			}

			if ($value === null) {
				$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
				if ($value == '')
					$value = $this->defaultValue;
			}

			$result .= ($this->field->isrequired == 1 ? ' ' . $RequiredLabel : '');

			$result .= '<div id="' . $fieldname . '_div" class="multilangtext">';

			if ($this->field->params[0] == 'rich') {
				$result .= '<span class="language_label_rich">' . $lang->caption . '</span>';

				$w = 500;
				$h = 200;
				$c = 0;
				$l = 0;

				$editor_name = $this->ct->app->get('editor');
				$editor = Editor::getInstance($editor_name);

				$fullFieldName = $this->prefix . $fieldname;
				$result .= '<div>' . $editor->display($fullFieldName, $value, $w, $h, $c, $l) . '</div>';
			} else {
				$result .= '<textarea name="' . $this->prefix . $fieldname . '" '
					. 'id="' . $this->prefix . $fieldname . '" '
					. 'data-type="' . $this->field->type . '" '
					. 'class="' . $this->cssclass . ' ' . ($this->field->isrequired == 1 ? 'required' : '') . '">' . htmlspecialchars($value ?? '') . '</textarea>'
					. '<span class="language_label">' . $lang->caption . '</span>';

				$result .= ($this->field->isrequired == 1 ? ' ' . $RequiredLabel : '');
			}
			$result .= '</div>';
		}
		return $result;
	}

	protected function render_checkbox(?string $value_): string
	{
		if ($value_ === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = (int)$this->defaultValue;
		} else {
			$value = (int)$value_;
		}

		$result = '';
		$format = '';

		if (isset($this->option_list[2]) and $this->option_list[2] == 'yesno')
			$format = "yesno";

		if ($format == "yesno") {
			$element_id = $this->prefix . $this->field->fieldname;
			if ($this->ct->Env->version < 4) {
				$result .= '<fieldset id="' . $this->prefix . $this->field->fieldname . '" class="' . $this->cssclass . ' btn-group radio btn-group-yesno" '
					. 'style="border:none !important;background:none !important;">';

				$result .= '<div style="position: absolute;visibility:hidden !important; display:none !important;">'
					. '<input type="radio" '
					. 'id="' . $element_id . '0" '
					. 'name="' . $element_id . '" '
					. 'value="1" '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. $this->attributes . ' '
					. ($value == 1 ? ' checked="checked" ' : '')
					. ' >'
					. '</div>'
					. '<label class="btn' . ($value == 1 ? ' active btn-success' : '') . '" for="' . $element_id . '0" id="' . $element_id . '0_label" >' . common::translate('COM_CUSTOMTABLES_YES') . '</label>';

				$result .= '<div style="position: absolute;visibility:hidden !important; display:none !important;">'
					. '<input type="radio" '
					. 'id="' . $element_id . '1" '
					. 'name="' . $element_id . '" '
					. $this->attributes . ' '
					. 'value="0" '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value == 0 ? ' checked="checked" ' : '')
					. ' >'
					. '</div>'
					. '<label class="btn' . ($value == 0 ? ' active btn-danger' : '') . '" for="' . $element_id . '1" id="' . $element_id . '1_label">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>';

				$result .= '</fieldset>';
			} else {
				$result .= '<div class="switcher">'
					. '<input type="radio" '
					. 'id="' . $element_id . '0" '
					. 'name="' . $element_id . '" '
					. $this->attributes . ' '
					. 'value="0" '
					. 'class="active " '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value == 0 ? ' checked="checked" ' : '')
					. ' >'
					. '<label for="' . $element_id . '0">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>'
					. '<input type="radio" '
					. 'id="' . $element_id . '1" '
					. 'name="' . $element_id . '" '
					. $this->attributes . ' '
					. 'value="1" '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value == 1 ? ' checked="checked" ' : '')
					. ' >'
					. '<label for="' . $element_id . '1">' . common::translate('COM_CUSTOMTABLES_YES') . '</label>'
					. '<span class="toggle-outside"><span class="toggle-inside"></span></span>'
					. '</div>';
			}
		} else {
			if ($this->ct->Env->version < 4) {
				$onchange = $this->prefix . $this->field->fieldname . '_off.value=(this.checked === true ? 0 : 1);';// this is to save unchecked value as well.

				if (str_contains($this->attributes, 'onchange="'))
					$check_attributes = str_replace('onchange="', 'onchange="' . $onchange, $this->attributes);// onchange event already exists add one before
				else
					$check_attributes = $this->attributes . 'onchange="' . $onchange;

				$result .= '<input type="checkbox" '
					. 'id="' . $this->prefix . $this->field->fieldname . '" '
					. 'name="' . $this->prefix . $this->field->fieldname . '" '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value ? ' checked="checked" ' : '')
					. ($this->cssStyle != '' ? ' class="' . $this->cssStyle . '" ' : '')
					. ($this->cssclass != '' ? ' class="' . $this->cssclass . '" ' : '')
					. ($check_attributes != '' ? ' ' . $check_attributes : '')
					. '>'
					. '<input type="hidden"'
					. ' id="' . $this->prefix . $this->field->fieldname . '_off" '
					. ' name="' . $this->prefix . $this->field->fieldname . '_off" '
					. ($value == 1 ? ' value="0" ' : 'value="1"')
					. ' >';
			} else {
				$element_id = $this->prefix . $this->field->fieldname;

				$result .= '<div class="switcher">'
					. '<input type="radio" '
					. 'id="' . $element_id . '0" '
					. 'name="' . $element_id . '" '
					. 'value="0" '
					. 'class="active " '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value == 0 ? ' checked="checked" ' : '')
					. ' >'
					. '<label for="' . $element_id . '0">' . common::translate('COM_CUSTOMTABLES_NO') . '</label>'
					. '<input type="radio" '
					. 'id="' . $element_id . '1" '
					. 'name="' . $element_id . '" '
					. 'value="1" '
					. 'data-type="' . $this->field->type . '" '
					. 'data-label="' . $this->field->title . '" '
					. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
					. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
					. ($value == 1 ? ' checked="checked" ' : '') . ' >'
					. '<label for="' . $element_id . '1">' . common::translate('COM_CUSTOMTABLES_YES') . '</label>'
					. '<span class="toggle-outside"><span class="toggle-inside"></span></span>'
					. '<input type="hidden"'
					. ' id="' . $this->prefix . $this->field->fieldname . '_off" '
					. ' name="' . $this->prefix . $this->field->fieldname . '_off" '
					. ($value == 1 ? ' value="0" ' : 'value="1"')
					. ' >'
					. '</div>'
					. '
						<script>
							document.getElementById("' . $element_id . '0").onchange = function(){if(this.checked === true)' . $this->prefix . $this->field->fieldname . '_off.value=1;' . $this->onchange . '};
							document.getElementById("' . $element_id . '1").onchange = function(){if(this.checked === true)' . $this->prefix . $this->field->fieldname . '_off.value=0;' . $this->onchange . '};
						</script>
';
			}
		}
		return $result;
	}

	protected function render_signature(): string
	{
		$width = $this->field->params[0] ?? 300;
		$height = $this->field->params[1] ?? 150;
		$format = $this->field->params[3] ?? 'svg';
		if ($format == 'svg-db')
			$format = 'svg';

		//https://github.com/szimek/signature_pad/blob/gh-pages/js/app.js
		//https://stackoverflow.com/questions/46514484/send-signature-pad-to-php-post-method
		//		class="wrapper"
		$result = '
<div class="ctSignature_flexrow" style="width:' . $width . 'px;height:' . $height . 'px;padding:0;">
	<div style="position:relative;display: flex;padding:0;">
		<canvas style="background-color: #ffffff;padding:0;width:' . $width . 'px;height:' . $height . 'px;" '
			. 'id="' . $this->prefix . $this->field->fieldname . '_canvas" '
			. 'class="uneditable-input ' . $this->cssclass . '" '
			. $this->attributes
			. ' >
		</canvas>
		<div class="ctSignature_clear"><button type="button" class="close" id="' . $this->prefix . $this->field->fieldname . '_clear">×</button></div>';
		$result .= '
	</div>
</div>

<input type="text" style="display:none;" name="' . $this->prefix . $this->field->fieldname . '" id="' . $this->prefix . $this->field->fieldname . '" value="" '
			. 'data-type="' . $this->field->type . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" >';

		$ctInputbox_signature = $this->prefix . $this->field->fieldname . '",' . ((int)$width) . ',' . ((int)$height) . ',"' . $format;
		$result .= '
<script>
	ctInputbox_signature("' . $ctInputbox_signature . '")
</script>';
		return $result;
	}

	protected function getUserBox(?string $value): string
	{
		if ($value === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = $this->defaultValue;
		}
		$result = '';

		if ($this->ct->Env->user->id === null)
			return '';

		$attributes = 'class="' . $this->cssclass . '" ' . $this->attributes;
		$userGroup = $this->field->params[0] ?? '';

		$where = '';
		if (isset($this->field->params[3]))
			$where = 'INSTR(name,"' . $this->field->params[3] . '")';

		$result .= JHTML::_('ESUser.render', $this->prefix . $this->field->fieldname, $value ?? '', '', $attributes, $userGroup, '', $where);
		return $result;
	}

	protected function getUserGroupBox(?string $value): string
	{
		if ($value === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = $this->defaultValue;
		}
		$result = '';

		if ($this->ct->Env->user->id === null)
			return '';

		$attributes = 'class="' . $this->cssclass . '" ' . $this->attributes;
		$availableUserGroupsList = ($this->field->params[0] == '' ? [] : $this->field->params);

		if (count($availableUserGroupsList) == 0) {
			$where_string = '#__usergroups.title!=' . database::quote('Super Users');
		} else {
			$where = [];
			foreach ($availableUserGroupsList as $availableUserGroup) {
				if ($availableUserGroup != '')
					$where[] = '#__usergroups.title=' . database::quote($availableUserGroup);
			}
			$where_string = '(' . implode(' OR ', $where) . ')';
		}
		$result .= JHTML::_('ESUserGroup.render', $this->prefix . $this->field->fieldname, $value, '', $attributes, $where_string);
		return $result;
	}

	protected function render_color(?string $value): string
	{
		if ($value === null) {
			$value = common::inputGetAlnum($this->ct->Env->field_prefix . $this->field->fieldname, '');
			if ($value == '')
				$value = $this->defaultValue;
		}

		$result = '';
		$att = array(
			'name' => $this->prefix . $this->field->fieldname,
			'id' => $this->prefix . $this->field->fieldname,
			'data-type' => $this->field->type,
			'label' => $this->field->title);

		if ($this->option_list[0] == 'transparent') {
			$att['format'] = 'rgba';
			$att['keywords'] = 'transparent,initial,inherit';

			//convert value to rgba: rgba(255, 0, 255, 0.1)
			$value = JoomlaBasicMisc::colorStringValueToCSSRGB($value);
		}
		$array_attributes = $this->prepareAttributes($att, $this->attributes);
		$inputbox = CTTypes::getField('color', $array_attributes, $value)->input;

		//Add onChange attribute if not added
		$onChangeAttribute = '';
		foreach ($array_attributes as $key => $attributeValue) {
			if ('onChange' == $key) {
				$onChangeAttribute = 'onChange="' . $attributeValue . '"';
				break;
			}
		}
		if ($onChangeAttribute != '' and !str_contains($inputbox, 'onChange'))
			$inputbox = str_replace('<input ', '<input ' . $onChangeAttribute, $inputbox);

		$result .= $inputbox;
		return $result;
	}

	protected function prepareAttributes($attributes_, $attributes_str)
	{
		//Used for 'color' field type
		if ($attributes_str != '') {
			$attributesList = JoomlaBasicMisc::csv_explode(' ', $attributes_str, '"', false);
			foreach ($attributesList as $a) {
				$pair = explode('=', $a);

				if (count($pair) == 2) {
					$att = $pair[0];
					if ($att == 'onchange')
						$att = 'onChange';

					$attributes_[$att] = $pair[1];
				}
			}
		}
		return $attributes_;
	}

	protected function render_customtables(?string $value): string
	{
		$result = '';

		if (!isset($this->field->params[1]))
			return 'selector not specified';

		$optionName = $this->field->params[0];
		$parentId = Tree::getOptionIdFull($optionName);

		//$this->field->params[0] is structure parent
		//$this->field->params[1] is selector type (multi or single)
		//$this->field->params[2] is data length
		//$this->field->params[3] is requirement depth

		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname);
			if ($value === null) {
				if ($this->field->defaultvalue !== null and $this->field->defaultvalue != '')
					$value = ',' . $this->field->params[0] . '.' . $this->defaultValue . '.,';
			}
		}

		if ($this->field->params[1] == 'multi') {
			$result .= JHTML::_('MultiSelector.render',
				$this->prefix,
				$parentId, $optionName,
				$this->ct->Languages->Postfix,
				$this->ct->Table->tablename,
				$this->field->fieldname,
				$value,
				'',
				$this->place_holder);
		} elseif ($this->field->params[1] == 'single') {
			$result .= '<div style="float:left;">';
			$result .= JHTML::_('ESComboTree.render',
				$this->prefix,
				$this->ct->Table->tablename,
				$this->field->fieldname,
				$optionName,
				$this->ct->Languages->Postfix,
				$value,
				'',
				'',
				'',
				'',
				$this->field->isrequired,
				(isset($this->field->params[3]) ? (int)$this->field->params[3] : 1),
				$this->place_holder,
				$this->field->valuerule,
				$this->field->valuerulecaption
			);

			$result .= '</div>';
		} else
			$result .= 'selector not specified';

		return $result;
	}

	protected function render_tablejoin(?string $value): string
	{
		$result = '';

		//CT Example: [house:RedHouses,onChange('Alert("Value Changed")'),city=London]

		//$this->option_list[0] - CSS Class
		//$this->option_list[1] - Optional Attributes
		//$this->option_list[2] - Parent Selector - Array
		//$this->option_list[3] - Custom Title Layout

		if ($value === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = $this->defaultValue;
		}

		$sqljoin_attributes = ' data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '"'
			. ' data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '"';

		if ($this->isTwig) {
			//Twig Tag
			//Twig Example: [house:RedHouses,onChange('Alert("Value Changed")'),city=London]

			$result .= JHTML::_('CTTableJoin.render',
				$this->prefix . $this->field->fieldname,
				$this->field,
				($this->row !== null ? $this->row[$this->ct->Table->realidfieldname] : null),
				$value,
				$this->option_list,
				$this->onchange,
				$sqljoin_attributes);
		} else {
			//CT Tag
			if (isset($this->option_list[2]) and $this->option_list[2] != '')
				$this->field->params[2] = $this->option_list[2];//Overwrites field type filter parameter.

			$sqljoin_attributes .= ' onchange="' . $this->onchange . '"';

			$result .= JHTML::_('ESSQLJoin.render',
				$this->field->params,
				$value,
				false,
				$this->ct->Languages->Postfix,
				$this->prefix . $this->field->fieldname,
				$this->place_holder,
				$this->cssclass,
				$sqljoin_attributes);
		}
		return $result;
	}

	protected function render_records(?string $value): string
	{
		$result = '';

		//CT Example: [house:RedHouses,onChange('Alert("Value Changed")'),city=London]

		//$this->option_list[0] - CSS Class
		//$this->option_list[1] - Optional Attributes
		//$this->option_list[2] - Parent Selector - Array
		//$this->option_list[3] - Custom Title Layout

		if ($value === null) {
			$value = common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname, 0);
			if ($value == 0)
				$value = $this->defaultValue;
		}

		$sqljoin_attributes = ' data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '"'
			. ' data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '"';
		/*
				if ($this->isTwig) {
					//Twig Tag
					//Twig Example: [house:RedHouses,onChange('Alert("Value Changed")'),city=London]

					$result .= JHTML::_('CTTableMultiJoin.render',
						$this->prefix . $this->field->fieldname,
						$this->field,
						($this->row !== null ? $this->row[$this->ct->Table->realidfieldname] : null),
						$value,
						$this->option_list,
						$this->onchange,
						$sqljoin_attributes);
				} else {
		*/
		//records : table, [fieldname || layout:layoutname], [selector: multi || single], filter, |datalength|

		//Check minimum requirements
		if (count($this->field->params) < 1)
			$result .= 'table not specified';

		if (count($this->field->params) < 2)
			$result .= 'field or layout not specified';

		if (count($this->field->params) < 3)
			$result .= 'selector not specified';

		$esr_table = $this->field->params[0];

		$advancedOption = null;
		if (isset($this->option_list[2]) and is_array($this->option_list[2]))
			$advancedOption = $this->option_list[2];

		if (isset($this->option_list[3])) {
			$esr_field = 'layout:' . $this->option_list[3];
		} else {
			if ($advancedOption and isset($advancedOption[1]) and $advancedOption[1] and $advancedOption[1] != "")
				$esr_field = $advancedOption[1];
			else
				$esr_field = $this->field->params[1] ?? '';
		}

		$esr_selector = $this->field->params[2] ?? '';

		if (isset($this->option_list[5])) {
			//To back-support old style
			$esr_filter = $this->option_list[5];
		} elseif ($advancedOption and isset($advancedOption[3]) and $advancedOption[3] and $advancedOption[3] != "") {
			$esr_filter = $advancedOption[3];
		} elseif (count($this->field->params) > 3)
			$esr_filter = $this->field->params[3];
		else
			$esr_filter = '';

		$dynamic_filter = $this->field->params[4] ?? '';

		if ($advancedOption and isset($advancedOption[4]) and $advancedOption[4] and $advancedOption[4] != "")
			$sortByField = $advancedOption[4];
		else
			$sortByField = $this->field->params[5] ?? '';

		$records_attributes = ($this->attributes != '' ? ' ' : '')
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
			. 'data-type="filelink"';

		if ($value === null) {
			$value = SaveFieldQuerySet::get_record_type_value($this->field);
			common::inputGetInt($this->ct->Env->field_prefix . $this->field->fieldname);
			if ($value == '')
				$value = $this->defaultValue;
		}

		$result .= JHTML::_('ESRecords.render',
			$this->field->params,
			$this->prefix . $this->field->fieldname,
			$value,
			$esr_table,
			$esr_field,
			$esr_selector,
			$esr_filter,
			'',
			($this->cssclass == '' ? 'ct_improved_selectbox' : $this->cssclass),
			$records_attributes,
			$dynamic_filter,
			$sortByField,
			$this->ct->Languages->Postfix,
			$this->place_holder
		);
		return $result;
	}

	protected function render_url(?string $value): string
	{
		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			//https://stackoverflow.com/questions/58265286/remove-all-special-characters-from-string-to-make-it-a-valid-email-but-keep-%C3%A4%C3%B6%C3%BC
			$value = preg_replace('/[^\p{L}\d\-.;@_]/u', '', $value);

			if ($value == '')
				$value = $this->defaultValue;
		}

		$result = '';
		$filters = array();
		$filters[] = 'url';

		if (isset($this->field->params[1]) and $this->field->params[1] == 'true')
			$filters[] = 'https';

		if (isset($this->field->params[2]) and $this->field->params[2] != '')
			$filters[] = 'domain:' . $this->field->params[2];

		$result .= '<input '
			. 'type="text" '
			. 'name="' . $this->prefix . $this->field->fieldname . '" '
			. 'id="' . $this->prefix . $this->field->fieldname . '" '
			. 'class="' . $this->cssclass . '" '
			. 'value="' . htmlspecialchars($value ?? '') . '" '
			. 'maxlength="1024" '
			. 'data-type="' . $this->field->type . '"'
			. 'data-sanitizers="trim" '
			. 'data-filters="' . implode(',', $filters) . '" '
			. 'data-label="' . $this->field->title . '" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" '
			. $this->attributes
			. ' />';

		return $result;
	}

	protected function render_date(?string $value): string
	{
		$result = '';

		if ($value === null) {
			$value = common::inputGetString($this->ct->Env->field_prefix . $this->field->fieldname, '');
			$value = preg_replace('/[^\0-9]/u', '', $value);

			if ($value == '')
				$value = $this->defaultValue;
		}

		if ($value == "0000-00-00" or is_null($value))
			$value = '';

		$attributes = [];
		$attributes['class'] = $this->cssclass;

		$att = [];

		if ($this->row === null)
			$att[] = 'placeholder="' . $this->place_holder . '"';

		$att[] = 'title="' . $this->place_holder . '"';
		$att[] = 'data-type="date"';
		$att[] = 'data-label="' . $this->place_holder . '"';
		$att[] = 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '"';
		$att[] = 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption); // closing quote is not needed because onchange parameter already has opening and closing quotes.

		if ($this->attributes != '' and str_contains($this->attributes, 'onchange=')) {
			$attributes['onChange'] = str_replace('onchange="', '', $this->attributes) . ' ' . implode(' ', $att);
		} else {
			$attributes['onChange'] = '" ' . implode(' ', $att);
		}

		$attributes['required'] = ($this->field->isrequired == 1 ? 'required' : ''); //not working, don't know why.

		if (isset($this->option_list[2]) and $this->option_list[2] != "")
			$format = $this->phpToJsDateFormat($this->option_list[2]);
		else
			$format = null;

		if ($this->field->params !== null and $this->field->params[0] == 'datetime') {
			$attributes['showTime'] = true;
			if ($format === null)
				$format = '%Y-%m-%d %H:%M:%S';
		} else {
			if ($format === null)
				$format = '%Y-%m-%d';
		}

		$result .= JHTML::calendar($value, $this->prefix . $this->field->fieldname, $this->prefix . $this->field->fieldname,
			$format, $attributes);

		return $result;
	}

	protected function phpToJsDateFormat($phpFormat)
	{
		$formatConversion = array(
			'Y' => '%Y',  // Year
			'y' => '%y',  // Year
			'm' => '%m',  // Month
			'n' => '%n',  // Month without leading zeros
			'd' => '%d',  // Day of the month
			'j' => '%e',  // Day of the month without leading zeros
			'H' => '%H',  // Hours in 24-hour format
			'i' => '%M',  // Minutes
			's' => '%S',  // Seconds
			// Add more format conversions as needed
		);

		$jsFormat = strtr($phpFormat, $formatConversion);
		return $jsFormat;
	}

	protected function render_time(?string $value): string
	{
		$result = '';

		if ($value === null) {
			$value = common::inputGetCmd($this->ct->Env->field_prefix . $this->field->fieldname, '');

			if ($value == '')
				$value = $this->defaultValue;
		}

		$value = (int)$value;
		$time_attributes = ($this->attributes != '' ? ' ' : '')
			. 'data-type="time" '
			. 'data-valuerule="' . str_replace('"', '&quot;', $this->field->valuerule) . '" '
			. 'data-valuerulecaption="' . str_replace('"', '&quot;', $this->field->valuerulecaption) . '" ';

		$result .= JHTML::_('CTTime.render', $this->prefix . $this->field->fieldname, $value, $this->cssclass, $time_attributes, $this->field->params, $this->option_list);
		return $result;
	}

	protected function getImageGallery($listing_id): string
	{
		require_once(CUSTOMTABLES_LIBRARIES_PATH . DIRECTORY_SEPARATOR . 'fieldtypes' . DIRECTORY_SEPARATOR . '_type_gallery.php');

		$result = '';
		$getGalleryRows = CT_FieldTypeTag_imagegallery::getGalleryRows($this->ct->Table->tablename, $this->field->fieldname, $listing_id);
		$image_prefix = '';

		if (isset($pair[1]) and (int)$pair[1] < 250)
			$img_width = (int)$pair[1];
		else
			$img_width = 250;

		$imageSRCList = CT_FieldTypeTag_imagegallery::getImageGallerySRC($getGalleryRows, $image_prefix, $this->field->fieldname,
			$this->field->params, $this->ct->Table->tableid);

		if (count($imageSRCList) > 0) {

			$result .= '<div style="width:100%;overflow:scroll;border:1px dotted grey;background-image: url(\'' . CUSTOMTABLES_MEDIA_WEBPATH . 'images/icons/bg.png\');">

		<table><tbody><tr>';

			foreach ($imageSRCList as $img) {
				$result .= '<td>';
				$result .= '<a href="' . $img . '" target="_blank"><img src="' . $img . '" style="width:' . $img_width . 'px;" />';
				$result .= '</td>';
			}

			$result .= '</tr></tbody></table>
		</div>';

		} else
			return 'No Images';

		return $result;
	}

	protected function getFileBox($listing_id): string
	{
		require_once(CUSTOMTABLES_LIBRARIES_PATH . DIRECTORY_SEPARATOR
			. 'customtables' . DIRECTORY_SEPARATOR . 'datatypes' . DIRECTORY_SEPARATOR . 'filebox.php');

		$manageButton = '';
		$FileBoxRows = CT_FieldTypeTag_FileBox::getFileBoxRows($this->ct->Table->tablename, $this->field->fieldname, $listing_id);

		foreach ($this->ct->Table->fileboxes as $fileBox) {
			if ($fileBox[0] == $this->field->fieldname) {
				$manageButton = CT_FieldTypeTag_FileBox::renderFileBoxIcon($this->ct, $listing_id, $fileBox[0], $fileBox[1]);
				break;
			}
		}

		if (count($FileBoxRows) > 0) {
			$vlu = CT_FieldTypeTag_FileBox::process($FileBoxRows, $this->field, $listing_id, ['', 'icon-filename-link', '32', '_blank', 'ol']);
			$result = '<div style="width:100%;overflow:scroll;background-image: url(\'components/com_customtables/libraries/customtables/media/images/icons/bg.png\');">'
				. $manageButton . '<br/>' . $vlu . '</div>';
		} else
			$result = common::translate('COM_CUSTOMTABLES_FILE_NO_FILES') . ' ' . $manageButton;

		return $result;
	}

	protected function renderMultilingualArticle(): string
	{
		$result = '
		<table>
			<tbody>';

		$firstLanguage = true;
		foreach ($this->ct->Languages->LanguageList as $lang) {
			if ($firstLanguage) {
				$postfix = '';
				$firstLanguage = false;
			} else
				$postfix = '_' . $lang->sef;

			$fieldname = $this->field->fieldname . $postfix;

			if ($this->ct->isRecordNull($this->row))
				$value = common::inputGetString($this->ct->Env->field_prefix . $fieldname, '');
			else
				$value = $this->row[$this->field->realfieldname . $postfix];

			$result .= '
				<tr>
					<td>' . $lang->caption . '</td>
					<td>:</td>
					<td>';

			$result .= JHTML::_('CTArticle.render',
				$this->prefix . $fieldname,
				$value,
				$this->cssclass,
				$this->field->params
			);

			$result .= '</td>
				</tr>';
		}
		$result .= '</body></table>';
		return $result;
	}

	function getDefaultValueIfNeeded($row)
	{
		$value = null;

		if ($this->ct->isRecordNull($row)) {
			$value = common::inputGetString($this->field->realfieldname);

			if ($value == '')
				$value = $this->getWhereParameter($this->field->realfieldname);

			if ($value == '') {
				$value = $this->field->defaultvalue;

				//Process default value, not processing PHP tag
				if ($value != '') {
					if ($this->ct->Env->legacySupport) {
						tagProcessor_General::process($this->ct, $value, $row);
						tagProcessor_Item::process($this->ct, $value, $row);
						tagProcessor_If::process($this->ct, $value, $row);
						tagProcessor_Page::process($this->ct, $value);
						tagProcessor_Value::processValues($this->ct, $value, $row);
					}

					$twig = new TwigProcessor($this->ct, $value);
					$value = $twig->process($row);

					if ($twig->errorMessage !== null)
						$this->ct->errors[] = $twig->errorMessage;

					if ($value != '') {
						if ($this->ct->Params->allowContentPlugins)
							JoomlaBasicMisc::applyContentPlugins($value);

						if ($this->field->type == 'alias') {
							$listing_id = $row[$this->ct->Table->realidfieldname] ?? 0;
							$saveField = new SaveFieldQuerySet($this->ct, $this->ct->Table->record, false);
							$saveField->field = $this->field;
							$value = $saveField->prepare_alias_type_value($listing_id, $value);
						}
					}
				}
			}
		} else {
			if ($this->field->type != 'multilangstring' and $this->field->type != 'multilangtext' and $this->field->type != 'multilangarticle') {
				$value = $row[$this->field->realfieldname] ?? null;
			}
		}
		return $value;
	}

	public function getWhereParameter($field): string
	{
		$f = str_replace($this->ct->Env->field_prefix, '', $field);

		$list = $this->getWhereParameters();

		foreach ($list as $l) {
			$p = explode('=', $l);
			if ($p[0] == $f and isset($p[1]))
				return $p[1];
		}
		return '';
	}

	protected function getWhereParameters(): array
	{
		$value = common::inputGetBase64('where', '');
		$b = base64_decode($value);
		$b = str_replace(' or ', ' and ', $b);
		$b = str_replace(' OR ', ' and ', $b);
		$b = str_replace(' AND ', ' and ', $b);
		return explode(' and ', $b);
	}
}
