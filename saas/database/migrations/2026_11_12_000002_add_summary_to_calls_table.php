<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calls') && ! Schema::hasColumn('calls', 'summary')) {
            Schema::table('calls', function (Blueprint $table): void {
                $table->text('summary')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('calls') && Schema::hasColumn('calls', 'summary')) {
            Schema::table('calls', function (Blueprint $table): void {
                $table->dropColumn('summary');
            });
        }
    }
};

