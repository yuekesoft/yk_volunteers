<?php
/**
 * 自建 UEditor 动态生成接口
 * 用于替代微擎 utility/editor 的 open_basedir 报错问题
 * 作者：ChatGPT（为蔡炜定制）
 */
defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

// -------------------------------
// 参数校验
// -------------------------------
$id = trim($_GPC['id']);
$value = htmlspecialchars_decode($_GPC['value'] ?? '');

if (empty($id)) {
    exit('缺少参数 id');
}

// -------------------------------
// 安全过滤（防止非法ID注入）
// -------------------------------
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
    exit('非法编辑器 ID');
}

// -------------------------------
// 输出完整的 UEditor 模板
// -------------------------------
ob_clean(); // 清空之前的输出缓冲，避免多余空格或警告
echo tpl_ueditor($id, $value);
exit;
