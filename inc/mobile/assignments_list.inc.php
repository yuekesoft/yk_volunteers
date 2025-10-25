<?php
global $_W, $_GPC;
$uniacid = $_W['uniacid'];
$openid = $_W['openid']; // 当前访问者微信openid

// 获取选择的日期范围（默认显示本周）
$start_date = $_GPC['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$end_date   = $_GPC['end_date'] ?? date('Y-m-d', strtotime($start_date . ' +6 days'));

// 通知
$setting_notice = pdo_get('yk_volunteers_settings', ['uniacid'=>$uniacid, 'key'=>'holiday_notice']);
$holiday_notice = $setting_notice ? $setting_notice['value'] : '';

// 查询当前 openid 是否绑定了家长
$volunteer = pdo_get('yk_volunteers_volunteers', ['uniacid'=>$uniacid, 'openid'=>$openid]);

if (!empty($volunteer)){
    $condition = ' and v.openid= :openid ';
    $params = [':openid'=>$openid];
}
$params['uniacid'] = $uniacid;
$params['start_date'] = $start_date;
$params['end_date'] = $end_date;

// 查询排班数据
$list = pdo_fetchall("
    SELECT a.*, v.name AS volunteer_name, v.child_class AS volunteer_child_class
    FROM " . tablename('yk_volunteers_assignments') . " AS a
    LEFT JOIN " . tablename('yk_volunteers_volunteers') . " AS v ON v.id = a.volunteer_id
    WHERE a.uniacid = :uniacid ".$condition."
      AND a.date BETWEEN :start_date AND :end_date
    ORDER BY a.date ASC, FIELD(a.slot_code, 'morning','afternoon','evening')
", $params);

$has_data = !empty($list); // 判断是否有数据

// 分组处理（按日期）
$grouped_assignments = [];
foreach ($list as $row) {
    $grouped_assignments[$row['date']][] = $row;
}

// 页面页脚标题
$_W['page']['title'] = '滨江初中志愿者值班表';
$_W['uniaccount']['name'] = '';

// 引入模板
include $this->template('assignments_list');
