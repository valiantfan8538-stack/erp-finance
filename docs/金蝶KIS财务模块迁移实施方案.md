# 金蝶KIS财务模块迁移至Vue+ThinkPHP — 项目实施计划书

> 本文档颗粒度达到可直接交付给其他AI执行的级别。
> 基于金蝶KIS专业版V16.0(PT162603)的功能结构进行对标迁移。
> 技术栈：Vue 3 (Composition API) + ThinkPHP 8 + MySQL 8.0

---

## 一、项目总览

### 1.1 源系统信息

| 项目 | 值 |
|------|------|
| 产品名称 | 金蝶KIS 专业版 |
| 版本 | V16.0 |
| 补丁 | PT162603 |
| 数据库 | SQL Server |
| 安装路径 | `C:\Program Files (x86)\Kingdee\KIS\Profession` |
| 当前账套数 | 7个 (AIS20240804202033 ~ AIS20241105172226) |

### 1.2 目标架构

| 层级 | 技术 | 版本 |
|------|------|------|
| 前端 | Vue 3 + Vite + Element Plus + Pinia + Vue Router | Vue 3.4+ |
| 后端 | ThinkPHP | 8.x |
| 数据库 | MySQL | 8.0 |
| API风格 | RESTful (JSON) | — |
| 认证 | JWT Token | — |
| 部署 | Nginx + PHP-FPM + MySQL | — |

### 1.3 项目根目录结构

```
erp-finance/
├── docs/                          # 本文档位置
├── backend/                       # ThinkPHP 8 后端
│   ├── app/
│   │   ├── controller/           # 控制器
│   │   │   ├── auth/            # 认证模块
│   │   │   ├── system/          # 系统管理
│   │   │   ├── finance/         # 财务模块
│   │   │   │   ├── AccountBook.php
│   │   │   │   ├── AccountSubject.php
│   │   │   │   ├── Voucher.php
│   │   │   │   ├── Ledger.php
│   │   │   │   ├── ARAP.php          # 应收应付
│   │   │   │   ├── FixedAsset.php    # 固定资产
│   │   │   │   └── Report.php        # 财务报表
│   │   │   └── crm/             # CRM集成接口
│   │   ├── model/               # 数据模型 (对应每个表)
│   │   ├── validate/            # 验证器
│   │   ├── service/             # 业务逻辑层
│   │   ├── middleware/          # 中间件 (JWT鉴权等)
│   │   └── common.php           # 公共函数
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php
│   │   ├── route.php
│   │   └── finance.php          # 财务配置(科目代码规则等)
│   ├── route/
│   │   └── app.php              # 路由定义
│   ├── sql/                     # 数据库建表SQL
│   │   └── init.sql
│   ├── .env.example
│   └── composer.json
├── frontend/                    # Vue 3 前端
│   ├── src/
│   │   ├── api/                 # API请求封装
│   │   │   ├── request.js       # axios实例
│   │   │   ├── auth.js
│   │   │   ├── system.js
│   │   │   ├── subjects.js
│   │   │   ├── vouchers.js
│   │   │   ├── ledger.js
│   │   │   ├── arap.js
│   │   │   ├── fixedAsset.js
│   │   │   └── reports.js
│   │   ├── views/               # 页面组件
│   │   │   ├── Login.vue
│   │   │   ├── Dashboard.vue
│   │   │   ├── layout/          # 布局组件
│   │   │   │   ├── MainLayout.vue
│   │   │   │   ├── Sidebar.vue
│   │   │   │   └── Header.vue
│   │   │   ├── subjects/        # 会计科目
│   │   │   ├── vouchers/        # 记账凭证
│   │   │   ├── ledger/          # 账簿
│   │   │   ├── arap/            # 应收应付
│   │   │   ├── fixedAsset/      # 固定资产
│   │   │   ├── reports/         # 财务报表
│   │   │   └── settings/        # 系统设置
│   │   ├── store/               # Pinia状态管理
│   │   ├── router/              # 路由配置
│   │   ├── components/          # 通用组件
│   │   │   ├── VoucherTable.vue
│   │   │   ├── SubjectTree.vue
│   │   │   ├── AmountInput.vue
│   │   │   ├── DateRangePicker.vue
│   │   │   └── ReportExport.vue
│   │   ├── utils/               # 工具函数
│   │   │   ├── finance.js       # 财务计算工具
│   │   │   ├── format.js        # 金额/日期格式化
│   │   │   └── validate.js      # 表单验证
│   │   ├── styles/              # 全局样式
│   │   ├── App.vue
│   │   └── main.js
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
└── README.md
```

---

## 二、数据库设计（完整SQL）

> 以下SQL可直接执行，覆盖金蝶KIS V16.0全部财务模块。
> 数据库名：`erp_finance`，字符集：`utf8mb4`

### 2.1 执行命令

```sql
CREATE DATABASE IF NOT EXISTS erp_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_finance;
```

### 2.2 系统管理模块

#### 2.2.1 用户表 (`sys_user`)

```sql
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
```

#### 2.2.2 角色表 (`sys_role`)

```sql
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

-- 预置角色
INSERT INTO `sys_role` (`name`, `code`, `description`, `sort`) VALUES
('系统管理员', 'admin', '拥有所有权限', 1),
('财务主管', 'finance_manager', '可审核凭证、查看所有报表', 2),
('会计', 'accountant', '可录入凭证、查看账簿', 3),
('出纳', 'cashier', '可管理现金银行日记账', 4),
('查看者', 'viewer', '仅查看权限', 5);
```

#### 2.2.3 权限/菜单表 (`sys_permission`)

```sql
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

-- 预置权限菜单(对标KIS功能结构)
INSERT INTO `sys_permission` (`id`, `parent_id`, `name`, `code`, `type`, `path`, `icon`, `component`, `sort`) VALUES
(1, 0, '财务管理', 'finance', 1, '/finance', 'money', '', 1),
(2, 1, '会计科目', 'finance:subject', 1, '/finance/subjects', '', 'subjects/Index', 1),
(3, 1, '记账凭证', 'finance:voucher', 1, '/finance/vouchers', '', 'vouchers/Index', 2),
(4, 1, '凭证审核', 'finance:voucher:audit', 1, '/finance/voucher-audit', '', 'vouchers/Audit', 3),
(5, 1, '总分类账', 'finance:ledger:general', 1, '/finance/ledger/general', '', 'ledger/GeneralLedger', 4),
(6, 1, '明细账', 'finance:ledger:detail', 1, '/finance/ledger/detail', '', 'ledger/DetailLedger', 5),
(7, 1, '现金日记账', 'finance:ledger:cash', 1, '/finance/ledger/cash', '', 'ledger/CashLedger', 6),
(8, 1, '银行日记账', 'finance:ledger:bank', 1, '/finance/ledger/bank', '', 'ledger/BankLedger', 7),
(9, 1, '科目余额表', 'finance:report:balance', 1, '/finance/report/balance', '', 'reports/BalanceSheet', 8),
(10, 1, '利润表', 'finance:report:income', 1, '/finance/report/income', '', 'reports/IncomeStatement', 9),
(11, 1, '现金流量表', 'finance:report:cashflow', 1, '/finance/report/cashflow', '', 'reports/CashFlowStatement', 10),
(12, 0, '应收应付', 'arap', 1, '/arap', 'exchange', '', 2),
(13, 12, '应收账款', 'arap:receivable', 1, '/arap/receivable', '', 'arap/Receivable', 1),
(14, 12, '应付账款', 'arap:payable', 1, '/arap/payable', '', 'arap/Payable', 2),
(15, 12, '往来核销', 'arap:verify', 1, '/arap/verify', '', 'arap/Verify', 3),
(16, 0, '固定资产', 'asset', 1, '/asset', 'wallet', '', 3),
(17, 16, '资产卡片', 'asset:card', 1, '/asset/cards', '', 'asset/Cards', 1),
(18, 16, '资产变动', 'asset:change', 1, '/asset/changes', '', 'asset/Changes', 2),
(19, 16, '折旧计提', 'asset:depreciation', 1, '/asset/depreciation', '', 'asset/Depreciation', 3),
(20, 16, '资产报表', 'asset:report', 1, '/asset/report', '', 'asset/Report', 4),
(21, 0, '系统设置', 'system', 1, '/system', 'setting', '', 4),
(22, 21, '账套管理', 'system:accountBook', 1, '/system/account-book', '', 'settings/AccountBook', 1),
(23, 21, '用户管理', 'system:user', 1, '/system/users', '', 'settings/Users', 2),
(24, 21, '角色管理', 'system:role', 1, '/system/roles', '', 'settings/Roles', 3),
(25, 21, '凭证字设置', 'system:voucherType', 1, '/system/voucher-type', '', 'settings/VoucherType', 4),
(26, 21, '期间管理', 'system:period', 1, '/system/periods', '', 'settings/Periods', 5);

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
(4, '审核凭证', 'finance:voucher:audit', 2, 1),
(4, '反审核', 'finance:voucher:unaudit', 2, 2),
(9, '导出余额表', 'finance:report:balance:export', 2, 1),
(13, '新增应收', 'arap:receivable:add', 2, 1),
(13, '核销应收', 'arap:receivable:verify', 2, 2),
(17, '新增资产', 'asset:card:add', 2, 1),
(17, '处置资产', 'asset:card:dispose', 2, 2),
(19, '计提折旧', 'asset:depreciation:run', 2, 1);
```

#### 2.2.4 角色权限关联表 (`sys_role_permission`)

```sql
CREATE TABLE `sys_role_permission` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`role_id`, `permission_id`),
  KEY `idx_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表';

-- admin角色拥有所有权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 1, id FROM `sys_permission`;

-- 财务主管拥有财务模块全部权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 2, id FROM `sys_permission` WHERE id <= 11 OR id = 19 OR id = 20;

-- 会计拥有凭证和账簿相关权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 3, id FROM `sys_permission` WHERE id IN (2,3,4,5,6,7,8,9,10,11);

-- 出纳拥有日记账和凭证查看权限
INSERT INTO `sys_role_permission` (`role_id`, `permission_id`)
SELECT 4, id FROM `sys_permission` WHERE id IN (3,7,8);
```

#### 2.2.5 用户角色关联表 (`sys_user_role`)

```sql
CREATE TABLE `sys_user_role` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色关联表';
```

#### 2.2.6 账套表 (`sys_account_book`)

```sql
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
```

#### 2.2.7 会计期间表 (`sys_accounting_period`)

```sql
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
```

### 2.3 总账模块

#### 2.3.1 会计科目表 (`finance_subject`)

```sql
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

-- 预置标准科目表(企业会计准则)
INSERT INTO `finance_subject` (`book_id`, `code`, `name`, `category`, `direction`, `level`, `is_leaf`) VALUES
-- 1001为演示账套ID，实际使用时替换
(1001, '1001', '库存现金', 'asset', 'debit', 1, 1),
(1001, '1002', '银行存款', 'asset', 'debit', 1, 0),
(1001, '100201', '工商银行', 'asset', 'debit', 2, 1),
(1001, '100202', '建设银行', 'asset', 'debit', 2, 1),
(1001, '1012', '其他货币资金', 'asset', 'debit', 1, 0),
(1001, '1101', '交易性金融资产', 'asset', 'debit', 1, 0),
(1001, '1121', '应收票据', 'asset', 'debit', 1, 0),
(1001, '1122', '应收账款', 'asset', 'debit', 1, 0),
(1001, '1123', '预付账款', 'asset', 'debit', 1, 0),
(1001, '1131', '应收股利', 'asset', 'debit', 1, 0),
(1001, '1132', '应收利息', 'asset', 'debit', 1, 0),
(1001, '1221', '其他应收款', 'asset', 'debit', 1, 0),
(1001, '1401', '材料采购', 'asset', 'debit', 1, 0),
(1001, '1402', '在途物资', 'asset', 'debit', 1, 0),
(1001, '1403', '原材料', 'asset', 'debit', 1, 0),
(1001, '1405', '库存商品', 'asset', 'debit', 1, 0),
(1001, '1601', '固定资产', 'asset', 'debit', 1, 0),
(1001, '1602', '累计折旧', 'asset', 'credit', 1, 0),
(1001, '1702', '无形资产', 'asset', 'debit', 1, 0),
(1001, '1703', '累计摊销', 'asset', 'credit', 1, 0),
(1001, '2001', '短期借款', 'liability', 'credit', 1, 0),
(1001, '2201', '应付票据', 'liability', 'credit', 1, 0),
(1001, '2202', '应付账款', 'liability', 'credit', 1, 0),
(1001, '2203', '预收账款', 'liability', 'credit', 1, 0),
(1001, '2211', '应付职工薪酬', 'liability', 'credit', 1, 0),
(1001, '2221', '应交税费', 'liability', 'credit', 1, 0),
(1001, '222101', '应交增值税', 'liability', 'credit', 2, 0),
(1001, '22210101', '进项税额', 'liability', 'credit', 3, 1),
(1001, '22210102', '销项税额', 'liability', 'credit', 3, 1),
(1001, '22210103', '已交税金', 'liability', 'credit', 3, 1),
(1001, '222102', '未交增值税', 'liability', 'credit', 2, 1),
(1001, '2241', '其他应付款', 'liability', 'credit', 1, 0),
(1001, '2501', '长期借款', 'liability', 'credit', 1, 0),
(1001, '4001', '实收资本', 'equity', 'credit', 1, 0),
(1001, '4002', '资本公积', 'equity', 'credit', 1, 0),
(1001, '4101', '盈余公积', 'equity', 'credit', 1, 0),
(1001, '4103', '本年利润', 'equity', 'credit', 1, 0),
(1001, '4104', '利润分配', 'equity', 'credit', 1, 0),
(1001, '5001', '生产成本', 'cost', 'debit', 1, 0),
(1001, '5101', '制造费用', 'cost', 'debit', 1, 0),
(1001, '6001', '主营业务收入', 'profit', 'credit', 1, 0),
(1001, '6051', '其他业务收入', 'profit', 'credit', 1, 0),
(1001, '6101', '公允价值变动损益', 'profit', 'credit', 1, 0),
(1001, '6111', '投资收益', 'profit', 'credit', 1, 0),
(1001, '6301', '以前年度损益调整', 'profit', 'credit', 1, 0),
(1001, '6401', '主营业务成本', 'profit', 'debit', 1, 0),
(1001, '6402', '其他业务成本', 'profit', 'debit', 1, 0),
(1001, '6403', '税金及附加', 'profit', 'debit', 1, 0),
(1001, '6601', '销售费用', 'profit', 'debit', 1, 0),
(1001, '6602', '管理费用', 'profit', 'debit', 1, 0),
(1001, '6603', '财务费用', 'profit', 'debit', 1, 0),
(1001, '6711', '营业外支出', 'profit', 'debit', 1, 0),
(1001, '6801', '所得税费用', 'profit', 'debit', 1, 0);
```

#### 2.3.2 凭证字表 (`finance_voucher_type`)

```sql
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

INSERT INTO `finance_voucher_type` (`book_id`, `code`, `name`, `sort`) VALUES
(1001, '记', '记账凭证', 1);
```

#### 2.3.3 记账凭证主表 (`finance_voucher`)

```sql
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
```

#### 2.3.4 凭证明细表 (`finance_voucher_entry`)

```sql
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
```

#### 2.3.5 科目余额表 (`finance_subject_balance`)

```sql
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
```

#### 2.3.6 现金流量项目表 (`finance_cashflow_item`)

```sql
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
```

### 2.4 应收应付模块

#### 2.4.1 往来单位表 (`finance_partner`)

```sql
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
```

#### 2.4.2 应收单表 (`finance_ar_receivable`)

```sql
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
```

#### 2.4.3 应付单表 (`finance_ap_payable`)

```sql
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
```

#### 2.4.4 核销记录表 (`finance_verify_record`)

```sql
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
```

### 2.5 固定资产模块

#### 2.5.1 资产类别表 (`asset_category`)

```sql
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
```

#### 2.5.2 资产卡片表 (`asset_card`)

```sql
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
```

#### 2.5.3 折旧记录表 (`asset_depreciation`)

```sql
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
```

#### 2.5.4 资产变动记录表 (`asset_change`)

```sql
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
```

### 2.6 CRM集成相关表

#### 2.6.1 CRM客户关联表 (`crm_customer_finance`)

```sql
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
```

#### 2.6.2 CRM订单对接日志表 (`crm_sync_log`)

```sql
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
```

---

## 三、ThinkPHP 8 后端实现

### 3.1 环境要求

```
PHP >= 8.1
Composer >= 2.0
MySQL >= 8.0
PHP扩展: pdo_mysql, mbstring, json, openssl
```

### 3.2 安装命令

```bash
# 1. 创建ThinkPHP项目
composer create-project topthink/think backend

# 2. 安装JWT扩展
composer require firebase/php-jwt

# 3. 复制配置
cp .env.example .env
```

### 3.3 .env 配置

```env
APP_DEBUG = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = erp_finance
USERNAME = root
PASSWORD = your_password
HOSTPORT = 3306
CHARSET = utf8mb4
DEBUG = false

[JWT]
SECRET = your-secret-key-change-in-production
EXPIRE = 7200

[CRM]
API_URL = https://your-crm-api.com
API_TOKEN = your-crm-api-token
```

### 3.4 config/finance.php 财务配置文件

```php
<?php
return [
    // 科目编码规则
    'subject' => [
        'max_level' => 4,           // 最大级次
        'level_lengths' => [4,2,2,2], // 各级编码长度:1001-01-01-01
    ],
    // 凭证编号规则
    'voucher' => [
        'max_entries' => 99,        // 最大分录数
    ],
    // 金额精度
    'amount' => [
        'precision' => 2,           // 金额精度
        'max_amount' => 999999999999.99,
    ],
    // 折旧方法
    'depreciation_methods' => [
        'straight_line' => '年限平均法',
        'double_decline' => '双倍余额递减法',
        'sum_years' => '年数总和法',
    ],
    // 会计准则
    'credit_standards' => [
        'enterprise' => '企业会计准则',
        'small' => '小企业会计准则',
    ],
];
```

### 3.5 路由配置 (route/app.php)

```php
<?php
use think\facade\Route;

// 认证路由(无需JWT)
Route::group('api', function () {
    Route::post('auth/login', 'auth/Login@login');
    Route::post('auth/refresh', 'auth/Login@refresh');
});

// 需JWT认证的路由
Route::group('api', function () {

    // 认证
    Route::post('auth/logout', 'auth/Login@logout');
    Route::get('auth/me', 'auth/Login@me');

    // 系统管理
    Route::group('system', function () {
        Route::resource('users', 'system/User');
        Route::resource('roles', 'system/Role');
        Route::resource('permissions', 'system/Permission');
        Route::resource('account-books', 'system/AccountBook');
        Route::resource('periods', 'system/Period');
        Route::resource('voucher-types', 'system/VoucherType');
        Route::resource('partners', 'system/Partner');

        // 期间结账/反结账
        Route::post('periods/:id/close', 'system/Period@close');
        Route::post('periods/:id/open', 'system/Period@open');
    });

    // 会计科目
    Route::group('finance', function () {
        Route::resource('subjects', 'finance/AccountSubject');
        Route::post('subjects/import', 'finance/AccountSubject@import');
        Route::get('subjects/tree', 'finance/AccountSubject@tree');

        // 凭证
        Route::resource('vouchers', 'finance/Voucher');
        Route::post('vouchers/:id/audit', 'finance/Voucher@audit');
        Route::post('vouchers/:id/unaudit', 'finance/Voucher@unaudit');
        Route::post('vouchers/:id/post', 'finance/Voucher@post');
        Route::get('vouchers/:id/print', 'finance/Voucher@print');

        // 账簿
        Route::get('ledger/general', 'finance/Ledger@general');
        Route::get('ledger/detail', 'finance/Ledger@detail');
        Route::get('ledger/cash', 'finance/Ledger@cash');
        Route::get('ledger/bank', 'finance/Ledger@bank');
        Route::get('ledger/subject-balance', 'finance/Ledger@subjectBalance');

        // 应收应付
        Route::resource('receivables', 'finance/ARAP');
        Route::resource('payables', 'finance/ARAP');
        Route::post('receivables/:id/verify', 'finance/ARAP@verifyReceivable');
        Route::post('payables/:id/verify', 'finance/ARAP@verifyPayable');
        Route::get('arap/aging-analysis', 'finance/ARAP@agingAnalysis');

        // 固定资产
        Route::resource('asset-cards', 'finance/FixedAsset');
        Route::post('asset-cards/:id/depreciate', 'finance/FixedAsset@depreciate');
        Route::post('asset-cards/:id/dispose', 'finance/FixedAsset@dispose');
        Route::post('asset-batch-depreciate', 'finance/FixedAsset@batchDepreciate');
        Route::get('asset-depreciation-summary', 'finance/FixedAsset@depreciationSummary');

        // 财务报表
        Route::get('reports/balance-sheet', 'finance/Report@balanceSheet');
        Route::get('reports/income-statement', 'finance/Report@incomeStatement');
        Route::get('reports/cash-flow', 'finance/Report@cashFlow');
        Route::get('reports/subject-balance', 'finance/Report@subjectBalance');
        Route::get('reports/export', 'finance/Report@export');

        // CRM对接
        Route::post('crm/sync/order', 'finance/CRM@syncOrder');
        Route::post('crm/sync/payment', 'finance/CRM@syncPayment');
    });

})->middleware(\app\middleware\AuthMiddleware::class);
```

### 3.6 核心中间件 (app/middleware/AuthMiddleware.php)

```php
<?php
namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Request;
use think\Response;

class AuthMiddleware
{
    public function handle(Request $request, \Closure $next)
    {
        $token = $request->header('Authorization');
        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return json(['code' => 401, 'msg' => '未登录'], 401);
        }

        $token = substr($token, 7);
        try {
            $secret = config('jwt.secret', env('JWT_SECRET'));
            $payload = JWT::decode($token, new Key($secret, 'HS256'));
            $request->userId = $payload->user_id;
            $request->isAdmin = $payload->is_admin ?? false;
        } catch (\Exception $e) {
            return json(['code' => 401, 'msg' => 'Token无效或已过期'], 401);
        }

        return $next($request);
    }
}
```

### 3.7 登录控制器 (app/controller/auth/Login.php)

```php
<?php
namespace app\controller\auth;

use app\BaseController;
use app\model\SysUser;
use Firebase\JWT\JWT;

class Login extends BaseController
{
    public function login()
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');

        if (!$username || !$password) {
            return $this->error('账号和密码不能为空');
        }

        $user = SysUser::where('username', $username)
            ->whereNull('deleted_at')
            ->find();

        if (!$user || !password_verify($password, $user->password)) {
            return $this->error('账号或密码错误');
        }

        if ($user->status !== 1) {
            return $this->error('账号已禁用');
        }

        $token = $this->generateToken($user);

        SysUser::where('id', $user->id)->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $this->request->ip(),
        ]);

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'real_name' => $user->real_name,
                'is_admin' => $user->is_admin,
            ]
        ]);
    }

    protected function generateToken($user)
    {
        $secret = config('jwt.secret', env('JWT_SECRET'));
        $expire = config('jwt.expire', env('JWT_EXPIRE', 7200));

        $payload = [
            'iss' => 'erp-finance',
            'aud' => 'erp-finance',
            'iat' => time(),
            'exp' => time() + $expire,
            'data' => [
                'user_id' => $user->id,
                'is_admin' => $user->is_admin,
            ]
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    public function me()
    {
        $user = SysUser::with(['roles.permissions'])->find($this->request->userId);
        return $this->success($user);
    }

    public function logout()
    {
        return $this->success('退出成功');
    }
}
```

### 3.8 凭证控制器 (app/controller/finance/Voucher.php) — 核心示例

```php
<?php
namespace app\controller\finance;

use app\BaseController;
use app\model\FinanceVoucher;
use app\model\FinanceVoucherEntry;
use app\model\FinanceSubjectBalance;
use app\validate\VoucherValidate;
use think\facade\Db;
use think\facade\Log;

class Voucher extends BaseController
{
    public function index()
    {
        $bookId = $this->request->param('book_id');
        $year = $this->request->param('year');
        $period = $this->request->param('period');
        $status = $this->request->param('status');

        $query = FinanceVoucher::with(['voucherType', 'preparedBy', 'auditBy'])
            ->where('book_id', $bookId)
            ->when($year, fn($q) => $q->where('year', $year))
            ->when($period, fn($q) => $q->where('period', $period))
            ->when($status !== null, fn($q) => $q->where('status', $status))
            ->order('date', 'desc')
            ->order('id', 'desc');

        $list = $query->paginate($this->request->param('per_page', 20));
        return $this->success($list);
    }

    public function read($id)
    {
        $voucher = FinanceVoucher::with(['entries.subject', 'preparedBy', 'auditBy'])
            ->findOrFail($id);
        return $this->success($voucher);
    }

    public function save()
    {
        $data = $this->request->post();
        $validate = new VoucherValidate();
        if (!$validate->check($data)) {
            return $this->error($validate->getError());
        }

        Db::startTrans();
        try {
            // 计算借贷合计
            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($data['entries'] as $entry) {
                $totalDebit += $entry['debit_amount'] ?? 0;
                $totalCredit += $entry['credit_amount'] ?? 0;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('借贷不平衡:借方' . $totalDebit . ' != 贷方' . $totalCredit);
            }

            // 获取凭证号
            $voucherNo = $this->getNextVoucherNo(
                $data['book_id'],
                $data['voucher_type_id'],
                $data['year'],
                $data['period']
            );

            $voucher = new FinanceVoucher();
            $voucher->book_id = $data['book_id'];
            $voucher->voucher_type_id = $data['voucher_type_id'];
            $voucher->voucher_no = $voucherNo;
            $voucher->year = $data['year'];
            $voucher->period = $data['period'];
            $voucher->date = $data['date'];
            $voucher->attachment_count = $data['attachment_count'] ?? 0;
            $voucher->prepared_by = $this->request->userId;
            $voucher->status = 0;
            $voucher->total_debit = $totalDebit;
            $voucher->total_credit = $totalCredit;
            $voucher->remark = $data['remark'] ?? '';
            $voucher->save();

            // 保存分录
            foreach ($data['entries'] as $entryNo => $entry) {
                $voucherEntry = new FinanceVoucherEntry();
                $voucherEntry->voucher_id = $voucher->id;
                $voucherEntry->entry_no = $entryNo + 1;
                $voucherEntry->subject_id = $entry['subject_id'];
                $voucherEntry->subject_code = $entry['subject_code'];
                $voucherEntry->summary = $entry['summary'] ?? '';
                $voucherEntry->debit_amount = $entry['debit_amount'] ?? 0;
                $voucherEntry->credit_amount = $entry['credit_amount'] ?? 0;
                $voucherEntry->save();
            }

            Db::commit();
            return $this->success($voucher, '凭证保存成功');

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('Voucher save error: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    public function update($id)
    {
        $voucher = FinanceVoucher::findOrFail($id);

        if ($voucher->status >= 1) {
            return $this->error('已审核的凭证不能修改，请先反审核');
        }

        $data = $this->request->post();
        $validate = new VoucherValidate();
        if (!$validate->check($data)) {
            return $this->error($validate->getError());
        }

        Db::startTrans();
        try {
            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($data['entries'] as $entry) {
                $totalDebit += $entry['debit_amount'] ?? 0;
                $totalCredit += $entry['credit_amount'] ?? 0;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('借贷不平衡');
            }

            $voucher->date = $data['date'];
            $voucher->attachment_count = $data['attachment_count'] ?? 0;
            $voucher->remark = $data['remark'] ?? '';
            $voucher->total_debit = $totalDebit;
            $voucher->total_credit = $totalCredit;
            $voucher->save();

            // 删除旧分录，保存新分录
            FinanceVoucherEntry::where('voucher_id', $id)->delete();
            foreach ($data['entries'] as $entryNo => $entry) {
                $voucherEntry = new FinanceVoucherEntry();
                $voucherEntry->voucher_id = $id;
                $voucherEntry->entry_no = $entryNo + 1;
                $voucherEntry->subject_id = $entry['subject_id'];
                $voucherEntry->subject_code = $entry['subject_code'];
                $voucherEntry->summary = $entry['summary'] ?? '';
                $voucherEntry->debit_amount = $entry['debit_amount'] ?? 0;
                $voucherEntry->credit_amount = $entry['credit_amount'] ?? 0;
                $voucherEntry->save();
            }

            Db::commit();
            return $this->success($voucher, '凭证更新成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    public function delete($id)
    {
        $voucher = FinanceVoucher::findOrFail($id);
        if ($voucher->status >= 1) {
            return $this->error('已审核的凭证不能删除');
        }
        FinanceVoucherEntry::where('voucher_id', $id)->delete();
        $voucher->delete();
        return $this->success('删除成功');
    }

    public function audit($id)
    {
        $voucher = FinanceVoucher::findOrFail($id);
        if ($voucher->status >= 1) {
            return $this->error('凭证已审核');
        }
        if ($voucher->prepared_by == $this->request->userId) {
            return $this->error('不能审核自己制单的凭证');
        }

        $voucher->status = 1;
        $voucher->audit_by = $this->request->userId;
        $voucher->audit_at = date('Y-m-d H:i:s');
        $voucher->save();

        return $this->success('审核成功');
    }

    public function unaudit($id)
    {
        $voucher = FinanceVoucher::findOrFail($id);
        if ($voucher->status >= 2) {
            return $this->error('已过账的凭证不能反审核，请先反过账');
        }

        $voucher->status = 0;
        $voucher->audit_by = null;
        $voucher->audit_at = null;
        $voucher->save();

        return $this->success('反审核成功');
    }

    public function post($id)
    {
        $voucher = FinanceVoucher::with('entries')->findOrFail($id);
        if ($voucher->status !== 1) {
            return $this->error('只有已审核的凭证才能过账');
        }

        Db::startTrans();
        try {
            // 更新科目余额
            foreach ($voucher->entries as $entry) {
                $balance = FinanceSubjectBalance::where('book_id', $voucher->book_id)
                    ->where('subject_id', $entry->subject_id)
                    ->where('year', $voucher->year)
                    ->where('period', $voucher->period)
                    ->lock(true)
                    ->find();

                if (!$balance) {
                    $balance = new FinanceSubjectBalance();
                    $balance->book_id = $voucher->book_id;
                    $balance->subject_id = $entry->subject_id;
                    $balance->year = $voucher->year;
                    $balance->period = $voucher->period;
                    $balance->initial_debit = 0;
                    $balance->initial_credit = 0;
                    $balance->debit_occurrence = 0;
                    $balance->credit_occurrence = 0;
                    $balance->final_debit = 0;
                    $balance->final_credit = 0;
                }

                $balance->debit_occurrence += $entry->debit_amount;
                $balance->credit_occurrence += $entry->credit_amount;

                // 计算期末余额
                $subject = $entry->subject;
                if ($subject->direction === 'debit') {
                    $balance->final_debit = $balance->initial_debit + $balance->debit_occurrence
                        - ($balance->initial_credit + $balance->credit_occurrence);
                    $balance->final_credit = $balance->final_debit < 0 ? abs($balance->final_debit) : 0;
                    if ($balance->final_debit < 0) $balance->final_debit = 0;
                } else {
                    $balance->final_credit = $balance->initial_credit + $balance->credit_occurrence
                        - ($balance->initial_debit + $balance->debit_occurrence);
                    $balance->final_debit = $balance->final_credit < 0 ? abs($balance->final_credit) : 0;
                    if ($balance->final_credit < 0) $balance->final_credit = 0;
                }

                $balance->save();
            }

            $voucher->status = 2;
            $voucher->posted_by = $this->request->userId;
            $voucher->posted_at = date('Y-m-d H:i:s');
            $voucher->save();

            Db::commit();
            return $this->success('过账成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error($e->getMessage());
        }
    }

    protected function getNextVoucherNo($bookId, $voucherTypeId, $year, $period)
    {
        $maxNo = FinanceVoucher::where('book_id', $bookId)
            ->where('voucher_type_id', $voucherTypeId)
            ->where('year', $year)
            ->where('period', $period)
            ->max('voucher_no');

        return ($maxNo ?? 0) + 1;
    }
}
```

### 3.9 固定资产服务类 (app/service/FixedAssetService.php) — 折旧计算核心

```php
<?php
namespace app\service;

use app\model\AssetCard;
use app\model\AssetDepreciation;
use app\model\FinanceVoucher;
use app\model\FinanceVoucherEntry;
use think\facade\Db;

class FixedAssetService
{
    public function calculateMonthlyDepreciation(AssetCard $card): float
    {
        if ($card->remaining_months <= 0) {
            return 0;
        }

        switch ($card->depreciation_method) {
            case 'straight_line':
                return $this->straightLine($card);
            case 'double_decline':
                return $this->doubleDecline($card);
            case 'sum_years':
                return $this->sumOfYears($card);
            default:
                return $this->straightLine($card);
        }
    }

    protected function straightLine(AssetCard $card): float
    {
        $residualValue = $card->original_value * $card->residual_rate / 100;
        $depreciableAmount = $card->original_value - $residualValue;
        return round($depreciableAmount / $card->useful_months, 2);
    }

    protected function doubleDecline(AssetCard $card): float
    {
        $annualRate = 2 / ($card->useful_months / 12);
        $bookValue = $card->original_value - $card->accumulated_depreciation;
        $residualValue = $card->original_value * $card->residual_rate / 100;
        $depreciation = $bookValue * $annualRate / 12;

        if ($card->original_value - $card->accumulated_depreciation - $depreciation < $residualValue) {
            $depreciation = $card->original_value - $card->accumulated_depreciation - $residualValue;
        }

        return round(max(0, $depreciation), 2);
    }

    protected function sumOfYears(AssetCard $card): float
    {
        $remainingMonths = $card->useful_months - $card->used_months;
        $sumOfYears = $card->useful_months * ($card->useful_months + 1) / 2;
        $residualValue = $card->original_value * $card->residual_rate / 100;
        $depreciableAmount = $card->original_value - $residualValue;
        return round($depreciableAmount * $remainingMonths / $sumOfYears, 2);
    }

    public function batchDepreciate(int $bookId, int $year, int $period, int $userId): array
    {
        $cards = AssetCard::where('book_id', $bookId)
            ->where('status', 'in_use')
            ->where(function($q) use ($year, $period) {
                $q->whereNull('last_depreciation_year')
                  ->whereOr(function($q2) use ($year, $period) {
                      $q2->where('last_depreciation_year', '<', $year)
                         ->whereOr('last_depreciation_period', '<', $period);
                  });
            })
            ->select();

        $totalDepreciation = 0;
        $results = [];

        Db::startTrans();
        try {
            $voucherEntries = [];

            foreach ($cards as $card) {
                $depreciation = $this->calculateMonthlyDepreciation($card);
                if ($depreciation <= 0) continue;

                $card->accumulated_depreciation += $depreciation;
                $card->net_value = $card->original_value - $card->accumulated_depreciation;
                $card->used_months += 1;
                $card->remaining_months -= 1;
                $card->last_depreciation_year = $year;
                $card->last_depreciation_period = $period;
                if ($card->remaining_months <= 0) {
                    $card->remaining_months = 0;
                }
                $card->save();

                $deprRecord = new AssetDepreciation();
                $deprRecord->book_id = $bookId;
                $deprRecord->card_id = $card->id;
                $deprRecord->year = $year;
                $deprRecord->period = $period;
                $deprRecord->original_value = $card->original_value;
                $deprRecord->monthly_depreciation = $depreciation;
                $deprRecord->accumulated_depreciation = $card->accumulated_depreciation;
                $deprRecord->net_value = $card->net_value;
                $deprRecord->remaining_months = $card->remaining_months;
                $deprRecord->save();

                $totalDepreciation += $depreciation;
                $results[] = [
                    'card_id' => $card->id,
                    'card_no' => $card->card_no,
                    'asset_name' => $card->asset_name,
                    'depreciation' => $depreciation,
                ];
            }

            Db::commit();
            return [
                'total' => count($results),
                'amount' => $totalDepreciation,
                'details' => $results,
            ];
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }
}
```

### 3.10 财务报表服务 (app/service/ReportService.php)

```php
<?php
namespace app\service;

use app\model\FinanceSubject;
use app\model\FinanceSubjectBalance;
use think\facade\Db;

class ReportService
{
    public function generateBalanceSheet(int $bookId, int $year, int $period): array
    {
        $balances = FinanceSubjectBalance::where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', $period)
            ->select()
            ->keyBy('subject_id');

        $subjects = FinanceSubject::where('book_id', $bookId)
            ->where('status', 1)
            ->select();

        $assetTotal = 0;
        $liabilityTotal = 0;
        $equityTotal = 0;

        $assetItems = $this->calcBalanceItems($subjects, $balances, 'asset', $year, $period);
        $liabilityItems = $this->calcBalanceItems($subjects, $balances, 'liability', $year, $period);
        $equityItems = $this->calcBalanceItems($subjects, $balances, 'equity', $year, $period);

        $profit = $this->calcCurrentProfit($subjects, $balances, $year, $period);

        foreach ($assetItems as $item) $assetTotal += $item['end_balance'] ?? 0;
        foreach ($liabilityItems as $item) $liabilityTotal += $item['end_balance'] ?? 0;
        foreach ($equityItems as $item) $equityTotal += $item['end_balance'] ?? 0;

        $equityTotal += $profit;

        return [
            'report_date' => "{$year}-" . str_pad($period, 2, '0', STR_PAD_LEFT) . "-31",
            'assets' => [
                'items' => $assetItems,
                'total' => $assetTotal,
            ],
            'liabilities' => [
                'items' => $liabilityItems,
                'total' => $liabilityTotal,
            ],
            'equity' => [
                'items' => $equityItems,
                'total' => $equityTotal,
                'profit' => $profit,
            ],
            'check' => $assetTotal === ($liabilityTotal + $equityTotal),
        ];
    }

    public function generateIncomeStatement(int $bookId, int $year, int $period): array
    {
        $balances = FinanceSubjectBalance::where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', '<=', $period)
            ->select();

        $revenue = $this->sumByCategory($balances, 'profit', 'credit');
        $cost = $this->sumByCategory($balances, 'profit', 'debit');

        return [
            'period' => "1-{$period}",
            'year' => $year,
            'items' => [
                ['name' => '一、营业收入', 'amount' => $revenue],
                ['name' => '减：营业成本', 'amount' => $cost],
                ['name' => '营业利润', 'amount' => $revenue - $cost],
                ['name' => '利润总额', 'amount' => $revenue - $cost],
                ['name' => '减：所得税费用', 'amount' => 0],
                ['name' => '净利润', 'amount' => $revenue - $cost],
            ],
            'net_profit' => $revenue - $cost,
        ];
    }

    protected function calcBalanceItems($subjects, $balances, $category, $year, $period)
    {
        $items = [];
        foreach ($subjects as $subject) {
            if ($subject->category !== $category || $subject->parent_id !== 0) continue;
            $balance = $balances[$subject->id] ?? null;
            if (!$balance) continue;

            $endBalance = $subject->direction === 'debit'
                ? ($balance->final_debit - $balance->final_credit)
                : ($balance->final_credit - $balance->final_debit);

            $items[] = [
                'subject_code' => $subject->code,
                'subject_name' => $subject->name,
                'begin_balance' => $subject->direction === 'debit'
                    ? $balance->initial_debit - $balance->initial_credit
                    : $balance->initial_credit - $balance->initial_debit,
                'end_balance' => $endBalance,
            ];
        }
        return $items;
    }

    protected function sumByCategory($balances, $category, $direction): float
    {
        $total = 0;
        foreach ($balances as $balance) {
            $subject = $balance->subject ?? null;
            if (!$subject || $subject->category !== $category) continue;
            if ($direction === 'debit') {
                $total += $balance->debit_occurrence;
            } else {
                $total += $balance->credit_occurrence;
            }
        }
        return $total;
    }

    protected function calcCurrentProfit($subjects, $balances, $year, $period): float
    {
        $revenue = $this->sumByCategory($balances, 'profit', 'credit');
        $cost = $this->sumByCategory($balances, 'profit', 'debit');
        return $revenue - $cost;
    }
}
```

### 3.11 Model 示例 (app/model/FinanceVoucher.php)

```php
<?php
namespace app\model;

use think\Model;

class FinanceVoucher extends Model
{
    protected $autoWriteTimestamp = 'datetime';

    public function voucherType()
    {
        return $this->belongsTo(FinanceVoucherType::class);
    }

    public function entries()
    {
        return $this->hasMany(FinanceVoucherEntry::class)->order('entry_no');
    }

    public function preparedBy()
    {
        return $this->belongsTo(SysUser::class, 'prepared_by');
    }

    public function auditBy()
    {
        return $this->belongsTo(SysUser::class, 'audit_by');
    }
}
```

---

## 四、Vue 3 前端实现

### 4.1 环境要求

```
Node.js >= 18.0
npm >= 9.0 或 pnpm >= 8.0
```

### 4.2 安装命令

```bash
# 1. 创建项目
npm create vite@latest frontend -- --template vue

# 2. 安装依赖
cd frontend
npm install vue-router pinia element-plus @element-plus/icons-vue axios dayjs
npm install -D sass unplugin-auto-import unplugin-vue-components
```

### 4.3 vite.config.js

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import { resolve } from 'path'

export default defineConfig({
  plugins: [
    vue(),
    AutoImport({
      resolvers: [ElementPlusResolver()],
    }),
    Components({
      resolvers: [ElementPlusResolver()],
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
```

### 4.4 API请求封装 (src/api/request.js)

```js
import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

const service = axios.create({
  baseURL: '/api',
  timeout: 15000,
})

service.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

service.interceptors.response.use(
  response => {
    const res = response.data
    if (res.code !== 200 && res.code !== undefined) {
      ElMessage.error(res.msg || '请求失败')
      if (res.code === 401) {
        localStorage.removeItem('token')
        router.push('/login')
      }
      return Promise.reject(new Error(res.msg))
    }
    return res
  },
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      router.push('/login')
    }
    ElMessage.error(error.message || '网络异常')
    return Promise.reject(error)
  }
)

export default service
```

### 4.5 凭证页面核心组件 (src/views/vouchers/Index.vue)

```vue
<template>
  <div class="voucher-page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>记账凭证</span>
          <div>
            <el-select v-model="query.book_id" placeholder="选择账套" style="width:160px">
              <el-option v-for="b in books" :key="b.id" :label="b.name" :value="b.id"/>
            </el-select>
            <el-select v-model="query.year" style="width:100px" class="ml-2">
              <el-option v-for="y in years" :key="y" :label="y" :value="y"/>
            </el-select>
            <el-select v-model="query.period" style="width:80px" class="ml-2">
              <el-option v-for="p in 12" :key="p" :label="p + '月'" :value="p"/>
            </el-select>
            <el-button type="primary" @click="handleAdd" class="ml-2">新增凭证</el-button>
          </div>
        </div>
      </template>

      <el-table :data="tableData" border stripe>
        <el-table-column prop="voucher_type_code" label="凭证字" width="80"/>
        <el-table-column prop="voucher_no" label="凭证号" width="80"/>
        <el-table-column prop="date" label="日期" width="120"/>
        <el-table-column prop="summary" label="摘要" min-width="200" show-overflow-tooltip/>
        <el-table-column label="借方合计" width="120" align="right">
          <template #default="{row}">{{ formatMoney(row.total_debit) }}</template>
        </el-table-column>
        <el-table-column label="贷方合计" width="120" align="right">
          <template #default="{row}">{{ formatMoney(row.total_credit) }}</template>
        </el-table-column>
        <el-table-column prop="prepared_by.real_name" label="制单人" width="100"/>
        <el-table-column label="状态" width="100">
          <template #default="{row}">
            <el-tag v-if="row.status === 0" type="info">草稿</el-tag>
            <el-tag v-else-if="row.status === 1" type="warning">已审核</el-tag>
            <el-tag v-else type="success">已过账</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{row}">
            <el-button link type="primary" @click="handleView(row)">查看</el-button>
            <el-button link type="primary" v-if="row.status === 0" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="success" v-if="row.status === 0" @click="handleAudit(row)">审核</el-button>
            <el-button link type="warning" v-if="row.status === 1" @click="handleUnaudit(row)">反审</el-button>
            <el-button link type="danger" v-if="row.status === 0" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.per_page"
        :total="total"
        @current-change="fetchList"
        layout="total, prev, pager, next"
        class="mt-4"
      />
    </el-card>

    <!-- 凭证编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="90%" top="3vh">
      <VoucherForm
        ref="formRef"
        :book-id="query.book_id"
        :voucher-id="currentVoucher?.id"
        @success="fetchList; dialogVisible = false"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/api/request'
import VoucherForm from './VoucherForm.vue'

const query = reactive({ book_id: null, year: 2024, period: 1, page: 1, per_page: 20 })
const tableData = ref([])
const total = ref(0)
const books = ref([])
const years = ref([2023, 2024, 2025, 2026])
const dialogVisible = ref(false)
const dialogTitle = ref('新增凭证')
const currentVoucher = ref(null)
const formRef = ref()

const fetchList = async () => {
  const { data } = await request.get('/finance/vouchers', { params: query })
  tableData.value = data.data
  total.value = data.total
}

const handleAdd = () => {
  dialogTitle.value = '新增凭证'
  currentVoucher.value = null
  dialogVisible.value = true
}

const handleEdit = (row) => {
  dialogTitle.value = '编辑凭证'
  currentVoucher.value = row
  dialogVisible.value = true
}

const handleAudit = async (row) => {
  await ElMessageBox.confirm('确认审核此凭证？')
  await request.post(`/finance/vouchers/${row.id}/audit`)
  ElMessage.success('审核成功')
  fetchList()
}

const handleDelete = async (row) => {
  await ElMessageBox.confirm('确认删除此凭证？')
  await request.delete(`/finance/vouchers/${row.id}`)
  ElMessage.success('删除成功')
  fetchList()
}

const formatMoney = (v) => Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 })

onMounted(() => {
  fetchList()
  request.get('/system/account-books').then(({ data }) => books.value = data)
})
</script>
```

### 4.6 路由配置 (src/router/index.js)

```js
import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/login', component: () => import('@/views/Login.vue') },
  {
    path: '/',
    component: () => import('@/views/layout/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('@/views/Dashboard.vue') },
      { path: 'finance/subjects', component: () => import('@/views/subjects/Index.vue') },
      { path: 'finance/vouchers', component: () => import('@/views/vouchers/Index.vue') },
      { path: 'finance/voucher-audit', component: () => import('@/views/vouchers/Audit.vue') },
      { path: 'finance/ledger/general', component: () => import('@/views/ledger/GeneralLedger.vue') },
      { path: 'finance/ledger/detail', component: () => import('@/views/ledger/DetailLedger.vue') },
      { path: 'finance/ledger/cash', component: () => import('@/views/ledger/CashLedger.vue') },
      { path: 'finance/ledger/bank', component: () => import('@/views/ledger/BankLedger.vue') },
      { path: 'finance/report/balance', component: () => import('@/views/reports/BalanceSheet.vue') },
      { path: 'finance/report/income', component: () => import('@/views/reports/IncomeStatement.vue') },
      { path: 'finance/report/cashflow', component: () => import('@/views/reports/CashFlowStatement.vue') },
      { path: 'arap/receivable', component: () => import('@/views/arap/Receivable.vue') },
      { path: 'arap/payable', component: () => import('@/views/arap/Payable.vue') },
      { path: 'arap/verify', component: () => import('@/views/arap/Verify.vue') },
      { path: 'asset/cards', component: () => import('@/views/asset/Cards.vue') },
      { path: 'asset/changes', component: () => import('@/views/asset/Changes.vue') },
      { path: 'asset/depreciation', component: () => import('@/views/asset/Depreciation.vue') },
      { path: 'asset/report', component: () => import('@/views/asset/Report.vue') },
      { path: 'system/account-book', component: () => import('@/views/settings/AccountBook.vue') },
      { path: 'system/users', component: () => import('@/views/settings/Users.vue') },
      { path: 'system/roles', component: () => import('@/views/settings/Roles.vue') },
      { path: 'system/periods', component: () => import('@/views/settings/Periods.vue') },
    ],
  },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('token')) {
    return '/login'
  }
})

export default router
```

---

## 五、CRM系统对接方案

### 5.1 对接流程

```
[CRM系统] 订单创建/收款确认
     │
     │  POST /api/finance/crm/sync/order
     │  POST /api/finance/crm/sync/payment
     ▼
[财务系统]
     │
     ├──→ 验证数据合法性
     ├──→ 自动生成应收单/收款单
     ├──→ (可选)自动生成记账凭证
     ├──→ 更新科目余额
     └──→ 返回对接结果给CRM
```

### 5.2 CRM对接控制器 (app/controller/finance/CRM.php)

```php
<?php
namespace app\controller\finance;

use app\BaseController;
use app\model\FinanceArReceivable;
use app\model\FinancePartner;
use app\model\CrmSyncLog;
use think\facade\Db;

class CRM extends BaseController
{
    public function syncOrder()
    {
        $data = $this->request->post();
        $log = new CrmSyncLog();
        $log->source_type = 'order';
        $log->source_id = $data['order_id'];
        $log->action = 'sync';
        $log->request_data = json_encode($data);

        try {
            Db::startTrans();

            // 查找或创建往来单位
            $partner = FinancePartner::where('book_id', $data['book_id'])
                ->where('name', $data['customer_name'])
                ->find();
            if (!$partner) {
                $partner = new FinancePartner();
                $partner->book_id = $data['book_id'];
                $partner->name = $data['customer_name'];
                $partner->type = 'customer';
                $partner->save();
            }

            // 创建应收单
            $receivable = new FinanceArReceivable();
            $receivable->book_id = $data['book_id'];
            $receivable->bill_no = 'AR' . date('YmdHis') . rand(100, 999);
            $receivable->partner_id = $partner->id;
            $receivable->bill_date = $data['order_date'] ?? date('Y-m-d');
            $receivable->subject_id = $data['subject_id'] ?? 1; // 应收账款科目
            $receivable->amount = $data['amount'];
            $receivable->tax_amount = $data['tax_amount'] ?? 0;
            $receivable->total_amount = $receivable->amount + $receivable->tax_amount;
            $receivable->unreceived_amount = $receivable->total_amount;
            $receivable->due_date = $data['due_date'] ?? null;
            $receivable->save();

            $log->response_data = json_encode(['receivable_id' => $receivable->id, 'bill_no' => $receivable->bill_no]);
            $log->status = 1;
            $log->save();

            Db::commit();
            return json(['code' => 200, 'msg' => '同步成功', 'data' => ['receivable_id' => $receivable->id]]);

        } catch (\Exception $e) {
            Db::rollback();
            $log->response_data = json_encode(['error' => $e->getMessage()]);
            $log->status = 2;
            $log->error_msg = $e->getMessage();
            $log->save();

            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    public function syncPayment()
    {
        $data = $this->request->post();

        try {
            Db::startTrans();

            $receivable = FinanceArReceivable::where('bill_no', $data['receivable_bill_no'])
                ->lock(true)
                ->findOrFail();

            $receivable->received_amount += $data['payment_amount'];
            $receivable->unreceived_amount = $receivable->total_amount - $receivable->received_amount;
            $receivable->status = $receivable->unreceived_amount <= 0 ? 2 : 1;
            $receivable->save();

            Db::commit();
            return json(['code' => 200, 'msg' => '收款同步成功']);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
```

---

## 六、开发实施计划（分阶段）

### 阶段一：基础架构搭建（预计3-5天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 1.1 | 初始化ThinkPHP项目 | backend/目录结构完整 | `php think run` 正常启动 |
| 1.2 | 执行数据库初始化SQL | 所有表创建成功 | `SHOW TABLES` 返回22张表 |
| 1.3 | 配置JWT认证中间件 | 登录/鉴权接口可用 | 登录后携带token可访问受保护接口 |
| 1.4 | 创建基础Model/Validate | 所有数据模型文件 | 每个表对应一个Model |
| 1.5 | 初始化Vue项目 | frontend/目录结构完整 | `npm run dev` 正常启动 |
| 1.6 | 搭建Vue路由和布局 | 侧边栏+顶部导航 | 所有菜单路由可正常跳转 |
| 1.7 | 登录页面 | Login.vue | 输入账号密码可登录 |

### 阶段二：总账模块（预计7-10天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 2.1 | 会计科目管理(后端API) | AccountSubject控制器+接口 | CRUD全部可用,支持树形结构 |
| 2.2 | 会计科目管理(前端) | subjects/Index.vue + SubjectTree组件 | 树形展示,可增删改 |
| 2.3 | 凭证管理(后端API) | Voucher控制器+接口 | 增删改查+审核+过账 |
| 2.4 | 凭证录入(前端) | vouchers/Index.vue + VoucherForm.vue | 支持多分录,借贷平衡校验 |
| 2.5 | 凭证审核(前端) | vouchers/Audit.vue | 批量审核,制单人不可审自己 |
| 2.6 | 科目余额计算 | FinanceSubjectBalance自动更新 | 过账后余额自动更新 |
| 2.7 | 总分类账 | Ledger@general + GeneralLedger.vue | 按科目/期间查询 |
| 2.8 | 明细账 | Ledger@detail + DetailLedger.vue | 显示所有分录明细 |
| 2.9 | 现金/银行日记账 | 对应Controller+View | 仅显示现金银行科目 |
| 2.10 | 科目余额表 | Ledger@subjectBalance + 前端页面 | 显示期初/本期/期末 |
| 2.11 | 期间结账/反结账 | Period控制器 | 结账后不可修改凭证 |

### 阶段三：应收应付模块（预计3-5天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 3.1 | 往来单位管理 | Partner CRUD | 增删改查,支持类型筛选 |
| 3.2 | 应收单管理 | ARAP@receivable + Receivable.vue | 创建/查看/列表 |
| 3.3 | 应付单管理 | ARAP@payable + Payable.vue | 创建/查看/列表 |
| 3.4 | 往来核销 | ARAP@verify + Verify.vue | 支持一对一/一对多核销 |
| 3.5 | 账龄分析 | ARAP@agingAnalysis + 前端页面 | 按30/60/90/180/365天 |

### 阶段四：固定资产模块（预计5-7天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 4.1 | 资产类别管理 | Category CRUD + 前端 | 树形结构,预设5大类 |
| 4.2 | 资产卡片管理 | FixedAsset卡片CRUD + Cards.vue | 录入完整资产信息 |
| 4.3 | 折旧计算(年限平均) | FixedAssetService@straightLine | 计算结果与KIS一致 |
| 4.4 | 折旧计算(双倍余额) | FixedAssetService@doubleDecline | — |
| 4.5 | 折旧计算(年数总和) | FixedAssetService@sumOfYears | — |
| 4.6 | 批量计提折旧 | batchDepreciate + Depreciation.vue | 一键计提所有资产 |
| 4.7 | 资产变动记录 | AssetChange CRUD + Changes.vue | 记录所有变动 |
| 4.8 | 资产处置 | dispose方法 + 前端 | 出售/报废/捐赠 |
| 4.9 | 资产报表 | depreciationSummary + Report.vue | 汇总表/明细表 |

### 阶段五：财务报表模块（预计5-7天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 5.1 | 资产负债表 | ReportService@balanceSheet + 前端 | 资产=负债+所有者权益 |
| 5.2 | 利润表 | ReportService@incomeStatement + 前端 | 净利润计算正确 |
| 5.3 | 现金流量表 | ReportService@cashFlow + 前端 | 三大类现金流 |
| 5.4 | 报表导出Excel | Report@export (PhpSpreadsheet) | 导出格式正确 |
| 5.5 | 报表打印 | 打印模板 | 格式符合会计规范 |

### 阶段六：CRM对接集成（预计3-5天）

| 序号 | 任务 | 输出物 | 验证标准 |
|------|------|--------|----------|
| 6.1 | CRM订单同步接口 | CRM@syncOrder | CRM创建订单→自动生成应收单 |
| 6.2 | CRM收款同步接口 | CRM@syncPayment | CRM确认收款→更新应收核销状态 |
| 6.3 | 自动生成凭证 | 业务逻辑(可选) | 订单/收款自动生成记账凭证 |
| 6.4 | 同步日志查看 | SyncLog管理 | 查看所有同步记录 |
| 6.5 | 错误重试机制 | 定时任务 | 失败订单自动重试 |

### 阶段七：测试与优化（预计3-5天）

| 序号 | 任务 | 验证标准 |
|------|------|----------|
| 7.1 | 借贷平衡测试 | 所有凭证借贷合计相等 |
| 7.2 | 账簿一致性测试 | 总账=明细账=科目余额表 |
| 7.3 | 报表平衡测试 | 资产负债表平衡 |
| 7.4 | 折旧准确性测试 | 与KIS折旧结果对比 |
| 7.5 | 权限测试 | 各角色仅可访问授权功能 |
| 7.6 | 性能测试 | 10000条凭证查询<3s |

---

## 七、核心业务规则清单

### 7.1 凭证规则（对标KIS）

| 规则 | 说明 |
|------|------|
| 借贷平衡 | 借方合计 = 贷方合计，误差 < 0.01 |
| 凭证编号 | 按账套+凭证字+年度+期间连续编号 |
| 制单与审核分离 | 同一人不能审核自己制单的凭证 |
| 状态流转 | 草稿→已审核→已过账，不可跳级 |
| 已审核凭证 | 必须先反审核才能修改/删除 |
| 已过账凭证 | 必须先反过账才能反审核 |
| 已结账期间 | 该期间内所有凭证不可修改/删除 |
| 分录限制 | 最小2条分录(至少一借一贷) |

### 7.2 科目规则

| 规则 | 说明 |
|------|------|
| 编码格式 | 4-2-2-2 (如:1001-01-01-01) |
| 新增下级 | 非末级科目不能有发生额 |
| 停用科目 | 已有发生额的科目不可停用 |
| 删除科目 | 有发生额或有余的科目不可删除 |
| 科目类别 | 必须属于五大类之一 |

### 7.3 固定资产折旧规则

| 规则 | 说明 |
|------|------|
| 当月增加 | 当月不计提折旧，次月开始 |
| 当月减少 | 当月仍计提折旧，次月停止 |
| 提足折旧 | 累计折旧=原值-残值后停止 |
| 跨期补提 | 支持补提以前期间未计提折旧 |

---

## 八、部署配置

### 8.1 Nginx配置

```nginx
server {
    listen 80;
    server_name finance.yourdomain.com;
    root /var/www/erp-finance/frontend/dist;
    index index.html;

    # 前端路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 后端API
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|gif|svg|ico|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

### 8.2 PHP-FPM启动

```bash
cd /var/www/erp-finance/backend
php think run -p 8000 &

# 生产环境推荐使用 supervisor
[program:erp-finance]
command=php think run -p 8000
directory=/var/www/erp-finance/backend
autostart=true
autorestart=true
stderr_logfile=/var/log/erp-finance.err.log
stdout_logfile=/var/log/erp-finance.out.log
```

### 8.3 前端构建

```bash
cd frontend
npm run build
# 输出到 dist/ 目录
# 将 dist/ 目录内容部署到 nginx root 路径
```

---

## 九、关键差异对照表（KIS → 本系统）

| KIS功能 | 本系统实现 | 备注 |
|---------|-----------|------|
| 账套管理 | sys_account_book | 支持多账套 |
| 会计科目 | finance_subject | 支持树形+导入 |
| 记账凭证 | finance_voucher + entries | 标准三栏式/多栏式 |
| 凭证审核 | voucher status=1 | 制审分离 |
| 过账 | voucher status=2 | 自动更新科目余额 |
| 总分类账 | finance_subject_balance | 按科目/期间 |
| 明细账 | voucher entries关联查询 | 全部分录 |
| 现金/银行日记账 | 按科目类别筛选 | 自动识别 |
| 资产负债表 | ReportService动态计算 | 实时生成 |
| 利润表 | ReportService动态计算 | 月报/年报 |
| 现金流量表 | finance_cashflow_item | 需手动指定 |
| 应收应付 | finance_ar_receivable/payable | 独立单据 |
| 往来核销 | finance_verify_record | 多对多核销 |
| 固定资产 | asset_card | 资产卡片 |
| 折旧计提 | FixedAssetService | 三种方法 |
| 资产变动 | asset_change | 记录追溯 |

---

## 十、快速开始指令集（给AI的完整执行清单）

```bash
# ==================== 第一步：数据库 ====================
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS erp_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p erp_finance < backend/sql/init.sql

# ==================== 第二步：后端 ====================
cd backend
composer create-project topthink/think . --no-interaction
composer require firebase/php-jwt
cp .env.example .env
# 编辑 .env 配置数据库连接
php think run -p 8000 &

# ==================== 第三步：前端 ====================
cd ../frontend
npm create vite@latest . -- --template vue --no-interaction
npm install vue-router pinia element-plus @element-plus/icons-vue axios dayjs
npm install -D sass unplugin-auto-import unplugin-vue-components
# 按照本文档配置 vite.config.js, router, api, views 等文件
npm run dev

# ==================== 第四步：验证 ====================
# 1. 访问 http://localhost:3000 → 应看到登录页面
# 2. 使用初始账号 admin/admin123 登录
# 3. 创建账套 → 确认期间自动生成
# 4. 添加会计科目 → 确认树形结构
# 5. 录入凭证 → 确认借贷平衡校验
# 6. 审核凭证 → 确认制审分离
# 7. 过账 → 确认科目余额更新
# 8. 查看报表 → 确认资产负债表平衡
```

---

> 本文档覆盖金蝶KIS V16.0专业版全部财务模块，可直接交付给AI逐个实现。
> 如需进一步细化某个模块（如凭证打印模板、特定报表格式），请告知。
