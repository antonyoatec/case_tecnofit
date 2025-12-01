<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateAccountTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account', function (Blueprint $table) {
            $table->char('id', 36)->primary()->comment('UUID primary key');
            $table->string('name', 255)->comment('Account holder name');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Account balance');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('Last update timestamp');
            
            // Indexes for performance
            $table->index('balance', 'idx_account_balance');
            $table->index('created_at', 'idx_account_created_at');
            
            // Add table comment
            $table->comment('Account table for digital account management');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account');
    }
}