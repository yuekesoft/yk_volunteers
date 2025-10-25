<?php
// 家长列表
defined('IN_IA') or exit('Access Denied');

global $_W, $_GPC;

$uniacid = $_W['uniacid'];
$op = !empty($_GPC['op']) ? $_GPC['op'] : 'display';

$table = 'ims_yk_volunteers_volunteers';

if ($op == 'display') {
    $slot_setting = pdo_get('yk_volunteers_settings', ['uniacid' => $uniacid, 'key' => 'slot_labels']);
    $slot_labels = $slot_setting ? json_decode($slot_setting['value'], true) : [];
    
    // 搜索 + 分页
    $pindex = max(1, intval($_GPC['page']));
    $psize = 20;
    $condition = " WHERE uniacid = :uniacid ";
    $params = [':uniacid' => $uniacid];

    if (!empty($_GPC['keyword'])) {
        $condition .= " AND (name LIKE :keyword OR phone LIKE :keyword OR prefer_slots LIKE :keyword) ";
        $params[':keyword'] = "%{$_GPC['keyword']}%";
    }

    $total = pdo_fetchcolumn("SELECT COUNT(*) FROM {$table} {$condition}", $params);
    $list = pdo_fetchall("SELECT * FROM {$table} {$condition} ORDER BY id DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
    $pager = pagination($total, $pindex, $psize);

    include $this->template('volunteers_list');

} elseif ($op == 'add' || $op == 'edit') {
    $id = intval($_GPC['id']);
    if ($id > 0) {
        $item = pdo_get('yk_volunteers_volunteers', ['id' => $id, 'uniacid' => $uniacid]);
    }

    if (checksubmit('submit')) {
        $data = [
            'uniacid' => $uniacid,
            'uid' => $_W['uid'],
            'openid' => trim($_GPC['openid']),
            'name' => trim($_GPC['name']),
            'phone' => trim($_GPC['phone']),
            'child_class' => trim($_GPC['child_class']),
            'openid' => trim($_GPC['openid']),
            'prefer_slots' => trim($_GPC['prefer_slots']),
            'can_substitute' => intval($_GPC['can_substitute']),
            'max_per_week' => intval($_GPC['max_per_week']),
            'max_substitute_per_week' => intval($_GPC['max_substitute_per_week']),
            'update_time' => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            pdo_update('yk_volunteers_volunteers', $data, ['id' => $id]);
            itoast('更新成功', $this->createWebUrl('volunteers'), 'success');
        } else {
            $data['create_time'] = date('Y-m-d H:i:s');
            pdo_insert('yk_volunteers_volunteers', $data);
            itoast('添加成功', $this->createWebUrl('volunteers'), 'success');
        }
    }

    include $this->template('volunteers_edit');

} elseif ($op == 'delete') {
    $id = intval($_GPC['id']);
    pdo_delete('yk_volunteers_volunteers', ['id' => $id, 'uniacid' => $uniacid]);
    itoast('删除成功', $this->createWebUrl('volunteers'), 'success');
} 

// ===============================
// 导出Excel
// ===============================
elseif ($op == 'export') {
    //load()->library('phpexcel');

    require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel.php';
    require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel/Writer/Excel5.php';
    $objPHPExcel = new PHPExcel();
    $sheet = $objPHPExcel->getActiveSheet();
    $sheet->setTitle('志愿者家长');

    // ========== 1. 设置表头 ==========
    $headers = ['ID', '姓名', '手机号', '孩子班级', '志愿偏好', '可替补', '每周最多次数', '替补次数', '最近安排', '总安排次数'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);       // 表头加粗
        $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($col . '1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($col)->setAutoSize(true);          // 自动列宽
        $col++;
    }

    // ========== 2. 获取数据 ==========
    $list = pdo_fetchall("SELECT * FROM {$table} WHERE uniacid=:uniacid ORDER BY id ASC", [':uniacid' => $uniacid]);

    // ========== 3. 填充数据 ==========
    $row = 2;
    foreach ($list as $item) {
        $sheet->setCellValueExplicit('A' . $row, $item['id'], PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row, $item['name']);
        $sheet->setCellValue('C' . $row, $item['phone']);
        $sheet->setCellValue('D' . $row, $item['child_class']);
        // 处理志愿偏好字段（兼容 &quot; 及 JSON 格式）
        $prefer = html_entity_decode($item['prefer_slots'], ENT_QUOTES);
        $prefer = trim($prefer);

        if (substr($prefer, 0, 1) == '[') {
            // 如果是 JSON 数组，则解析为文字
            $slots = json_decode($prefer, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($slots)) {
                $prefer = implode(',', $slots);
            }
        }

        $sheet->setCellValue('E' . $row, $prefer);

        $sheet->setCellValue('F' . $row, $item['can_substitute'] ? '是' : '否');
        $sheet->setCellValue('G' . $row, $item['max_per_week']);
        $sheet->setCellValue('H' . $row, $item['max_substitute_per_week']);
        $sheet->setCellValue('I' . $row, $item['last_assigned']);
        $sheet->setCellValue('J' . $row, $item['total_assigned']);
        $row++;
    }

    // ========== 4. 设置整体样式 ==========
    $lastRow = $row - 1;
    $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
    $sheet->getStyle("A1:J{$lastRow}")->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

    // 行高、颜色
    $sheet->getStyle('A1:J1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $sheet->getStyle('A1:J1')->getFill()->getStartColor()->setARGB('FFEFEFEF'); // 灰色背景
    $sheet->getRowDimension(1)->setRowHeight(22);

    // 设置字体
    $sheet->getDefaultStyle()->getFont()->setName('微软雅黑')->setSize(10);

    // 自动列宽
    foreach (range('A', 'J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // ========== 5. 输出下载 ==========
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="志愿者家长名单.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $writer->save('php://output');
    exit;
}

// ===============================
// 导入Excel
// ===============================
elseif ($op == 'import') {
    if (!empty($_FILES['file']['tmp_name'])) {
        require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel.php';
        require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel/Writer/Excel5.php';
        $filePath = $_FILES['file']['tmp_name'];
        $objPHPExcel = PHPExcel_IOFactory::load($filePath);
        $sheet = $objPHPExcel->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $count = 0;
        for ($row = 2; $row <= $highestRow; $row++) {
            $name = trim($sheet->getCell("A".$row)->getValue());
            $phone = trim($sheet->getCell("B".$row)->getValue());
            $child_class = trim($sheet->getCell("C".$row)->getValue());
            $prefer_slots = trim($sheet->getCell("D".$row)->getValue());
            if (!empty($prefer_slots)) {
                // JSON格式 → 解析成数组 → 用逗号拼接
                if (preg_match('/^\[.*\]$/', $prefer_slots)) {
                    $arr = json_decode($prefer_slots, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($arr)) {
                        $prefer_slots = implode(',', $arr);
                    }
                }
                // 替换中文逗号、空格
                $prefer_slots = str_replace(['，', ' '], [',', ''], $prefer_slots);
            }
            $max_per_week = intval($sheet->getCell("E".$row)->getValue());
            $max_substitute_per_week = intval($sheet->getCell("F".$row)->getValue());
            $can_substitute = intval($sheet->getCell("G".$row)->getValue());

            if (empty($name)) continue;

            $data = [
                'uniacid' => $uniacid,
                'uid' => $_W['uid'],
                'name' => $name,
                'phone' => $phone,
                'child_class' => $child_class,
                'prefer_slots' => $prefer_slots,
                'max_per_week' => $max_per_week,
                'max_substitute_per_week' => $max_substitute_per_week,
                'can_substitute' => $can_substitute,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            pdo_insert('yk_volunteers_volunteers', $data);
            $count++;
        }

        itoast("成功导入 {$count} 条记录", $this->createWebUrl('volunteers'), 'success');
    } else {
        itoast('请上传Excel文件', referer(), 'error');
    }
}

// ===============================
// 下载导入模板
// ===============================
elseif ($op == 'template') {
    require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel.php';
    require_once IA_ROOT .'/framework/library/phpexcel/PHPExcel/Writer/Excel5.php';
    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', '姓名')
        ->setCellValue('B1', '手机号')
        ->setCellValue('C1', '孩子班级')
        ->setCellValue('D1', '志愿偏好(如 Mon_morning,Tue_evening)')
        ->setCellValue('E1', '每周最多次数')
        ->setCellValue('F1', '替补次数')
        ->setCellValue('G1', '是否可替补(1=是,0=否)');

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="volunteers_template.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $writer->save('php://output');
    exit;
}
