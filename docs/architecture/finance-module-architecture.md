---

# ERP 财务模块架构设计文档

**版本**: 1.0
**日期**: 2026-05-03
**状态**: 初稿 Review

---

## 目录

1. [当前架构分析与问题诊断](#1-当前架构分析与问题诊断)
2. [目标分层架构](#2-目标分层架构)
3. [模块目录结构](#3-模块目录结构)
4. [领域模型设计](#4-领域模型设计)
5. [关键接口契约](#5-关键接口契约)
6. [财务计算引擎](#6-财务计算引擎)
7. [API 设计](#7-api-设计)
8. [数据流与事务边界](#8-数据流与事务边界)
9. [前端架构](#9-前端架构)
10. [实现路线图](#10-实现路线图)
11. [架构决策记录](#11-架构决策记录)

---

## 1. 当前架构分析与问题诊断

### 1.1 现状总览

当前代码库位于 ThinkPHP 8 框架之上，采用扁平 MVC 结构，呈现典型的 "胖控制器、瘦模型" 反模式。

**现有文件格局:**

```
backend/
  app/
    controller/
      finance/
        AccountSubject.php    -- 会计科目 CRUD + 树形结构
        Voucher.php           -- 凭证 CRUD + 审核 + 过账 + 反审核 + 删除
        Ledger.php            -- 总账/明细账查询
        Report.php            -- 资产负债表 + 利润表计算
      system/
        AccountBook.php       -- 账套管理 + 会计期间自动生成
        Period.php            -- 期间管理 + 结账/反结账
    BaseController.php        -- 基础控制器
```

### 1.2 关键问题

| 问题 | 具体表现 | 风险等级 |
|------|----------|----------|
| **扁平无分层** | Controller 直接调用 `Db::table()` 操作数据库、处理业务逻辑、组装响应 | 严重 |
| **业务逻辑散落** | 借贷平衡校验散落在 Voucher::save() ；余额计算散落在 Voucher::post() ；报表计算在 Report 控制器中直接写 SQL | 严重 |
| **无领域模型** | 业务概念用数组表示，没有 `Money`、`Direction` 等值对象，金额用 `(float)` 强转 | 严重 |
| **事务边界模糊** | 过账操作涉及凭证状态更新 + 多科目余额更新 + 锁定行，当前依赖手写 `Db::startTrans()` | 高 |
| **过账逻辑脆弱** | 余额计算在 Voucher::post() 中硬编码，未分离为可测试/可审计的独立引擎 | 高 |
| **缺少接口抽象** | Repository 层不存在，数据库实现与业务逻辑紧耦合 | 严重 |
| **金额精度隐患** | 多处使用 `(float)` 转换而非 DECIMAL 或 BCMath 运算，存在精度丢失风险 | 高 |
| **记账完整性校验缺失** | 缺少对科目有效性、期间状态、制单人/审核人分离、反过账等业务规则的统一校验 | 中 |
| **前端架构空白** | 仅有两个占位路由，无状态管理、API 层、组件拆分 | 中 |

### 1.3 与金蝶 KIS 对比的能力差距

当前系统仅覆盖了小企业会计准则（2013）中最基础的总账功能（科目+凭证+基础报表）。需要扩展至：

- 完整的复式记账生命周期：制单 -> 审核 -> 过账 -> 期末调汇 -> 结转损益 -> 结账
- 应收应付模块：应收/应付单管理 -> 往来核销 -> 账龄分析 -> 坏账计提
- 固定资产模块：资产卡片 -> 折旧计提（包括直线法/双倍余额递减法/年数总和法）-> 资产变动 -> 处置
- 多维度报表：科目余额表、辅助核算余额表、现金流量表（直接法+间接法）

---

## 2. 目标分层架构

### 2.1 架构总览

```
┌──────────────────────────────────────────────────────────────┐
│                        HTTP Layer                            │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐   │
│  │  Controllers    │  │  Middleware     │  │  Validators  │   │
│  │  (thin)         │  │  (auth/tenant)  │  │  (input)     │   │
│  └───────┬────────┘  └────────────────┘  └──────────────┘   │
│          │ 依赖注入                                            │
│          ▼                                                    │
├──────────────────────────────────────────────────────────────┤
│                    Application Layer                          │
│  ┌─────────────────────────────────────────────────────┐     │
│  │  Application Services (Service Layer)                │     │
│  │  - VoucherAppService       - AssetAppService         │     │
│  │  - SubjectAppService       - DepreciationService     │     │
│  │  - ARAPAppService          - ReportAppService        │     │
│  │  - PeriodClosingService    - VerificationService     │     │
│  │  职责: 事务编排, 权限校验, 事件发布, DTO转换           │     │
│  └─────────────────────────────────────────────────────┘     │
│          │                                                    │
│          ▼                                                    │
├──────────────────────────────────────────────────────────────┤
│                     Domain Layer                              │
│  ┌─────────────────────────────────────────────────────┐     │
│  │  Domain Services                                     │     │
│  │  - PostingEngine         - BalanceCalculator         │     │
│  │  - DepreciationEngine    - AgingAnalyzer             │     │
│  │  - VerificationEngine    - ReportGenerator           │     │
│  │  职责: 核心业务规则, 计算逻辑, 不依赖框架              │     │
│  └─────────────────────────────────────────────────────┘     │
│  ┌────────────────┐  ┌────────────────┐  ┌──────────────┐   │
│  │  Entities       │  │  Value Objects  │  │  Aggregates  │   │
│  │  (有ID, 可变)   │  │  (无ID, 不可变) │  │  (一致性边界)│   │
│  └────────────────┘  └────────────────┘  └──────────────┘   │
│          │ 依赖倒置                                            │
│          ▼                                                    │
├──────────────────────────────────────────────────────────────┤
│                 Repository Interfaces                         │
│  ┌─────────────────────────────────────────────────────┐     │
│  │  Interface Layer (契约)                             │     │
│  │  VoucherRepositoryInterface                         │     │
│  │  SubjectBalanceRepositoryInterface                  │     │
│  │  AccountingPeriodRepositoryInterface                │     │
│  └─────────────────────────────────────────────────────┘     │
│          │ 实现                                              │
│          ▼                                                    │
├──────────────────────────────────────────────────────────────┤
│                 Infrastructure Layer                           │
│  ┌─────────────────────────────────────────────────────┐     │
│  │  Repository Implementations                         │     │
│  │  - ThinkOrmVoucherRepository                        │     │
│  │  - ThinkOrmSubjectBalanceRepository                 │     │
│  │  - ThinkOrmPeriodRepository                         │     │
│  │  职责: 数据持久化, ORM 操作, 查询优化                  │     │
│  └─────────────────────────────────────────────────────┘     │
│  ┌──────────────────────┐  ┌──────────────────────────┐      │
│  │  Events / Queue      │  │  External Integrations   │      │
│  └──────────────────────┘  └──────────────────────────┘      │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 分层职责定义

| 层级 | 职责 | 依赖方向 | 测试策略 |
|------|------|----------|----------|
| **Controller** | HTTP 输入解析、参数校验、返回响应 | 依赖 Application Service（接口） | 集成测试 |
| **Application Service** | 事务编排、权限校验、DTO 转换、事件发布 | 依赖 Domain Service + Repository Interface | 单元测试 + Mock Repository |
| **Domain Service** | 核心业务规则、计算逻辑、不感知框架 | 纯 PHP，无外部依赖 | 单元测试（最核心） |
| **Domain Entity/VO** | 业务概念建模、不变量保证 | 纯 PHP | 单元测试 |
| **Repository Interface** | 数据访问契约 | 无依赖 | N/A |
| **Repository Impl** | ORM 操作、查询实现 | 依赖 Interface + think-orm | 集成测试 + 真实数据库 |

### 2.3 依赖注入配置

使用 ThinkPHP 的 Service Provider 机制绑定接口与实现。在 `app/provider.php` 或 `app/finance/provider.php` 中注册：

```
app/finance/
  provider.php           -- 领域服务 + 仓库绑定
  event.php              -- 事件监听器注册
  middleware.php          -- 模块级中间件（账套上下文）
```

Key 绑定示例：

```php
// provider.php
use app\domain\finance\gl\VoucherRepositoryInterface;
use app\infrastructure\finance\ThinkOrmVoucherRepository;

return [
    VoucherRepositoryInterface::class => ThinkOrmVoucherRepository::class,
    SubjectBalanceRepositoryInterface::class => ThinkOrmSubjectBalanceRepository::class,
    AccountingPeriodRepositoryInterface::class => ThinkOrmAccountingPeriodRepository::class,
    // ...
];
```

---

## 3. 模块目录结构

### 3.1 Backend 完整目录树

```
backend/
  app/
    # ── 控制器层（薄）────────────────────────────────
    controller/
      finance/
        gl/
          SubjectController.php       -- /api/finance/subjects
          VoucherController.php       -- /api/finance/vouchers
          LedgerController.php        -- /api/finance/ledger
          PeriodClosingController.php -- /api/finance/periods/{id}/close
        ar_ap/
          ReceivableController.php    -- /api/finance/ar/receivables
          PayableController.php       -- /api/finance/ap/payables
          VerificationController.php  -- /api/finance/verification
          AgingController.php         -- /api/finance/reports/aging
        fixed_asset/
          AssetCardController.php     -- /api/finance/asset-cards
          DepreciationController.php  -- /api/finance/asset-cards/{id}/depreciation
          AssetChangeController.php   -- /api/finance/asset-cards/{id}/changes
          AssetDisposalController.php -- /api/finance/asset-cards/{id}/disposal
        report/
          BalanceReportController.php -- /api/finance/reports/balance-sheet
          ProfitReportController.php  -- /api/finance/reports/income-statement
          CashflowController.php      -- /api/finance/reports/cashflow
          SubjectBalanceController.php-- /api/finance/reports/subject-balance
      system/
        AccountBookController.php     -- /api/system/account-books
        PeriodController.php          -- /api/system/periods
        AuthController.php            -- /api/auth/login

    # ── 应用服务层（事务编排）─────────────────────────
    application/
      finance/
        gl/
          SubjectAppService.php
          VoucherAppService.php
          LedgerQueryService.php
          PeriodClosingAppService.php
        ar_ap/
          ReceivableAppService.php
          PayableAppService.php
          VerificationAppService.php
          AgingQueryService.php
        fixed_asset/
          AssetCardAppService.php
          DepreciationAppService.php
          AssetDisposalAppService.php
        report/
          BalanceSheetService.php
          IncomeStatementService.php
          CashflowStatementService.php
          SubjectBalanceQueryService.php
      dto/
        finance/
          gl/
            VoucherCreateRequest.php
            VoucherListRequest.php
            VoucherResponse.php
            SubjectCreateRequest.php
            SubjectTreeResponse.php
          ar_ap/
            ReceivableCreateRequest.php
            VerificationRequest.php
          fixed_asset/
            AssetCardCreateRequest.php
            DepreciationRunRequest.php

    # ── 领域层（核心业务逻辑 + 值对象）────────────────
    domain/
      finance/
        gl/
          # Entities
          Voucher.php                     -- 聚合根
          VoucherEntry.php                -- 聚合内的实体
          AccountSubject.php              -- 实体
          SubjectBalance.php              -- 实体
          # Value Objects
          VoucherNo.php                   -- 凭证号
          SubjectCode.php                 -- 科目编码
          VoucherStatus.php               -- 凭证状态枚举
          EntryDirection.php              -- 借贷方向枚举
          # Domain Services
          PostingEngine.php               -- 过账引擎
          PostingResult.php               -- 过账结果值对象
          BalanceCalculator.php           -- 余额计算器
          VoucherValidator.php            -- 凭证校验规则
          # Repository Interfaces
          VoucherRepositoryInterface.php
          SubjectRepositoryInterface.php
          SubjectBalanceRepositoryInterface.php
        ar_ap/
          # Entities
          Receivable.php                  -- 应收单 (聚合根)
          Payable.php                     -- 应付单 (聚合根)
          VerificationRecord.php          -- 核销记录
          PartnerAccount.php              -- 往来单位账户
          # Value Objects
          DueDate.php
          AgingBucket.php                 -- 账龄区间
          VerificationMethod.php          -- 核销方式枚举
          # Domain Services
          VerificationEngine.php          -- 核销引擎
          AgingAnalyzer.php               -- 账龄分析器
          # Repository Interfaces
          ReceivableRepositoryInterface.php
          PayableRepositoryInterface.php
          VerificationRepositoryInterface.php
        fixed_asset/
          # Entities
          AssetCard.php                   -- 资产卡片 (聚合根)
          AssetDepreciation.php           -- 折旧记录
          AssetChange.php                 -- 资产变动记录
          # Value Objects
          DepreciationMethod.php          -- 折旧方法枚举
          AssetStatus.php                 -- 资产状态枚举
          UsefulLife.php                  -- 使用年限值对象
          ResidualRate.php                -- 残值率值对象
          # Domain Services
          DepreciationEngine.php          -- 折旧计算引擎
          # Repository Interfaces
          AssetCardRepositoryInterface.php
          AssetDepreciationRepositoryInterface.php
        shared/
          # 共享值对象
          Money.php                       -- 金额 (封装 DECIMAL 运算)
          AccountPeriod.php               -- 会计期间值对象
          BookId.php                      -- 账套标识
          UserId.php                      -- 用户标识
          AuditTrail.php                  -- 审计轨迹值对象
          CurrencyCode.php                -- 币别
          # 共享领域服务
          PeriodValidator.php             -- 期间状态校验

    # ── 基础设施层（ORM 实现）───────────────────────
    infrastructure/
      finance/
        gl/
          ThinkOrmVoucherRepository.php
          ThinkOrmSubjectRepository.php
          ThinkOrmSubjectBalanceRepository.php
        ar_ap/
          ThinkOrmReceivableRepository.php
          ThinkOrmPayableRepository.php
          ThinkOrmVerificationRepository.php
        fixed_asset/
          ThinkOrmAssetCardRepository.php
          ThinkOrmAssetDepreciationRepository.php
        report/
          BalanceSheetQueryBuilder.php
          IncomeStatementQueryBuilder.php
          CashflowQueryBuilder.php
        shared/
          MoneyCast.php                   -- Money 值对象与数据库互转
          PeriodHelper.php                -- 期间工具函数

    # ── 事件系统 ─────────────────────────────────────
    event/
      finance/
        VoucherCreatedEvent.php
        VoucherPostedEvent.php
        VoucherAuditedEvent.php
        PeriodClosedEvent.php
        DepreciationRecordedEvent.php
      listener/
        finance/
          VoucherPostBalanceUpdateListener.php
          DepreciationVoucherGenerateListener.php
          VerificationUpdateARAPBalanceListener.php

    # ── 异常定义 ────────────────────────────────────
    exception/
      finance/
        VoucherNotBalancedException.php
        PeriodClosedException.php
        InvalidSubjectException.php
        AuditSelfVoucherException.php
        SelfVerificationException.php
        InsufficientARBalanceException.php

    # ── 路由 ────────────────────────────────────────
    route/
      finance.php                         -- 财务模块路由
      system.php                          -- 系统模块路由

    # ── 配置 ────────────────────────────────────────
    config/
      finance.php                         -- 财务模块配置
```

### 3.2 分层引用规则

```
Controller -> ApplicationService (接口) 
            -> DTO (请求/响应)

ApplicationService -> DomainService (接口)
                   -> RepositoryInterface
                   -> DTO

DomainService      -> Entity / ValueObject
                   -> RepositoryInterface (只读查询)

RepositoryImpl     -> think-orm Model / Db
```

**禁止的依赖方向:**
- Controller 不允许直接调用 Repository
- Domain 不允许依赖 Infrastructure
- ApplicationService 不允许持有 Entity（应使用 DTO 交互）

---

## 4. 领域模型设计

### 4.1 总账子域核心模型

```
┌───────────────────────────────┐
│         Voucher (聚合根)       │  ← 凭证是整个总账的一致性边界
│───────────────────────────────│
│  id: int                       │
│  bookId: BookId                │
│  voucherNo: VoucherNo          │
│  voucherTypeId: int            │
│  period: AccountPeriod         │
│  date: DateImmutable           │
│  status: VoucherStatus         │
│  entries: VoucherEntry[]       │  ← 聚合内实体
│  preparedBy: UserId            │
│  auditBy: ?UserId              │
│  auditAt: ?DateTimeImmutable   │
│  postedBy: ?UserId             │
│  postedAt: ?DateTimeImmutable  │
│  remark: string                │
│  attachmentCount: int          │
│───────────────────────────────│
│  + isBalanced(): bool          │  借贷平衡校验
│  + totalDebit(): Money         │  借方合计
│  + totalCredit(): Money        │  贷方合计
│  + canAudit(UserId): bool      │
│  + canPost(): bool             │
│  + canUnpost(): bool           │
│  + audit(UserId): void         │  ← 状态变更方法
│  + unaudit(): void             │
│  + post(UserId): void          │
│  + unpost(): void              │
└──────────┬────────────────────┘
           │ 包含
           │ 1..N
┌──────────▼────────────────────┐
│      VoucherEntry (实体)       │
│───────────────────────────────│
│  id: int                       │
│  voucherId: int                │
│  entryNo: int                  │
│  subjectId: int                │
│  summary: string               │
│  debitAmount: Money            │  ← 值对象
│  creditAmount: Money           │
│  customerId: ?int              │  辅助核算
│  supplierId: ?int              │
│───────────────────────────────│
│  + direction(): EntryDirection │  推算出借方还是贷方
└───────────────────────────────┘
```

**Voucher 聚合的不变量:**
1. `entries` 不能为空，至少包含 2 条分录
2. `totalDebit() == totalCredit()` 必须成立
3. `status` 状态转换必须遵循：草稿 -> 已审核 -> 已过账（不可跳跃、不可逆向）
4. 所有 `entries` 的 `subjectId` 必须存在于 `finance_subject` 表
5. 制单人不能审核自己制作的凭证
6. 所属期间不能是已结账状态

```
┌───────────────────────────────┐
│     AccountSubject (实体)     │
│───────────────────────────────│
│  id: int                       │
│  bookId: BookId                │
│  code: SubjectCode             │  ← 值对象（含编码规则校验）
│  name: string                  │
│  parentId: ?int                │
│  category: string              │  asset/liability/equity/
│                                 │  cost/profit/loss
│  direction: EntryDirection     │  余额方向
│  level: int                    │
│  isLeaf: bool                  │
│  isCashAccount: bool           │  现金流量科目标记
│  isBankAccount: bool           │
│  isQuantityAccount: bool       │  数量辅助核算
│  status: int                   │
│───────────────────────────────│
│  + fullCode(): string          │  含父级前缀的完整编码
│  + canDelete(): bool           │  检查是否已有发生额
│  + changeParent(Subject): void │  变更父级
└───────────────────────────────┘

┌───────────────────────────────┐
│    SubjectBalance (实体)      │
│───────────────────────────────│
│  id: int                       │
│  bookId: BookId                │
│  subjectId: int                │
│  period: AccountPeriod         │
│  initialDebit: Money           │  期初借方
│  initialCredit: Money          │  期初贷方
│  debitOccurrence: Money        │  本期借方发生额
│  creditOccurrence: Money       │  本期贷方发生额
│  finalDebit: Money             │  期末借方
│  finalCredit: Money            │  期末贷方
│───────────────────────────────│
│  + carryForward(): void        │  结转到下期
│  + recalculate(): void         │  重新计算期末余额
│  + periodBalance(): Money      │  本期净发生额
│  + cumulativeBalance(): Money  │  累计余额
└───────────────────────────────┘
```

### 4.2 应收应付子域模型

```
┌───────────────────────────────────┐
│      Receivable (聚合根)           │  ← 应收单
│───────────────────────────────────│
│  id: int                           │
│  bookId: BookId                    │
│  docNo: string                     │  单据编号
│  partnerId: int                    │  往来单位
│  docType: string                   │  发票/其他应收
│  totalAmount: Money                │  原币金额
│  balanceAmount: Money              │  未核销余额
│  dueDate: DueDate                  │  到期日
│  period: AccountPeriod             │  所属期间
│  status: string                    │  已审核/已核销/已结清
│  remark: string                    │
│───────────────────────────────────│
│  + canVerify(): bool               │  余额>0
│  + verify(Money): VerificationRecord│  核销（返回核销记录）
│  + isFullySettled(): bool          │  余额=0
│  + generateVoucherEntries(): array │  生成凭证分录
└───────────────────────────────────┘

┌───────────────────────────────────┐
│  VerificationRecord (实体)         │  ← 核销记录
│───────────────────────────────────│
│  id: int                           │
│  bookId: BookId                    │
│  recevableId: ?int                 │  应收单ID
│  payableId: ?int                   │  应付单ID
│  voucherId: ?int                   │  关联凭证ID
│  verifyAmount: Money               │  核销金额
│  verifyDate: DateImmutable         │  核销日期
│  verifyMethod: VerificationMethod  │  逐笔/冲销
│  createdBy: UserId                 │
│  createdAt: DateTimeImmutable      │
└───────────────────────────────────┘
```

### 4.3 固定资产子域模型

```
┌───────────────────────────────────┐
│      AssetCard (聚合根)            │  ← 资产卡片
│───────────────────────────────────│
│  id: int                           │
│  bookId: BookId                    │
│  code: string                      │  资产编号
│  name: string                      │  资产名称
│  categoryId: int                   │  资产类别
│  acquireDate: DateImmutable        │  取得日期
│  originalValue: Money              │  原值
│  residualValue: Money              │  残值
│  residualRate: ResidualRate        │  残值率
│  usefulLife: UsefulLife            │  使用年限（月）
│  depreciationMethod: DepMethod     │  折旧方法
│  netValue: Money                   │  净值
│  accumulatedDepreciation: Money    │  累计折旧
│  status: AssetStatus               │  使用中/已提足/已处置
│  departmentId: ?int                │  使用部门
│  location: ?string                 │  存放地点
│───────────────────────────────────│
│  + calculateMonthlyDepreciation(): Money  │  计算月折旧
│  + recordDepreciation(Depreciation): void │  记录折旧
│  + change(AssetChange): void              │  资产变动
│  + dispose(Disposal): void                │  资产处置
│  + netBookValue(): Money                  │  账面价值
└───────────────────────────────────┘

┌───────────────────────────────────┐
│      AssetDepreciation (实体)     │  ← 折旧记录
│───────────────────────────────────│
│  id: int                           │
│  cardId: int                       │
│  period: AccountPeriod             │
│  depreciationAmount: Money         │  本期折旧额
│  accumulatedDepreciation: Money    │  累计折旧
│  netValue: Money                   │  计提后净值
│  method: DepreciationMethod        │  计提方法
│  booked: bool                      │  是否已生成凭证
│  voucherId: ?int                   │  关联凭证ID
│  createdAt: DateTimeImmutable      │
└───────────────────────────────────┘
```

### 4.4 共享值对象

```
┌───────────────────────────────┐
│      Money (值对象)            │
│───────────────────────────────│
│  - amount: string              │  内部用字符串存储
│───────────────────────────────│
│  + fromDecimal(float): Money   │  工厂方法
│  + fromString(string): Money   │
│  + add(Money): Money           │  不可变，返回新对象
│  + subtract(Money): Money      │  BCMath 运算
│  + multiply(float): Money      │
│  + divide(int): Money          │
│  + abs(): Money                │
│  + isZero(): bool              │
│  + isPositive(): bool          │
│  + compareTo(Money): int       │
│  + toDecimal(): string         │  始终返回保留2位小数字符串
│  + round(): Money              │  四舍五入
└───────────────────────────────┘

┌───────────────────────────────┐
│   AccountPeriod (值对象)      │
│───────────────────────────────│
│  - year: int                   │
│  - period: int                 │  (1-12)
│───────────────────────────────│
│  + isBefore(AccountPeriod): bool     │
│  + isAfter(AccountPeriod): bool      │
│  + next(): AccountPeriod             │  下一期
│  + previous(): AccountPeriod         │  上一期
│  + toKey(): string                   │  "2026-04"
│  + fromKey(string): AccountPeriod    │  反解析
└───────────────────────────────┘

┌───────────────────────────────┐
│   VoucherStatus (枚举)        │
│───────────────────────────────│
│  DRAFT = 0                    │  草稿/未审核
│  AUDITED = 1                  │  已审核
│  POSTED = 2                   │  已过账
│───────────────────────────────│
│  + canTransitionTo(VoucherStatus): bool   │
└───────────────────────────────┘

┌───────────────────────────────┐
│   EntryDirection (枚举)       │
│───────────────────────────────│
│  DEBIT = "debit"               │  借方
│  CREDIT = "credit"             │  贷方
│───────────────────────────────│
│  + opposite(): EntryDirection  │
└───────────────────────────────┘
```

### 4.5 聚合与聚合根定义

| 聚合根 | 子域 | 聚合边界内实体 | 不变量 |
|--------|------|---------------|--------|
| `Voucher` | 总账 | `VoucherEntry` (1..N) | 借贷平衡; 状态机转换; 期间不可修改 |
| `AccountSubject` | 总账 | 无 | 编码在账套内唯一; 有下级时不可标记为叶子 |
| `SubjectBalance` | 总账 | 无 | 每个期间每个科目一条记录 |
| `Receivable` | AR/AP | `VerificationRecord` (0..N) | 未核销余额不超过原金额 |
| `Payable` | AR/AP | `VerificationRecord` (0..N) | 同上 |
| `AssetCard` | 固定资产 | `AssetDepreciation` (0..N); `AssetChange` (0..N) | 累计折旧 <= 原值 - 残值 |

---

## 5. 关键接口契约

### 5.1 Repository 接口

```php
// ── 总账 ──────────────────────────────────────

interface VoucherRepositoryInterface
{
    public function findById(int $id): ?Voucher;
    public function findByPeriod(BookId $bookId, AccountPeriod $period): array;
    public function findByBookAndStatus(BookId $bookId, VoucherStatus $status, int $page, int $perPage): PaginatedResult;
    public function findNextVoucherNo(BookId $bookId, int $voucherTypeId, AccountPeriod $period): VoucherNo;
    public function save(Voucher $voucher): int;        // 新增 or 更新
    public function delete(int $id): void;
    public function lockById(int $id): ?Voucher;        // 悲观锁
}

interface SubjectRepositoryInterface
{
    public function findById(int $id): ?AccountSubject;
    public function findByCode(BookId $bookId, SubjectCode $code): ?AccountSubject;
    public function findByBookId(BookId $bookId): array;
    public function findTree(BookId $bookId): array;     // 树形结构
    public function findLeafByBookId(BookId $bookId): array;
    public function save(AccountSubject $subject): int;
    public function delete(int $id): void;
    public function existsWithTransactions(int $subjectId): bool;
}

interface SubjectBalanceRepositoryInterface
{
    public function findBySubjectAndPeriod(int $subjectId, AccountPeriod $period): ?SubjectBalance;
    public function findByBookAndPeriod(BookId $bookId, AccountPeriod $period): array;
    public function findByBookAndYear(BookId $bookId, int $year): array;
    public function findOrCreate(BookId $bookId, int $subjectId, AccountPeriod $period): SubjectBalance;
    public function save(SubjectBalance $balance): void;
    public function batchSave(array $balances): void;
    public function lockForUpdate(BookId $bookId, array $subjectIds, AccountPeriod $period): void;
}

interface AccountingPeriodRepositoryInterface
{
    public function findById(int $id): ?AccountingPeriod;
    public function findByBookAndPeriod(BookId $bookId, AccountPeriod $period): ?AccountingPeriod;
    public function findByBook(BookId $bookId): array;   // 所有期间
    public function getCurrentPeriod(BookId $bookId): ?AccountingPeriod;
    public function isClosed(BookId $bookId, AccountPeriod $period): bool;
    public function save(AccountingPeriod $period): void;
}


// ── 应收应付 ──────────────────────────────────

interface ReceivableRepositoryInterface
{
    public function findById(int $id): ?Receivable;
    public function findByPartner(BookId $bookId, int $partnerId): array;
    public function findUnverified(BookId $bookId, int $partnerId): array;  // 未核销
    public function findDueForAging(BookId $bookId, string $asOfDate): array;
    public function save(Receivable $receivable): int;
}

interface PayableRepositoryInterface
{
    // 同上模式
}

interface VerificationRepositoryInterface
{
    public function findById(int $id): ?VerificationRecord;
    public function findByReceivable(int $receivableId): array;
    public function save(VerificationRecord $record): int;
}


// ── 固定资产 ─────────────────────────────────

interface AssetCardRepositoryInterface
{
    public function findById(int $id): ?AssetCard;
    public function findByBook(BookId $bookId): array;
    public function findDueForDepreciation(BookId $bookId, AccountPeriod $period): array;  // 需要计提折旧的
    public function save(AssetCard $card): int;
    public function delete(int $id): void;
}

interface AssetDepreciationRepositoryInterface
{
    public function findByCardAndPeriod(int $cardId, AccountPeriod $period): ?AssetDepreciation;
    public function findByPeriod(BookId $bookId, AccountPeriod $period): array;
    public function save(AssetDepreciation $depreciation): int;
}
```

### 5.2 领域服务接口

```php
// ── 过账引擎 ──────────────────────────────────
interface PostingEngine
{
    /**
     * @param Voucher $voucher  已审核待过账的凭证
     * @param UserId  $postedBy 过账人
     * @return PostingResult     过账结果
     * @throws PeriodClosedException        期间已结账
     * @throws VoucherAlreadyPostedException 重复过账
     */
    public function post(Voucher $voucher, UserId $postedBy): PostingResult;

    /**
     * 反过账（回滚余额更新）
     */
    public function unpost(Voucher $voucher, UserId $unpostedBy): PostingResult;
}

// ── 折旧计算引擎 ──────────────────────────────
interface DepreciationEngine
{
    /**
     * 计算单张资产卡片的月度折旧额
     */
    public function calculateMonthly(AssetCard $card): Money;

    /**
     * 批量计提某期间的全部折旧
     * @return AssetDepreciation[]
     */
    public function runForPeriod(BookId $bookId, AccountPeriod $period): array;
}

// ── 核销引擎 ──────────────────────────────────
interface VerificationEngine
{
    /**
     * 对某往来单位的应收应付进行核销
     */
    public function verify(
        BookId $bookId,
        int $partnerId,
        array $receivableIds,
        array $payableIds,
        Money $amount,
        UserId $userId
    ): VerificationResult;
}

// ── 账龄分析器 ────────────────────────────────
interface AgingAnalyzer
{
    /**
     * @param string $asOfDate 分析的基准日期
     * @param array  $buckets  账龄区间定义 [(天数上限, 标签)]
     * @return AgingReport
     */
    public function analyze(BookId $bookId, string $asOfDate, array $buckets): AgingReport;
}
```

### 5.3 Application Service 接口（供 Controller 调用）

```php
// ── 凭证应用服务 ──────────────────────────────
interface VoucherAppServiceInterface
{
    public function create(VoucherCreateRequest $request): VoucherResponse;
    public function update(int $id, VoucherUpdateRequest $request): VoucherResponse;
    public function findById(int $id): VoucherResponse;
    public function list(VoucherListRequest $request): PaginatedResponse;
    public function audit(int $id, UserId $userId): void;
    public function unaudit(int $id, UserId $userId): void;
    public function post(int $id, UserId $userId): void;
    public function unpost(int $id, UserId $userId): void;
    public function delete(int $id): void;
}

// ── 期间结转应用服务 ──────────────────────────
interface PeriodClosingAppServiceInterface
{
    public function close(BookId $bookId, AccountPeriod $period, UserId $userId): ClosingResult;
    public function open(BookId $bookId, AccountPeriod $period, UserId $userId): void;
    public function carryForward(BookId $bookId, AccountPeriod $fromPeriod, AccountPeriod $toPeriod): void;
}
```

---

## 6. 财务计算引擎

### 6.1 过账引擎 (PostingEngine)

过账是系统中最关键的计算流程，必须满足原子性和可审计性。

```
过账流程:
┌──────────┐
│ Voucher  │
│ (已审核)  │
└────┬─────┘
     │
     ▼
┌─────────────────────────┐
│ 1. 校验期间未结账        │ ← AccountingPeriodRepository.isClosed()
│ 2. 校验凭证状态=已审核    │ ← Voucher.status == AUDITED
│ 3. 校验借贷平衡          │ ← Voucher.isBalanced()
│ 4. 校验科目均为叶子科目   │ ← SubjectRepository.findLeaf()
└─────────────────────────┘
     │
     ▼
┌─────────────────────────┐
│ 5. 锁定所有涉及科目余额   │ ← SubjectBalanceRepository.lockForUpdate()
│     (悲观锁，防并发)      │
└─────────────────────────┘
     │
     ▼
┌─────────────────────────┐
│ 6. 逐分录更新余额         │
│   ┌─────────────────┐   │
│   │ for each entry:  │   │
│   │ - 获取/创建余额记录 │   │
│   │ - 增加借方/贷方发生额│   │
│   │ - 按科目方向计算    │   │
│   │   期末余额         │   │
│   │ - 写入 updated_at  │   │
│   └─────────────────┘   │
└─────────────────────────┘
     │
     ▼
┌─────────────────────────┐
│ 7. 更新凭证状态=已过账    │
│ 8. 记录审计日志          │
│ 9. 发布 VoucherPostedEvent│
│ 10. 提交事务             │
└─────────────────────────┘
```

**余额计算规则:**

```
对于借方科目 (direction='debit'):
  期末余额 = 期初余额 + 借方发生额 - 贷方发生额
  若结果 < 0: 期末余额 = 0，期末贷方 = |结果|
  若结果 >= 0: 期末贷方 = 0

对于贷方科目 (direction='credit'):
  期末余额 = 期初余额 + 贷方发生额 - 借方发生额
  若结果 < 0: 期末余额 = 0，期末借方 = |结果|
  若结果 >= 0: 期末借方 = 0
```

**反过账流程:**
与过账对称，将分录金额从发生额中扣除，重新计算余额。反过账的前提条件：
1. 凭证状态 = 已过账
2. 之后没有新的凭证引用本次过账产生的余额（需要检查后续期间是否有已过账凭证）

### 6.2 折旧计算引擎 (DepreciationEngine)

支持的折旧方法：

```
D1: 平均年限法（直线法）
  月折旧额 = (原值 - 残值) / (使用年限 * 12)

D2: 双倍余额递减法
  年折旧率 = 2 / 使用年限 * 100%
  月折旧率 = 年折旧率 / 12
  月折旧额 = 期初账面净值 * 月折旧率
  最后两年改为直线法（净值 / 剩余月份）

D3: 年数总和法
  年折旧率 = 尚可使用年数 / 年数总和
  月折旧额 = (原值 - 残值) * 年折旧率 / 12

D4: 工作量法
  单位折旧 = (原值 - 残值) / 总工作量
  月折旧额 = 本月实际工作量 * 单位折旧
```

**折旧计提调度:**

```
DepreciationAppService.runPeriodDepreciation(BookId, AccountPeriod):
  1. 校验期间未结账
  2. 查询该期间需要计提折旧的全部卡片
     (条件: 状态=使用中, 取得日期 <= 本期末,
      累计折旧 < 原值-残值)
  3. 逐卡片计算月折旧额
  4. 记录 AssetDepreciation 记录
  5. 自动生成计提折旧凭证（可选，根据系统配置）
     - 借: 管理费用/制造费用/销售费用-折旧费
     - 贷: 累计折旧
  6. 更新卡片累计折旧、净值
  7. 发布 DepreciationRecordedEvent
```

### 6.3 核销引擎 (VerificationEngine)

```
VerificationEngine.verify(BookId, PartnerId, ReceivableIds, PayableIds, Amount):
  1. 计算所有选中应收单的未核销余额合计 >= Amount
  2. 计算所有选中应付单的未核销余额合计 >= Amount
  3. 按先进先出 (FIFO) 顺序逐单核销：
     for each 应收单:
       核销金额 = min(应收单余额, 剩余核销金额)
       更新应收单余额
       记录核销记录
       剩余核销金额 -= 核销金额
  4. 对应付单同理
  5. 生成核销凭证（可选）
     - 借: 应付账款 (或相关科目)
     - 贷: 应收账款
```

### 6.4 账龄分析器 (AgingAnalyzer)

```
AgingAnalyzer.analyze(BookId, as_of_date, buckets=[30,60,90,180,365]):
  1. 查询所有未结清的应收单（balance_amount > 0）
  2. 按 Partner 分组
  3. 对每个 Partner:
     for each 应收单:
       逾期天数 = as_of_date - due_date
       根据逾期天数分配到对应账龄区间
       累加各区间金额
  4. 返回结构:
     [
       {
         partner_id, partner_name,
         total_unsettled,
         buckets: [ {days: "0-30", amount, percentage}, ... ]
       }
     ]
```

### 6.5 报表计算引擎

**资产负债表:**
- 从 `finance_subject_balance` 表取期末余额
- 按科目类别（asset/liability/equity）分组汇总
- 资产 = 负债 + 所有者权益 恒等校验
- 本年利润需要动态计算（收入-费用）并入未分配利润

**利润表:**
- 取损益类科目的本期发生额（借方/贷方）
- 收入类科目取贷方发生额
- 费用类科目取借方发生额
- 营业收入 - 营业成本 - 期间费用 + 其他收益 = 利润总额

**现金流量表（间接法）:**
- 以净利润为起点
- 加回非现金支出（折旧、摊销）
- 调整流动资产/流动负债变动
- 区分经营活动/投资活动/筹资活动

### 6.6 期末结转流程

```
PeriodClosingService.close(BookId, AccountPeriod):
  1. 校验前置期间必须已结账
  2. 校验本期所有凭证均已过账
  3. 校验无未审核凭证
  4. (可选) 校验损益类科目余额 = 0 (已结转损益)
  5. 锁定所有余额记录
  6. 生成结转损益凭证:
     - 收入类科目: 借: 各收入科目 ; 贷: 本年利润
     - 费用类科目: 借: 本年利润 ; 贷: 各费用科目
  7. 过账结转凭证
  8. 结转到下一期（可选自动）
  9. 将本期科目余额的期末数复制为下期期初数
  10. 将期间状态标记为已结账
  11. 发布 PeriodClosedEvent
```

---

## 7. API 设计

### 7.1 通用规范

- **基础路径**: `/api/finance/*`, `/api/system/*`
- **版本**: URL 路径中不包含版本号，通过请求头 `Accept: application/vnd.erp.v1+json` 管理
- **响应格式**:

```json
// 成功
{ "code": 200, "data": { ... }, "msg": "ok" }

// 分页
{ "code": 200, "data": { "items": [...], "total": 100, "page": 1, "per_page": 20 }, "msg": "ok" }

// 错误
{ "code": 400, "msg": "借贷不平衡: 借方100.00 ≠ 贷方99.00" }
```

- **金额字段**: 统一使用 `string` 类型（"100.00"），前端展示时格式化
- **日期字段**: 使用 `YYYY-MM-DD` 格式
- **认证**: Bearer JWT Token，在中间件中解析

### 7.2 总账模块 API

```
# ── 会计科目 ──────────────────────────────────────
GET    /api/finance/subjects              ?book_id=&status=1
GET    /api/finance/subjects/tree         ?book_id=
GET    /api/finance/subjects/:id
POST   /api/finance/subjects              (json body)
PUT    /api/finance/subjects/:id           (json body)
DELETE /api/finance/subjects/:id

# ── 记账凭证 ──────────────────────────────────────
GET    /api/finance/vouchers               ?book_id=&year=&period=&status=&page=&per_page=
GET    /api/finance/vouchers/:id
POST   /api/finance/vouchers               (json body)
PUT    /api/finance/vouchers/:id
DELETE /api/finance/vouchers/:id
POST   /api/finance/vouchers/:id/audit
POST   /api/finance/vouchers/:id/unaudit
POST   /api/finance/vouchers/:id/post
POST   /api/finance/vouchers/:id/unpost

# ── 账簿查询 ──────────────────────────────────────
GET    /api/finance/ledger/general           ?book_id=&subject_id=&year=&period=&page=
GET    /api/finance/ledger/subject-balance   ?book_id=&year=&period=
GET    /api/finance/ledger/detail            ?book_id=&subject_id=&start_date=&end_date=
GET    /api/finance/ledger/subject-balance/detail ?book_id=&subject_id=&year=&period=

# ── 期间管理 ──────────────────────────────────────
GET    /api/finance/periods                  ?book_id=
POST   /api/finance/periods/:id/close
POST   /api/finance/periods/:id/open
POST   /api/finance/periods/:id/carry-forward
```

### 7.3 应收应付模块 API

```
# ── 应收单 ──────────────────────────────────────
GET    /api/finance/ar/receivables          ?book_id=&partner_id=&status=&page=
GET    /api/finance/ar/receivables/:id
POST   /api/finance/ar/receivables
PUT    /api/finance/ar/receivables/:id
DELETE /api/finance/ar/receivables/:id
POST   /api/finance/ar/receivables/:id/audit

# ── 应付单 ──────────────────────────────────────
GET    /api/finance/ap/payables             ?book_id=&partner_id=&status=&page=
GET    /api/finance/ap/payables/:id
POST   /api/finance/ap/payables
PUT    /api/finance/ap/payables/:id
DELETE /api/finance/ap/payables/:id
POST   /api/finance/ap/payables/:id/audit

# ── 核销 ──────────────────────────────────────────
GET    /api/finance/verification            ?book_id=&partner_id=&page=
POST   /api/finance/verification            (核销操作)
GET    /api/finance/verification/:id
DELETE /api/finance/verification/:id         (反核销)

# ── 账龄分析 ──────────────────────────────────────
GET    /api/finance/reports/aging           ?book_id=&as_of_date=&type=receivable|payable
```

### 7.4 固定资产模块 API

```
# ── 资产类别 ──────────────────────────────────────
GET    /api/finance/asset-categories         ?book_id=
POST   /api/finance/asset-categories
PUT    /api/finance/asset-categories/:id
DELETE /api/finance/asset-categories/:id

# ── 资产卡片 ──────────────────────────────────────
GET    /api/finance/asset-cards             ?book_id=&status=&category_id=&page=
GET    /api/finance/asset-cards/:id
POST   /api/finance/asset-cards
PUT    /api/finance/asset-cards/:id
DELETE /api/finance/asset-cards/:id

# ── 折旧管理 ──────────────────────────────────────
GET    /api/finance/asset-cards/:id/depreciation-history  ?year=&period=
GET    /api/finance/depreciation/estimate    (预计折旧: card_id, year, period)
POST   /api/finance/depreciation/run         (执行批量计提: book_id, year, period)

# ── 资产变动 ──────────────────────────────────────
POST   /api/finance/asset-cards/:id/changes  (原值变动/使用部门变动/使用状况变动)
GET    /api/finance/asset-cards/:id/changes

# ── 资产处置 ──────────────────────────────────────
POST   /api/finance/asset-cards/:id/disposal (报废/出售/捐赠)
GET    /api/finance/asset-cards/:id/disposal-history
```

### 7.5 报表模块 API

```
GET    /api/finance/reports/balance-sheet      ?book_id=&year=&period=
GET    /api/finance/reports/income-statement    ?book_id=&year=&period=
GET    /api/finance/reports/cashflow            ?book_id=&year=&period=&method=direct|indirect
GET    /api/finance/reports/subject-balance     ?book_id=&year=&period=&subject_id=&level=
```

### 7.6 请求/响应示例

**凭证创建请求:**
```json
POST /api/finance/vouchers
{
  "book_id": 1,
  "voucher_type_id": 1,
  "year": 2026,
  "period": 4,
  "date": "2026-04-15",
  "attachment_count": 2,
  "remark": "采购原材料",
  "entries": [
    {
      "subject_id": 1403,
      "summary": "采购钢材",
      "debit_amount": "50000.00",
      "credit_amount": "0.00"
    },
    {
      "subject_id": 2202,
      "summary": "应付供应商货款",
      "debit_amount": "0.00",
      "credit_amount": "50000.00"
    }
  ]
}
```

**凭证响应:**
```json
{
  "code": 200,
  "msg": "凭证保存成功",
  "data": {
    "id": 1024,
    "voucher_no": 56,
    "status": 0,
    "total_debit": "50000.00",
    "total_credit": "50000.00",
    "entries": [
      { "entry_no": 1, "subject_id": 1403, "subject_name": "原材料",
        "subject_code": "1403", "summary": "采购钢材",
        "debit_amount": "50000.00", "credit_amount": "0.00" },
      { "entry_no": 2, "subject_id": 2202, "subject_name": "应付账款",
        "subject_code": "2202", "summary": "应付供应商货款",
        "debit_amount": "0.00", "credit_amount": "50000.00" }
    ]
  }
}
```

---

## 8. 数据流与事务边界

### 8.1 凭证创建数据流

```
[用户] -> [Vue VoucherForm]
  -> [Pinia VoucherStore]
    -> [Axios POST /api/finance/vouchers]
      -> [VoucherController::save()]
        -> 1. 参数验证 (validate())
        -> 2. 构建 VoucherCreateRequest DTO
        -> 3. 调用 VoucherAppService.create(request)
          -> 4. 检查期间状态 (PeriodValidator)
          -> 5. 构建 Voucher 聚合（含 VoucherEntry[]）
          -> 6. 调用 VoucherValidator.validate(voucher)
               - 借贷平衡校验
               - 分录不少于2行
               - 科目有效性
          -> 7. 调用 VoucherRepository.save(voucher)  [开启事务]
               - 插入 finance_voucher 表
               - 批量插入 finance_voucher_entry
               - 获取自增ID
               - 提交事务
          -> 8. 发布 VoucherCreatedEvent
          -> 9. 返回 VoucherResponse DTO
        -> 10. Controller 返回 JSON 响应
  <- [Axios response]
<- [Pinia store 更新本地状态]
<- [Vue router 跳转至凭证列表 / 显示成功消息]
```

### 8.2 过账数据流

```
[用户] -> [Vue 点击"过账"按钮]
  -> [Pinia VoucherStore.post(id)]
    -> [Axios POST /api/finance/vouchers/:id/post]
      -> [VoucherController::post(id)]
        -> 1. 获取当前用户ID
        -> 2. 调用 VoucherAppService.post(id, userId)
          -> 3. VoucherRepository.findById(id)
          -> 4. Domain: voucher.canPost() -> 必须为 AUDITED 状态
          -> 5. AccountingPeriodRepository.isClosed() -> 必须未结账
          -> 6. PostingEngine.post(voucher, userId)  [开启事务]
               -> 6a. 悲观锁: SubjectBalanceRepository.lockForUpdate()
               -> 6b. VoucherValidator.validateBeforePost(voucher)
               -> 6c. 逐分录更新 SubjectBalance
               -> 6d. Voucher.post(userId) -> 状态变为 POSTED
               -> 6e. VoucherRepository.save(voucher)
               -> 6f. 提交事务
          -> 7. 发布 VoucherPostedEvent
          -> 8. 返回
        -> 9. Controller 返回 JSON
  <- [Axios response]
<- [Pinia store 刷新凭证列表]
```

### 8.3 事务边界定义

| 操作 | 事务边界 | 隔离级别 | 说明 |
|------|----------|----------|------|
| 凭证保存 | `VoucherRepository.save()` | READ COMMITTED | 单凭证原子写入 |
| 凭证审核 | `Voucher.updateStatus()` + 审计日志 | READ COMMITTED | 轻量操作 |
| 凭证过账 | `PostingEngine.post()` | REPEATABLE READ + 行锁 | 最关键的金融操作 |
| 凭证反过账 | `PostingEngine.unpost()` | REPEATABLE READ + 行锁 | 与过账对称 |
| 批量折旧计提 | `DepreciationEngine.runForPeriod()` | READ COMMITTED | 支持断点续提 |
| 期间结账 | `PeriodClosingService.close()` | REPEATABLE READ + 表锁 | 全局排他操作 |
| 核销 | `VerificationEngine.verify()` | READ COMMITTED | 原子写入 |

**事务切面策略:**
- 使用 ThinkPHP 的 `Db::transaction()` 或自定义 `@Transactional` 注解
- 所有 Repository 实现使用同一个 `Connection` 实例，确保事务一致
- 应用层 Service 负责事务边界，Domain Service 不处理事务

### 8.4 并发控制

**策略:**
1. **悲观锁**: 过账和反过账操作中使用 `SELECT ... FOR UPDATE` 锁定相关余额行
2. **乐观锁**: 在 Entity 上添加 `version` 字段，更新时检查版本号（适用于不频繁的写操作）
3. **期间锁**: 已结账期间的数据在数据库层面不可修改，由 Repository 层强制检查
4. **唯一约束**: 凭证号在"账套+类型+年度+月度"范围内唯一，由数据库唯一索引保证

**锁粒度:**
```
过账: 锁定所有涉及科目的 finance_subject_balance 行
      锁定当前 finance_voucher 行
结账: 锁定所有 finance_subject_balance (by book_id + year)
     + 锁定所有 finance_voucher (by book_id + year + period)
```

---

## 9. 前端架构

### 9.1 目录结构

```
frontend/
  src/
    # ── 视图页面 ────────────────────────────────
    views/
      Login.vue
      Dashboard.vue
      layout/
        MainLayout.vue
        Sidebar.vue
        TopBar.vue
      finance/
        gl/
          subjects/
            Index.vue              -- 科目列表
            Form.vue               -- 科目新增/编辑
            Tree.vue               -- 科目树
          vouchers/
            Index.vue              -- 凭证列表（含筛选）
            Create.vue             -- 凭证录入（核心交互）
            Detail.vue             -- 凭证详情
          ledgers/
            SubjectBalance.vue     -- 科目余额表
            GeneralLedger.vue      -- 总分类账
            DetailLedger.vue       -- 明细账
          period/
            PeriodList.vue         -- 期间列表
            PeriodClosing.vue      -- 期末结转向导
        ar_ap/
          receivables/
            Index.vue              -- 应收单列表
            Form.vue               -- 应收单录入
          payables/
            Index.vue              -- 应付单列表
            Form.vue               -- 应付单录入
          verification/
            Index.vue              -- 核销页面（核心交互：选单核销）
          reports/
            AgingAnalysis.vue      -- 账龄分析表
        fixed_asset/
          cards/
            Index.vue              -- 资产卡片列表
            Form.vue               -- 资产卡片录入
            Detail.vue             -- 卡片详情（含折旧明细、变动记录）
          depreciation/
            Index.vue              -- 折旧计提管理
            RunDepreciation.vue    -- 执行批量计提
          disposal/
            Index.vue              -- 资产处置记录
        reports/
          BalanceSheet.vue         -- 资产负债表
          IncomeStatement.vue      -- 利润表
          CashflowStatement.vue    -- 现金流量表
          SubjectBalanceReport.vue -- 科目余额表

    # ── 全局状态管理 (Pinia) ────────────────────
    stores/
      app.js                       -- 全局状态（当前账套、用户信息）
      finance/
        gl/
          subjects.js
          vouchers.js
          ledgers.js
        ar_ap/
          receivables.js
          payables.js
          verification.js
        fixed_asset/
          cards.js
          depreciation.js
        reports/
          reports.js

    # ── 通用组件 ────────────────────────────────
    components/
      finance/
        common/
          MoneyInput.vue            -- 金额输入（千分位格式、精度控制、禁用浮点）
          SubjectSelector.vue       -- 科目选择器（树形弹窗+模糊搜索）
          PeriodSelector.vue        -- 会计期间下拉选择
          BookSelector.vue          -- 账套切换
          VoucherStatusTag.vue      -- 凭证状态标签
          AmountDisplay.vue         -- 金额展示（自动对齐红字负号）
          TablePagination.vue       -- 统一分页
          SearchForm.vue            -- 搜索表单容器
        gl/
          VoucherForm.vue           -- 凭证录入表单主体（含多分录行编辑）
          EntryRow.vue              -- 单行分录组件（科目+摘要+金额）
          EntryTable.vue            -- 分录表格（支持增删行、借贷合计校验）
          BalanceTable.vue          -- 余额表通用渲染
          PeriodStatusBadge.vue     -- 期间状态徽标
        ar_ap/
          PartnerSelector.vue       -- 往来单位选择器
          ReceivableTable.vue       -- 应收单表格
          PayableTable.vue          -- 应付单表格
          VerificationForm.vue      -- 核销表单（选单+金额分配）
        fixed_asset/
          AssetCardForm.vue         -- 资产卡片表单
          DepreciationMethodSelector.vue -- 折旧方法选择
          DepreciationTable.vue     -- 折旧明细表
        reports/
          ReportTable.vue           -- 报表表格（固定列头+合计行）
          BalanceSheetContent.vue   -- 资产负债表内容区域
          IncomeStatementContent.vue-- 利润表内容区域
          CashflowContent.vue       -- 现金流量表内容区域

    # ── API 层 ──────────────────────────────────
    api/
      request.js                    -- Axios 实例（拦截器、错误处理）
      finance/
        subjects.js
        vouchers.js
        ledgers.js
        periods.js
        ar_ap.js
        fixed_asset.js
        reports.js
      system/
        accountBooks.js
        auth.js

    # ── 组合式函数 ──────────────────────────────
    composables/
      useFinanceStore.js            -- 财务模块通用 store 组合
      usePeriodCheck.js             -- 期间状态检查
      useSubjectTree.js             -- 科目树加载与缓存
      useDebounce.js                -- 防抖
      usePagination.js              -- 分页逻辑

    # ── 工具函数 ────────────────────────────────
    utils/
      money.js                      -- 金额格式化、BCMath 运算（前端精度控制）
      validator.js                  -- 表单校验规则
      tree.js                       -- 树形结构工具
      date.js                       -- 日期工具（使用 dayjs）
      constants.js                  -- 常量（状态枚举、字典）

    # ── 路由 ────────────────────────────────────
    router/
      index.js
      finance.js                    -- 财务模块子路由
```

### 9.2 路由设计

```javascript
// router/finance.js
const financeRoutes = [
  {
    path: 'finance',
    children: [
      // 总账
      { path: 'subjects',           component: () => import('@/views/finance/gl/subjects/Index.vue') },
      { path: 'subjects/create',    component: () => import('@/views/finance/gl/subjects/Form.vue') },
      { path: 'subjects/:id/edit',  component: () => import('@/views/finance/gl/subjects/Form.vue') },
      { path: 'vouchers',           component: () => import('@/views/finance/gl/vouchers/Index.vue') },
      { path: 'vouchers/create',    component: () => import('@/views/finance/gl/vouchers/Create.vue') },
      { path: 'vouchers/:id',       component: () => import('@/views/finance/gl/vouchers/Detail.vue') },
      { path: 'ledger/general',     component: () => import('@/views/finance/gl/ledgers/GeneralLedger.vue') },
      { path: 'ledger/detail',      component: () => import('@/views/finance/gl/ledgers/DetailLedger.vue') },
      { path: 'ledger/subject-balance', component: () => import('@/views/finance/gl/ledgers/SubjectBalance.vue') },
      { path: 'periods',            component: () => import('@/views/finance/gl/period/PeriodList.vue') },
      { path: 'periods/close',      component: () => import('@/views/finance/gl/period/PeriodClosing.vue') },
      // 应收应付
      { path: 'ar/receivables',     component: () => import('@/views/finance/ar_ap/receivables/Index.vue') },
      { path: 'ar/receivables/create', component: () => import('@/views/finance/ar_ap/receivables/Form.vue') },
      { path: 'ap/payables',        component: () => import('@/views/finance/ar_ap/payables/Index.vue') },
      { path: 'ap/payables/create', component: () => import('@/views/finance/ar_ap/payables/Form.vue') },
      { path: 'verification',       component: () => import('@/views/finance/ar_ap/verification/Index.vue') },
      { path: 'reports/aging',      component: () => import('@/views/finance/ar_ap/reports/AgingAnalysis.vue') },
      // 固定资产
      { path: 'asset-cards',        component: () => import('@/views/finance/fixed_asset/cards/Index.vue') },
      { path: 'asset-cards/create', component: () => import('@/views/finance/fixed_asset/cards/Form.vue') },
      { path: 'asset-cards/:id',    component: () => import('@/views/finance/fixed_asset/cards/Detail.vue') },
      { path: 'depreciation',       component: () => import('@/views/finance/fixed_asset/depreciation/Index.vue') },
      // 报表
      { path: 'reports/balance-sheet',    component: () => import('@/views/finance/reports/BalanceSheet.vue') },
      { path: 'reports/income-statement', component: () => import('@/views/finance/reports/IncomeStatement.vue') },
      { path: 'reports/cashflow',         component: () => import('@/views/finance/reports/CashflowStatement.vue') },
    ]
  }
]
```

### 9.3 前端状态管理策略

**Store 设计原则:**
- 每个业务聚合对应一个 Pinia Store
- Store 职责：管理 UI 状态 + 维护服务端数据的本地缓存
- 写操作直接调用 API，成功后更新本地缓存
- 读操作优先使用缓存，支持强制刷新

```javascript
// stores/finance/gl/vouchers.js 结构示例
import { defineStore } from 'pinia'
import { voucherApi } from '@/api/finance/vouchers'

export const useVoucherStore = defineStore('finance/gl/vouchers', () => {
  // ── State ──
  const list = ref([])
  const currentVoucher = ref(null)
  const pagination = ref({ page: 1, perPage: 20, total: 0 })
  const filters = ref({ bookId: null, year: null, period: null, status: null })
  const loading = ref(false)

  // ── Actions ──
  async function fetchList(params) { /* 调用 API，更新 list + pagination */ }
  async function fetchById(id) { /* 获取详情，赋值 currentVoucher */ }
  async function create(data) { /* POST 创建，返回新 ID */ }
  async function update(id, data) { /* PUT 更新 */ }
  async function audit(id) { /* POST audit */ }
  async function post(id) { /* POST post */ }
  async function remove(id) { /* DELETE */ }

  // ── Getters ──
  const currentEntries = computed(() => currentVoucher.value?.entries ?? [])
  const isBalanced = computed(() => /* 借贷平衡校验 */)

  return { list, currentVoucher, pagination, filters, loading,
           fetchList, fetchById, create, update, audit, post, remove,
           currentEntries, isBalanced }
})
```

### 9.4 前端金额精度控制

**`MoneyInput.vue` 核心约束:**
- 用户输入时实时格式化为千分位显示: `1,234.56`
- 内部值存储为 `string` 类型
- 输入框不允许非数字字符（除数字、小数点、负号外）
- 最多输入2位小数
- 负数用红字表示
- 提交时发送纯数字字符串: `"1234.56"`

### 9.5 凭证录入页面交互设计

```
VoucherCreate.vue
├── VoucherForm.vue (主表单)
│   ├── 凭证头数据 (日期、凭证类型、附件数、备注)
│   ├── PeriodSelector.vue (期间选择)
│   ├── EntryTable.vue (分录表格体)
│   │   ├── EntryRow.vue × N (分录行)
│   │   │   ├── SubjectSelector.vue (科目选择)
│   │   │   ├── SummaryInput.vue (摘要)
│   │   │   ├── MoneyInput.vue (借方金额)
│   │   │   └── MoneyInput.vue (贷方金额)
│   │   └── AddRowButton.vue (新增行)
│   └── 底部合计栏 (借方合计 - 贷方合计 = 差额)
│       └── 差额为 0 时显示 "借贷平衡" 绿色标识
└── 操作按钮: [保存草稿] [保存并审核] [保存并新增]
```

**关键交互:**
- 用户在某一分录行输入借方金额后，下一行默认跳转至贷方，反之亦然
- 回车自动新增一行分录
- 科目选择器支持编码搜索+名称搜索+树形浏览
- 保存前实时校验借贷平衡，差额变化即时更新

---

## 10. 实现路线图

### 10.1 总体阶段规划

```
Phase 1 (Foundation)          Phase 2 (Core GL)            Phase 3 (AR/AP)
┌─────────────┐              ┌──────────────┐             ┌──────────────┐
│  W1-3: 骨架搭建 │  ──→    │  W4-7: 核心过账  │  ──→   │  W8-11: 应收应付 │
│  - 目录结构    │              │  - 凭证全生命周期 │             │  - 应收单      │
│  - 值对象     │              │  - 过账引擎    │             │  - 应付单      │
│  - 仓库接口    │              │  - 余额计算    │             │  - 核销引擎    │
│  - DI 配置    │              │  - 账簿查询    │             │  - 账龄分析    │
│  - 科目管理    │              │  - 期间结账    │             │  - 前端联动    │
│  - 期间管理    │              │  - 前端实现    │             └──────────────┘
└─────────────┘              └──────────────┘                    │
        │                                                         │
        ▼                                                         ▼
┌────────────────┐        ┌────────────────┐        ┌────────────────┐
│ Phase 4 (Asset)│        │ Phase 5 (Report)│        │ Phase 6 (Polish)│
│ W12-15: 固定资产 │        │ W16-17: 报表    │        │ W18-20: 收尾    │
│ - 资产卡片     │        │ - 资产负债表   │        │ - 审计日志      │
│ - 折旧引擎     │        │ - 利润表       │        │ - 性能优化      │
│ - 资产变动/处置  │        │ - 现金流量表   │        │ - 异常处理完善   │
│ - 前端联动     │        │ - 科目余额表   │        │ - 集成测试      │
└────────────────┘        └────────────────┘        └────────────────┘
```

### 10.2 Phase 1: 基础设施与科目管理 (3 周)

**目标: 建立分层架构骨架，完成会计科目和期间管理**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 1 | 创建目录结构；实现共享值对象 `Money`、`AccountPeriod`、`Direction`；实现 `MoneyCast` 数据库互转；配置 DI 绑定 | 目录就绪；值对象单元测试通过 |
| 2 | 实现 `AccountingPeriodRepositoryInterface` 和 `ThinkOrmAccountingPeriodRepository`；重写 `PeriodController`（薄）调用 `PeriodAppService`；实现期间结账/反结账领域逻辑 | 期间管理完整可用 |
| 3 | 实现 `SubjectRepositoryInterface` 和 `ThinkOrmSubjectRepository`；实现 `AccountSubject` 实体（含编码校验、层级计算）；重写 `SubjectController`；实现前端科目管理页面（列表+树+CRUD） | 科目管理可操作 |

### 10.3 Phase 2: 总账核心 (4 周)

**目标: 凭证全生命周期管理，过账引擎，账簿查询，期间结转**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 4 | 实现 `Voucher` 聚合和 `VoucherEntry` 实体；实现 `VoucherValidator`（借贷平衡、科目有效性、期间校验）；实现 `VoucherRepositoryInterface` | 凭证领域模型就绪 |
| 5 | 实现 `VoucherAppService`（创建、更新、审核、反审核）；重写 `VoucherController`；实现前端凭证录入页面（最核心交互） | 凭证录入可用 |
| 6 | 实现 `SubjectBalance` 实体和 `SubjectBalanceRepository`；实现 `PostingEngine`（过账+反过账，含悲观锁）；实现凭证前端过账/反过账操作 | 过账功能可用 |
| 7 | 实现 `LedgerQueryService`（总分类账、明细账、科目余额表）；实现 `PeriodClosingService`（结转损益、期末结转）；实现前端账簿查询页面 | 总账核心完整闭环 |

### 10.4 Phase 3: 应收应付 (4 周)

**目标: 应收应付单管理、核销、账龄分析**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 8 | 实现 `Receivable` 和 `Payable` 聚合；实现应收应付单 CRUD 的 Repository + Application Service | AR/AP 基础可用 |
| 9 | 实现 `VerificationEngine`（核销逻辑、FIFO 顺序）；实现核销记录 Repository；实现前端核销页面 | 核销功能可用 |
| 10 | 实现 `AgingAnalyzer`；实现账龄查询 API；实现前端账龄分析页面；实现坏账计提（可选） | 账龄分析可用 |
| 11 | 实现应收应付生成凭证的自动联动；实现反核销；集成测试 | AR/AP 完整闭环 |

### 10.5 Phase 4: 固定资产 (4 周)

**目标: 资产卡片管理、折旧计提（多种方法）、资产变动与处置**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 12 | 实现 `AssetCard` 聚合；实现固定资产 Repository；实现前端资产卡片页面 | 资产卡片可用 |
| 13 | 实现 `DepreciationEngine`（直线法+双倍余额递减法+年数总和法+工作量法）；实现折旧计提 Repository | 折旧引擎就绪 |
| 14 | 实现折旧批量计提流程；实现自动生成折旧凭证；实现前端折旧管理页面 | 折旧功能可用 |
| 15 | 实现资产变动（原值变动、部门转移）；实现资产处置（报废、出售）；实现资产变动/处置记录 Repository + 前端 | 固定资产完整闭环 |

### 10.6 Phase 5: 报表 (2 周)

**目标: 四张核心财务报表**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 16 | 实现 `BalanceSheetService` 和 `IncomeStatementService`；实现前端资产负债表和利润表页面；确保资产=负债+权益恒等 | 两张主表可用 |
| 17 | 实现 `CashflowStatementService`（直接法+间接法）；实现 `SubjectBalanceQueryService`（辅助核算余额表）；实现前端相应页面 | 四张报表全部可用 |

### 10.7 Phase 6: 收尾与优化 (3 周)

**目标: 审计、性能、安全、质量**

| 周 | 任务 | 交付物 |
|----|------|--------|
| 18 | 审计日志系统（记录所有财务操作的关键字段变更历史）；统一异常处理错误码；完善输入验证 | 审计可追溯 |
| 19 | 性能优化：余额查询索引优化、凭证列表加载优化、报表缓存（Redis）；前端大数据量表格虚拟滚动 | 性能达标 |
| 20 | 集成测试覆盖核心业务路径；压力测试（并发过账场景）；安全审计（权限校验、IP 白名单、操作日志）；文档完善 | 发布就绪 |

### 10.8 迁移策略

**不要求一次性全部重写。** 采用逐步替换策略：

```
Step 1: 保持旧控制器不动
Step 2: 新功能使用新架构
Step 3: 旧功能通过 "防腐层" 逐渐迁移

防腐层示例:
class VoucherController extends LegacyBaseController
{
    public function save()
    {
        // 旧逻辑（原样保留）
        // 但内部可以调用新的 AppService 替代硬编码的 Db 操作
        $this->voucherAppService->create($request);
    }
}
```

**核心原则:**
- 不删除已存在的旧控制器，而是逐渐让其委托给新的 Service 层
- 每次只重写一个子域（先 GL 再 AR/AP 再 FA）
- 每个重写的子域必须 100% 覆盖旧功能后再废弃旧代码
- 数据库表结构作为契约，前端和旧控制器可以继续访问

---

## 11. 架构决策记录

### ADR-001: 使用分层架构替代扁平 MVC

- **上下文**: 当前 Controller 直接调用 `Db::table()` 处理全部逻辑
- **决策**: 引入四层架构 (Controller -> ApplicationService -> DomainService -> Repository)
- **理由**: 金融系统业务规则复杂，需要可测试性和可审计性；分层将核心计算逻辑与框架解耦
- **后果**: 增加文件数量和包结构复杂度；但每个文件职责单一，长期维护成本显著降低

### ADR-002: Money 值对象使用字符串存储和 BCMath 运算

- **上下文**: 现有代码使用 `(float)` 进行金额运算，存在精度丢失风险
- **决策**: 所有金额运算使用 PHP 的 `bcmath` 扩展，`Money` 值对象内部使用字符串存储
- **备选方案**: 使用 Decimal128 或整数分存储
- **理由**: DECIMAL(18,2) 配合 BCMath 最符合会计精度要求；字符串避免 float 比较陷阱
- **后果**: 需要确保 PHP bcmath 扩展已安装；运算效率略低于 float，但对财务系统可以接受

### ADR-003: Voucher 作为聚合根保证一致性边界

- **上下文**: 凭证头和分录行在业务上必须保持一致（借贷平衡是系统核心约束）
- **决策**: Voucher 作为聚合根，VoucherEntry 作为聚合内的实体，所有对分录的修改必须通过 Voucher 聚合
- **备选方案**: 凭证头和分录分开管理，在 Service 层协调一致性
- **理由**: 聚合根保证业务不变量不会被绕过（不能单独修改某条分录）
- **后果**: 大聚合在大量分录时可能影响性能（单笔凭证通常不超过 100 行，可以接受）

### ADR-004: 过账使用悲观锁

- **上下文**: 过账操作涉及多个科目余额的原子更新，并发场景下可能产生数据竞争
- **决策**: 使用 `SELECT ... FOR UPDATE` 悲观锁锁定所有涉及科目的余额行
- **备选方案**: 乐观锁（版本号）或应用层队列化
- **理由**: 财务数据一致性要求高于吞吐量；悲观锁防止任何并发写入导致的余额不一致
- **后果**: 并发过账场景下可能出现等待，但实际业务中并发过账量很小（ERP 非高并发系统）

### ADR-005: Repository 接口定义在领域层，实现在基础设施层

- **上下文**: 需要将数据访问与业务逻辑解耦
- **决策**: 遵循依赖倒置原则，Repository 接口定义在 `domain/` 层，实现定义在 `infrastructure/` 层
- **理由**: Domain 层不依赖框架，可移植可测试；替换 ORM 或数据库时只需修改基础设施层
- **后果**: 增加接口定义文件；需要使用 DI 容器绑定接口和实现

### ADR-006: 前端按业务子域拆分 Store

- **上下文**: 当前没有前端状态管理；未来状态复杂度随功能增加而增长
- **决策**: 使用 Pinia，每个业务子域一个 Store（如 `useVoucherStore`、`useSubjectStore`）
- **备选方案**: 单个全局大 Store
- **理由**: 域分离降低耦合；各模块可独立开发测试；Tree-shaking 友好
- **后果**: Store 间需要通信时通过 AppService 或事件总线，不能直接互相引用

### ADR-007: API 使用统一的 JSON 响应格式

- **上下文**: 当前已有 `{code, msg, data}` 的雏形
- **决策**: 统一使用 `{code: int, msg: string, data: mixed}` 格式，所有错误使用错误码
- **理由**: 前端统一拦截器处理错误；错误码便于国际化
- **后果**: 需要定义错误码枚举（见附录）

---

## 附录

### A. 错误码枚举示例

```php
class FinanceErrorCode
{
    // 通用 (40000-40099)
    const VALIDATION_ERROR       = 40000;
    const RESOURCE_NOT_FOUND     = 40004;
    const PERIOD_CLOSED          = 40100;  // 期间已结账
    const PERIOD_NOT_OPEN        = 40101;  // 期间未开账

    // 凭证 (41000-41099)
    const VOUCHER_NOT_BALANCED   = 41000;
    const VOUCHER_MIN_ENTRIES    = 41001;
    const VOUCHER_ALREADY_AUDITED= 41002;
    const VOUCHER_NOT_AUDITED    = 41003;
    const VOUCHER_ALREADY_POSTED = 41004;
    const VOUCHER_CANNOT_UNPOST  = 41005;
    const VOUCHER_SELF_AUDIT     = 41006;

    // 科目 (41100-41199)
    const SUBJECT_CODE_EXISTS    = 41100;
    const SUBJECT_HAS_CHILDREN   = 41101;
    const SUBJECT_HAS_ENTRIES    = 41102;
    const SUBJECT_INVALID_PARENT = 41103;

    // 应收应付 (42000-42099)
    const INSUFFICIENT_AR_BALANCE= 42000;
    const INSUFFICIENT_AP_BALANCE= 42001;
    const PARTIAL_VERIFY_NOT_ALLOWED = 42002;
    const SELF_VERIFICATION      = 42003;

    // 固定资产 (43000-43099)
    const ASSET_ALREADY_DISPOSED = 43000;
    const ASSET_NOT_DEPRECIABLE  = 43001;
    const DEPRECIATION_EXCEEDS_COST = 43002;
}
```

### B. 数据库索引优化建议

```sql
-- finance_voucher: 凭证列表查询加速
CREATE INDEX idx_voucher_book_period ON finance_voucher(book_id, year, period, status);
CREATE INDEX idx_voucher_book_date   ON finance_voucher(book_id, date);

-- finance_voucher_entry: 明细账查询加速
CREATE INDEX idx_entry_voucher      ON finance_voucher_entry(voucher_id);
CREATE INDEX idx_entry_subject_book ON finance_voucher_entry(subject_id, voucher_id);

-- finance_subject_balance: 余额查询加速
CREATE INDEX idx_balance_book_period ON finance_subject_balance(book_id, year, period);
CREATE INDEX idx_balance_subject     ON finance_subject_balance(subject_id, year, period);

-- finance_ar_receivable / finance_ap_payable: 核销查询
CREATE INDEX idx_receivable_partner_status ON finance_ar_receivable(book_id, partner_id, status);
CREATE INDEX idx_payable_partner_status    ON finance_ap_payable(book_id, partner_id, status);
```

### C. ThinkPHP 8 兼容性说明

本架构完全兼容 ThinkPHP 8 的能力：

| ThinkPHP 特性 | 架构中的应用 |
|---------------|-------------|
| PSR-4 自动加载 | `app\` 命名空间下所有类 |
| 依赖注入容器 | ApplicationService 构造函数注入 Repository |
| Service Provider | 用于注册接口与实现的绑定 |
| 中间件 | 鉴权、账套上下文、事务等横切关注点 |
| ORM Model | Repository 实现内部使用（不暴露到 Domain） |
| 数据库迁移 | 用于 DDL 版本管理（可选） |
| 验证器 | Controller 层或 ApplicationService 首部使用 |
| 事件系统 | 领域事件发布（可选用框架事件或独立事件总线） |

不使用的能力：`Db::table()` 直接在 Controller 中调用（仅在 Repository 实现内部使用）。

---

*文档结束*
agentId: abad511ff02b980e0 (use SendMessage with to: 'abad511ff02b980e0' to continue this agent)
<usage>total_tokens: 45842
tool_uses: 52
duration_ms: 390926</usage>
