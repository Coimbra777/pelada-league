<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expenses')
            ->whereNotIn('status', ['open', 'closed'])
            ->update(['status' => 'open']);
    }

    public function down(): void
    {
        //
    }
};
