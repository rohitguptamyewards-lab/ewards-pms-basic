<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('work_type')->nullable()->after('priority');
            $table->string('task_type')->nullable()->after('work_type');
            $table->text('ticket_link')->nullable()->after('task_type');
            $table->unsignedBigInteger('analyst_id')->nullable()->after('owner_id');
            $table->unsignedBigInteger('analyst_testing_id')->nullable()->after('analyst_id');
            $table->unsignedBigInteger('developer_id')->nullable()->after('analyst_testing_id');
            $table->text('document_link')->nullable()->after('linked_project_ids');
            $table->text('ai_chat_link')->nullable()->after('document_link');

            $table->index('analyst_id');
            $table->index('analyst_testing_id');
            $table->index('developer_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'work_type', 'task_type', 'ticket_link',
                'analyst_id', 'analyst_testing_id', 'developer_id',
                'document_link', 'ai_chat_link',
            ]);
        });
    }
};
