<?php
/**
 * 志愿时段配置管理
 * 文件：inc/web/slot.inc.php
 */

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

$op = !empty($_GPC['op']) ? $_GPC['op'] : 'display';

// 表名
$table = 'ims_yk_volunteers_slot_templates';

if ($op == 'display') {
    // === 列表页 ===
    $pindex = max(1, intval($_GPC['page']));
    $psize = 15;

    $condition = ' WHERE uniacid = :uniacid';
    $params = [':uniacid' => $_W['uniacid']];

    $list = pdo_fetchall("SELECT * FROM {$table} {$condition} ORDER BY ID ASC, slot_code ASC LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM {$table} {$condition}", $params);
    $pager = pagination($total, $pindex, $psize);

    include $this->template('slot_list');
    exit;
}

elseif ($op == 'add' || $op == 'edit') {
    $id = intval($_GPC['id']);
    if ($id > 0) {
        $item = pdo_get($table, ['id' => $id, 'uniacid' => $_W['uniacid']]);
    }

    if ($_W['ispost']) {
        $data = [
            'uniacid' => $_W['uniacid'],
            'uid' => $_W['uid'],
            'weekday' => intval($_GPC['weekday']),
            'slot_code' => trim($_GPC['slot_code']),
            'display_name' => trim($_GPC['display_name']),
            'required_min' => intval($_GPC['required_min']),
            'required_max' => intval($_GPC['required_max']),
        ];    

        if (empty($data['weekday']) || empty($data['slot_code']) || empty($data['display_name'])) {
            message('请填写完整信息！', referer(), 'error');
        }

        if ($id > 0) {
            pdo_update($table, $data, ['id' => $id]);
            message('更新成功！', $this->createWebUrl('slot', ['op' => 'display']), 'success');
        } else {
            pdo_insert($table, $data);
            message('添加成功！', $this->createWebUrl('slot', ['op' => 'display']), 'success');
        }
    }

    include $this->template('slot_edit');
    exit;
}

elseif ($op == 'delete') {
    $id = intval($_GPC['id']);
    $item = pdo_get($table, ['id' => $id, 'uniacid' => $_W['uniacid']]);
    if (empty($item)) {
        message('记录不存在或已删除！', referer(), 'error');
    }
    pdo_delete($table, ['id' => $id, 'uniacid' => $_W['uniacid']]);
    message('删除成功！', referer(), 'success');
}
