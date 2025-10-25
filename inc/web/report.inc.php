<?php
// 报表
defined('IN_IA') or exit('Access Denied');

global $_W, $_GPC;
$weid = $_W['uniacid'];

// 当前选中的 tab（默认为 tab_detail）
$tab = $_GPC['tab'] ?: 'tab_detail';

// 日期筛选（默认显示本月）
$start_date = $_GPC['start_date'] ?: date('Y-m-01');
$end_date   = $_GPC['end_date'] ?: date('Y-m-d');

// 通用分页参数
$pindex = max(1, intval($_GPC['page']));
$psize  = 50;

// ========== TAB 1：签到明细统计 ==========
if ($tab == 'tab_detail') {

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
    $sql = "SELECT s.*, v.name AS volunteer_name, total_assigned, child_class
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

    // 统计汇总
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

    foreach ($list as &$row) {
        $row['status_text'] = $row['checked_in'] == 1 ? '已签到' : '未签到';
    }

    $pager = pagination($total, $pindex, $psize);
}

// ========== TAB 2：家长签到总次数统计 ==========
if ($tab == 'tab_summary') {

    // 查询总数（家长数量）
    $total = pdo_fetchcolumn(
        "SELECT COUNT(DISTINCT v.id)
         FROM " . tablename('yk_volunteers_volunteers') . " v
         LEFT JOIN " . tablename('yk_volunteers_assignments') . " s ON v.id = s.volunteer_id
         WHERE s.uniacid = :uniacid
           AND s.date BETWEEN :start AND :end",
        [':uniacid' => $weid, ':start' => $start_date, ':end' => $end_date]
    );

    // 分页查询每位家长签到次数
    $sql = "SELECT v.id, v.name AS volunteer_name, v.child_class,prefer_slots,
                   SUM(CASE WHEN s.checked_in = 1 THEN 1 ELSE 0 END) AS sign_count,
                   total_assigned
            FROM " . tablename('yk_volunteers_volunteers') . " v
            LEFT JOIN " . tablename('yk_volunteers_assignments') . " s ON v.id = s.volunteer_id
            WHERE s.uniacid = :uniacid
              AND s.date BETWEEN :start AND :end
            GROUP BY v.id
            ORDER BY sign_count DESC,total_assigned DESC
            LIMIT " . (($pindex - 1) * $psize) . ", {$psize}";

    $params = [':uniacid' => $weid, ':start' => $start_date, ':end' => $end_date];
    $checkin_list = pdo_fetchall($sql, $params);
    foreach($checkin_list as $k => &$v){
        $v['index'] = ($pindex-1)*$psize + $k + 1; // 计算全局序号
    }
    unset($v);

    $pager = pagination($total, $pindex, $psize);
}

// ========== AJAX 支持 ==========
if ($_W['isajax']) {
    if ($tab == 'tab_detail') {
        include $this->template('report_detail_list'); // 只返回当前 tab 内容
    } else {
        include $this->template('report_summary_list');
    }
    exit;
}

// ========== 普通访问，加载整个页面 ==========
include $this->template('report');
