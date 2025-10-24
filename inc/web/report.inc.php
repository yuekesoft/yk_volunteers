<?php
// 报表
defined('IN_IA') or exit('Access Denied');

global $_W, $_GPC;
$weid = $_W['uniacid'];

// 日期筛选（默认显示本月）
$start_date = $_GPC['start_date'] ?: date('Y-m-01');
$end_date   = $_GPC['end_date'] ?: date('Y-m-d');

// 分页参数
$pindex = max(1, intval($_GPC['page']));
$psize  = 50; // 每页显示数量，可根据需要调整

// 查询总数
$total = pdo_fetchcolumn(
    "SELECT COUNT(*) 
     FROM " . tablename('yk_volunteers_assignments') . " s
     LEFT JOIN " . tablename('yk_volunteers_volunteers') . " v ON v.id = s.volunteer_id
     WHERE s.uniacid = :uniacid 
       AND s.date BETWEEN :start AND :end",
    [
        ':uniacid' => $weid,
        ':start'   => $start_date,
        ':end'     => $end_date,
    ]
);

// 查询分页数据
$sql = "SELECT s.*, v.name AS volunteer_name,total_assigned,child_class
        FROM " . tablename('yk_volunteers_assignments') . " s
        LEFT JOIN " . tablename('yk_volunteers_volunteers') . " v ON v.id = s.volunteer_id
        WHERE s.uniacid = :uniacid 
          AND s.date BETWEEN :start AND :end
        ORDER BY s.date ASC, FIELD(s.slot_code, 'morning','afternoon','evening')
        LIMIT " . (($pindex - 1) * $psize) . ", {$psize}";

$params = [
    ':uniacid' => $weid,
    ':start'   => $start_date,
    ':end'     => $end_date,
];

$list = pdo_fetchall($sql, $params);

// 统计汇总（基于当前日期条件的所有数据）
$all = pdo_fetchall(
    "SELECT checked_in FROM " . tablename('yk_volunteers_assignments') . " 
     WHERE uniacid = :uniacid 
       AND date BETWEEN :start AND :end",
    [':uniacid' => $weid, ':start' => $start_date, ':end' => $end_date]
);

$total_all = count($all);
$signed = 0;
foreach ($all as $a) {
    if ($a['checked_in'] == 1) $signed++;
}
$unsigned = $total_all - $signed;
$rate = $total_all > 0 ? round(($signed / $total_all) * 100, 2) : 0;

// 处理每行数据
foreach ($list as &$row) {
    $row['status_text'] = $row['checked_in'] == 1 ? '已签到' : '未签到';
}

// 生成分页导航
$pager = pagination($total, $pindex, $psize);

include $this->template('report');
