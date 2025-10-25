<?php
defined('IN_IA') or exit('Access Denied');

global $_W, $_GPC;
$weid = $_W['uniacid'];
$openid = $_W['openid'];

// 如果没有openid，提示需从公众号访问
if (empty($openid)) {
    message('请通过微信公众号访问此页面以完成绑定。');
}

// ✅ 提交绑定请求
if ($_W['ispost']) {
    $child_name  = trim($_GPC['child_name']);
    $child_class = trim($_GPC['child_class']);

    if (empty($child_name) || empty($child_class)) {
        exit(json_encode(['status' => 0, 'msg' => '请填写完整信息']));
    }

    // 查找是否存在对应的家长记录
    $volunteer = pdo_fetch(
        "SELECT * FROM " . tablename('yk_volunteers_volunteers') . " 
         WHERE uniacid = :uniacid 
           AND name = :child_name 
           AND child_class = :child_class 
         LIMIT 1",
        [
            ':uniacid' => $weid,
            ':child_name' => $child_name,
            ':child_class' => $child_class
        ]
    );

    if (!$volunteer) {
        exit(json_encode(['status' => 0, 'msg' => '未找到该学生信息，请确认输入是否正确']));
    }

    // ✅ 更新微信 openid
    pdo_update(
        'yk_volunteers_volunteers',
        ['openid' => $openid],
        ['id' => $volunteer['id']]
    );

    exit(json_encode(['status' => 1, 'msg' => '绑定成功']));
}

// 页面页脚标题
$_W['page']['title'] = '滨江初中志愿者家长';
$_W['uniaccount']['name'] = '';
$_W['page']['footer'] = '';

// ✅ 非提交时，加载表单页面
include $this->template('bind_wechat');
