<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('phone')->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('asaas_customer_id')->nullable()->index()->after('email');
        });

        // Backfill existing records from users table
        DB::statement('
            UPDATE team_members
            SET name = (SELECT name FROM users WHERE users.id = team_members.user_id),
                phone = COALESCE((SELECT phone FROM users WHERE users.id = team_members.user_id), \'\'),
                email = (SELECT email FROM users WHERE users.id = team_members.user_id)
            WHERE user_id IS NOT NULL
        ');

        // Drop both FKs first (MySQL uses the composite unique index for team_id FK)
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['team_id']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'user_id']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['team_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'phone']);
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['team_id', 'user_id']);

            $table->dropIndex(['asaas_customer_id']);
            $table->dropColumn(['name', 'phone', 'email', 'asaas_customer_id']);
        });
    }
};
