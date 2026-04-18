<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('projects', 'expected_live_date')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->date('expected_live_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'expected_live_date')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('expected_live_date');
            });
        }
    }
};
