<?php
defined('IN_IA') or exit('Access Denied');

global $_W, $_GPC;
$weid = $_W['uniacid'];
$op = $_GPC['op'] ?? 'display';

// 读取系统设置（按 key 索引）
$sysset = pdo_getall('yk_volunteers_settings', ['uniacid' => $weid], [], 'key');

// 清空家长与排班
if ($op == 'clear_volunteers') {
    pdo_delete('yk_volunteers_volunteers', ['uniacid' => $weid]);
    // 同时删除排班记录（可选）
    pdo_delete('yk_volunteers_assignments', ['uniacid' => $weid]);
    exit(json_encode(['status' => 1, 'msg' => '家长数据已清空！']));
}

// 清空排班
if ($op == 'clear_assignments') {
    pdo_delete('yk_volunteers_assignments', ['uniacid' => $weid]);
    exit(json_encode(['status' => 1, 'msg' => '自动排班表已清空！']));
}

// 归零累计安排次数
if ($op == 'reset_total_assigned') {
    pdo_update('yk_volunteers_volunteers', ['total_assigned' => 0, 'last_assigned' => ''], ['uniacid' => $weid]);
    exit(json_encode(['status' => 1, 'msg' => '所有家长的总安排次数已归零']));
}

// ---------- 获取节假日设置 ----------
$holidays_arr = !empty($sysset['holidays']) ? json_decode($sysset['holidays']['value'], true) : [];
// 供前端文本框使用
$holidays_str = implode(',', $holidays_arr);

// ---------- 获取节假日通知 ----------
$holiday_notice = !empty($sysset['holiday_notice']) ? $sysset['holiday_notice']['value'] : '';

// ---------- 自动排班分页数量 ----------
$auto_assign_page_size = !empty($sysset['auto_assign_page_size']) ? $sysset['auto_assign_page_size']['value'] : 20;

// ---------- 模板消息设置 ----------
$tmplmsg_id = !empty($sysset['tmplmsg_id']) ? $sysset['tmplmsg_id']['value'] : '';
$admin_openid = !empty($sysset['admin_openid']) ? $sysset['admin_openid']['value'] : '';

// ---------- 模板消息自动发送设置 ----------
$enable_auto_notice = !empty($sysset['enable_auto_notice']) ? $sysset['enable_auto_notice']['value'] : 1;
$notice_time = !empty($sysset['notice_time']) ? $sysset['notice_time']['value'] : 1;
$auto_notice_tmplmsg_id = !empty($sysset['auto_notice_tmplmsg_id']) ? $sysset['auto_notice_tmplmsg_id']['value'] : '';

// ---------- 时段名称 ----------
$slot_labels = !empty($sysset['slot_labels']) ? json_decode($sysset['slot_labels']['value'], true) : [
    'Mon_morning' => '周一早上', 'Mon_afternoon' => '周一傍晚', 'Mon_evening' => '周一晚上',
    'Tue_morning' => '周二早上', 'Tue_afternoon' => '周二傍晚', 'Tue_evening' => '周二晚上',
    'Wed_morning' => '周三早上', 'Wed_afternoon' => '周三傍晚', 'Wed_evening' => '周三晚上',
    'Thu_morning' => '周四早上', 'Thu_afternoon' => '周四傍晚', 'Thu_evening' => '周四晚上',
    'Fri_morning' => '周五早上', 'Fri_afternoon' => '周五傍晚'
];

// ---------- 家长扫码签到有效时间段 ----------
$slot_times = !empty($sysset['slot_times']) ? json_decode($sysset['slot_times']['value'], true) : [];

// ---------- 处理保存请求（AJAX） ----------
if ($op == 'save_holidays') {
    $holidays = trim($_GPC['holidays']);
    $holidays_arr = array_filter(array_map('trim', explode(',', $holidays)));

    if (!empty($sysset['holidays'])) {
        pdo_update('yk_volunteers_settings', ['value' => json_encode($holidays_arr), 'create_time' => time()], ['id' => $sysset['holidays']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'holidays', 'value' => json_encode($holidays_arr), 'create_time' => time()]);
    }

    exit(json_encode(['status' => 1, 'msg' => '节假日设置已保存！']));
}

if ($op == 'save_holiday_notice') {
    $notice = trim($_GPC['notice']);
    if (!empty($sysset['holiday_notice'])) {
        pdo_update('yk_volunteers_settings', ['value' => $notice, 'create_time' => time()], ['id' => $sysset['holiday_notice']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'holiday_notice', 'value' => $notice, 'create_time' => time()]);
    }

    exit(json_encode(['status' => 1, 'msg' => '节假日通知已保存！']));
}

if ($op == 'save_auto_assign_page_size') {
    $pagesize = trim($_GPC['pagesize']);
    if (!empty($sysset['auto_assign_page_size'])) {
        pdo_update('yk_volunteers_settings', ['value' => $pagesize, 'create_time' => time()], ['id' => $sysset['auto_assign_page_size']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'auto_assign_page_size', 'value' => $pagesize, 'create_time' => time()]);
    }

    exit(json_encode(['status' => 1, 'msg' => '自动排班分页显示数量已保存！']));
}

// 保存模板消息设置
if ($op == 'save_tmplmsg') {
    $tmplmsg_id = trim($_GPC['tmplmsg_id']);
    $admin_openid = trim($_GPC['admin_openid']);
    if (!empty($sysset['tmplmsg_id'])) {
        pdo_update('yk_volunteers_settings', ['value' => $tmplmsg_id, 'create_time' => time()], ['id' => $sysset['tmplmsg_id']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'tmplmsg_id', 'value' => $tmplmsg_id, 'create_time' => time()]);
    }

    if (!empty($sysset['admin_openid'])) {
        pdo_update('yk_volunteers_settings', ['value' => $admin_openid, 'create_time' => time()], ['id' => $sysset['admin_openid']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'admin_openid', 'value' => $admin_openid, 'create_time' => time()]);
    }

    exit(json_encode(['status' => 1, 'msg' => '模板消息设置已保存！']));
}

// 保存自动模板消息设置
if ($op == 'save_auto_notice') {
    $enable_auto_notice = intval($_GPC['enable_auto_notice']);
    $notice_time = intval($_GPC['notice_time']);
    $auto_notice_tmplmsg_id = trim($_GPC['auto_notice_tmplmsg_id']);

    if (!empty($sysset['enable_auto_notice'])) {
        pdo_update('yk_volunteers_settings', ['value' => $enable_auto_notice, 'create_time' => time()], ['id' => $sysset['enable_auto_notice']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'enable_auto_notice', 'value' => $enable_auto_notice, 'create_time' => time()]);
    }

    if (!empty($sysset['notice_time'])) {
        pdo_update('yk_volunteers_settings', ['value' => $notice_time, 'create_time' => time()], ['id' => $sysset['notice_time']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'notice_time', 'value' => $notice_time, 'create_time' => time()]);
    }

    if (!empty($sysset['auto_notice_tmplmsg_id'])) {
        pdo_update('yk_volunteers_settings', ['value' => $auto_notice_tmplmsg_id, 'create_time' => time()], ['id' => $sysset['auto_notice_tmplmsg_id']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'auto_notice_tmplmsg_id', 'value' => $auto_notice_tmplmsg_id, 'create_time' => time()]);
    }

    // 注意：为避免潜在前端依赖，保持原本的返回键名
    exit(json_encode(['status' => 'success', 'message' => '模板消息自动发送设置已保存！']));
}

// 保存时段名称设置
if ($op == 'save_slots') {
    $slot_labels = $_GPC['slot_labels'];
    $data = [
        'uniacid' => $weid,
        'key' => 'slot_labels',
        'value' => json_encode($slot_labels, JSON_UNESCAPED_UNICODE),
        'create_time' => time(),
    ];
    if (!empty($sysset['slot_labels'])) {
        pdo_update('yk_volunteers_settings', ['value' => $data['value'], 'create_time' => time()], ['id' => $sysset['slot_labels']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', $data);
    }
    exit(json_encode(['status' => 1, 'msg' => '时段名称设置已保存！']));
}

// 保存扫码签到有效时间段
if ($op == 'save_slot_times' && $_W['ispost']) {
    $slot_time = $_GPC['slot_times'] ?? [];
    $data = [
        'uniacid' => $weid,
        'key' => 'slot_times',
        'value' => json_encode($slot_time, JSON_UNESCAPED_UNICODE),
        'create_time' => time(),
    ];
    if (!empty($sysset['slot_times'])) {
        pdo_update('yk_volunteers_settings', ['value' => $data['value'], 'create_time' => time()], ['id' => $sysset['slot_times']['id']]);
    } else {
        pdo_insert('yk_volunteers_settings', $data);
    }
    exit(json_encode(['status' => 1, 'msg' => '签到有效时间段设置成功！']));
}

// 默认显示页面
include $this->template('sysset');

