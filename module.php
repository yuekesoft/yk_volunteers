<?php
/**
 * yk_volunteers模块定义
 *
 * @author Mob446221402609
 * @url
 */
defined('IN_IA') or exit('Access Denied');

class Yk_volunteersModule extends WeModule {


	public function welcomeDisplay($menus = array()) {
		//这里来展示DIY管理界面
		include $this->template('welcome');
	}
}