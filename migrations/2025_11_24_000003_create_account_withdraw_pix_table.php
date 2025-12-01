<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateAccountWithdrawPixTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_withdraw_pix', function (Blueprint $table) {
            $table->char('id', 36)->primary()->comment('UUID primary key');
            $table->char('account_withdraw_id', 36)->comment('Reference to account_withdraw table');
            $table->enum('type', ['EMAIL'])
                  ->default('EMAIL')
                  ->comment('PIX key type - Only EMAIL as per case requirements');
            $table->string('key', 255)->comment('PIX key value');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            
            // Foreign key constraint with cascade delete
            $table->foreign('account_withdraw_id', 'fk_account_withdraw_pix_withdraw_id')
                  ->references('id')
                  ->on('account_withdraw')
                  ->onDelete('cascade');
            
            // Indexes for performance
            $table->index('account_withdraw_id', 'idx_withdraw_pix_withdraw_id');
            $table->index(['type', 'key'], 'idx_pix_type_key');
            $table->index('created_at', 'idx_pix_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_withdraw_pix');
    }
}