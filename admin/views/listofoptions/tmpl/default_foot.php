<?php
/**
 * CustomTables Joomla! 3.x/4.x Native Component
 * @package Custom Tables
 * @author Ivan komlev <support@joomlaboat.com>
 * @link https://joomlaboat.com
 * @copyright Copyright (C) 2018-2020. All Rights Reserved
 * @license GNU/GPL Version 2 or later - https://www.gnu.org/licenses/gpl-2.0.html
 **/

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
?>

<tfoot>
<tr>
    <td colspan="<?php echo(count($this->languages) + 4); ?>">
        <?php echo $this->pagination->getListFooter(); ?>
    </td>
</tr>
</tfoot>
