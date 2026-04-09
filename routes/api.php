<?php
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\PlannerController;
use App\Http\Controllers\Api\V1\StageController;
use App\Http\Controllers\Api\V1\UpdateController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Controllers\Api\V1\TicketLinkController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\BlockerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\WorkLogController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->middleware(['auth'])->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show', 'update']);

    // Project Attachments
    Route::post('projects/{id}/attachments', [\App\Http\Controllers\Api\V1\ProjectController::class, 'uploadAttachment']);
    Route::delete('attachments/{id}', [\App\Http\Controllers\Api\V1\ProjectController::class, 'deleteAttachment']);

    // Planners
    Route::get('projects/{projectId}/planners', [PlannerController::class, 'index']);
    Route::post('planners', [PlannerController::class, 'store']);
    Route::put('planners/{id}', [PlannerController::class, 'update']);
    Route::delete('planners/{id}', [PlannerController::class, 'destroy']);
    Route::post('projects/{projectId}/planners/reorder', [PlannerController::class, 'reorder']);

    // Workers
    Route::get('projects/{projectId}/workers', [WorkerController::class, 'index']);
    Route::post('projects/{projectId}/workers/owner', [WorkerController::class, 'assignOwner']);
    Route::post('projects/{projectId}/workers/contributor', [WorkerController::class, 'addContributor']);
    Route::delete('projects/{projectId}/workers/{userId}', [WorkerController::class, 'removeContributor']);

    // Stages
    Route::get('projects/{projectId}/stage', [StageController::class, 'show']);
    Route::put('projects/{projectId}/stage', [StageController::class, 'update']);
    Route::get('projects/{projectId}/stage/history', [StageController::class, 'history']);

    // Updates
    Route::get('projects/{projectId}/updates', [UpdateController::class, 'index']);
    Route::post('projects/{projectId}/updates', [UpdateController::class, 'store']);

    // Ticket Links
    Route::get('projects/{projectId}/tickets', [TicketLinkController::class, 'index']);
    Route::post('projects/{projectId}/tickets', [TicketLinkController::class, 'store']);
    Route::delete('tickets/{id}', [TicketLinkController::class, 'destroy']);

    // Transfers
    Route::get('projects/{projectId}/transfers', [TransferController::class, 'index']);
    Route::post('projects/{projectId}/transfers', [TransferController::class, 'store']);

    // Blockers
    Route::get('projects/{projectId}/blockers', [BlockerController::class, 'index']);
    Route::post('projects/{projectId}/blockers', [BlockerController::class, 'store']);
    Route::put('blockers/{id}/resolve', [BlockerController::class, 'resolve']);

    // Dashboard
    Route::get('dashboard/manager', [DashboardController::class, 'manager']);
    Route::get('dashboard/employee', [DashboardController::class, 'employee']);
    Route::get('dashboard/activity-report', [DashboardController::class, 'activityReport']);

    // Reports
    Route::get('reports/projects', [ReportController::class, 'projects']);
    Route::get('reports/workers', [ReportController::class, 'workers']);
    Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/projects/{projectId}/worklogs', [ReportController::class, 'projectWorklogs']);
    Route::get('reports/members/{memberId}/worklogs', [ReportController::class, 'memberWorklogs']);

    // Work Logs
    Route::get('work-logs', [WorkLogController::class, 'index']);
    Route::post('work-logs', [WorkLogController::class, 'store']);
    Route::put('work-logs/{id}', [WorkLogController::class, 'update']);
    Route::delete('work-logs/{id}', [WorkLogController::class, 'destroy']);

    // Team Members
    Route::put('team-members/{id}', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'update']);
    Route::post('team-members/{id}/toggle-active', [\App\Http\Controllers\Api\V1\TeamMemberController::class, 'toggleActive']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Dropdowns
    Route::get('team-members', function () {
        return response()->json(
            \Illuminate\Support\Facades\DB::table('team_members')
                ->select('id', 'name', 'email', 'role')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    });
});
