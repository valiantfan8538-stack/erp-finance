-- ====================================================
-- 金蝶KIS财务模块迁移 - 数据库初始化脚本
-- 基于: 金蝶KIS专业版V16.0 (PT162603)
-- 数据库: MySQL 8.0
-- 字符集: utf8mb4
-- ====================================================

CREATE DATABASE IF NOT EXISTS erp_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_finance;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. 系统管理模块
-- ============================================================

-- 1.1 用户表
CREATE TABLE `sys_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` VARCHAR(50) NOT NULL COMMENT '登录账号',
  `password` VARCHAR(255) NOT NULL COMMENT '密码(bcrypt)',
  `real_name` VARCHAR(50) NOT NULL COMMENT '真实姓名',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
  `dept_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '部门ID',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
  `is_admin` TINYINT NOT NULL DEFAULT 0 COMMENT '是否超级管理员',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`, `deleted_at`),
  KEY `idx_dept` (`dept_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统用户表';

-- 默认管理员 (密码: admin123, bcrypt hash)
INSERT INTO `sys_user` (`username`, `password`, `real_name`, `is_admin`, `status`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 1, 1);

-- 1.2 角色表
CREATE TABLE `sys_role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
  `code` VARCHAR(50) NOT NULL COMMENT '角色编码',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '角色描述',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';

INSERT INTO `sys_role` (`name`, `code`, `description`, `sort`) VALUES
('系统管理员', 'admin', '拥有所有权限', 1),
('财务主管', 'finance_manager', '可审核凭证、查看所有报表', 2),
('会计', 'accountant', '可录入凭证、查看账簿', 3),
('出纳', 'cashier', '可管理现金银行日记账', 4),
('查看者', 'viewer', '仅查看权限', 5);

-- 1.3 权限菜单表
CREATE TABLE `sys_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级ID',
  `name` VARCHAR(50) NOT NULL COMMENT '权限名称',
  `code` VARCHAR(100) NOT NULL COMMENT '权限标识(如:finance:voucher:add)',
  `type` TINYINT NOT NULL DEFAULT 1 COMMENT '类型:1菜单 2按钮 3API',
  `path` VARCHAR(200) DEFAULT NULL COMMENT '前端路由路径',
  `icon` VARCHAR(50) DEFAULT NULL COMMENT '菜单图标',
  `component` VARCHAR(200) DEFAULT NULL COMMENT '前端组件路径',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `hidden` TINYINT NOT NULL DEFAULT 0 COMMENT '是否隐藏',
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限菜单表';

-- 一级菜单
INSERT INTO `sys_permission` (`id`, `parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(1, 0, '财务管理', 'finance', 1, '/finance', 'money', '', 1),
(12, 0, '应收应付', 'arap', 1, '/arap', 'exchange', '', 2),
(16, 0, '固定资产', 'asset', 1, '/asset', 'wallet', '', 3),
(21, 0, '系统设置', 'system', 1, '/system', 'setting', '', 4);

-- 财务管理子菜单
INSERT INTO `sys_permission` (`parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(1, '会计科目', 'finance:subject', 1, '/finance/subjects', '', 'subjects/Index', 1),
(1, '记账凭证', 'finance:voucher', 1, '/finance/vouchers', '', 'vouchers/Index', 2),
(1, '凭证审核', 'finance:voucher:audit', 1, '/finance/voucher-audit', '', 'vouchers/Audit', 3),
(1, '总分类账', 'finance:ledger:general', 1, '/finance/ledger/general', '', 'ledger/GeneralLedger', 4),
(1, '明细账', 'finance:ledger:detail', 1, '/finance/ledger/detail', '', 'ledger/DetailLedger', 5),
(1, '现金日记账', 'finance:ledger:cash', 1, '/finance/ledger/cash', '', 'ledger/CashLedger', 6),
(1, '银行日记账', 'finance:ledger:bank', 1, '/finance/ledger/bank', '', 'ledger/BankLedger', 7),
(1, '科目余额表', 'finance:report:balance', 1, '/finance/report/balance', '', 'reports/BalanceSheet', 8),
(1, '利润表', 'finance:report:income', 1, '/finance/report/income', '', 'reports/IncomeStatement', 9),
(1, '现金流量表', 'finance:report:cashflow', 1, '/finance/report/cashflow', '', 'reports/CashFlowStatement', 10);

-- 应收应付子菜单
INSERT INTO `sys_permission` (`parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(12, '应收账款', 'arap:receivable', 1, '/arap/receivable', '', 'arap/Receivable', 1),
(12, '应付账款', 'arap:payable', 1, '/arap/payable', '', 'arap/Payable', 2),
(12, '往来核销', 'arap:verify', 1, '/arap/verify', '', 'arap/Verify', 3);

-- 固定资产子菜单
INSERT INTO `sys_permission` (`parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(16, '资产卡片', 'asset:card', 1, '/asset/cards', '', 'asset/Cards', 1),
(16, '资产变动', 'asset:change', 1, '/asset/changes', '', 'asset/Changes', 2),
(16, '折旧计提', 'asset:depreciation', 1, '/asset/depreciation', '', 'asset/Depreciation', 3),
(16, '资产报表', 'asset:report', 1, '/asset/report', '', 'asset/Report', 4);

-- 系统设置子菜单
INSERT INTO `sys_permission` (`parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(21, '账套管理', 'system:accountBook', 1, '/system/account-book', '', 'settings/AccountBook', 1),
(21, '用户管理', 'system:user', 1, '/system/users', '', 'settings/Users', 2),
(21, '角色管理', 'system:role', 1, '/system/roles', '', 'settings/Roles', 3),
(21, '凭证字设置', 'system:voucherType', 1, '/system/voucher-type', '', 'settings/VoucherType', 4),
(21, '期间管理', 'system:period', 1, '/system/periods', '', 'settings/Periods', 5);

-- 按钮级权限
INSERT INTO `sys_permission` (`parent_id`, `name`, `code`, `type`, `sort`) VALUES
(2, '新增科目', 'finance:subject:add', 2, 1),
(2, '编辑科目', 'finance:subject:edit', 2, 2),
(2, '删除科目', 'finance:subject:delete', 2, 3),
(2, '导入科目', 'finance:subject:import', 2, 4),
(3, '新增凭证', 'finance:voucher:add', 2, 1),
(3, '编辑凭证', 'finance:voucher:edit', 2, 2),
(3, '删除凭证', 'finance:voucher:delete', 2, 3),
(3, '查看凭证', 'finance:voucher:view', 2, 4),
(4, '审核凭证', 'finance:voucher:audit:approve', 2, 1),
(4, '反审核', 'finance:voucher:unaudit', 2, 2),
(9, '导出余额表', 'finance:report:balance:export', 2, 1),
(13, '新增应收', 'arap:receivable:add', 2, 1),
(13, '核销应收', 'arap:receivable:verify', 2, 2),
(17, '新增资产', 'asset:card:add', 2, 1),
(17, '处置资产', 'asset:card:dispose', 2, 2),
(19, '计提折旧', 'asset:depreciation:run', 2, 1);

-- 1.4 角色权限关联表
CREATE TABLE `sys_role_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`role_id`, `permission_id`),
  KEY `idx_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- admin拥有所有权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 1, id FROM `sys_permission`;

-- 财务主管拥有财务全部权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 2, id FROM `sys_permission` WHERE id <= 11 OR id = 19 OR id = 20;

-- 会计拥有凭证和账簿
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 3, id FROM `sys_permission` WHERE id IN (2,3,4,5,6,7,8,9,10,11);

-- 出纳拥有日记账和凭证查看
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 4, id FROM `sys_permission` WHERE id IN (3,7,8);

-- 1.5 用户角色关联表
CREATE TABLE `sys_user_role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';

-- 1.6 账套表
CREATE TABLE `sys_account_book` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '账套ID',
  `name` VARCHAR(100) NOT NULL COMMENT '账套名称',
  `company_name` VARCHAR(200) NOT NULL COMMENT '公司全称',
  `short_name` VARCHAR(50) DEFAULT NULL COMMENT '公司简称',
  `credit_standard` VARCHAR(20) NOT NULL DEFAULT 'enterprise' COMMENT '会计准则:enterprise企业/small小微企业',
  `start_year` SMALLINT NOT NULL COMMENT '启用年度',
  `start_period` TINYINT NOT NULL COMMENT '启用期间(1-12)',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'CNY' COMMENT '本位币',
  `book_code` VARCHAR(20) NOT NULL COMMENT '账套编码',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1启用 0停用',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_code` (`book_code`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账套表';

-- 1.7 会计期间表
CREATE TABLE `sys_accounting_period` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `year` SMALLINT NOT NULL COMMENT '年度',
  `period` TINYINT NOT NULL COMMENT '期间(1-12,13为年结)',
  `start_date` DATE NOT NULL COMMENT '开始日期',
  `end_date` DATE NOT NULL COMMENT '结束日期',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0未结账 1已结账 2已年结',
  `closed_at` DATETIME DEFAULT NULL COMMENT '结账时间',
  `closed_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '结账人',
  `is_current` TINYINT NOT NULL DEFAULT 0 COMMENT '是否当前期间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_year_period` (`book_id`, `year`, `period`),
  KEY `idx_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会计期间表';

-- ============================================================
-- 2. 总账模块
-- ============================================================

-- 2.1 会计科目表
CREATE TABLE `finance_subject` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `code` VARCHAR(20) NOT NULL COMMENT '科目编码(如:1001)',
  `name` VARCHAR(100) NOT NULL COMMENT '科目名称',
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级科目ID',
  `category` VARCHAR(20) NOT NULL COMMENT '科目类别:asset资产/liability负债/equity权益/cost成本/profit损益',
  `direction` VARCHAR(10) NOT NULL DEFAULT 'debit' COMMENT '余额方向:debit借方/credit贷方',
  `level` TINYINT NOT NULL DEFAULT 1 COMMENT '科目级次',
  `is_leaf` TINYINT NOT NULL DEFAULT 1 COMMENT '是否末级科目:1是 0否',
  `is_cash_account` TINYINT NOT NULL DEFAULT 0 COMMENT '是否现金类科目',
  `is_bank_account` TINYINT NOT NULL DEFAULT 0 COMMENT '是否银行类科目',
  `is_quantity_account` TINYINT NOT NULL DEFAULT 0 COMMENT '是否数量核算',
  `is_foreign_currency` TINYINT NOT NULL DEFAULT 0 COMMENT '是否外币核算',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '计量单位',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1启用 0停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_code` (`book_id`, `code`),
  KEY `idx_book` (`book_id`),
  KEY `idx_category` (`category`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会计科目表';

-- 2.2 凭证字表
CREATE TABLE `finance_voucher_type` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(10) NOT NULL COMMENT '凭证字(如:记/收/付/转)',
  `name` VARCHAR(50) NOT NULL COMMENT '名称',
  `sort` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_code` (`book_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='凭证字表';

-- 2.3 记账凭证主表
CREATE TABLE `finance_voucher` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `voucher_type_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证字ID',
  `voucher_no` INT NOT NULL COMMENT '凭证号',
  `year` SMALLINT NOT NULL COMMENT '年度',
  `period` TINYINT NOT NULL COMMENT '期间',
  `date` DATE NOT NULL COMMENT '凭证日期',
  `attachment_count` INT NOT NULL DEFAULT 0 COMMENT '附件张数',
  `prepared_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '制单人',
  `audit_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人',
  `audit_at` DATETIME DEFAULT NULL COMMENT '审核时间',
  `posted_by` BIGINT UNSIGNED DEFAULT NULL COMMENT '过账人',
  `posted_at` DATETIME DEFAULT NULL COMMENT '过账时间',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0草稿 1已审核 2已过账',
  `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
  `total_debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '借方合计',
  `total_credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '贷方合计',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_year_period_no` (`book_id`, `voucher_type_id`, `year`, `period`, `voucher_no`),
  KEY `idx_book` (`book_id`),
  KEY `idx_year_period` (`book_id`, `year`, `period`),
  KEY `idx_date` (`date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='记账凭证主表';

-- 2.4 凭证明细表
CREATE TABLE `finance_voucher_entry` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `voucher_id` BIGINT UNSIGNED NOT NULL COMMENT '凭证ID',
  `entry_no` INT NOT NULL COMMENT '分录号(1,2,3...)',
  `subject_id` BIGINT UNSIGNED NOT NULL COMMENT '科目ID',
  `subject_code` VARCHAR(20) NOT NULL COMMENT '科目编码(冗余)',
  `summary` VARCHAR(200) DEFAULT NULL COMMENT '摘要',
  `debit_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '借方金额',
  `credit_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '贷方金额',
  `currency` VARCHAR(10) DEFAULT 'CNY' COMMENT '币种',
  `exchange_rate` DECIMAL(18,6) DEFAULT 1.000000 COMMENT '汇率',
  `original_debit` DECIMAL(18,2) DEFAULT 0.00 COMMENT '原币借方',
  `original_credit` DECIMAL(18,2) DEFAULT 0.00 COMMENT '原币贷方',
  `quantity` DECIMAL(18,4) DEFAULT NULL COMMENT '数量',
  `unit_price` DECIMAL(18,4) DEFAULT NULL COMMENT '单价',
  `customer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '往来单位-客户',
  `supplier_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '往来单位-供应商',
  `department_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '部门',
  `employee_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '员工',
  `project_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '项目',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_voucher_entry` (`voucher_id`, `entry_no`),
  KEY `idx_subject` (`subject_id`),
  KEY `idx_subject_code` (`subject_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='凭证明细表';

-- 2.5 科目余额表
CREATE TABLE `finance_subject_balance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `subject_id` BIGINT UNSIGNED NOT NULL,
  `year` SMALLINT NOT NULL,
  `period` TINYINT NOT NULL,
  `initial_debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '期初借方余额',
  `initial_credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '期初贷方余额',
  `debit_occurrence` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '本期借方发生额',
  `credit_occurrence` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '本期贷方发生额',
  `final_debit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '期末借方余额',
  `final_credit` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '期末贷方余额',
  `quantity_initial` DECIMAL(18,4) DEFAULT NULL COMMENT '期初数量',
  `quantity_debit` DECIMAL(18,4) DEFAULT NULL COMMENT '本期收入数量',
  `quantity_credit` DECIMAL(18,4) DEFAULT NULL COMMENT '本期发出数量',
  `quantity_final` DECIMAL(18,4) DEFAULT NULL COMMENT '期末数量',
  `is_initial` TINYINT NOT NULL DEFAULT 0 COMMENT '是否期初余额(建账时录入)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_subject_year_period` (`book_id`, `subject_id`, `year`, `period`),
  KEY `idx_year_period` (`book_id`, `year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='科目余额表';

-- 2.6 现金流量项目表
CREATE TABLE `finance_cashflow_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL COMMENT '项目编号',
  `name` VARCHAR(100) NOT NULL COMMENT '项目名称',
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `category` VARCHAR(20) NOT NULL COMMENT '类别:operate经营/invest投资/finance筹资',
  `level` TINYINT NOT NULL DEFAULT 1,
  `is_leaf` TINYINT NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='现金流量项目表';

INSERT INTO `finance_cashflow_item` (`code`, `name`, `category`, `level`, `is_leaf`) VALUES
('A', '经营活动产生的现金流量', 'operate', 1, 0),
('A01', '销售商品、提供劳务收到的现金', 'operate', 2, 1),
('A02', '收到的税费返还', 'operate', 2, 1),
('A03', '收到其他与经营活动有关的现金', 'operate', 2, 1),
('A04', '经营活动现金流入小计', 'operate', 2, 1),
('A05', '购买商品、接受劳务支付的现金', 'operate', 2, 1),
('A06', '支付给职工以及为职工支付的现金', 'operate', 2, 1),
('A07', '支付的各项税费', 'operate', 2, 1),
('A08', '支付其他与经营活动有关的现金', 'operate', 2, 1),
('A09', '经营活动现金流出小计', 'operate', 2, 1),
('A10', '经营活动产生的现金流量净额', 'operate', 2, 1),
('B', '投资活动产生的现金流量', 'invest', 1, 0),
('B01', '收回投资收到的现金', 'invest', 2, 1),
('B02', '取得投资收益收到的现金', 'invest', 2, 1),
('B03', '处置固定资产收回的现金净额', 'invest', 2, 1),
('B04', '投资活动现金流入小计', 'invest', 2, 1),
('B05', '购建固定资产支付的现金', 'invest', 2, 1),
('B06', '投资支付的现金', 'invest', 2, 1),
('B07', '投资活动现金流出小计', 'invest', 2, 1),
('B08', '投资活动产生的现金流量净额', 'invest', 2, 1),
('C', '筹资活动产生的现金流量', 'finance', 1, 0),
('C01', '吸收投资收到的现金', 'finance', 2, 1),
('C02', '取得借款收到的现金', 'finance', 2, 1),
('C03', '筹资活动现金流入小计', 'finance', 2, 1),
('C04', '偿还债务支付的现金', 'finance', 2, 1),
('C05', '分配股利、利润或偿付利息支付的现金', 'finance', 2, 1),
('C06', '筹资活动现金流出小计', 'finance', 2, 1),
('C07', '筹资活动产生的现金流量净额', 'finance', 2, 1),
('D', '汇率变动对现金的影响', 'operate', 1, 1),
('E', '现金及现金等价物净增加额', 'operate', 1, 1);

-- ============================================================
-- 3. 应收应付模块
-- ============================================================

-- 3.1 往来单位表
CREATE TABLE `finance_partner` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL COMMENT '往来单位编码',
  `name` VARCHAR(200) NOT NULL COMMENT '往来单位名称',
  `short_name` VARCHAR(50) DEFAULT NULL COMMENT '简称',
  `type` VARCHAR(10) NOT NULL COMMENT '类型:customer客户/supplier供应商/both往来',
  `tax_no` VARCHAR(30) DEFAULT NULL COMMENT '纳税人识别号',
  `contact_person` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` VARCHAR(500) DEFAULT NULL COMMENT '地址',
  `bank_name` VARCHAR(200) DEFAULT NULL COMMENT '开户银行',
  `bank_account` VARCHAR(50) DEFAULT NULL COMMENT '银行账号',
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_code` (`book_id`, `code`),
  KEY `idx_book` (`book_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='往来单位表';

-- 3.2 应收单表
CREATE TABLE `finance_ar_receivable` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `bill_no` VARCHAR(30) NOT NULL COMMENT '单据编号',
  `partner_id` BIGINT UNSIGNED NOT NULL COMMENT '客户ID',
  `bill_date` DATE NOT NULL COMMENT '单据日期',
  `business_date` DATE DEFAULT NULL COMMENT '业务日期',
  `subject_id` BIGINT UNSIGNED NOT NULL COMMENT '对应科目',
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '应收金额',
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '税额',
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '价税合计',
  `received_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '已收金额',
  `unreceived_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '未收金额',
  `voucher_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联凭证ID',
  `due_date` DATE DEFAULT NULL COMMENT '到期日期',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0未核销 1部分核销 2已核销',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bill_no` (`book_id`, `bill_no`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_bill_date` (`bill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应收单表';

-- 3.3 应付单表
CREATE TABLE `finance_ap_payable` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `bill_no` VARCHAR(30) NOT NULL COMMENT '单据编号',
  `partner_id` BIGINT UNSIGNED NOT NULL COMMENT '供应商ID',
  `bill_date` DATE NOT NULL COMMENT '单据日期',
  `business_date` DATE DEFAULT NULL COMMENT '业务日期',
  `subject_id` BIGINT UNSIGNED NOT NULL COMMENT '对应科目',
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '应付金额',
  `tax_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '税额',
  `total_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '价税合计',
  `paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '已付金额',
  `unpaid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '未付金额',
  `voucher_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联凭证ID',
  `due_date` DATE DEFAULT NULL COMMENT '到期日期',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0未核销 1部分核销 2已核销',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bill_no` (`book_id`, `bill_no`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_bill_date` (`bill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应付单表';

-- 3.4 核销记录表
CREATE TABLE `finance_verify_record` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `partner_id` BIGINT UNSIGNED NOT NULL,
  `verify_date` DATE NOT NULL COMMENT '核销日期',
  `source_type` VARCHAR(20) NOT NULL COMMENT '来源类型:receivable应收/payable应付',
  `source_id` BIGINT UNSIGNED NOT NULL COMMENT '来源单据ID',
  `voucher_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联凭证ID',
  `amount` DECIMAL(18,2) NOT NULL COMMENT '核销金额',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_source` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='核销记录表';

-- ============================================================
-- 4. 固定资产模块
-- ============================================================

-- 4.1 资产类别表
CREATE TABLE `asset_category` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL COMMENT '类别编码',
  `name` VARCHAR(100) NOT NULL COMMENT '类别名称',
  `parent_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `level` TINYINT NOT NULL DEFAULT 1,
  `is_leaf` TINYINT NOT NULL DEFAULT 1,
  `depreciation_method` VARCHAR(20) NOT NULL DEFAULT 'straight_line' COMMENT '折旧方法:straight_line年限平均/double_decline双倍余额递减/sum_years年数总和',
  `useful_months` INT NOT NULL COMMENT '预计使用月数',
  `residual_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '残值率(%)',
  `subject_depreciation` BIGINT UNSIGNED DEFAULT NULL COMMENT '对应折旧科目',
  `sort` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_book_code` (`book_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产类别表';

INSERT INTO `asset_category` (`book_id`, `code`, `name`, `depreciation_method`, `useful_months`, `residual_rate`) VALUES
(1001, '01', '房屋及建筑物', 'straight_line', 240, 5.00),
(1001, '02', '机器设备', 'straight_line', 120, 5.00),
(1001, '03', '运输工具', 'straight_line', 48, 5.00),
(1001, '04', '电子及办公设备', 'straight_line', 36, 5.00),
(1001, '05', '其他设备', 'straight_line', 60, 5.00);

-- 4.2 资产卡片表
CREATE TABLE `asset_card` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `card_no` VARCHAR(30) NOT NULL COMMENT '卡片编号',
  `asset_name` VARCHAR(200) NOT NULL COMMENT '资产名称',
  `asset_code` VARCHAR(50) DEFAULT NULL COMMENT '资产编码',
  `category_id` BIGINT UNSIGNED NOT NULL COMMENT '资产类别ID',
  `specification` VARCHAR(200) DEFAULT NULL COMMENT '规格型号',
  `manufacturer` VARCHAR(200) DEFAULT NULL COMMENT '生产厂家',
  `quantity` DECIMAL(18,2) NOT NULL DEFAULT 1.00 COMMENT '数量',
  `unit` VARCHAR(10) DEFAULT '台' COMMENT '单位',
  `original_value` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '原值',
  `accumulated_depreciation` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '累计折旧(期初)',
  `net_value` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '净值',
  `residual_value` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '预计净残值',
  `residual_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00 COMMENT '残值率(%)',
  `depreciation_method` VARCHAR(20) NOT NULL DEFAULT 'straight_line' COMMENT '折旧方法',
  `useful_months` INT NOT NULL COMMENT '预计使用月数',
  `used_months` INT NOT NULL DEFAULT 0 COMMENT '已使用月数',
  `remaining_months` INT NOT NULL DEFAULT 0 COMMENT '剩余月数',
  `monthly_depreciation` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT '月折旧额',
  `purchase_date` DATE DEFAULT NULL COMMENT '购入日期',
  `use_date` DATE NOT NULL COMMENT '启用日期(开始折旧日期)',
  `last_depreciation_year` SMALLINT DEFAULT NULL COMMENT '上次计提折旧年度',
  `last_depreciation_period` TINYINT DEFAULT NULL COMMENT '上次计提折旧期间',
  `department_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '使用部门',
  `responsible_person` VARCHAR(50) DEFAULT NULL COMMENT '责任人',
  `location` VARCHAR(200) DEFAULT NULL COMMENT '存放地点',
  `supplier_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '供应商',
  `subject_original` BIGINT UNSIGNED DEFAULT NULL COMMENT '原值对应科目',
  `subject_depreciation` BIGINT UNSIGNED DEFAULT NULL COMMENT '折旧对应科目',
  `status` VARCHAR(20) NOT NULL DEFAULT 'in_use' COMMENT '状态:in_use在用/repair维修/idle闲置/dispose已处置',
  `dispose_date` DATE DEFAULT NULL COMMENT '处置日期',
  `dispose_method` VARCHAR(20) DEFAULT NULL COMMENT '处置方式:sale出售/scrap报废/donate捐赠',
  `dispose_income` DECIMAL(18,2) DEFAULT NULL COMMENT '处置收入',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_no` (`book_id`, `card_no`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_use_date` (`use_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产卡片表';

-- 4.3 折旧记录表
CREATE TABLE `asset_depreciation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `card_id` BIGINT UNSIGNED NOT NULL COMMENT '资产卡片ID',
  `year` SMALLINT NOT NULL COMMENT '折旧年度',
  `period` TINYINT NOT NULL COMMENT '折旧期间',
  `original_value` DECIMAL(18,2) NOT NULL COMMENT '期初原值',
  `monthly_depreciation` DECIMAL(18,2) NOT NULL COMMENT '本月折旧额',
  `accumulated_depreciation` DECIMAL(18,2) NOT NULL COMMENT '累计折旧(期末)',
  `net_value` DECIMAL(18,2) NOT NULL COMMENT '期末净值',
  `remaining_months` INT NOT NULL COMMENT '剩余月数',
  `voucher_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联凭证ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_year_period` (`book_id`, `card_id`, `year`, `period`),
  KEY `idx_year_period` (`book_id`, `year`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='折旧记录表';

-- 4.4 资产变动记录表
CREATE TABLE `asset_change` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `book_id` BIGINT UNSIGNED NOT NULL,
  `card_id` BIGINT UNSIGNED NOT NULL,
  `change_type` VARCHAR(20) NOT NULL COMMENT '变动类型:value原值变动/department部门变动/method折旧方法变动/useful_life使用年限变动/other其他',
  `change_date` DATE NOT NULL COMMENT '变动日期',
  `old_value` VARCHAR(500) DEFAULT NULL COMMENT '变动前值',
  `new_value` VARCHAR(500) DEFAULT NULL COMMENT '变动后值',
  `remark` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_card` (`card_id`),
  KEY `idx_change_date` (`change_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资产变动记录表';

-- ============================================================
-- 5. CRM集成模块
-- ============================================================

-- 5.1 CRM客户财务关联表
CREATE TABLE `crm_customer_finance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `crm_customer_id` BIGINT UNSIGNED NOT NULL COMMENT 'CRM客户ID(关联CRM系统)',
  `partner_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '财务往来单位ID',
  `receivable_subject_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '应收科目ID',
  `advance_subject_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '预收科目ID',
  `tax_rate` DECIMAL(5,2) DEFAULT 13.00 COMMENT '默认税率(%)',
  `payment_terms` VARCHAR(50) DEFAULT NULL COMMENT '付款条件',
  `credit_limit` DECIMAL(18,2) DEFAULT NULL COMMENT '信用额度',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_crm_customer` (`crm_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CRM客户财务关联表';

-- 5.2 CRM对接同步日志表
CREATE TABLE `crm_sync_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(30) NOT NULL COMMENT '来源类型:order订单/payment收款/refund退款',
  `source_id` VARCHAR(50) NOT NULL COMMENT '来源单据ID',
  `action` VARCHAR(30) NOT NULL COMMENT '动作:sync同步/callback回调',
  `request_data` JSON DEFAULT NULL COMMENT '请求数据',
  `response_data` JSON DEFAULT NULL COMMENT '响应数据',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0处理中 1成功 2失败',
  `error_msg` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_type`, `source_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CRM对接同步日志';

-- 6. 审计日志表
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '操作用户ID',
  `action` VARCHAR(30) NOT NULL COMMENT '动作:create/update/delete/audit/post/unpost/verify/dispose',
  `table_name` VARCHAR(50) NOT NULL COMMENT '表名',
  `record_id` BIGINT UNSIGNED NOT NULL COMMENT '记录ID',
  `changes` JSON DEFAULT NULL COMMENT '变更内容',
  `ip` VARCHAR(45) DEFAULT NULL COMMENT '操作IP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`, `record_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审计日志表';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 验证: 检查所有表是否创建成功
-- ============================================================
SELECT CONCAT('共创建 ', COUNT(*), ' 张表') AS result FROM information_schema.tables WHERE table_schema = 'erp_finance';
