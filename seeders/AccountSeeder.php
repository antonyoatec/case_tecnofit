<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use App\Model\Account;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test accounts with different balance amounts
        $accounts = [
            [
                'name' => 'João Silva',
                'balance' => 1000.00,
            ],
            [
                'name' => 'Maria Santos',
                'balance' => 2500.50,
            ],
            [
                'name' => 'Pedro Oliveira',
                'balance' => 500.75,
            ],
            [
                'name' => 'Ana Costa',
                'balance' => 10000.00,
            ],
            [
                'name' => 'Carlos Ferreira',
                'balance' => 150.25,
            ],
        ];

        foreach ($accounts as $accountData) {
            Account::create($accountData);
        }

        $this->command->info('Created ' . count($accounts) . ' test accounts');
    }
}