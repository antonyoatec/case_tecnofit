<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateAccountWithdrawTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_withdraw', function (Blueprint $table) {
            $table->char('id', 36)->primary()->comment('UUID primary key');
            $table->char('account_id', 36)->comment('Reference to account table');
            $table->string('method', 50)->comment('Withdrawal method (pix, ted, etc.)');
            $table->decimal('amount', 15, 2)->comment('Withdrawal amount');
            $table->boolean('scheduled')->default(false)->comment('Whether withdrawal is scheduled');
            $table->datetime('scheduled_for')->nullable()->comment('When to process scheduled withdrawal');
            $table->enum('status', ['PENDING', 'PROCESSING', 'DONE', 'REJECTED'])
                  ->default('PENDING')
                  ->comment('Withdrawal processing status');
            $table->text('error_reason')->nullable()->comment('Error reason if withdrawal failed');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Last update timestamp');

            
            // Foreign key constraint
            $table->foreign('account_id', 'fk_account_withdraw_account_id')
                  ->references('id')
                  ->on('account')
                  ->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['status', 'scheduled_for'], 'idx_status_scheduled');
            $table->index(['account_id', 'status'], 'idx_account_status');
            $table->index(['status', 'scheduled_for', 'created_at'], 'idx_processing_queue');
            $table->index('method', 'idx_withdrawal_method');
            $table->index('created_at', 'idx_withdrawal_created_at');
            
            // Add table comment
            $table->comment('Account withdrawal transactions table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_withdraw');
    }
}