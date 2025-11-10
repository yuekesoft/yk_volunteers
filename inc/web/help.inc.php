<?php

global $_W, $_GPC;

// 页面页脚标题
$_W['page']['title'] = '志愿者家长排班';
$_W['uniaccount']['name'] = '';
$_W['page']['footer'] = '';

// ✅ 非提交时，加载表单页面
include $this->template('help');