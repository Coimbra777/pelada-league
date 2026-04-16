<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->change();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('created_by');
            $table->string('owner_phone', 32)->nullable()->after('owner_name');
            $table->string('manage_token', 64)->nullable()->after('public_hash');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('unique_hash', 64)->nullable()->after('team_id');
        });

        while (DB::table('team_members')->whereNull('unique_hash')->exists()) {
            $ids = DB::table('team_members')->whereNull('unique_hash')->limit(500)->pluck('id');
            foreach ($ids as $id) {
                DB::table('team_members')->where('id', $id)->update([
                    'unique_hash' => (string) Str::uuid(),
                ]);
            }
        }

        while (DB::table('expenses')->whereNull('manage_token')->exists()) {
            $ids = DB::table('expenses')->whereNull('manage_token')->limit(500)->pluck('id');
            foreach ($ids as $id) {
                DB::table('expenses')->where('id', $id)->update([
                    'manage_token' => (string) Str::uuid(),
                ]);
            }
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->unique('manage_token');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->unique('unique_hash');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('manage_token', 64)->nullable(false)->change();
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('unique_hash', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['manage_token']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropUnique(['unique_hash']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'owner_phone', 'manage_token']);
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('unique_hash');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable(false)->change();
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
