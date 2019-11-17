<?php defined('_JEXEC') or die;
/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) 2019 Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Joomla\CMS\Language\Text;

extract($displayData);
?>
<div id="js-cfi-well" class="cfi-well hidden">
    <button id="js-cfi-wellclose" type="button" class="close">×</button>
    
    <div class="cfi-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="17" viewBox="0 0 20 17"><path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"></path></svg>
        <div class="cfi-header-title"><?php echo Text::_('PLG_CFI_TITLE'); ?></div>
    </div>

    <div class="cfi-cb-block">
        <label><input id="js-cfi-convert" class="cfi-cb" type="checkbox" value="1" checked><?php echo Text::sprintf('PLG_CFI_CB_UTF_CONVERT', $cp); ?></label>
    </div>

    <div id="js-cfi-expzone" class="cfi-export">
        <select id="js-cfi-categories">
            <option value="0" selected><?php echo Text::_('PLG_CFI_SELECT_CATEGORIES'); ?></option>
            <?php foreach ($categories as $category) { ?>
            <option value="<?php echo $category->id; ?>"><?php echo $category->title; ?></option>
            <?php } ?>
        </select>
        <button id="js-cfi-export" type="button" class="btn" 
            data-nosel="<?php echo Text::_('PLG_CFI_NO_SELECT_CATEGORY'); ?>"
            data-success="<?php echo Text::_('PLG_CFI_EXPORT_SUCCESS'); ?>"
            data-error="<?php echo Text::_('PLG_CFI_EXPORT_ERROR'); ?>"
        ><?php echo Text::_('PLG_CFI_BTN_EXPORT'); ?></button>
        <label id="js-cfi-exportlabel"></label>
    </div>
    
    <div id="js-cfi-dropzone" class="cfi-dropzone" 
        data-ready="<?php echo Text::_('PLG_CFI_FILELABEL'); ?>"
        data-worktitle="<?php echo Text::_('PLG_CFI_FILELABEL_WORKTITLE'); ?>" 
        data-success="<?php echo Text::_('PLG_CFI_FILELABEL_SUCCESS'); ?>" 
        data-error="<?php echo Text::_('PLG_CFI_FILELABEL_ERROR'); ?>"
    >
        <input type="file" name="cfifile" id="js-cfi-file" class="cfi-input-file">
        <label id="js-cfi-importlabel" for="js-cfi-file" class="cfi-input-label"></label>
    </div>
    
    <div class="cfi-desc">
        <?php
        echo 
            ($showdesc ? Text::_('PLG_CFI_DESC_FORMAT') . '<hr>' : ''), 
            Text::_('PLG_CFI_DESC_WARN'), 
            ($showdesc ? Text::_('PLG_CFI_DESC_SD') : '');
        ?>
    </div>

</div>
