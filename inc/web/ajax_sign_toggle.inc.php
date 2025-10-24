<?php
defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

$userid = intval($_GPC['userid']);
if (empty($userid)) {
    exit(json_encode(['status' => 0, 'msg' => '缺少参数']));
}

$meetingid = intval($_GPC['meetingid']);

// 取当前签到状态
$row = pdo_get('ims_yk_wxsign_signup', ['user_id' => $userid, 'meeting_id' => $meetingid], ['verify_status']);
if (empty($row)) {
    exit(json_encode(['status' => 0, 'msg' => '记录不存在']));
}

// 切换状态
$new_status = $row['verify_status'] ? 0 : 1;
$update = [
    'verify_status' => $new_status,
    'verify_time' => $new_status ? TIMESTAMP : 0,
];
pdo_update('ims_yk_wxsign_signup', $update, ['user_id' => $userid, 'meeting_id' => $meetingid]);

// 重新计算统计
$total = pdo_fetchcolumn("SELECT COUNT(*) FROM ims_yk_wxsign_signup WHERE meeting_id = :meetingid", [':meetingid' => $meetingid]);
$signed = pdo_fetchcolumn("SELECT COUNT(*) FROM ims_yk_wxsign_signup WHERE meeting_id = :meetingid AND verify_status = 1", [':meetingid' => $meetingid]);
$unsigned = $total - $signed;
$rate = $total > 0 ? round($signed / $total * 100, 1) : 0;

// 返回 JSON
exit(json_encode([
    'status' => 1,
    'new_status' => $new_status,
    'msg' => $new_status ? '签到成功' : '撤销成功',
    'time_text' => $new_status ? date('Y-m-d H:i:s', TIMESTAMP) : '',
    'total' => $total,
    'signed' => $signed,
    'unsigned' => $unsigned,
    'rate' => $rate        
]));
