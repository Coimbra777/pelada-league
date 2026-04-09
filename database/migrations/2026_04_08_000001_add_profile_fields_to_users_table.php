<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('password');
            $table->string('cpf')->nullable()->unique()->after('phone');
            $table->string('asaas_customer_id')->nullable()->index()->after('cpf');
            $table->boolean('is_active')->default(true)->after('asaas_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['asaas_customer_id']);
            $table->dropUnique(['cpf']);
            $table->dropColumn(['phone', 'cpf', 'asaas_customer_id', 'is_active']);
        });
    }
};
