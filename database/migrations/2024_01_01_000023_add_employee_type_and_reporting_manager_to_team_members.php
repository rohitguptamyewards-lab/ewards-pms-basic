<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            if (!Schema::hasColumn('team_members', 'employee_type')) {
                $table->string('employee_type')->nullable()->after('role');
            }
            if (!Schema::hasColumn('team_members', 'reporting_manager_id')) {
                $table->unsignedBigInteger('reporting_manager_id')->nullable()->after('employee_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['employee_type', 'reporting_manager_id']);
        });
    }
};
