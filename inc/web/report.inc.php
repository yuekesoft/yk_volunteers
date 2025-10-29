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

// ========== 导出 Excel 处理 ==========
if ($_GPC['op'] == 'export') {
    require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';

    if ($tab == 'tab_detail') {
        // 导出签到明细
        $sql = "SELECT s.*, v.name AS volunteer_name, v.child_class, v.prefer_slots
                FROM " . tablename('yk_volunteers_assignments') . " s
                LEFT JOIN " . tablename('yk_volunteers_volunteers') . " v ON v.id = s.volunteer_id
                WHERE s.uniacid = :uniacid
                  AND s.date BETWEEN :start AND :end
                ORDER BY s.date ASC";
        $params = [':uniacid' => $weid, ':start' => $start_date, ':end' => $end_date];
        $list = pdo_fetchall($sql, $params);

        // 设置表头
        $objPHPExcel = new PHPExcel();
        $sheet = $objPHPExcel->setActiveSheetIndex(0);
        $sheet->setCellValue('A1', '日期')
              ->setCellValue('B1', '家长姓名')
              ->setCellValue('C1', '孩子班级')
              ->setCellValue('D1', '时段')
              ->setCellValue('E1', '签到状态')
              ->setCellValue('F1', '签到时间');

        $row = 2;
        foreach ($list as $v) {
            $status = $v['checked_in'] == 1 ? '已签到' : '未签到';
            $sign_time = $v['checkin_time'] ? date('Y-m-d H:i', $v['checkin_time']) : '';
            $sheet->setCellValue('A'.$row, $v['date'])
                  ->setCellValue('B'.$row, $v['volunteer_name'])
                  ->setCellValue('C'.$row, $v['child_class'])
                  ->setCellValue('D'.$row, $v['slot_code'])
                  ->setCellValue('E'.$row, $status)
                  ->setCellValue('F'.$row, $sign_time);
            $row++;
        }

        $filename = "签到明细_{$start_date}_{$end_date}.xlsx";
    } else {
        // 导出家长签到次数汇总
        $sql = "SELECT v.name AS volunteer_name, v.child_class, v.prefer_slots,
                       SUM(CASE WHEN s.checked_in = 1 THEN 1 ELSE 0 END) AS sign_count,
                       COUNT(s.id) AS total_assigned
                FROM " . tablename('yk_volunteers_volunteers') . " v
                LEFT JOIN " . tablename('yk_volunteers_assignments') . " s ON v.id = s.volunteer_id
                WHERE s.uniacid = :uniacid
                  AND s.date BETWEEN :start AND :end
                GROUP BY v.id
                ORDER BY sign_count DESC, total_assigned DESC";
        $params = [':uniacid' => $weid, ':start' => $start_date, ':end' => $end_date];
        $list = pdo_fetchall($sql, $params);

        // 偏好时段映射
        $slot_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $_W['uniacid'], 'key' => 'slot_labels']);
        $slot_labels = $slot_setting ? json_decode($slot_setting['value'], true) : [];

        // 创建表
        $objPHPExcel = new PHPExcel();
        $sheet = $objPHPExcel->setActiveSheetIndex(0);
        $sheet->setCellValue('A1', '家长姓名')
              ->setCellValue('B1', '孩子班级')
              ->setCellValue('C1', '偏好时段')
              ->setCellValue('D1', '签到次数')
              ->setCellValue('E1', '总安排次数');

        $row = 2;
        foreach ($list as $v) {
            $slot_label = $slot_labels[$v['prefer_slots']] ?? $v['prefer_slots'];
            $sheet->setCellValue('A'.$row, $v['volunteer_name'])
                  ->setCellValue('B'.$row, $v['child_class'])
                  ->setCellValue('C'.$row, $slot_label)
                  ->setCellValue('D'.$row, $v['sign_count'])
                  ->setCellValue('E'.$row, $v['total_assigned']);
            $row++;
        }

        $filename = "家长签到汇总_{$start_date}_{$end_date}.xlsx";
    }

    // 输出 Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');
    $writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $writer->save('php://output');
    exit;
}

// 通用分页参数
$pindex = max(1, intval($_GPC['page']));
$psize  = 50;

$slot_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $_W['uniacid'], 'key' => 'slot_labels']);
$slot_labels = $slot_setting ? json_decode($slot_setting['value'], true) : [];

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
if ($_W['isajax'] && $_GPC['tabsearch']=='tabsearch') {
    if ($tab == 'tab_detail') {
        include $this->template('report_detail_list'); // 只返回当前 tab 内容
    } else {
        include $this->template('report_summary_list');
    }
    exit;
}

// 日志记录显示
$logDir = IA_ROOT . '/data/logs/';
$pattern = $logDir . 'cron_notice_*.php'; // ✅ 注意这里是 .php
$files = glob($pattern);
rsort($files);

if ($_W['isajax'] && $_GPC['tabsearch'] == 'auto_notice_log') {
    $file = $_GPC['file'];
    $path = realpath($logDir . basename($file));

    // 安全性检查，防止越权访问
    if (strpos($path, realpath($logDir)) !== 0) {
        die(json_encode(['status' => 0, 'msg' => '非法路径']));
    }

    if (file_exists($path)) {
        // ✅ 强制按文本读取，而非执行
        $content = file_get_contents($path);
        $content = htmlspecialchars($content, ENT_QUOTES);
        die(json_encode(['status' => 1, 'data' => $content]));
    } else {
        die(json_encode(['status' => 0, 'msg' => '日志文件不存在']));
    }
}

// ========== 普通访问，加载整个页面 ==========
include $this->template('report');
