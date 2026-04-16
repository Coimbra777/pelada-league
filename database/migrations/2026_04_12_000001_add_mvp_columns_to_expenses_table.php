<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_per_member', 10, 2)->nullable()->after('total_amount');
            $table->string('pix_key')->nullable()->after('due_date');
            $table->text('pix_qr_code')->nullable()->after('pix_key');
            $table->string('public_hash')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['amount_per_member', 'pix_key', 'pix_qr_code', 'public_hash']);
        });
    }
};
