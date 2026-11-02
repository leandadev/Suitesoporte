<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('client_name');
            $table->string('branch_code')->default('01');
            $table->string('action_type'); // PRODUCT_UPDATE_SYNC, CANCEL_ANTICIPO, UNLOCK_POS, etc.
            $table->string('target_id')->nullable(); // Codigo Producto o Documento Anticipo
            $table->string('ticket_number')->nullable();
            $table->string('status')->default('SUCCESS'); // SUCCESS, FAILED, DRY_RUN
            $table->text('notes')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_audit_logs');
    }
};
