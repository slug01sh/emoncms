<!------------------------------------------------------------------------------------------------------------------------------------------------- -->
<!-- FEED EXPORT                                                                                                                                   -->
<!------------------------------------------------------------------------------------------------------------------------------------------------- -->
<div id="feedExportModal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="feedExportModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="feedExportModalLabel"><b><span id="SelectedExport"></span></b> <?php echo _('CSV导出'); ?></h3>
    </div>
    <div class="modal-body">
    <p><?php echo _('选择要导出的时间范围和间隔：'); ?></p>
        <table class="table">
        <tr>
            <td>
                <p><b><?php echo _('开始日期和时间'); ?></b></p>
                <div id="datetimepicker1" class="input-append date">
                    <input id="export-start" data-format="dd/MM/yyyy hh:mm:ss" type="text" />
                    <span class="add-on"> <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i></span>
                </div>
            </td>
            <td>
                <p><b><?php echo _('结束日期和时间');?></b></p>
                <div id="datetimepicker2" class="input-append date">
                    <input id="export-end" data-format="dd/MM/yyyy hh:mm:ss" type="text" />
                    <span class="add-on"> <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i></span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <p><b><?php echo _('间隔');?></b></p>
                <select id="export-interval" >
                    <option value=original><?php echo _('原始间隔');?></option>
                    <option value=5><?php echo _('5s');?></option>
                    <option value=10><?php echo _('10s');?></option>
                    <option value=30><?php echo _('30s');?></option>
                    <option value=60><?php echo _('1 min');?></option>
                    <option value=300><?php echo _('5 mins');?></option>
                    <option value=600><?php echo _('10 mins');?></option>
                    <option value=900><?php echo _('15 mins');?></option>
                    <option value=1800><?php echo _('30 mins');?></option>
                    <option value=3600><?php echo _('1 hour');?></option>
                    <option value=21600><?php echo _('6 hour');?></option>
                    <option value=43200><?php echo _('12 hour');?></option>
                    <option value=daily><?php echo _('每天');?></option>
                    <option value=weekly><?php echo _('每周');?></option>
                    <option value=monthly><?php echo _('每月');?></option>
                    <option value=annual><?php echo _('每年');?></option>
                </select>
                
                <p class="hide"><input id="export-average" type="checkbox" style="margin-top:-4px">返回平均值</p>
            </td>
            <td>
                <p><b><?php echo _('日期时间格式');?></b></p>
                <select id="export-timeformat">
                    <option value="unix">Unix时间戳</option>
                    <option value="excel">Excel (d/m/Y H:i:s)，在用户帐户中设置的时区</option>
                    <option value="iso8601">ISO 8601 (e.g: 2020-01-01T10:00:00+01:00)</option>
                </select>
            </td>
        </tr>
        </table>
    </div>
    <div class="modal-footer">
        <div id="downloadsizeplaceholder" style="float: left"><?php echo _('估计下载大小: ');?><span id="downloadsize">0</span></div>
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('关闭'); ?></button>
        <button class="btn" id="export"><?php echo _('导出'); ?></button>
    </div>
</div>
