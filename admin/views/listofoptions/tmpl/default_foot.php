<?php
/**
 * CustomTables Joomla! 3.x Native Component
 * @package Custom Tables
 * @author Ivan komlev <support@joomlaboat.com>
 * @link http://www.joomlaboat.com
 * @copyright Copyright (C) 2018-2020. All Rights Reserved
 * @license GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html
 **/

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
?>

<tfoot>
		<tr>
			<td colspan="<?php echo (count($this->LanguageList)+4); ?>">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
</tfoot>
