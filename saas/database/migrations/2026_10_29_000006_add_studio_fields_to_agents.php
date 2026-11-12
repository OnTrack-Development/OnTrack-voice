<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            if (! Schema::hasColumn('agents', 'instructions')) {
                $table->text('instructions')->nullable()->after('kind');
            }
            if (! Schema::hasColumn('agents', 'persona')) {
                $table->string('persona', 120)->nullable()->after('instructions');
            }
            if (! Schema::hasColumn('agents', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['instructions', 'persona', 'published_at'],
                fn (string $c): bool => Schema::hasColumn('agents', $c)
            ));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};