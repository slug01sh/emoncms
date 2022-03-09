<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project: http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

// This is core Process list module
class Process_ProcessList
{
    private $mysqli;
    private $input;
    private $feed;
    private $timezone;

    private $proc_initialvalue;  // save the input value at beginning of the processes list execution
    private $proc_skip_next;     // skip execution of next process in process list
    private $proc_goto;          // goto step in process list

    private $log;
    private $mqtt = false;
    
    private $data_cache = array();
    
    // Module required constructor, receives parent as reference
    public function __construct(&$parent)
    {
        $this->mysqli = &$parent->mysqli;
        $this->input = &$parent->input;
        $this->feed = &$parent->feed;
        $this->timezone = &$parent->timezone;
        $this->proc_initialvalue = &$parent->proc_initialvalue;
        $this->proc_skip_next = &$parent->proc_skip_next;
        $this->proc_goto = &$parent->proc_goto;

        $this->log = new EmonLogger(__FILE__);

        // Load MQTT if enabled
        // Publish value to MQTT topic, see: http://openenergymonitor.org/emon/node/5943
        global $settings, $log;
        
        if ($settings['mqtt']['enabled'] && !$this->mqtt)
        {
            // @see: https://github.com/emoncms/emoncms/blob/master/docs/RaspberryPi/MQTT.md
            if (class_exists("Mosquitto\Client")) {
                /*
                    new Mosquitto\Client($id,$cleanSession)
                    $id (string) – The client ID. If omitted or null, one will be generated at random.
                    $cleanSession (boolean) – Set to true to instruct the broker to clean all messages and subscriptions on disconnect. Must be true if the $id parameter is null.
                 */ 
                $mqtt_client = new Mosquitto\Client(null, true);
                
                $mqtt_client->onDisconnect(function($responseCode) use ($log) {
                    if ($responseCode > 0) $log->info('unexpected disconnect from mqtt server');
                });

                $this->mqtt = $mqtt_client;
            }
        }
    }
    
    public function process_list() {

        textdomain("process_messages");
                    
        $list = array(
           array(
              "id_num"=>1,
              "name"=>_("记录到反馈"),
              "short"=>"log",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"log_to_feed",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Main"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY,Engine::CASSANDRA),
              "nochange"=>true,
              "description"=>_("<p><b>记录到反馈:</b> 该处理器记录到时间序列提要，然后可用于探索历史数据。 推荐用于记录功率、温度、湿度、电压和电流数据。 </p><p><b>反馈引擎:</b><ul><li><b> 固定间隔时间序列 (PHPFina) </b> 是推荐的反馈引擎，它是一个固定间隔时间序列引擎。 </li><li><b> 可变间隔时间序列 (PHPTimeseries) </b> 用于不定期发布的数据。 </li></ul></p><p><b>反馈间隔：</b> 选择反馈间隔时，请选择与监控设备中设置的更新速率相同或更长的间隔。 将间隔率设置为小于设备的更新率会导致磁盘空间浪费。</p>")
           ),
           array(
              "id_num"=>2,
              "name"=>_("x 乘法"),
              "short"=>"x",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"scale",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Calibration"),
              "description"=>_("<p>将当前值乘以给定常数。这对于校准网络上的特定变量而不是通过重新编程硬件很有用。</p>")
           ),
           array(
              "id_num"=>3,
              "name"=>_("+ 加法"),
              "short"=>"+",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"offset",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Calibration"),
              "description"=>_("<p>通过给定值偏移当前值。 这对于校准网络上的特定变量而不是通过重新编程硬件再次很有用。</p>")
           ),
           array(
              "id_num"=>4,
              "name"=>_("瓦特 转换为 千瓦时"),
              "short"=>"kwh",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"power_to_kwh",
              "datafields"=>1,
              "unit"=>"kWh",
              "group"=>_("Main"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p><b>功率转换为 kWh：</b> 将以瓦特为单位的功率值转换为累积 kWh 馈送。<br><br><b>可视化提示：</b> 使用此输入处理器创建的反馈可以用于使用 delta 属性设置为 1 的 BarGraph 可视化生成每日 kWh 数据。请参阅 <a href='https://guide.openenergymonitor.org/setup/daily-kwh/' target='_blank' rel='noopener '>单位：每日千瓦时</a><br><br>")
           ),
           array(
              "id_num"=>5,
              "name"=>_("瓦特 转换为 千瓦时/天"),
              "short"=>"kwhd",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"power_to_kwhd",
              "datafields"=>1,
              "unit"=>"kWhd",
              "group"=>_("Power & Energy"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>将以瓦特为单位的功率值转换为包含每天使用的总能量 (kWh/d) 条目的反馈</p>")
           ),
           array(
              "id_num"=>6,
              "name"=>_("x 乘法输入"),
              "short"=>"x inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"times_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>将当前值与从输入列表中选择的其他输入的最后一个值相乘。</p>")
           ),
           array(
              "id_num"=>7,
              "name"=>_("按时输入"),
              "short"=>"ontime",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"input_ontime",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Input"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>计算每天输入高的时间量并将结果记录到反馈中。为计算太阳能热水泵每天工作的小时数而创建</p>")
           ),
           array(
              "id_num"=>8,
              "name"=>_("瓦时 转换为 千瓦时/天"),
              "short"=>"whinckwhd",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"whinc_to_kwhd",
              "datafields"=>1,
              "unit"=>"kWhd",
              "group"=>_("Power & Energy"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>将 Wh 测量值累积为 kWh/d。<p><b>输入</b>：以 Wh 为单位的能量增量。</p>")
           ),
           array(
              "id_num"=>9,
              "name"=>_("千瓦时 转换为 千瓦时/天 (OLD)"),
              "short"=>"kwhkwhdold",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"kwh_to_kwhd_old",
              "datafields"=>1,
              "unit"=>"kWhd",
              "group"=>_("Deleted"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "description"=>""
           ),
           array(
              "id_num"=>10,
              "name"=>_("每天更新反馈"),
              "short"=>"update",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"update_feed_data",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Input"),
              "engines"=>array(Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>在指定提要的指定时间（由 API 中的 JSON 时间参数给出）更新或插入每日值</p>")
           ),
           array(
              "id_num"=>11,
              "name"=>_("+ 加法输入"),
              "short"=>"+ inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"add_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>将当前值与从输入列表中选择的其他输入的最后一个值相加。 结果被传回，以供处理列表中的下一个处理器进一步处理。</p>")
           ),
           array(
              "id_num"=>12,
              "name"=>_("/ 除法输入"),
              "short"=>"/ inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"divide_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>将当前值除以从输入列表中选择的其他输入的最后一个值。 结果被传回，以供处理列表中的下一个处理器进一步处理。</p>")
           ),
           array(
              "id_num"=>13,
              "name"=>_("相移"),
              "short"=>"phaseshift",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"phaseshift",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Deleted"),
              "description"=>""
           ),
           array(
              "id_num"=>14,
              "name"=>_("累加器"),
              "short"=>"accumulate",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"accumulator",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Misc"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "description"=>_("<p>输出到反馈，按输入值累加</p>")
           ),
           array(
              "id_num"=>15,
              "name"=>_("变化率"),
              "short"=>"rate",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"ratechange",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Misc"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "requireredis"=>true,
              "description"=>_("<p>输出反馈，按当前值与上一个值之间的差</p>")
           ),
           array(
              "id_num"=>16,
              "name"=>_("直方图"),
              "short"=>"hist",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"histogram",
              "datafields"=>2,
              "unit"=>"",
              "group"=>_("Deleted"),
              "engines"=>array(Engine::MYSQL,Engine::MYSQLMEMORY),
              "description"=>""
           ),
           array(
              "id_num"=>17,
              "name"=>_("日均值"),
              "short"=>"mean",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"average",
              "datafields"=>2,
              "unit"=>"",
              "group"=>_("Deleted"),
              "engines"=>array(Engine::PHPTIMESERIES),
              "description"=>""
           ),
           array(
              "id_num"=>18,
              "name"=>_("热通量"),
              "short"=>"flux",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"heat_flux",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Deleted"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "description"=>""
           ),
           array(
              "id_num"=>19,
              "name"=>_("获得的功率为 千瓦时/天（kwh/d）"),
              "short"=>"pwrgain",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"power_acc_to_kwhd",
              "datafields"=>1,
              "unit"=>"kWhd",
              "group"=>_("Deleted"),
              "engines"=>array(Engine::PHPTIMESERIES),
              "description"=>""
           ),
           array(
              "id_num"=>20,
              "name"=>_("总脉冲计数 转换为 脉冲增量"),
              "short"=>"pulsdiff",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"pulse_diff",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Pulse"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "description"=>_("<p>返回自上次更新以来累积脉冲计数的输入增加的脉冲数。 即如果输入从 23400 更新到 23410，结果将是 10 的增量。</p>")
           ),
           array(
              "id_num"=>21,
              "name"=>_("千瓦时 转换为 瓦特"),
              "short"=>"kwhpwr",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"kwh_to_power",
              "datafields"=>1,
              "unit"=>"W",
              "group"=>_("Power & Energy"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "requireredis"=>true,
              "description"=>_("<p>将累积千瓦时转换为瞬时功率</p>")
           ),
           array(
              "id_num"=>22,
              "name"=>_("- 减法输入"),
              "short"=>"- inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"subtract_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>从当前值中减去从输入列表中选择的其他输入的最后一个值。</p>")
           ),
           array(
              "id_num"=>23,
              "name"=>_("千瓦时 转换为 千瓦时/天"),
              "short"=>"kwhkwhd",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"kwh_to_kwhd",
              "datafields"=>2,
              "unit"=>"kWhd",
              "group"=>_("Power & Energy"),
              "engines"=>array(Engine::PHPTIMESERIES),
              "requireredis"=>true,
              "nochange"=>true,
              "description"=>_("<p>Upsert kWh to a daily value.</p>")
           ),
           array(
              "id_num"=>24,
              "name"=>_("Allow positive"),
              "short"=>"> 0",
              "argtype"=>ProcessArg::NONE,
              "function"=>"allowpositive",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Limits"),
              "description"=>_("<p>Negative values are zeroed for further processing by the next processor in the processing list.</p>")
           ),
           array(
              "id_num"=>25,
              "name"=>_("Allow negative"),
              "short"=>"< 0",
              "argtype"=>ProcessArg::NONE,
              "function"=>"allownegative",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Limits"),
              "description"=>_("<p>Positive values are zeroed for further processing by the next processor in the processing list.</p>")
           ),
           array(
              "id_num"=>26,
              "name"=>_("有符号 转换为 无符号"),
              "short"=>"unsign",
              "argtype"=>ProcessArg::NONE,
              "function"=>"signed2unsigned",
              "datafields"=>0,
              "unit"=>"unsign",
              "group"=>_("Misc"),
              "description"=>_("<p>将被解释为 16 位有符号数的数字转换为无符号数。</p>")
           ),
           array(
              "id_num"=>27,
              "name"=>_("每日最大值"),
              "short"=>"max",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"max_value",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Misc"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>每日最大值。 在选定的每日反馈上更新每天达到的最高值。</p>")
           ),
           array(
              "id_num"=>28,
              "name"=>_("每日最小值"),
              "short"=>"min",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"min_value",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Misc"),
              "engines"=>array(Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY),
              "nochange"=>true,
              "description"=>_("<p>最小的每日价值。 在选定的每日反馈上更新每天达到的最低值。</p>")
           ),
           array(
              "id_num"=>29,
              "name"=>_("+ feed"),
              "short"=>"+ feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"add_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Adds the current value with the last value from a feed as selected from the feed list.</p>")
           ),
           array(
              "id_num"=>30,
              "name"=>_("- feed"),
              "short"=>"- feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"sub_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Subtracts from the current value the last value from a feed as selected from the feed list.</p>")
           ),
           array(
              "id_num"=>31,
              "name"=>_("* feed"),
              "short"=>"x feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"multiply_by_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Multiplies the current value with the last value from a feed as selected from the feed list.</p>")
           ),
           array(
              "id_num"=>32,
              "name"=>_("/ feed"),
              "short"=>"/ feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"divide_by_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Divides the current value by the last value from a feed as selected from the feed list.</p>")
           ),
           array(
              "id_num"=>33,
              "name"=>_("重置为0"),
              "short"=>"0",
              "argtype"=>ProcessArg::NONE,
              "function"=>"reset2zero",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Misc"),
              "description"=>_("<p>值“0”被传回处理列表中的下一个处理器进一步处理。</p>")
           ),
           array(
              "id_num"=>34,
              "name"=>_("瓦时累加器"),
              "short"=>"whacc",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"wh_accumulator",
              "datafields"=>1,
              "unit"=>"Wh",
              "group"=>_("Main"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "requireredis"=>true,
              "description"=>_("<b>Wh 累加器：</b> 与 emontx、emonth 或 emonpi pulsecount 或 emontx 运行固件一起使用 <i>emonTxV3_4_continuous_kwhtotals</i> 发送累积瓦时。<br><br>此处理器确保当 emontx 处于 重置 emoncms 中的瓦时计数不会重置，它还会检查过滤器在能源使用中的峰值是否大于处理器中设置的最大功率阈值，假设这些是错误的，则最大功率阈值设置为 60 kW。 <br><br><b>可视化提示：</b> 使用此输入处理器创建的 Feed 可用于使用 BarGraph 可视化生成每日 kWh 数据，其中 delta 属性设置为 1，比例设置为 0.001。 请参阅：<a href='https://guide.openenergymonitor.org/setup/daily-kwh/' target='_blank' rel='noopener'>单位：每日千瓦时</a><br><br>")
           ),
           array(
              "id_num"=>35,
              "name"=>_("发布到 MQTT"),
              "short"=>"MQTT",
              "argtype"=>ProcessArg::TEXT,
              "function"=>"publish_to_mqtt",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Misc"),
              "nochange"=>true,
              "description"=>_("<p>向 MQTT 主题发布值，例如 '家庭/电源/厨房'</p>")
           ),
           array(
              "id_num"=>36,
              "name"=>_("重置为NULL"),
              "short"=>"null",
              "argtype"=>ProcessArg::NONE,
              "function"=>"reset2null",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Misc"),
              "description"=>_("<p>值设置为 NULL。</p><p>对条件处理很有用。</p>")
           ),
           array(
              "id_num"=>37,
              "name"=>_("重置为原始值"),
              "short"=>"ori",
              "argtype"=>ProcessArg::NONE,
              "function"=>"reset2original",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Misc"),
              "description"=>_("<p>该值设置为进程列表开头的原始值。</p>")
           ),
           array(
              "id_num"=>42,
              "name"=>_("If ZERO, skip next"),
              "short"=>"0? skip",
              "argtype"=>ProcessArg::NONE,
              "function"=>"if_zero_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is ZERO, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>43,
              "name"=>_("If !ZERO, skip next"),
              "short"=>"!0? skip",
              "argtype"=>ProcessArg::NONE,
              "function"=>"if_not_zero_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is NOT ZERO, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>44,
              "name"=>_("If NULL, skip next"),
              "short"=>"N? skip",
              "argtype"=>ProcessArg::NONE,
              "function"=>"if_null_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is NULL, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>45,
              "name"=>_("If !NULL, skip next"),
              "short"=>"!N? skip",
              "argtype"=>ProcessArg::NONE,
              "function"=>"if_not_null_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is NOT NULL, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>46,
              "name"=>_("If >, skip next"),
              "short"=>">? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_gt_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is greater than the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>47,
              "name"=>_("If >=, skip next"),
              "short"=>">=? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_gt_equal_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is greater or equal to the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>48,
              "name"=>_("If <, skip next"),
              "short"=>"<? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_lt_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is lower than the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>49,
              "name"=>_("If <=, skip next"),
              "short"=>"<=? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_lt_equal_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is lower or equal to the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>50,
              "name"=>_("If =, skip next"),
              "short"=>"=? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_equal_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is equal to the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>51,
              "name"=>_("If !=, skip next"),
              "short"=>"!=? skip",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"if_not_equal_skip",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Conditional - User value"),
              "nochange"=>true,
              "description"=>_("<p>If value from last process is NOT equal to the specified value, process execution will skip execution of next process in list.</p>")
           ),
           array(
              "id_num"=>52,
              "name"=>_("跳转"),
              "short"=>"GOTO",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"goto_process",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Misc"),
              "nochange"=>true,
              "description"=>_("<p>将进程执行跳转到指定位置。</p><p><b>警告</b><br>如果你不小心你可以在进程列表上创建一个goto循环。<br> 发生循环时，API 会出现锁定状态，直到服务器 php 超时并出现错误。</p>")
           ),
           array(
              "id_num"=>53,
              "name"=>_("Source Feed"),
              "short"=>"sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"source_feed_data_time",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p><b>Source Feed:</b><br>Virtual feeds should use this processor as the first one in the process list. It sources data from the selected feed.<br>The sourced value is passed back for further processing by the next processor in the processing list.<br>You can then add other processors to apply logic on the passed value for post-processing calculations in realtime.</p><p>Note: This virtual feed process list is executed on visualizations requests that use this virtual feed.</p>")
           ),
           array(
              "id_num"=>55,
              "name"=>_("+ source feed"),
              "short"=>"+ sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"add_source_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p>Add the specified feed.</p>")
           ),
           array(
              "id_num"=>56,
              "name"=>_("- source feed"),
              "short"=>"- sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"sub_source_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p>Subtract the specified feed.</p>")
           ),
           array(
              "id_num"=>57,
              "name"=>_("* source feed"),
              "short"=>"x sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"multiply_by_source_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p>Multiply by specified feed.</p>")
           ),
           array(
              "id_num"=>58,
              "name"=>_("/ source feed"),
              "short"=>"/ sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"divide_by_source_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p>Divide by specified feed. Returns NULL for zero values.</p>")
           ),
           array(
              "id_num"=>59,
              "name"=>_("/ source feed"),
              "short"=>"/ sfeed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"reciprocal_by_source_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Virtual"),
              "description"=>_("<p>Return the reciprical of the specified feed. Returns NULL for zero values.</p>")
           ),
           array(
              "name"=>_("EXIT"),
              "short"=>"EXIT",
              "argtype"=>ProcessArg::NONE,
              "function"=>"error_found",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Hidden"),
              "description"=>_("<p>This was automaticaly added when a loop error was discovered on the processList or execution took too many steps to process.  Review the usage of GOTOs or decrease the number of items and delete this entry to resume execution.</p>"),
              "internalerror"=>true,
              "internalerror_reason"=>"HAS ERRORS",
              "internalerror_desc"=>"Processlist disabled due to errors found during execution."
           ),
           array(
              "name"=>_("允许的最大值"),
              "short"=>"<max",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"max_value_allowed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Limits"),
              "description"=>_("<p>如果值大于<i>允许的最大值</i>，则传递给后续进程的值将是<i>允许的最大值</i></p>"),
              "requireredis"=>false,
              "nochange"=>false
           ),
           array(
              "name"=>_("允许的最小值"),
              "short"=>">min",
              "argtype"=>ProcessArg::VALUE,
              "function"=>"min_value_allowed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Limits"),
              "description"=>_("<p>如果值小于<i>允许的最小值</i>，那么传递给后续进程的值将是<i>允许的最小值</i></p>"),
              "requireredis"=>false,
              "nochange"=>false
           ),
            array(
                "name"=>_("绝对值"),
                "short"=>"abs",
                "argtype"=>ProcessArg::VALUE,
                "function"=>"abs_value",
                "datafields"=>0,
                "unit"=>"",
                "group"=>_("Calibration"),
                "description"=>_("<p>返回当前值的绝对值。 这对于校准网络上的特定变量而不是通过重新编程硬件很有用。</p>")
            ),
            array(
              "name"=>_("千瓦时累加器"),
              "short"=>"kwhacc",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"kwh_accumulator",
              "datafields"=>1,
              "unit"=>"kWh",
              "group"=>_("Main"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES),
              "requireredis"=>true,
              "description"=>_("<b>kWh 累加器：</b>此处理器从累积 kWh 输入中移除复位，它还过滤掉大于处理器中设置的最大功率阈值的能源使用峰值，假设这些是错误的，即最大功率阈值 设置为 60 kW。 <br><br><b>可视化提示：</b> 使用此输入处理器创建的 Feed 可用于使用 BarGraph 可视化生成每日 kWh 数据，其中 delta 属性设置为 1，比例设置为 0.001。 请参阅：<a href='https://guide.openenergymonitor.org/setup/daily-kwh/' target='_blank' rel='noopener'>千瓦时：每日千瓦时</a><br><br>")
           ),
           array(
              "name"=>_("记录到反馈 (Join)"),
              "short"=>"log_join",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"log_to_feed_join",
              "datafields"=>1,
              "unit"=>"",
              "group"=>_("Main"),
              "engines"=>array(Engine::PHPFINA,Engine::PHPTIMESERIES,Engine::MYSQL,Engine::MYSQLMEMORY,Engine::CASSANDRA),
              "nochange"=>true,
              "description"=>_("<p><b>记录到馈送（join）：</b> 除了标准的记录到馈送过程之外，此过程还通过最新值和先前值之间的直线将缺失的数据点链接起来。 它设计用于总累积 kWh 抄表输入，在创建条形图时生成可与 delta 属性一起使用的馈送。 请参阅：<a href='https://guide.openenergymonitor.org/setup/daily-kwh/' target='_blank' rel='noopener'>单位：每日千瓦时</a><br><br>")
           ),
           array(
              "name"=>_("最大输入"),
              "short"=>"max_inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"max_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>将当前值限制为从输入列表中选择的输入的最后一个值。 结果被传回，以供处理列表中的下一个处理器进一步处理。</p>")
           ),
           array(
              "name"=>_("最小输入"),
              "short"=>"min_inp",
              "argtype"=>ProcessArg::INPUTID,
              "function"=>"min_input",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Input"),
              "description"=>_("<p>将当前值限制为从输入列表中选择的输入的最后一个值。 结果被传回，以供处理列表中的下一个处理器进一步处理。</p>")
           ),
           array(
              "name"=>_("max by feed"),
              "short"=>"max_feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"max_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Limits the current value by the last value from an feed as selected from the feed list. The result is passed back for further processing by the next processor in the processing list.</p>")
           ),
           array(
              "name"=>_("min by feed"),
              "short"=>"min_feed",
              "argtype"=>ProcessArg::FEEDID,
              "function"=>"min_feed",
              "datafields"=>0,
              "unit"=>"",
              "group"=>_("Feed"),
              "description"=>_("<p>Limits the current value by the last value from an feed as selected from the feed list. The result is passed back for further processing by the next processor in the processing list.</p>")
           )
        );
        return $list;
    }
    
    // / Below are functions of this module processlist
    public function scale($arg, $time, $value)
    {
        if ($value===null) return $value;
        return $value * $arg;
    }

    public function divide($arg, $time, $value)
    {
        if ($arg!=0) {
            return $value / $arg;
        } else {
            return null;
        }
    }

    public function offset($arg, $time, $value)
    {
        if ($value===null) return $value;
        return $value + $arg;
    }

    public function allowpositive($arg, $time, $value)
    {
        if ($value<0) $value = 0;
        return $value;
    }

    public function allownegative($arg, $time, $value)
    {
        if ($value>0) $value = 0;
        return $value;
    }
    
     public function max_value_allowed($arg, $time, $value)
    {
        if ($value>$arg) $value = $arg;
        return $value;
    }
    
    public function min_value_allowed($arg, $time, $value)
    {
        if ($value<$arg) $value = $arg;
        return $value;
    }

    public function reset2zero($arg, $time, $value)
    {
         $value = 0;
         return $value;
    }

    public function reset2original($arg, $time, $value)
    {
         return $this->proc_initialvalue;
    }

    public function reset2null($arg, $time, $value)
    {
         return null;
    }

    public function signed2unsigned($arg, $time, $value)
    {
        if($value < 0) $value = $value + 65536;
        return $value;
    }

    public function log_to_feed($id, $time, $value)
    {
        $this->feed->post($id, $time, $time, $value);

        return $value;
    }

    public function log_to_feed_join($id, $time, $value)
    {
        $padding_mode = "join";
        $this->feed->post($id, $time, $time, $value, $padding_mode);
        return $value;
    }

    public function abs_value($arg, $time, $value)
    {
        return abs($value);
    }

    //---------------------------------------------------------------------------------------
    // Times value by current value of another input
    //---------------------------------------------------------------------------------------
    public function times_input($id, $time, $value)
    {
        return $value * $this->input->get_last_value($id);
    }

    public function divide_input($id, $time, $value)
    {
        $lastval = $this->input->get_last_value($id);
        if($lastval > 0){
            return $value / $lastval;
        } else {
            return null; // should this be null for a divide by zero?
        }
    }
    
    public function update_feed_data($id, $time, $value)
    {
        $time = $this->getstartday($time);

        $feedname = "feed_".trim($id)."";
        $result = $this->mysqli->query("SELECT time FROM $feedname WHERE `time` = '$time'");
        $row = $result->fetch_array();

        if (!$row)
        {
            $this->mysqli->query("INSERT INTO $feedname (time,data) VALUES ('$time','$value')");
        }
        else
        {
            $this->mysqli->query("UPDATE $feedname SET data = '$value' WHERE `time` = '$time'");
        }
        return $value;
    } 

    public function add_input($id, $time, $value)
    {
        return $value + $this->input->get_last_value($id);
    }

    public function subtract_input($id, $time, $value)
    {
        return $value - $this->input->get_last_value($id);
    }
    
    public function max_input($id, $time, $value)
    {
        $max_limit = $this->input->get_last_value($id);
        if ($value>$max_limit) $value = $max_limit;
        return $value;
    }
    
    public function min_input($id, $time, $value)
    {
        $min_limit = $this->input->get_last_value($id);
        if ($value<$min_limit) $value = $min_limit;
        return $value;
    }
    
    public function max_feed($id, $time, $value)
    {
        $timevalue = $this->feed->get_timevalue($id);
        $max_limit = $timevalue['value']*1;
        if ($value>$max_limit) $value = $max_limit;
        return $value;
    }
    
    public function min_feed($id, $time, $value)
    {
        $timevalue = $this->feed->get_timevalue($id);
        $min_limit = $timevalue['value']*1;
        if ($value<$min_limit) $value = $min_limit;
        return $value;
    }

    //---------------------------------------------------------------------------------------
    // Power to kwh
    //---------------------------------------------------------------------------------------
    public function power_to_kwh($feedid, $time_now, $value)
    {
        $new_kwh = 0;

        // Get last value
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
        $last_kwh = $last['value']*1; // will convert null to 0, required for first reading starting from 0
        $last_time = $last['time']*1; // will convert null to 0
        if (!$last_time) $last_time = $time_now;

        // only update if last datapoint was less than 2 hour old
        // this is to reduce the effect of monitor down time on creating
        // often large kwh readings.
        $time_elapsed = ($time_now - $last_time);   
        if ($time_elapsed>0 && $time_elapsed<7200) { // 2hrs
            // kWh calculation
            $kwh_inc = ($time_elapsed * $value) / 3600000.0;
            $new_kwh = $last_kwh + $kwh_inc;
        } else {
            // in the event that redis is flushed the last time will
            // likely be > 7200s ago and so kwh inc is not calculated
            // rather than enter 0 we enter the last value
            $new_kwh = $last_kwh;
        }

        $padding_mode = "join";
        $this->feed->post($feedid, $time_now, $time_now, $new_kwh, $padding_mode);
        
        return $value;
    }

    public function power_to_kwhd($feedid, $time_now, $value)
    {
        $new_kwh = 0;

        // Get last value
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
        $last_kwh = $last['value']*1; // will convert null to 0, required for first reading starting from 0
        $last_time = $last['time']*1; // will convert null to 0
        if (!$last_time) $last_time = $time_now;

        $current_slot = $this->getstartday($time_now);
        $last_slot = $this->getstartday($last_time);    

        $time_elapsed = ($time_now - $last_time);   
        if ($time_elapsed>0 && $time_elapsed<7200) { // 2hrs
            // kWh calculation
            $kwh_inc = ($time_elapsed * $value) / 3600000.0;
        } else {
            // in the event that redis is flushed the last time will
            // likely be > 7200s ago and so kwh inc is not calculated
            // rather than enter 0 we dont increase it
            $kwh_inc = 0;
        }

        if($last_slot == $current_slot) {
            $new_kwh = $last_kwh + $kwh_inc;
        } else {
            # We are working in a new slot (new day) so don't increment it with the data from yesterday
            $new_kwh = $kwh_inc;
        }
        $this->feed->post($feedid, $time_now, $current_slot, $new_kwh);

        return $value;
    }

    public function kwh_to_kwhd($feedid, $time_now, $value)
    {
        global $redis;
        if (!$redis) return $value; // return if redis is not available
        
        $currentkwhd = $this->feed->get_timevalue($feedid);
        if ($currentkwhd===null) return $value; // feed does not exist
        
        $last_time = $currentkwhd['time'];
        
        //$current_slot = floor($time_now / 86400) * 86400;
        //$last_slot = floor($last_time / 86400) * 86400;
        $current_slot = $this->getstartday($time_now);
        $last_slot = $this->getstartday($last_time);

        if ($redis->exists("process:kwhtokwhd:$feedid")) {
            $lastkwhvalue = $redis->hmget("process:kwhtokwhd:$feedid",array('time','value'));
            $kwhinc = $value - $lastkwhvalue['value'];

            // kwh values should always be increasing so ignore ones that are less
            // assume they are errors
            if ($kwhinc<0) { $kwhinc = 0; $value = $lastkwhvalue['value']; }
            
            if($last_slot == $current_slot) {
                $new_kwh = $currentkwhd['value'] + $kwhinc;
            } else {
                $new_kwh = $kwhinc;
            }

            $this->feed->post($feedid, $time_now, $current_slot, $new_kwh);
        }
        
        $redis->hMset("process:kwhtokwhd:$feedid", array('time' => $time_now, 'value' => $value));

        return $value;
    }

    //---------------------------------------------------------------------------------------
    // input on-time counter
    //---------------------------------------------------------------------------------------
    public function input_ontime($feedid, $time_now, $value)
    {
        // Get last value
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist 
        $last_time = $last['time'];
        
        //$current_slot = floor($time_now / 86400) * 86400;
        //$last_slot = floor($last_time / 86400) * 86400;
        $current_slot = $this->getstartday($time_now);
        $last_slot = $this->getstartday($last_time);
        
        if (!isset($last['value'])) $last['value'] = 0;
        $ontime = $last['value'];
        $time_elapsed = 0;
        
        if ($value > 0 && (($time_now-$last_time)<7200))
        {
            $time_elapsed = $time_now - $last_time;
            $ontime += $time_elapsed;
        }
        
        if($last_slot != $current_slot) $ontime = $time_elapsed;

        $this->feed->post($feedid, $time_now, $current_slot, $ontime);

        return $value;
    }

    //--------------------------------------------------------------------------------
    // Display the rate of change for the current and last entry
    //--------------------------------------------------------------------------------
    public function ratechange($feedid, $time, $value)
    {
        global $redis;
        if (!$redis) return $value; // return if redis is not available
        
        if ($redis->exists("process:ratechange:$feedid")) {
            $lastvalue = $redis->hmget("process:ratechange:$feedid",array('time','value'));
            $ratechange = $value - $lastvalue['value'];
            $this->feed->post($feedid, $time, $time, $ratechange);
        }
        $redis->hMset("process:ratechange:$feedid", array('time' => $time, 'value' => $value));

        return $ratechange;
    }

    public function save_to_input($inputid, $time, $value)
    {
        $this->input->set_timevalue($inputid, $time, $value);
        return $value;
    }

    public function whinc_to_kwhd($feedid, $time_now, $value)
    {
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist 
        $last_time = $last['time'];
        
        //$current_slot = floor($time_now / 86400) * 86400;
        //$last_slot = floor($last_time / 86400) * 86400;
        $current_slot = $this->getstartday($time_now);
        $last_slot = $this->getstartday($last_time);
               
        $new_kwh = $last['value'] + ($value / 1000.0);
        if ($last_slot != $current_slot) $new_kwh = ($value / 1000.0);
        
        $this->feed->post($feedid, $time_now, $current_slot, $new_kwh);

        return $value;
    }

    public function accumulator($feedid, $time, $value)
    {
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist   
        $value = $last['value'] + $value;
        $padding_mode = "join";
        $this->feed->post($feedid, $time, $time, $value, $padding_mode);
        return $value;
    }
    /*
    public function accumulator_daily($feedid, $time_now, $value)
    {
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist  
        $value = $last['value'] + $value;
        $feedtime = $this->getstartday($time_now);
        $this->feed->post($feedid, $time_now, $feedtime, $value);
        return $value;
    }*/

    // No longer supported
    public function histogram($feedid, $time_now, $value)
    {
        return $value;
    }

    public function pulse_diff($feedid,$time_now,$value)
    {
        $value = $this->signed2unsigned(false,false, $value);

        if($value>0)
        {
            $pulse_diff = 0;
            $last = $this->feed->get_timevalue($feedid);
            if ($last===null) return 0; // feed does not exist
            
            if ($last['time']) {
                // Need to handle resets of the pulse value (and negative 2**15?)
                if ($value >= $last['value']) {
                    $pulse_diff = $value - $last['value'];
                } else {
                    $pulse_diff = $value;
                }
            }

            // Save to allow next difference calc.
            $this->feed->post($feedid,$time_now,$time_now,$value);

            return $pulse_diff;
        }
    }

    public function kwh_to_power($feedid,$time,$value)
    {
        global $redis;
        if (!$redis) return $value; // return if redis is not available
        
        $power = 0;
        if ($redis->exists("process:kwhtopower:$feedid")) {
            $lastvalue = $redis->hmget("process:kwhtopower:$feedid",array('time','value'));
            $kwhinc = $value - $lastvalue['value'];
            $joules = $kwhinc * 3600000.0;
            $timeelapsed = ($time - $lastvalue['time']);
            if ($timeelapsed>0) {     //This only avoids a crash, it's not ideal to return "power = 0" to the next process.
                $power = $joules / $timeelapsed;
                $this->feed->post($feedid, $time, $time, $power);
            } // should have else { log error message }
        }
        $redis->hMset("process:kwhtopower:$feedid", array('time' => $time, 'value' => $value));

        return $power;
    }

    public function max_value($feedid, $time_now, $value)
    {
        // Get last values
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
         
        $last_val = $last['value'];
        $last_time = $last['time'];
        $feedtime = $this->getstartday($time_now);
        $time_check = $this->getstartday($last_time);

        // Runs on setup and midnight to reset current value - (otherwise db sets 0 as new max)
        if ($time_check != $feedtime || $value > $last_val) {
            $this->feed->post($feedid, $time_now, $feedtime, $value);
        }
        return $value;
    }

    public function min_value($feedid, $time_now, $value)
    {
        // Get last values
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
                
        $last_val = $last['value'];
        $last_time = $last['time'];
        $feedtime = $this->getstartday($time_now);
        $time_check = $this->getstartday($last_time);

        // Runs on setup and midnight to reset current value - (otherwise db sets 0 as new min)
        if ($time_check != $feedtime || $value < $last_val) {
            $this->feed->post($feedid, $time_now, $feedtime, $value);
        }
        return $value;

    }
    
    public function add_feed($feedid, $time, $value)
    {
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
        
        $value = $last['value'] + $value;
        return $value;
    }

    public function sub_feed($feedid, $time, $value)
    {
        $last  = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
             
        $myvar = $last['value'] *1;
        return $value - $myvar;
    }
    
    public function multiply_by_feed($feedid, $time, $value)
    {
        $last = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
          
        $value = $last['value'] * $value;
        return $value;
    }

   public function divide_by_feed($feedid, $time, $value)
    {
        $last  = $this->feed->get_timevalue($feedid);
        if ($last===null) return $value; // feed does not exist
            
        $myvar = $last['value'] *1;
        
        if ($myvar!=0) {
            return $value / $myvar;
        } else {
            return null;
        }
    }
    
    public function wh_accumulator($feedid, $time, $value)
    {
        $max_power = 60000; // in Watt
        $totalwh = $value;
        
        global $redis;
        if (!$redis) return $value; // return if redis is not available

        if ($redis->exists("process:whaccumulator:$feedid")) {
            $last_input = $redis->hmget("process:whaccumulator:$feedid",array('time','value'));
    
            $last_feed = $this->feed->get_timevalue($feedid);
            if ($last_feed===null) return $value; // feed does not exist
               
            $totalwh = $last_feed['value'];
            
            $time_diff = $time - $last_feed['time'];
            $val_diff = $value - $last_input['value'];
            
            if ($time_diff>0) {
                $power = ($val_diff * 3600) / $time_diff;
            
                if ($val_diff>0 && $power<$max_power) $totalwh += $val_diff;
            }

            $padding_mode = "join";
            $this->feed->post($feedid, $time, $time, $totalwh, $padding_mode);
        }
        $redis->hMset("process:whaccumulator:$feedid", array('time' => $time, 'value' => $value));

        return $totalwh;
    }
    
    public function kwh_accumulator($feedid, $time, $value)
    {
        $max_power = 60000; // in Watt
        $totalkwh = $value;
        
        global $redis;
        if (!$redis) return $value; // return if redis is not available

        if ($redis->exists("process:kwhaccumulator:$feedid")) {
            $last_input = $redis->hmget("process:kwhaccumulator:$feedid",array('time','value'));
    
            $last_feed = $this->feed->get_timevalue($feedid);
            if ($last_feed===null) return $value; // feed does not exist
             
            $totalkwh = $last_feed['value'];
            
            $time_diff = $time - $last_feed['time'];
            $val_diff = $value - $last_input['value'];
            
            if ($time_diff>0) {
                $power = ($val_diff * 3600000) / $time_diff;
            
                if ($val_diff>0 && $power<$max_power) $totalkwh += $val_diff;
            }

            $padding_mode = "join";
            $this->feed->post($feedid, $time, $time, $totalkwh, $padding_mode);
            
        }
        $redis->hMset("process:kwhaccumulator:$feedid", array('time' => $time, 'value' => $value));

        return $totalkwh;
    }
    
    public function publish_to_mqtt($topic, $time, $value)
    {
        global $redis;
        // saves value to redis
        // phpmqtt_input.php is then used to publish the values
        if ($this->mqtt){
            $data = array('topic'=>$topic,'value'=>$value,'timestamp'=>$time);
            $redis->hset("publish_to_mqtt",$topic,$value);
            // $redis->rpush('mqtt-pub-queue', json_encode($data));
        }
        return $value;
    }
    

    // Conditional process list flow
    public function if_zero_skip($noarg, $time, $value) {
        if ($value == 0)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_not_zero_skip($noarg, $time, $value) {
        if ($value != 0)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_null_skip($noarg, $time, $value) {
        if ($value === NULL)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_not_null_skip($noarg, $time, $value) {
        if (!($value === NULL))
            $this->proc_skip_next = true;
        return $value;
    }

    public function if_gt_skip($arg, $time, $value) {
        if ($value > $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_gt_equal_skip($arg, $time, $value) {
        if ($value >= $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_lt_skip($arg, $time, $value) {
        if ($value < $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_lt_equal_skip($arg, $time, $value) {
        if ($value <= $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    
    public function if_equal_skip($arg, $time, $value) {
        if ($value == $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    public function if_not_equal_skip($arg, $time, $value) {
        if ($value != $arg)
            $this->proc_skip_next = true;
        return $value;
    }
    
    public function goto_process($proc_no, $time, $value){
        $this->proc_goto = $proc_no - 2;
        return $value;
    }

    public function error_found($arg, $time, $value){
        $this->proc_goto = PHP_INT_MAX;
        return $value;
    }


    // Fetch datapoint from source feed data at specified timestamp
    // Loads full feed to data cache if it's the first time to load
    public function source_feed_data_time($feedid, $time, $value, $options)
    {
        // If start and end are set this is a request over muultiple data points
        if (isset($options['start']) && isset($options['end'])) {      
            // Load feed to data cache if it has not yet been loaded
            if (!isset($this->data_cache[$feedid])) {
                $this->data_cache[$feedid] = $this->feed->get_data($feedid,$options['start']*1000,$options['end']*1000,$options['interval'],$options['average'],$options['timezone'],'unix',false,0,0);
            }
            // Return value
            if (isset($this->data_cache[$feedid][$options['index']])) {
                return $this->data_cache[$feedid][$options['index']][1];
            }
        } else {
            // This is a request for the last value only
            $timevalue = $this->feed->get_timevalue($feedid);
            return $timevalue["value"];
        }
        return null;
    }

    public function add_source_feed($feedid, $time, $value, $options)
    {
        $last = $this->source_feed_data_time($feedid, $time, $value, $options);
        
        if ($value===null || $last===null) return null;
        $value = $last + $value;
        return $value;
    }
    
    public function sub_source_feed($feedid, $time, $value, $options)
    {
        $last = $this->source_feed_data_time($feedid, $time, $value, $options);
        
        if ($value===null || $last===null) return null;
        $myvar = $last*1;
        return $value - $myvar;
    }
    
    public function multiply_by_source_feed($feedid, $time, $value, $options)
    {
        $last = $this->source_feed_data_time($feedid, $time, $value, $options);

        if ($value===null || $last===null) return null;
        $value = $last * $value;
        return $value;
    }
    
    public function divide_by_source_feed($feedid, $time, $value, $options)
    {
        $last = $this->source_feed_data_time($feedid, $time, $value, $options);
        
        if ($value===null || $last===null) return null;
        $myvar = $last*1;

        if ($myvar!=0) {
            return $value / $myvar;
        } else {
            return null;
        }
    }
    
    public function reciprocal_by_source_feed($feedid, $time, $value, $options)
    {
        $last = $this->source_feed_data_time($feedid, $time, $value, $options);

        if ($value==null || $last==null) return null;
        $myvar = $last*1;

        if ($myvar!=0) {
            return 1 / $myvar;
        } else {
            return null;
        }
    }


    //CHAVEIRO TBD: virtual feed daily - not required on sql engine but needs tests for other engines
    public function get_feed_data_day($id, $time, $value, $options)
    {
        if ($options['start'] && $options['end']) {
            $time = $this->getstartday($options['start']);
        } else {
            $time = $this->getstartday($time);
        }

        $feedname = "feed_".trim($id)."";
        $result = $this->mysqli->query("SELECT data FROM $feedname WHERE `time` = '$time'");
        if ($result != null ) $row = $result->fetch_array();
        if (isset($row))
        {
            return $row['data'];
        }
        else
        {
            return null;
        }
    }

    
    
    // No longer used
    public function average($feedid, $time_now, $value) { return $value; } // needs re-implementing    
    public function phaseshift($id, $time, $value) { return $value; }
    public function kwh_to_kwhd_old($feedid, $time_now, $value) { return $value; }
    public function power_acc_to_kwhd($feedid,$time_now,$value) { return $value; } // Process can now be achieved with allow positive process before power to kwhd

    //------------------------------------------------------------------------------------------------------
    // Calculate the energy used to heat up water based on the rate of change for the current and a previous temperature reading
    // See http://harizanov.com/2012/05/measuring-the-solar-yield/ for more info on how to use it
    //------------------------------------------------------------------------------------------------------
    public function heat_flux($feedid,$time_now,$value) { return $value; } // Removed to be reintroduced as a post-processing based visualisation calculated on the fly.
    
    // Get the start of the day
    public function getstartday($time_now)
    {
        $now = DateTime::createFromFormat("U", (int)$time_now);
        $now->setTimezone(new DateTimeZone($this->timezone));
        $now->setTime(0,0);    // Today at 00:00
        return $now->format("U");
    }

}
