<?php
/**
 * CustomTables Joomla! 3.x Native Component
 * @package Custom Tables
 * @author Ivan Komlev <support@joomlaboat.com>
 * @link http://www.joomlaboat.com
 * @copyright (C) 2018-2022 Ivan Komlev
 * @license GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');

use CustomTables\CT;
use CustomTables\Layouts;
use Joomla\CMS\Factory;

class CustomTablesViewCatalog extends JViewLegacy
{
    var $Model;
    var CT $ct;
    var $listing_id;
    var int $layoutType = 0;
    var string $catalogTableCode;
    var string $pagelayout;
    var string $itemlayout;

    function display($tpl = null)
    {
        $this->itemlayout = '';

        $this->Model = $this->getModel();

        $this->ct = $this->Model->ct;

        $key = $this->ct->Env->jinput->getCmd('key');
        if ($key != '')
            $this->renderTableJoinSelectorJSON($key);
        else
            $this->renderCatalog($tpl);
    }

    function renderTableJoinSelectorJSON($key)
    {
        $db = Factory::getDBO();

        $index = $this->Model->ct->Env->jinput->getInt('index');
        $selectors = (array)$this->ct->app->getUserState($key);

        if ($index < 0 or $index >= count($selectors))
            die(json_encode(['error' => 'Index out of range.']));

        $selector = $selectors[$index];

        $tablename = $selector[0];
        if ($tablename == '')
            die(json_encode(['error' => 'Table not selected']));

        $this->ct->getTable($tablename);
        if ($this->ct->Table->tablename == '')
            die(json_encode(['error' => 'Table "' . $tablename . '"not found']));

        $fieldname_or_layout = $selector[1];
        if ($fieldname_or_layout == null or $fieldname_or_layout == '')
            $fieldname_or_layout = $this->ct->Table->fields[0]['fieldname'];

        //$showPublished = 0 - show published
        //$showPublished = 1 - show unpublished
        //$showPublished = 2 - show any
        $showPublished = (($selector[2] ?? '') == '' ? 2 : ((int)($selector[2] ?? 0) == 1 ? 0 : 1)); //$selector[2] can be "" or "true" or "false"

        $filter = $selector[3] ?? '';

        $additional_filter = $this->ct->Env->jinput->getCmd('filter');

        $additional_where = '';
        //Find the field name that has a join to the parent (index-1) table
        foreach ($this->ct->Table->fields as $fld) {
            if ($fld['type'] == 'sqljoin') {
                $type_params = JoomlaBasicMisc::csv_explode(',', $fld['typeparams']);
                $join_tablename = $type_params[0];
                $join_to_tablename = $selector[5];

                if ($additional_filter != '') {
                    if ($join_tablename == $join_to_tablename)
                        $filter = $filter . ' and ' . $fld['fieldname'] . '=' . $additional_filter;
                } else {
                    //Check if this table has self-parent field - the TableJoin field linked with the same table.
                    if ($join_tablename == $tablename) {
                        $subFilter = $this->ct->Env->jinput->getCmd('subfilter');
                        if ($subFilter == '')
                            $additional_where = '(' . $fld['realfieldname'] . ' IS NULL OR ' . $fld['realfieldname'] . '="")';
                        else
                            $additional_where = $fld['realfieldname'] . '=' . $db->quote($subFilter);
                    }
                }
            }
        }
        $this->ct->setFilter($filter, $showPublished);
        if ($additional_where != '')
            $this->ct->Filter->where[] = $additional_where;

        $orderby = $selector[4] ?? '';

        //sorting
        $this->ct->Ordering->ordering_processed_string = $orderby;
        $this->ct->Ordering->parseOrderByString();

        $this->ct->getRecords();

        $this->catalogTableCode = JoomlaBasicMisc::generateRandomString();//this is temporary replace placeholder. to not parse content result again

        if (!str_contains($fieldname_or_layout, '{{') and !str_contains($fieldname_or_layout, 'layout')) {
            $fieldname_or_layout_tag = '{{ ' . $fieldname_or_layout . ' }}';
        } else {
            $pair = explode(':', $fieldname_or_layout);

            if (count($pair) == 2) {
                $layout_mode = true;
                if ($pair[0] != 'layout' and $pair[0] != 'tablelesslayout')
                    die(json_encode(['error' => JoomlaBasicMisc::JTextExtended('COM_CUSTOMTABLES_ERROR_UNKNOWN_FIELD_LAYOUT') . ' "' . $fieldname_or_layout . '"']));

                $Layouts = new Layouts($this->ct);
                $fieldname_or_layout_tag = $Layouts->getLayout($pair[1]);

                if (!isset($fieldname_or_layout_tag) or $fieldname_or_layout_tag == '')
                    die(JoomlaBasicMisc::JTextExtended('COM_CUSTOMTABLES_ERROR_LAYOUT_NOT_FOUND') . ' "' . $pair[1] . '"');
            } else
                $fieldname_or_layout_tag = $fieldname_or_layout;
        }

        $itemlayout = '{"id":"{{ record.id }}","label":"' . $fieldname_or_layout_tag . '"}';

        $this->pagelayout = '[{% block record %}'.$itemlayout.',{% endblock %}{}]';

        $paramsArray['establename'] = $tablename;

        $_params = new JRegistry;
        $_params->loadArray($paramsArray);
        $this->ct->Env->menu_params = $_params;

        require_once('tmpl' . DIRECTORY_SEPARATOR . 'json.php');
    }

    function renderCatalog($tpl): bool
    {
        $menu_params = null;
        $this->Model->load($menu_params, false, Factory::getApplication()->input->getCMD('layout', ''));

        $jinput = Factory::getApplication()->input;

        $addition_filter = '';
        $this->listing_id = $jinput->getCmd("listing_id", '');
        if ($this->listing_id != '')
            $addition_filter = $this->ct->Table->realidfieldname . '=' . $this->listing_id;

        $this->Model->getSearchResult($addition_filter);

        if (!isset($this->ct->Table->fields))
            return false;

        //Save view log
        $allowed_fields = $this->SaveViewLog_CheckIfNeeded();
        if (count($allowed_fields) > 0 and $this->ct->Records !== null) {
            foreach ($this->ct->Records as $rec)
                $this->SaveViewLogForRecord($rec, $allowed_fields);
        }

        if ($this->ct->Env->legacysupport)
            $this->catalogTableCode = JoomlaBasicMisc::generateRandomString();//this is temporary replace placeholder. to not parse content result again

        $Layouts = new Layouts($this->ct);

        $this->pagelayout = '';
        $layout_catalog_name = $this->ct->Env->menu_params->get('escataloglayout');
        if ($layout_catalog_name != '') {
            $this->pagelayout = $Layouts->getLayout($layout_catalog_name, false);//It is safer to process layout after rendering the table

            if ($Layouts->layouttype == 8)
                $this->ct->Env->frmt = 'xml';
            elseif ($Layouts->layouttype == 9)
                $this->ct->Env->frmt = 'csv';
            elseif ($Layouts->layouttype == 10)
                $this->ct->Env->frmt = 'json';

            $this->layoutType = $Layouts->layouttype;
        } else
            $this->pagelayout = '{catalog:,notable}';

        $this->itemlayout = '';
        $layout_item_name = $this->ct->Env->menu_params->get('esitemlayout');
        if ($layout_item_name != '')
            $this->itemlayout = $Layouts->getLayout($layout_item_name);

        if ($this->ct->Env->frmt == 'csv') {
            if (function_exists('mb_convert_encoding')) {
                require_once('tmpl' . DIRECTORY_SEPARATOR . 'csv.php');
            } else {
                $msg = '"mbstring" PHP extension not installed.<br/>
				You need to install this extension. It depends on of your operating system, here are some examples:<br/><br/>
				sudo apt-get install php-mbstring  # Debian, Ubuntu<br/>
				sudo yum install php-mbstring  # RedHat, Fedora, CentOS<br/><br/>
				Uncomment the following line in php.ini, and restart the Apache server:<br/>
				extension=mbstring<br/><br/>
				Then restart your webs\' server. Example:<br/>service apache2 restart';

                Factory::getApplication()->enqueueMessage($msg, 'error');
            }
        } else {
            parent::display($tpl);
        }
        return true;
    }

    function SaveViewLogForRecord($rec, $allowedFields)
    {
        $update_fields = array();

        foreach ($this->ct->Table->fields as $mFld) {
            if (in_array($mFld['fieldname'], $allowedFields)) {
                if ($mFld['type'] == 'lastviewtime')
                    $update_fields[] = $mFld['realfieldname'] . '="' . date('Y-m-d H:i:s') . '"';

                if ($mFld['type'] == 'viewcount')
                    $update_fields[] = $mFld['realfieldname'] . '="' . ((int)($rec[$this->ct->Env->field_prefix . $mFld['fieldname']]) + 1) . '"';
            }
        }

        if (count($update_fields) > 0) {
            $db = Factory::getDBO();
            $query = 'UPDATE ' . $this->ct->Table->realtablename . ' SET ' . implode(', ', $update_fields) . ' WHERE id=' . $rec[$this->ct->Table->realidfieldname];
            $db->setQuery($query);
            $db->execute();
        }
    }

    function SaveViewLog_CheckIfNeeded(): array
    {
        $user_groups = $this->ct->Env->user->get('groups');
        $allowed_fields = array();

        foreach ($this->ct->Table->fields as $mFld) {
            if ($mFld['type'] == 'lastviewtime' or $mFld['type'] == 'viewcount' or $mFld['type'] == 'phponview') {
                $pair = explode(',', $mFld['typeparams']);
                $user_group = '';

                if (isset($pair[1])) {
                    if ($pair[1] == 'catalog')
                        $user_group = $pair[0];
                } else
                    $user_group = $pair[0];

                $group_id = JoomlaBasicMisc::getGroupIdByTitle($user_group);

                if ($user_group != '') {
                    if (in_array($group_id, $user_groups))
                        $allowed_fields[] = $mFld['fieldname'];
                }
            }
        }
        return $allowed_fields;
    }
}
