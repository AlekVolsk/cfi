<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.cfi
 * @copyright   Copyright (C) Aleksey A. Morozov. All rights reserved.
 * @license     GNU General Public License version 3 or later; see http://www.gnu.org/licenses/gpl-3.0.txt
 */

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

$title = Text::_('PLG_CFI_BUTTON');
?>
<joomla-toolbar-button id="toolbar-cfi">
    <button id="js-cfi-toolbarbtn" class="btn btn-small btn-primary cfi-tollbarbtn">
        <span class="icon-upload icon-fw" aria-hidden="true"></span>
        <?php echo $title; ?>
    </button>
</joomla-toolbar-button>
