<?php
/**
 * 家长扫码签到
 * 文件名: self_check.inc.php
 */

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

$uniacid = $_W['uniacid'];
$openid = $_W['openid']; // 当前微信用户 openid

// 页面页脚标题
$_W['page']['title'] = '滨江初中志愿者值班表';
$_W['uniaccount']['name'] = '';

// 查询家长信息
$volunteer = pdo_get('yk_volunteers_volunteers', ['uniacid' => $uniacid, 'openid' => $openid]);
if (empty($volunteer)) {
    message('您尚未绑定家长信息，请先绑定微信', $this->createMobileUrl('bind_wechat'), 'error');
}

// 获取当前日期和时间
$today = date('Y-m-d');
$now_time = date('H:i');

// 获取系统设置的签到时间段
$slot_times_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $uniacid, 'key' => 'slot_times']);
$slot_times = $slot_times_setting ? json_decode($slot_times_setting['value'], true) : [
    'morning'   => ['start' => '06:30', 'end' => '07:30'],
    'afternoon' => ['start' => '16:00', 'end' => '17:30'],
    'evening'   => ['start' => '19:40', 'end' => '20:30'],
];

// 判断当前时间属于哪个时段
$current_slot = null;
foreach ($slot_times as $slot_code => $range) {
    if ($now_time >= $range['start'] && $now_time <= $range['end']) {
        $current_slot = $slot_code;
        break;
    }
}

if (!$current_slot) {
    message('当前时间不在任何签到有效时段内', $this->createMobileUrl('assignments_list'), 'error');
}

// 查询今天该家长在当前时段是否有排班
$assignment = pdo_get('yk_volunteers_assignments', [
    'uniacid' => $uniacid,
    'volunteer_id' => $volunteer['id'],
    'date' => $today,
    'slot_code' => $current_slot
]);

if ($current_slot=='morning'){
    $current_text = $today.' 早上';
} elseif ($current_slot=='afternoon') {
    $current_text = $today.' 傍晚';
} elseif ($current_slot=='evening') {
    $current_text = $today.' 晚上';
}

if (empty($assignment)) {
    message("您今天没有安排在 {$current_text} 时段的排班", '', 'error');
}

// 判断是否已签到
if ($assignment['checked_in']) {
    message('您今天该时段已经签到过了', $this->createMobileUrl('assignments_list'), 'info');
}

// 更新签到时间和状态
pdo_update('yk_volunteers_assignments', [
    'checked_in' => 1,
    'checkin_time' => date('Y-m-d H:i:s'),
    'status' => 'completed'
], ['id' => $assignment['id'],'uniacid'=>$uniacid]);

message("签到成功！当前时段：{$current_text}", $this->createMobileUrl('assignments_list'), 'success');
