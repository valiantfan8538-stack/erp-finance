<?php

use app\ExceptionHandle;
use app\Request;
use app\domain\finance\repository\SubjectRepositoryInterface;
use app\domain\finance\repository\SubjectBalanceRepositoryInterface;
use app\domain\finance\repository\VoucherRepositoryInterface;
use app\domain\system\repository\AccountingPeriodRepositoryInterface;
use app\domain\arap\repository\ReceivableRepositoryInterface;
use app\domain\arap\repository\PayableRepositoryInterface;
use app\domain\arap\repository\VerifyRecordRepositoryInterface;
use app\domain\asset\repository\AssetCardRepositoryInterface;
use app\domain\asset\repository\AssetDepreciationRepositoryInterface;
use app\application\finance\SubjectAppService;
use app\application\finance\VoucherAppService;
use app\application\system\PeriodAppService;
use app\application\arap\ARAPAppService;
use app\application\asset\AssetAppService;
use app\domain\finance\service\PostingEngine;
use app\domain\arap\service\VerificationEngine;
use app\domain\asset\service\DepreciationEngine;
use app\domain\finance\service\AuditService;
use app\domain\finance\service\ReportService;

return [
    'think\Request'          => Request::class,
    'think\exception\Handle' => ExceptionHandle::class,

    // System repositories
    AccountingPeriodRepositoryInterface::class => \app\infrastructure\system\ThinkOrmAccountingPeriodRepository::class,

    // Finance repositories
    SubjectRepositoryInterface::class        => \app\infrastructure\finance\ThinkOrmSubjectRepository::class,
    VoucherRepositoryInterface::class        => \app\infrastructure\finance\ThinkOrmVoucherRepository::class,
    SubjectBalanceRepositoryInterface::class => \app\infrastructure\finance\ThinkOrmSubjectBalanceRepository::class,

    // AR/AP repositories
    ReceivableRepositoryInterface::class   => \app\infrastructure\arap\ThinkOrmReceivableRepository::class,
    PayableRepositoryInterface::class      => \app\infrastructure\arap\ThinkOrmPayableRepository::class,
    VerifyRecordRepositoryInterface::class => \app\infrastructure\arap\ThinkOrmVerifyRecordRepository::class,

    // Asset repositories
    AssetCardRepositoryInterface::class        => \app\infrastructure\asset\ThinkOrmAssetCardRepository::class,
    AssetDepreciationRepositoryInterface::class => \app\infrastructure\asset\ThinkOrmAssetDepreciationRepository::class,

    // Application services
    PeriodAppService::class  => PeriodAppService::class,
    SubjectAppService::class => SubjectAppService::class,
    VoucherAppService::class => VoucherAppService::class,
    ARAPAppService::class    => ARAPAppService::class,
    AssetAppService::class   => AssetAppService::class,

    // Domain services
    PostingEngine::class     => PostingEngine::class,
    VerificationEngine::class => VerificationEngine::class,
    DepreciationEngine::class => DepreciationEngine::class,
    AuditService::class       => AuditService::class,
    ReportService::class      => ReportService::class,
];
