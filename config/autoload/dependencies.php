<?php

declare(strict_types=1);

use App\Repository\AccountRepositoryInterface;
use App\Repository\AccountRepository;
use App\Repository\AccountWithdrawRepositoryInterface;
use App\Repository\AccountWithdrawRepository;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use App\Repository\AccountWithdrawPixRepository;

return [
    // Repository bindings for dependency injection
    AccountRepositoryInterface::class => AccountRepository::class,
    AccountWithdrawRepositoryInterface::class => AccountWithdrawRepository::class,
    AccountWithdrawPixRepositoryInterface::class => AccountWithdrawPixRepository::class,
    
    // Strategy factory is auto-registered via #[Component] annotation
];