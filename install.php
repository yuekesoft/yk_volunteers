<?php
/**
 * 模块安装脚本
 * 模块标识：yk_volunteens
 * 文件路径：/addons/yk_volunteens/install.php
 */

defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

// =========================================================
// 构建建表 SQL 语句
// =========================================================

// 表1：客户表
$sql = "
-- 家长/志愿者表
CREATE TABLE ims_yk_volunteers_volunteers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uniacid int(11) NOT NULL DEFAULT '0' COMMENT '公众号/小程序ID',
  uid INT(10) NOT NULL DEFAULT 0 COMMENT '创建者UID', 
  openid VARCHAR(128),         
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(30),
  child_class VARCHAR(100),
  prefer_slots VARCHAR(255),    
  can_substitute TINYINT(1) DEFAULT 1,
  max_per_week INT DEFAULT 1,                
  max_substitute_per_week INT DEFAULT 1,
  last_assigned DATE,                   
  total_assigned INT DEFAULT 0,         
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 时段模板表（配置每周各日各时段需求）
CREATE TABLE ims_yk_volunteers_slot_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uniacid int(11) NOT NULL DEFAULT '0' COMMENT '公众号/小程序ID',
  uid INT(10) NOT NULL DEFAULT 0 COMMENT '创建者UID', 
  weekday INT NOT NULL,         
  slot_code VARCHAR(50),       
  display_name VARCHAR(50),    
  required_min INT DEFAULT 0,
  required_max INT DEFAULT 999,
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 每周排班表（具体到日期）
CREATE TABLE ims_yk_volunteers_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uniacid int(11) NOT NULL DEFAULT '0' COMMENT '公众号/小程序ID',
  uid INT(10) NOT NULL DEFAULT 0 COMMENT '创建者UID', 
  date DATE NOT NULL,
  weekday INT NOT NULL,
  slot_code VARCHAR(50) NOT NULL,
  volunteer_id INT NOT NULL,
  replaced_by int DEFAULT NULL COMMENT '替班家长ID',
  leave_reason varchar(255) DEFAULT NULL;
  role ENUM('primary','substitute') DEFAULT 'primary',
  status ENUM('scheduled','cancelled','completed','leave','replaced') DEFAULT 'scheduled',
  checked_in TINYINT(1) DEFAULT 0,
  checkin_time DATETIME NULL,
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 候补池/等待表
CREATE TABLE ims_yk_volunteers_waitlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号/小程序ID',
  `uid` INT(10) NOT NULL DEFAULT 0 COMMENT '创建者UID', 
  date DATE,
  slot_code VARCHAR(50),
  volunteer_id INT,
  priority INT DEFAULT 100,     -- 优先级，数字小优先
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 请假/替班申请
CREATE TABLE ims_yk_volunteers_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号/小程序ID',
  `uid` INT(10) NOT NULL DEFAULT 0 COMMENT '创建者UID', 
  volunteer_id INT,
  type ENUM('leave','swap','substitute_request'),
  date DATE,
  slot_code VARCHAR(50),
  status ENUM('pending','approved','rejected'),
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 系统设置表
CREATE TABLE `ims_yk_volunteers_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 替班历史（可选，用于审计）
CREATE TABLE `ims_yk_volunteers_replacements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` int NOT NULL,
  `from_volunteer` int NOT NULL,
  `to_volunteer` int NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
";

// =========================================================
// 执行 SQL
// =========================================================
pdo_query($sql);

