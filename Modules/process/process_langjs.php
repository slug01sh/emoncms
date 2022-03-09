<?php
/*
  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.
  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// Create a Javascript associative array who contain all sentences from module
?>
var LANG_JS = new Array();
function _Tr(key)
{
<?php // will return the default value if LANG_JS[key] is not defined. ?>
    return LANG_JS[key] || key;
}
<?php
//Please USE the "builder" every javascript modify at: /scripts/process_langjs_builder.php
// paste source code below
?>
//START
// process_ui.js
LANG_JS["Changed, press to save"] = '<?php echo addslashes(dgettext('process_messages','已更改，点击保存')); ?>';
LANG_JS["Click here for additional information about this process."] = '<?php echo addslashes(dgettext('process_messages','单击此处了解有关此过程的更多信息。')); ?>';
LANG_JS["Delete"] = '<?php echo addslashes(dgettext('process_messages','删除')); ?>';
LANG_JS["Does NOT modify value passed onto next process step."] = '<?php echo addslashes(dgettext('process_messages','不修改传递到下一个流程步骤的值。')); ?>';
LANG_JS["Edit"] = '<?php echo addslashes(dgettext('process_messages','修改')); ?>';
LANG_JS["Feed"] = '<?php echo addslashes(dgettext('process_messages','反馈')); ?>';
LANG_JS["feed last value:"] = '<?php echo addslashes(dgettext('process_messages','反馈最后一个值：')); ?>';
LANG_JS["Input"] = '<?php echo addslashes(dgettext('process_messages','输入')); ?>';
LANG_JS["input last value:"] = '<?php echo addslashes(dgettext('process_messages','输入最后一个值')); ?>';
LANG_JS["Modified value passed onto next process step."] = '<?php echo addslashes(dgettext('process_messages','修改传递到下一个流程步骤的值。')); ?>';
LANG_JS["Move down"] = '<?php echo addslashes(dgettext('process_messages','向下移动')); ?>';
LANG_JS["Move up"] = '<?php echo addslashes(dgettext('process_messages','想上移动')); ?>';
LANG_JS["Not modified"] = '<?php echo addslashes(dgettext('process_messages','不需要修改')); ?>';
LANG_JS["Requires REDIS."] = '<?php echo addslashes(dgettext('process_messages','需要 REDIS.')); ?>';
LANG_JS["Saved"] = '<?php echo addslashes(dgettext('process_messages','已经保存')); ?>';
LANG_JS["Text"] = '<?php echo addslashes(dgettext('process_messages','文本')); ?>';
LANG_JS["Value"] = '<?php echo addslashes(dgettext('process_messages','值')); ?>';
//END 
