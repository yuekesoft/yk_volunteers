<?php
    defined('IN_IA') or exit('Access Denied');
    global $_W, $_GPC;
    
    // 接收参数
    $id = $_GPC['id'];
    $value = $_GPC['value'];
    
    // 返回 tpl_form_field_image 渲染后的 HTML
    echo tpl_form_field_image($id, $value);
    exit;
