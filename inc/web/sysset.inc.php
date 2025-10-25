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

    // 读取时段名称设置
    $slot_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $weid, 'key' => 'slot_labels']);
    $slot_labels = $slot_setting ? json_decode($slot_setting['value'], true) : [
        'Mon_morning' => '周一早上', 'Mon_afternoon' => '周一傍晚', 'Mon_evening' => '周一晚上',
        'Tue_morning' => '周二早上', 'Tue_afternoon' => '周二傍晚', 'Tue_evening' => '周二晚上',
        'Wed_morning' => '周三早上', 'Wed_afternoon' => '周三傍晚', 'Wed_evening' => '周三晚上',
        'Thu_morning' => '周四早上', 'Thu_afternoon' => '周四傍晚', 'Thu_evening' => '周四晚上',
        'Fri_morning' => '周五早上', 'Fri_afternoon' => '周五傍晚'
    ];

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

    // 保存时段名称设置
    if ($op == 'save_slots') {
        $slot_labels = $_GPC['slot_labels'];
        pdo_insert('yk_volunteers_settings', [
            'uniacid' => $weid,
            'key' => 'slot_labels',
            'value' => json_encode($slot_labels, JSON_UNESCAPED_UNICODE),
        ], true);
        exit(json_encode(['status'=>1, 'msg'=>'时段名称设置已保存']));
    }

    if($_GPC['op'] == 'save_slot_times' && $_W['ispost']){
        $slot_times = $_GPC['slot_times'] ?? [];
        if(!empty($slot_times)){
            $exist = pdo_get('yk_volunteers_settings', ['uniacid'=>$_W['uniacid'], 'key'=>'slot_times']);
            $data = [
                'uniacid' => $_W['uniacid'],
                'key' => 'slot_times',
                'value' => json_encode($slot_times, JSON_UNESCAPED_UNICODE),
            ];
            if($exist){
                pdo_update('yk_volunteers_settings', ['value'=>$data['value']], ['id'=>$exist['id']]);
            } else {
                pdo_insert('yk_volunteers_settings', $data);
            }
            exit(json_encode(['status'=>1,'msg'=>'保存成功']));
        }
        exit(json_encode(['status'=>0,'msg'=>'参数为空']));
    }

    // 默认显示页面（可选择返回模板）
    include $this->template('sysset');