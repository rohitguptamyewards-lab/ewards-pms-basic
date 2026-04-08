<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (!Schema::hasColumn('projects', 'work_type')) {
                    $table->string('work_type')->nullable()->after('priority');
                }
                if (!Schema::hasColumn('projects', 'task_type')) {
                    $table->string('task_type')->nullable()->after('work_type');
                }
                if (!Schema::hasColumn('projects', 'ticket_link')) {
                    $table->text('ticket_link')->nullable()->after('task_type');
                }
                if (!Schema::hasColumn('projects', 'analyst_id')) {
                    $table->unsignedBigInteger('analyst_id')->nullable()->after('owner_id');
                }
                if (!Schema::hasColumn('projects', 'analyst_testing_id')) {
                    $table->unsignedBigInteger('analyst_testing_id')->nullable()->after('analyst_id');
                }
                if (!Schema::hasColumn('projects', 'developer_id')) {
                    $table->unsignedBigInteger('developer_id')->nullable()->after('analyst_testing_id');
                }
                if (!Schema::hasColumn('projects', 'document_link')) {
                    $table->text('document_link')->nullable()->after('linked_project_ids');
                }
                if (!Schema::hasColumn('projects', 'ai_chat_link')) {
                    $table->text('ai_chat_link')->nullable()->after('document_link');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn([
                    'work_type', 'task_type', 'ticket_link',
                    'analyst_id', 'analyst_testing_id', 'developer_id',
                    'document_link', 'ai_chat_link',
                ]);
            });
        }
    }
};
