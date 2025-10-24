<?php
// 报表
    defined('IN_IA') or exit('Access Denied');

    global $_W, $_GPC;
    $op = $_GPC['op'] ?? 'display';
    
    $table_volunteers = tablename('yk_volunteers_volunteers');
    $table_assignments = tablename('yk_volunteers_assignments');
    
    if($op == 'clear_volunteers'){
        pdo_query("TRUNCATE TABLE {$table_volunteers}");
        exit(json_encode(['status'=>1,'msg'=>'家长数据已清空，ID已重置为1']));
    }
    
    if($op == 'clear_assignments'){
        pdo_query("TRUNCATE TABLE {$table_assignments}");
        exit(json_encode(['status'=>1,'msg'=>'自动排班表已清空，ID已重置为1']));
    }
    
    if($op == 'reset_total_assigned'){
        pdo_update('yk_volunteers_volunteers', ['total_assigned' => 0,'last_assigned'=>''], ['uniacid' => $_W['uniacid']]);
        exit(json_encode(['status'=>1,'msg'=>'所有家长的总安排次数已归零']));
    }
    

    include $this->template('sysset');