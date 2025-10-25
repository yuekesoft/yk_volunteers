<?php
defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;
$uniacid = $_W['uniacid'];

$op = !empty($_GPC['op']) ? $_GPC['op'] : 'display';

// 表名
$table_assignments = 'ims_yk_volunteers_assignments';
$table_volunteers = 'ims_yk_volunteers_volunteers';
$table_slots = 'ims_yk_volunteers_slot_templates';

// 获取星期中文映射
$weekMap = [1=>'周一',2=>'周二',3=>'周三',4=>'周四',5=>'周五'];

// 获取系统设置的节假日日期
$setting_holidays = pdo_get('yk_volunteers_settings', ['uniacid'=>$uniacid, 'key'=>'holidays']);
$holidays = $setting_holidays ? json_decode($setting_holidays['value'], true) : [];


if ($op == 'display') {
    // === 排班列表分页 ===
    $pindex = max(1, intval($_GPC['page']));
    $psize = 196;

    $condition = ' WHERE a.uniacid = :uniacid';
    $params = [':uniacid'=>$uniacid];

    // 可以按日期或志愿者筛选
    if(!empty($_GPC['date'])) {
        $condition .= " AND date = :date";
        $params[':date'] = $_GPC['date'];
    }

    // 搜索关键字（支持姓名、手机号、偏好）
    if (!empty($_GPC['keyword'])) {
        $condition .= " AND (v.name LIKE :keyword OR v.phone LIKE :keyword OR v.prefer_slots LIKE :keyword) ";
        $params[':keyword'] = "%{$_GPC['keyword']}%";
    }

    // 主查询（含 JOIN）
    $list = pdo_fetchall("
        SELECT a.*, v.name AS volunteer_name, v.child_class AS volunteer_child_class 
        FROM {$table_assignments} a
        LEFT JOIN {$table_volunteers} v ON a.volunteer_id = v.id
        {$condition}
        ORDER BY a.date ASC, a.slot_code DESC
        LIMIT " . ($pindex - 1) * $psize . ", {$psize}", $params);

    // 统计总数（也需要 JOIN，否则条件不生效）
    $total = pdo_fetchcolumn("
        SELECT COUNT(*) 
        FROM {$table_assignments} a
        LEFT JOIN {$table_volunteers} v ON a.volunteer_id = v.id
        {$condition}", $params);
    $pager = pagination($total, $pindex, $psize);
   
    // ---- 分组逻辑 ----
    $grouped_assignments = [];
    foreach ($list as $a) {
        $date = $a['date'];
        if (!isset($grouped_assignments[$date])) {
            // 初始化以保证顺序和即使无数据也能显示“无安排”
            $grouped_assignments[$date] = [
                'morning' => [],
                'afternoon' => [],
                'evening' => []
            ];
        }
        // 将记录推入对应时段数组
        $slot = $a['slot_code'] ?: 'morning'; // 容错
        if (!isset($grouped_assignments[$date][$slot])) {
            // 如果碰到额外时段也创建该键
            $grouped_assignments[$date][$slot] = [];
        }
        $grouped_assignments[$date][$slot][] = $a;        
    }  

    include $this->template('assignments_list');
    exit;
}

elseif ($op == 'add' || $op == 'edit') {
    $id = intval($_GPC['id']);
    if($id>0){
        $item = pdo_get($table_assignments, ['id'=>$id,'uniacid'=>$_W['uniacid']]);
    }

    // 获取志愿者列表
    $volunteers = pdo_fetchall("SELECT * FROM {$table_volunteers} WHERE uniacid=:uniacid ORDER BY name ASC", [':uniacid'=>$_W['uniacid']]);

    // 获取时段模板
    $slots = pdo_fetchall("SELECT * FROM {$table_slots} WHERE uniacid=:uniacid ORDER BY weekday ASC, slot_code ASC", [':uniacid'=>$_W['uniacid']]);

    if($_W['ispost']){
        $data = [
            'uniacid'=>$_W['uniacid'],
            'uid'=>$_W['uid'],
            'date'=>$_GPC['date'],
            'weekday'=>$_GPC['weekday'],
            'slot_code'=>trim($_GPC['slot_code']),
            'volunteer_id'=>intval($_GPC['volunteer_id']),
            'role'=>$_GPC['role'] ?? 'primary',
            'status'=>$_GPC['status'] ?? 'scheduled',
            'checked_in'=>intval($_GPC['checked_in']),
            'checkin_time'=>!empty($_GPC['checked_in']) ? date('Y-m-d H:i:s') : null,
        ];

        if($id>0){
            pdo_update($table_assignments, $data, ['id'=>$id]);
            message('更新成功！',$this->createWebUrl('assignments',['op'=>'display']),'success');
        }else{
            pdo_insert($table_assignments,$data);
            message('添加成功！',$this->createWebUrl('assignments',['op'=>'display']),'success');
        }
    }

    include $this->template('assignments_edit');
    exit;
}

elseif($op=='delete'){
    $id = intval($_GPC['id']);
    $item = pdo_get($table_assignments,['id'=>$id,'uniacid'=>$_W['uniacid']]);
    if(empty($item)) message('记录不存在！',referer(),'error');
    // 更新志愿者总安排次数
    pdo_query("UPDATE " . tablename('yk_volunteers_volunteers') . " 
           SET total_assigned = total_assigned - 1, 
               last_assigned = '' 
           WHERE id = :id", 
           [':id' => $item['volunteer_id']]);

    pdo_delete($table_assignments,['id'=>$id,'uniacid'=>$_W['uniacid']]);
    message('删除成功！',referer(),'success');
}

elseif($op=='auto_assign'){
    global $_W,$_GPC;

    $start_date = $_GPC['start_date'];
    $mode = $_GPC['mode']; // week 或 month

    if(empty($start_date)){
        //show_json(0,'请选择起始日期');
        exit(json_encode(['status'=> 2,'message'=>'请选择起始日期']));
    }

    // 调整起始日期为周一
    $weekday = date('N',strtotime($start_date)); // 1=周一
    $start_monday = date('Y-m-d', strtotime($start_date.' -'.($weekday-1).' days'));

    if($mode=='week'){
        auto_assign_volunteers($start_monday,$holidays);
    }elseif($mode=='month'){
        // 循环每周
        $weeks = 4; // 示例排4周
        for($i=0;$i<$weeks;$i++){
            $week_start = date('Y-m-d', strtotime($start_monday.' +'.($i*7).' days'));
            auto_assign_volunteers($week_start,$holidays);
        }
    }

    exit(json_encode(['status'=> 1,'message'=>'排班完成']));
    //show_json(1,'排班完成');
}

// 签到按钮
elseif ($op == 'batch_checkin') {
    $ids = $_POST['ids'];
    if (empty($ids)) {
        exit(json_encode(['status' => 'error', 'message' => '未选择任何记录']));
    }

    foreach ($ids as $id) {
        pdo_update('ims_yk_volunteers_assignments', [
            'checked_in' => 1,
            'checkin_time' => date('Y-m-d H:i:s'),
            'status' => 'completed'
        ], [
            'id' => intval($id),
            'uniacid' => $_W['uniacid']
        ]);
    }

    exit(json_encode(['status' => 'success']));
}

// 撤销签到按钮
elseif ($op == 'batch_uncheckin') {
    $ids = $_POST['ids'];
    if (empty($ids)) {
        exit(json_encode(['status' => 'error', 'message' => '未选择任何记录']));
    }

    foreach ($ids as $id) {
        pdo_update('ims_yk_volunteers_assignments', [
            'checked_in' => 0,
            'checkin_time' => '',
            'status' => 'scheduled'
        ], [
            'id' => intval($id),
            'uniacid' => $_W['uniacid']
        ]);
    }

    exit(json_encode(['status' => 'success']));
}

/**
 * 自动生成一周排班
 * @param string $start_date 本周起始日期 YYYY-MM-DD（周一）
 */
function auto_assign_volunteers($start_date, $holidays_param) {
    global $_W;

    $uniacid = $_W['uniacid'];

    // 1. 取所有志愿者
    $volunteers = pdo_fetchall("SELECT * FROM ims_yk_volunteers_volunteers 
        WHERE uniacid=:uniacid ORDER BY total_assigned ASC", [':uniacid'=>$uniacid]);

    // 2. 取时段模板
    $slots = pdo_fetchall("SELECT * FROM ims_yk_volunteers_slot_templates 
        WHERE uniacid=:uniacid ORDER BY weekday ASC, slot_code ASC", [':uniacid'=>$uniacid]);

    // 3. 每个时段的人数上限（可根据 slot_template 或硬编码）
    // $slot_limits = [
    //     'morning' => ['min'=>8,'max'=>10],
    //     'afternoon'=>['min'=>2,'max'=>4],
    //     'evening'=>['min'=>8,'max'=>10]
    // ];

    // 4. 循环每个时段，安排志愿者
    foreach($slots as $slot){
        $date = date('Y-m-d', strtotime($start_date. ' +'.($slot['weekday']-1).' days'));

        // 如果日期是节假日，跳过
        if(in_array($date, $holidays_param)){
            continue; // 跳过当前循环，执行下一个 slot
        }

        $weekday = intval($slot['weekday']);//星期几
        $slot_code = $slot['slot_code'];
        $max_num = $slot['required_max'];

        // 找出偏好该时段的志愿者
        $candidates = [];
        foreach($volunteers as $v){
            // 志愿者偏好拆分
            $prefs = explode(',', $v['prefer_slots']); // ["Mon_morning","Fri_evening"]

            $slot_key_map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri'];
            $slot_key = $slot_key_map[$weekday] . '_' . $slot_code;

            // 判断是否偏好该时段
            if(in_array($slot_key, $prefs)){
                $candidates[] = $v;
            }
        }

        // 按 total_assigned 升序排列，优先安排少的
        usort($candidates,function($a,$b){
            return $a['total_assigned'] - $b['total_assigned'];
        });

        // 如果偏好不足，考虑所有可替补志愿者
        if(count($candidates)<$max_num){
            $extra = [];
            foreach($volunteers as $v){
                if($v['can_substitute'] && !in_array($v,$candidates)){
                    $extra[] = $v;
                }
            }
            $candidates = array_merge($candidates,$extra);
        }

        // 取前 $max_num 个安排
        $assigned = array_slice($candidates,0,$max_num);

        foreach($assigned as $v){
            $data = [
                'uniacid'=>$uniacid,
                'uid'=>$v['uid'],
                'date'=>$date,
                'weekday'=>$weekday,
                'slot_code'=>$slot_code,
                'volunteer_id'=>$v['id'],
                'role'=>'primary',
                'status'=>'scheduled',
                'checked_in'=>0,
                'create_time'=>date('Y-m-d H:i:s'),
                'update_time'=>date('Y-m-d H:i:s')
            ];
            pdo_insert('ims_yk_volunteers_assignments',$data);

            // 更新志愿者总安排次数
            pdo_update('ims_yk_volunteers_volunteers',['total_assigned'=> $v['total_assigned']+1,'last_assigned'=>$date],['id'=>$v['id']]);
        }

        // 超出人数顺延到下一周（简单示例，保留剩余）
        $remain = count($candidates) - $max_num;
        if($remain>0){
            // 可以递归或保存到队列，下周再安排
            // 示例仅打印
            // error_log("{$remain} 志愿者超额，需下周安排: ".implode(',',array_column(array_slice($candidates,$max_num),'id')));
        }
    }

    return true;
}
