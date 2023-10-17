<?php
/**
 * CustomTables Joomla! 3.x/4.x Native Component
 * @package Custom Tables
 * @author Ivan Komlev <support@joomlaboat.com>
 * @link https://joomlaboat.com
 * @copyright (C) 2018-2023 Ivan Komlev
 * @license GNU/GPL Version 2 or later - https://www.gnu.org/licenses/gpl-2.0.html
 **/

// Check to ensure this file is included in Joomla!
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

if (!defined('_JEXEC') and !defined('WPINC')) {
    die('Restricted access');
}

require_once(JPATH_SITE . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_customtables' . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'catalog.php');

class JHTMLESUser
{
    static public function render(string $control_name, string $value, string $style, string $cssclass, ?string $userGroup = '', string $attribute = '', string $mysqlWhere = '', string $mysqlJoin = '')
    {
        $db = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->select('#__users.id AS id, #__users.name AS name');
        $query->from('#__users ');

        if ($userGroup !== null and $userGroup != '') {
            $query->join('INNER', '#__user_usergroup_map ON user_id=id ');
            $query->join('INNER', '#__usergroups ON #__usergroups.id = #__user_usergroup_map.group_id ');

            $ug = explode(",", $userGroup);
            $w = array();
            foreach ($ug as $u)
                $w[] = '#__usergroups.title=' . $db->quote($u);

            if (count($w) > 0)
                $query->where(' ' . implode(' OR ', $w) . ' ');
        }

        if ($mysqlJoin != '')
            $query->join('INNER', $mysqlJoin);

        if ($mysqlWhere != '')
            $query->where($mysqlWhere);

        $query->group("#__users" . "." . "id");
        $query->order("#__users" . "." . "name");
        $options = database::loadObjectList((string)$query);

        $att = ['id' => '', 'data-type' => 'user', 'name' => '- ' . Text::_('COM_CUSTOMTABLES_SELECT')];
        $options = array_merge(array($att), $options);

        return JHTML::_('select.genericlist', $options, $control_name, $cssclass . ' style="' . $style . '" ' . $attribute . ' ', 'id', 'name', $value, $control_name);
    }
}
