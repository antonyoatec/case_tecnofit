<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AccountWithdraw;
use Hyperf\Database\Model\Collection;
use Hyperf\DbConnection\Db;
use Carbon\Carbon;

/**
 * Implementação do Repositório de Saque de Conta - FOCADO NOS REQUISITOS DO CASE
 * Gerencia operações de saque com atualizações atômicas para escalabilidade horizontal
 */
class AccountWithdrawRepository implements AccountWithdrawRepositoryInterface
{
    /**
     * Cria novo registro de saque
     */
    public function create(AccountWithdraw $withdraw): AccountWithdraw
    {
        $withdraw->save();
        return $withdraw;
    }

    /**
     * Encontra saques agendados pendentes prontos para processar
     * CRÍTICO: Usado pelo cron job com atualização atômica de status
     */
    public function findPendingScheduled(int $limit = 50): Collection
    {
        // Primeiro, reserva atomicamente os saques para prevenir processamento duplicado
        // Isso é CRÍTICO para escalabilidade horizontal
        $sql = "UPDATE account_withdraw 
                SET status = 'PROCESSING', updated_at = NOW() 
                WHERE status = 'PENDING' 
                AND scheduled = true 
                AND scheduled_for <= NOW() 
                LIMIT ?";
        
        $affected = Db::update($sql, [$limit]);
        
        if ($affected === 0) {
            return new Collection();
        }

        // Agora busca os saques reservados
        return AccountWithdraw::query()
            ->where('status', AccountWithdraw::STATUS_PROCESSING)
            ->where('scheduled', true)
            ->with(['account', 'pixDetails'])  // Eager load relationships
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();
    }

    /**
     * Atualiza status do saque atomicamente
     * Previne condições de corrida entre múltiplos containers
     */
    public function updateStatusAtomically(string $id, string $fromStatus, string $toStatus): bool
    {
        $affected = AccountWithdraw::query()
            ->where('id', $id)
            ->where('status', $fromStatus)
            ->update([
                'status' => $toStatus,
                'updated_at' => Carbon::now(),
            ]);

        return $affected > 0;
    }

    /**
     * Marca saque como concluído
     */
    public function markAsCompleted(string $id): bool
    {
        $affected = AccountWithdraw::query()
            ->where('id', $id)
            ->update([
                'status' => AccountWithdraw::STATUS_DONE,
                'updated_at' => Carbon::now(),
            ]);

        return $affected > 0;
    }

    /**
     * Marca saque como rejeitado com motivo do erro
     * Usado quando saldo insuficiente é detectado
     */
    public function markAsRejected(string $id, string $errorReason): bool
    {
        $affected = AccountWithdraw::query()
            ->where('id', $id)
            ->update([
                'status' => AccountWithdraw::STATUS_REJECTED,
                'error_reason' => $errorReason,
                'updated_at' => Carbon::now(),
            ]);

        return $affected > 0;
    }

    /**
     * Encontra saque por ID
     */
    public function findById(string $id): ?AccountWithdraw
    {
        return AccountWithdraw::find($id);
    }
}