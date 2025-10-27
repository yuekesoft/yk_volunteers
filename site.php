<?php
/**
 * yk_volunteers模块微站定义
 *
 * @author yuekesoft
 * @url
 */
defined('IN_IA') or exit('Access Denied');

class Yk_volunteersModuleSite extends WeModuleSite {

    // 前台手机端页面
	public function doMobileIndex() {
		//这个操作被定义用来呈现 功能封面
	}	


// 移动端：提交请假
    public function doMobileLeave() {
        global $_W, $_GPC;
 //       $uid = $_W['member']['uid'] ?: $_W['openid']; // 根据你系统的用户体系调整
        $id = intval($_GPC['schedule_id']);
        $reason = trim($_GPC['reason']);

        if ($_W['ispost']) {
            // 校验归属
            $schedule = pdo_get('yk_volunteers_assignments', ['id'=>$id,'uniacid' => $_W['uniacid']]);
            if (!$schedule) {
                //iajax(-1, '记录不存在');
				exit(json_encode(['status' => -1, 'message' => '记录不存在']));
            }

            // 取家长信息
            $volunteer = pdo_get('yk_volunteers_volunteers', ['id'=>$schedule['volunteer_id'],'uniacid' => $_W['uniacid']]);
            if (!$volunteer) {
                //iajax(-1, '记录不存在');
				exit(json_encode(['status' => -1, 'message' => '家长记录不存在']));
            }
            // if ($schedule['volunteer_id'] != $uid) {
            //     //iajax(-1, '你没有权限请假该排班');
			// 	exit(json_encode(['status' => -1, 'message' => '你没有权限请假该排班']));
            // }
            if ($schedule['status'] != 'scheduled') {
                //iajax(-1, '该排班当前不可请假');
				exit(json_encode(['status' => -1, 'message' => '该排班当前不可请假']));
            }

            pdo_update('yk_volunteers_assignments', [
                'status' => 'leave',
                'leave_reason' => $reason,
                'update_time' => date('Y-m-d H:i:s')
            ], ['id'=>$id, 'uniacid' => $_W['uniacid']]);

            // ========================
            // 可选：发送通知给管理员或班级群
            // ========================

            // 载入模板消息类
            load()->classs('weixin.account');
            $acc = WeAccount::create($_W['acid']);

            // 取系统设置中的模板消息ID
            $sysset_tmpl = pdo_get('yk_volunteers_settings', [
                'uniacid' => $_W['uniacid'],
                'key' => 'tmplmsg_id'
            ]);
            $tmplmsg_id = trim($sysset_tmpl['value']);

            // 取管理员 openid
            $sysset_admin = pdo_get('yk_volunteers_settings', [
                'uniacid' => $_W['uniacid'],
                'key' => 'admin_openid'
            ]);
            $admin_openid = trim($sysset_admin['value']);          

            // 如果多个管理员，用逗号或换行分隔
            $admin_openids = preg_split('/[,，\r\n]+/', $admin_openid, -1, PREG_SPLIT_NO_EMPTY);

            // ========== 模板消息内容 ==========
            $work_order_name = $reason . '请假'; // 工单名称
            $initiator = $volunteer['name'] . '(' . $volunteer['child_class'] . ')'; // 发起人

            // 时段文字
            if ($schedule['slot_code'] == 'morning') {
                $slot_text = '早上';
            } elseif ($schedule['slot_code'] == 'afternoon') {
                $slot_text = '傍晚';
            } elseif ($schedule['slot_code'] == 'evening') {
                $slot_text = '晚上';
            } else {
                $slot_text = '';
            }

            $project_name = $schedule['date'] . ' ' . $slot_text . ' 值日'; // 项目名称
            $submit_time = date('Y-m-d H:i:s'); // 提交时间

            $send_data = [
                'thing8'  => ['value' => $work_order_name],
                'thing10' => ['value' => $initiator],
                'thing31' => ['value' => $project_name],
                'time67'  => ['value' => $submit_time],
            ];

            // ========== 发送模板消息 ==========
            if (!empty($tmplmsg_id) && !empty($admin_openids)) {
                foreach ($admin_openids as $openid) {
                    $openid = trim($openid);
                    if (empty($openid)) continue;

                    $res = $acc->sendTplNotice($openid, $tmplmsg_id, $send_data, '', '#173177');
                }
            } else {
                // 若系统未配置模板ID或管理员openid，可记录提示日志
                load()->func('logging');
                logging_run('请假模板消息未发送：缺少 tmplmsg_id 或 admin_openid 设置'.$tmplmsg_id, 'warning', 'leave_notice');
            }                

            //iajax(0, '请假成功');
			exit(json_encode(['status' => 0, 'message' => '请假成功']));
        }

        // include $this->template('mobile/leave');
    }

    // 移动端：获取待替班列表
    public function doMobileAvailableSubs() {
        global $_W, $_GPC;
        $page = max(1, intval($_GPC['page']));
        $psize = 20;
        $start = ($page-1)*$psize;

		$conds = " WHERE a.status='leave' ";
		$sql = "SELECT a.*, v.name AS volunteer_name,v.child_class 
				FROM " . tablename('yk_volunteers_assignments') . " AS a
				LEFT JOIN " . tablename('yk_volunteers_volunteers') . " AS v
				ON a.volunteer_id = v.id
				" . $conds . "
				ORDER BY a.date ASC
				LIMIT {$start}, {$psize}";
		$list = pdo_fetchall($sql);
		

		// 页面页脚标题
		$_W['page']['title'] = '滨江初中志愿者值班表';
		$_W['uniaccount']['name'] = '';
		$_W['page']['footer'] = '';

        include $this->template('available_subs');
    }

    // 移动端：执行替班
    public function doMobileReplace() {
        global $_W, $_GPC;
    //    $uid = $_W['member']['uid'] ?: $_W['openid'];
        $id = intval($_GPC['schedule_id']);

        if ($_W['ispost']) {
            $schedule = pdo_get('yk_volunteers_assignments', ['id'=>$id]);
			$volunteer = pdo_get('yk_volunteers_volunteers', ['openid'=>$_W['openid']]);
            if (!$volunteer) {
                //iajax(-1, '您未绑定微信');
				exit(json_encode(['status' => -1, 'message' => '您未绑定微信']));
            }		
            if (!$schedule) {
                //iajax(-1, '记录不存在');
				exit(json_encode(['status' => -1, 'message' => '替班记录不存在']));
            }
            if ($schedule['volunteer_id'] == $volunteer['id']) {
                //iajax(-1, '不能替自己的班');
				exit(json_encode(['status' => -1, 'message' => '不能替自己的班']));
            }
            if ($schedule['status'] != 'leave' || !empty($schedule['replaced_by'])) {
                //iajax(-1, '该排班已被替或不可替');
				exit(json_encode(['status' => -1, 'message' => '该排班已被替或不可替']));
            }

            // 冲突检查：替班家长在同一日期时段是否已有值班
            $conflict = pdo_get('yk_volunteers_assignments', [
                'volunteer_id' => $volunteer['id'],
                'date' => $schedule['date'],
                'slot_code' => $schedule['slot_code'],
                'status !=' => 'cancel' // 示例，按需调整
            ]);
            if ($conflict) {
                //iajax(-1, '您在相同时段已有排班，无法替班');
				exit(json_encode(['status' => -1, 'message' => '您在相同时段已有排班，无法替班']));
            }

            // 原子更新：使用 where status='leave' AND replaced_by IS NULL 防止并发
            $res = pdo_update('yk_volunteers_assignments', [
                'replaced_by' => $schedule['volunteer_id'],
				'volunteer_id'=> $volunteer['id'],
                'status' => 'replaced',
                'update_time' => date('Y-m-d H:i:s')
            ], ['id'=>$id, 'status'=>'leave']);

            if ($res === false) {
                //iajax(-1, '替班失败，请重试');
				exit(json_encode(['status' => -1, 'message' => '替班失败，请重试']));
            }

            // 写替班历史
            pdo_insert('yk_volunteers_replacements', [
                'schedule_id' => $id,
                'from_volunteer' => $schedule['volunteer_id'],
                'to_volunteer' => $volunteer['id'],
				'reason'=> $schedule['leave_reason'],
                'create_time' => date('Y-m-d H:i:s')
            ]);	

            //iajax(0, '替班成功');
			exit(json_encode(['status' => 0, 'message' => '替班成功']));
	
        }

        include $this->template('replace');
    }

	

}