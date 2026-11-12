<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agents')) {
            Schema::table('agents', function (Blueprint $table): void {
                if (! Schema::hasColumn('agents', 'manager_callback_enabled')) {
                    $table->boolean('manager_callback_enabled')->default(false);
                }
                if (! Schema::hasColumn('agents', 'manager_callback_phone')) {
                    $table->string('manager_callback_phone', 32)->nullable();
                }
                if (! Schema::hasColumn('agents', 'ivr_enabled')) {
                    $table->boolean('ivr_enabled')->default(false);
                }
                if (! Schema::hasColumn('agents', 'ivr_greeting')) {
                    $table->text('ivr_greeting')->nullable();
                }
                if (! Schema::hasColumn('agents', 'ivr_routes_json')) {
                    $table->longText('ivr_routes_json')->nullable();
                }
            });
        }

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table): void {
                if (! Schema::hasColumn('sites', 'manager_callback_phone')) {
                    $table->string('manager_callback_phone', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agents')) {
            $drop = array_values(array_filter([
                Schema::hasColumn('agents', 'manager_callback_enabled') ? 'manager_callback_enabled' : null,
                Schema::hasColumn('agents', 'manager_callback_phone') ? 'manager_callback_phone' : null,
                Schema::hasColumn('agents', 'ivr_enabled') ? 'ivr_enabled' : null,
                Schema::hasColumn('agents', 'ivr_greeting') ? 'ivr_greeting' : null,
                Schema::hasColumn('agents', 'ivr_routes_json') ? 'ivr_routes_json' : null,
            ]));
            if ($drop !== []) {
                Schema::table('agents', function (Blueprint $table) use ($drop): void {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'manager_callback_phone')) {
            Schema::table('sites', function (Blueprint $table): void {
                $table->dropColumn('manager_callback_phone');
            });
        }
    }
};

