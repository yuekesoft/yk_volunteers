<?php
// 后台请假列表
    defined('IN_IA') or exit('Access Denied');
    global $_W, $_GPC;
    // 管理界面逻辑，分页、过滤等
    $page = max(1, intval($_GPC['page']));
    $psize = 20;
    $start = ($page-1)*$psize;
    
    // $sql = "SELECT s.*, r.to_volunteer, r.from_volunteer FROM " . tablename('yk_volunteers_replacements') . 
    //     " r LEFT JOIN " . tablename('yk_volunteers_assignments') . " s ON r.schedule_id = s.id ORDER BY s.date DESC LIMIT {$start},{$psize}";
    // $list = pdo_fetchall($sql);

    $condition = " WHERE s.uniacid = :uniacid ";
    $params = [':uniacid' => $_W['uniacid']];

    if (!empty($_GPC['keyword'])) {
        $condition .= " AND (fv.name LIKE :keyword) ";
        $params[':keyword'] = "%{$_GPC['keyword']}%";
    }    

    $sql = "
        SELECT 
            r.*, 
            fv.name AS from_name, fv.child_class AS from_class,s.status as s_status,slot_code as s_slot_code,s.date as s_date,
            tv.name AS to_name, tv.child_class AS to_class
        FROM " . tablename('yk_volunteers_replacements') . " AS r
        LEFT JOIN " . tablename('yk_volunteers_volunteers') . " AS fv ON r.from_volunteer = fv.id
        LEFT JOIN " . tablename('yk_volunteers_volunteers') . " AS tv ON r.to_volunteer = tv.id
        LEFT JOIN " . tablename('yk_volunteers_assignments') . " s ON r.schedule_id = s.id 
        ".$condition."
        ORDER BY r.create_time DESC
        LIMIT {$start}, {$psize}";

        $list = pdo_fetchall($sql,$params);


    include $this->template('requests');