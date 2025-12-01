<?php

declare(strict_types=1);

namespace App\Strategy;

use App\Dto\WithdrawRequestDto;
use App\Dto\ValidationResultDto;
use App\Dto\ProcessResultDto;
use App\Model\Account;
use App\Model\AccountWithdraw;
use App\Model\AccountWithdrawPix;
use App\Validation\PixKeyValidator;
use App\Repository\AccountWithdrawPixRepositoryInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;

/**
 * Estratégia de Saque PIX - FOCADO NOS REQUISITOS DO CASE
 * Gerencia saques PIX apenas com chaves de email
 */
class PixWithdrawStrategy implements WithdrawMethodInterface
{
    #[Inject]
    private PixKeyValidator $pixKeyValidator;

    #[Inject]
    private AccountWithdrawPixRepositoryInterface $pixRepository;

    /**
     * Verifica se esta estratégia suporta o método PIX
     */
    public function supports(string $method): bool
    {
        return strtolower($method) === 'pix';
    }

    /**
     * Valida requisição de saque PIX
     */
    public function validate(WithdrawRequestDto $request): ValidationResultDto
    {
        // Validação básica
        if ($request->amount <= 0) {
            return ValidationResultDto::invalid([
                ['field' => 'amount', 'message' => 'Amount must be greater than zero', 'code' => 'INVALID_AMOUNT']
            ]);
        }

        // Validação de chave PIX email
        return $this->pixKeyValidator->validate($request->pixKey);
    }

    /**
     * Processa saque PIX
     * Este método gerencia a lógica específica do PIX
     * Nota: Detalhes do PIX já foram criados pelo serviço
     */
    public function process(Account $account, AccountWithdraw $withdraw): ProcessResultDto
    {
        try {
            // Obtém detalhes do PIX (já criados pelo serviço)
            $pixDetails = $this->pixRepository->findByWithdrawId($withdraw->id);
            
            if (!$pixDetails) {
                return ProcessResultDto::failure(
                    'PIX details not found',
                    ['withdraw_id' => $withdraw->id]
                );
            }

            // Processamento PIX aconteceria aqui
            // Para este case, apenas simulamos sucesso
            // Em um cenário real, isso chamaria uma API PIX externa
            
            return ProcessResultDto::success([
                'pix_key' => $pixDetails->pix_key,
                'pix_type' => $pixDetails->type,
                'method' => 'pix'
            ]);

        } catch (\Exception $e) {
            return ProcessResultDto::failure(
                'PIX processing failed: ' . $e->getMessage(),
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Obtém campos obrigatórios para saque PIX
     */
    public function getRequiredFields(): array
    {
        return [
            'method',
            'amount', 
            'pix_key'
        ];
    }
}