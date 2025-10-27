<?php
// 报表
    defined('IN_IA') or exit('Access Denied');

    global $_W, $_GPC;
    $weid = $_W['uniacid'];
    $op = $_GPC['op'] ?? 'display';

    $settings = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid]);

    
    if($op == 'clear_volunteers'){
        pdo_delete('yk_volunteers_volunteers', ['uniacid'=>$weid]);
        // 同时删除排班记录（可选）
        pdo_delete('yk_volunteers_assignments', ['uniacid'=>$weid]);
        exit(json_encode(['status'=>1,'msg'=>'家长数据已清空！']));    
    }
    
    if($op == 'clear_assignments'){
        pdo_delete('yk_volunteers_assignments', ['uniacid'=>$weid]);
        exit(json_encode(['status'=>1,'msg'=>'自动排班表已清空！']));        
    }
    
    if($op == 'reset_total_assigned'){
        pdo_update('yk_volunteers_volunteers', ['total_assigned' => 0,'last_assigned'=>''], ['uniacid' => $weid]);
        exit(json_encode(['status'=>1,'msg'=>'所有家长的总安排次数已归零']));
    }

    // ---------- 获取节假日设置 ----------
    $holidays_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'holidays']);
    $holidays_arr = $holidays_setting ? json_decode($holidays_setting['value'], true) : [];
    // 转为逗号分隔字符串，供前端输入框使用
    $holidays_str = implode(',', $holidays_arr);

    // ---------- 获取节假日通知 ----------
    $notice_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'holiday_notice']);
    $holiday_notice = $notice_setting ? $notice_setting['value'] : '';

     // ---------- 获取自动排班分页数量设置 ----------
     $auto_assign_page_size_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'auto_assign_page_size']); 
     $auto_assign_page_size = $auto_assign_page_size_setting ? $auto_assign_page_size_setting['value'] : 20;  

     // ---------- 模板消息设置 ----------
     $tmplmsg_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'tmplmsg_id']); 
     $tmplmsg_id = $tmplmsg_setting ? $tmplmsg_setting['value'] : ''; 

     $tmplmsg_openid_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'admin_openid']); 
     $admin_openid = $tmplmsg_openid_setting ? $tmplmsg_openid_setting['value'] : ''; 
      

    // 读取时段名称设置
    $slot_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'slot_labels']);
    $slot_labels = $slot_setting ? json_decode($slot_setting['value'], true) : [
        'Mon_morning' => '周一早上', 'Mon_afternoon' => '周一傍晚', 'Mon_evening' => '周一晚上',
        'Tue_morning' => '周二早上', 'Tue_afternoon' => '周二傍晚', 'Tue_evening' => '周二晚上',
        'Wed_morning' => '周三早上', 'Wed_afternoon' => '周三傍晚', 'Wed_evening' => '周三晚上',
        'Thu_morning' => '周四早上', 'Thu_afternoon' => '周四傍晚', 'Thu_evening' => '周四晚上',
        'Fri_morning' => '周五早上', 'Fri_afternoon' => '周五傍晚'
    ];

    // ---------- 家长扫码签到有效时间段设置 ----------
    $slot_times_setting = pdo_get('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'slot_times']);
    $slot_times = $slot_times_setting ? json_decode($slot_times_setting['value'], true) : [];

    // ---------- 处理 AJAX 保存请求 ----------
    if($op == 'save_holidays'){
        $holidays = trim($_GPC['holidays']);
        $holidays_arr = array_filter(array_map('trim', explode(',', $holidays)));

        if($holidays_setting){
            pdo_update('yk_volunteers_settings', ['value'=>json_encode($holidays_arr),'create_time'=>time()], ['id'=>$holidays_setting['id']]);
        } else {
            pdo_insert('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'holidays','value'=>json_encode($holidays_arr),'create_time'=>time()]);
        }

        exit(json_encode(['status'=>1,'msg'=>'节假日设置已保存！']));
    }

    if($op == 'save_holiday_notice'){
        $notice = trim($_GPC['notice']);
        if($notice_setting){
            pdo_update('yk_volunteers_settings', ['value'=>$notice,'create_time'=>time()], ['id'=>$notice_setting['id']]);
        } else {
            pdo_insert('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'holiday_notice','value'=>$notice,'create_time'=>time()]);
        }

        exit(json_encode(['status'=>1,'msg'=>'节假日通知已保存！']));
    }

    if($op == 'save_auto_assign_page_size'){
        $pagesize = trim($_GPC['pagesize']);

        if($auto_assign_page_size_setting){
            pdo_update('yk_volunteers_settings', ['value'=>$pagesize,'create_time'=>time()], ['id'=>$auto_assign_page_size_setting['id']]);
        } else {
            pdo_insert('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'auto_assign_page_size','value'=>$pagesize,'create_time'=>time()]);
        }

        exit(json_encode(['status'=>1,'msg'=>'自动排班分页显示数量设置已保存！']));
    }

    // 保存模板消息设置
    if($op == 'save_tmplmsg'){
        $tmplmsg_id = trim($_GPC['tmplmsg_id']);
        $admin_openid = trim($_GPC['admin_openid']);

        if($tmplmsg_setting){
            pdo_update('yk_volunteers_settings', ['value'=>$tmplmsg_id,'create_time'=>time()], ['id'=>$tmplmsg_setting['id']]);
        } else {
            pdo_insert('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'tmplmsg_id','value'=>$tmplmsg_id,'create_time'=>time()]);
        }

        if($tmplmsg_openid_setting){
            pdo_update('yk_volunteers_settings', ['value'=>$admin_openid,'create_time'=>time()], ['id'=>$tmplmsg_openid_setting['id']]);
        } else {
            pdo_insert('yk_volunteers_settings', ['uniacid'=>$weid,'key'=>'admin_openid','value'=>$admin_openid,'create_time'=>time()]);
        }

        exit(json_encode(['status'=>1,'msg'=>'模板消息设置已保存！']));
    }

    // 保存时段名称设置
    if ($op == 'save_slots') {
        $slot_labels = $_GPC['slot_labels'];
        $data = [
            'uniacid' => $weid,
            'key' => 'slot_labels',
            'value' => json_encode($slot_labels, JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ];
        if(!empty($slot_setting)){
                pdo_update('yk_volunteers_settings', ['value'=>$data['value'],'create_time'=>time()], ['id'=>$slot_setting['id']]);
            } else {
                pdo_insert('yk_volunteers_settings', $data);
            }           
        exit(json_encode(['status'=>1, 'msg'=>'时段名称设置已保存']));
    }

    if($_GPC['op'] == 'save_slot_times' && $_W['ispost']){
        $slot_time = $_GPC['slot_times'] ?? [];
        $data = [
            'uniacid' => $weid,
            'key' => 'slot_times',
            'value' => json_encode($slot_time, JSON_UNESCAPED_UNICODE),
            'create_time'=>time()
        ];
        if(!empty($slot_times_setting)){
                pdo_update('yk_volunteers_settings', ['value'=>$data['value'],'create_time'=>time()], ['id'=>$slot_times_setting['id']]);
        } else {
                pdo_insert('yk_volunteers_settings', $data);
        }
        exit(json_encode(['status'=>1,'msg'=>'签到有效时间段设置成功']));
    }

    // 默认显示页面（可选择返回模板）
    include $this->template('sysset');