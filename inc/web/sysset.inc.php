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

    // 默认显示页面（可选择返回模板）
    include $this->template('sysset');