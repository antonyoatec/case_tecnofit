<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\WithdrawRequestDto;
use App\Service\WithdrawOrchestrator;
use App\Validation\PixKeyValidator;

use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

/**
 * Controlador de Conta - FOCADO NOS REQUISITOS DO CASE
 * Gerencia o endpoint POST /account/{id}/balance/withdraw
 */
class AccountController extends AbstractController
{
    #[Inject]
    private WithdrawOrchestrator $withdrawOrchestrator;

    #[Inject]
    private PixKeyValidator $pixKeyValidator;

    #[Inject]
    private LoggerInterface $logger;

    #[Inject]
    private ValidatorFactoryInterface $validatorFactory;

    /**
     * Obtém o saldo da conta
     * GET /account/{id}/balance
     */
    public function getBalance(string $id)
    {
        try {
            $account = \App\Model\Account::find($id);
            
            if (!$account) {
                return $this->response->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ACCOUNT_NOT_FOUND',
                        'message' => 'Account not found'
                    ]
                ])->withStatus(404);
            }

            return $this->response->json([
                'success' => true,
                'data' => [
                    'account_id' => $account->id,
                    'name' => $account->name,
                    'balance' => (float) $account->balance
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error getting account balance', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->response->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Internal server error'
                ]
            ])->withStatus(500);
        }
    }

    /**
     * Processa requisição de saque - ENDPOINT PRINCIPAL DO CASE
     * POST /account/{id}/balance/withdraw
     */
    public function withdraw(string $id)
    {
        $this->logger->info('Withdrawal request received', [
            'account_id' => $id,
            'ip' => $this->request->getServerParams()['remote_addr'] ?? 'unknown',
            'user_agent' => $this->request->getHeaderLine('user-agent')
        ]);

        try {
            // Obtém e valida os dados da requisição
            $requestData = $this->request->all();
            
            // Validação básica da requisição
            $validator = $this->validatorFactory->make($requestData, [
                'method' => 'required|string|in:pix',
                'amount' => 'required|numeric|min:0.01',
                'pix_key' => 'required|string|email',
                'scheduled_for' => 'nullable|date|after:now'
            ]);

            if ($validator->fails()) {
                $this->logger->warning('Withdrawal request validation failed', [
                    'account_id' => $id,
                    'errors' => $validator->errors()->toArray()
                ]);

                return $this->response->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Invalid request data',
                        'details' => $validator->errors()->toArray()
                    ]
                ])->withStatus(400);
            }

            // Cria DTO a partir da requisição
            $withdrawRequest = WithdrawRequestDto::fromArray($requestData);

            // Valida usando o orquestrador
            $validationResult = $this->withdrawOrchestrator->validateRequest($withdrawRequest);
            if ($validationResult->isFailure()) {
                $this->logger->warning('Withdrawal orchestrator validation failed', [
                    'account_id' => $id,
                    'error' => $validationResult->errorMessage
                ]);

                return $this->response->json([
                    'success' => false,
                    'error' => [
                        'code' => $validationResult->errorCode ?? 'VALIDATION_ERROR',
                        'message' => $validationResult->errorMessage
                    ]
                ])->withStatus(400);
            }

            // Processa o saque
            $result = $this->withdrawOrchestrator->processWithdraw($id, $withdrawRequest);
            
            if ($result->isSuccess()) {
                $this->logger->info('Withdrawal processed successfully', [
                    'account_id' => $id,
                    'withdraw_id' => $result->metadata['withdraw_id'] ?? null,
                    'amount' => $withdrawRequest->amount,
                    'status' => $result->metadata['status'] ?? 'unknown'
                ]);

                return $this->response->json([
                    'success' => true,
                    'data' => $result->metadata
                ])->withStatus(200);
            } else {
                $errorCode = $result->errorCode ?? 'PROCESSING_ERROR';
                $statusCode = $this->getHttpStatusFromErrorCode($errorCode);

                $this->logger->warning('Withdrawal processing failed', [
                    'account_id' => $id,
                    'error_code' => $errorCode,
                    'error_message' => $result->errorMessage
                ]);

                return $this->response->json([
                    'success' => false,
                    'error' => [
                        'code' => $errorCode,
                        'message' => $result->errorMessage
                    ]
                ])->withStatus($statusCode);
            }

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in withdrawal endpoint', [
                'account_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Internal server error'
                ]
            ])->withStatus(500);
        }
    }

    /**
     * Mapeia códigos de erro para códigos de status HTTP
     */
    private function getHttpStatusFromErrorCode(string $errorCode): int
    {
        return match ($errorCode) {
            'ACCOUNT_NOT_FOUND' => 404,
            'INSUFFICIENT_BALANCE' => 422,
            'VALIDATION_ERROR', 'INVALID_AMOUNT', 'MISSING_PIX_KEY', 'INVALID_SCHEDULED_DATE' => 400,
            'CONCURRENCY_ERROR' => 409,
            default => 500
        };
    }
}