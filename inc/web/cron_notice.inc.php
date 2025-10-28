<?php
defined('IN_IA') or exit('Access Denied');

global $_W;

// 加载日志
load()->func('logging');

// 读取系统设置
$sysset = pdo_getall('yk_volunteers_settings', ['uniacid' => $_W['uniacid']], [], 'key');
if (empty($sysset['enable_auto_notice']['value']) || $sysset['enable_auto_notice']['value'] != 1) {
    logging_run('自动提醒未启用', 'info', 'cron_notice');
    exit('auto_notice disabled');
}

$notice_days = intval($sysset['notice_time']['value'] ?: 1); // 默认提前1天
$target_date = date('Y-m-d', strtotime("+{$notice_days} day"));

logging_run("开始执行排班提醒任务，目标日期：{$target_date}", 'info', 'cron_notice');

// 查询即将值班的记录
$schedules = pdo_getall('yk_volunteers_assignments', [
    'uniacid' => $_W['uniacid'],
    'date'    => $target_date
]);

if (empty($schedules)) {
    logging_run('无待提醒排班记录', 'info', 'cron_notice');
    exit('no schedule');
}

load()->classs('weixin.account');
$acc = WeAccount::create($_W['acid']);

// 模板消息配置
$auto_notice_tmplmsg_id = trim($sysset['auto_notice_tmplmsg_id']['value']);
$admin_openids = explode(',', trim($sysset['admin_openid']['value']));

// 遍历发送消息
foreach ($schedules as $s) {
    $volunteer = pdo_get('yk_volunteers_volunteers', ['id' => $s['volunteer_id']]);
    if (empty($volunteer['openid'])) continue;

    // 时段文字
    $slot = [
        'morning' => '早上',
        'afternoon' => '傍晚',
        'evening' => '晚上'
    ][$s['slot_code']] ?? '';

    // 模板数据
    $send_data = [
        'thing31'  => ['value' => '滨江初中志愿者家长值班提醒'],
        'thing5' => ['value' => $volunteer['name'].'('.$volunteer['child_class'].')'],
        'thing8' => ['value' => "{$s['date']} {$slot} 值日"],
    ];

    // 给家长本人发送提醒
    $res = $acc->sendTplNotice($volunteer['openid'], $auto_notice_tmplmsg_id, $send_data, '', '#173177');
    logging_run("家长({$volunteer['name']})提醒发送结果: " . json_encode($res), 'info', 'cron_notice');

    // 可选：同时发给管理员
    // foreach ($admin_openids as $openid) {
    //     $openid = trim($openid);
    //     if (empty($openid)) continue;
    //     $acc->sendTplNotice($openid, $tmplmsg_id, $send_data, '', '#173177');
    // }
}

echo "排班提醒任务执行完成: ".date('Y-m-d H:i:s');
