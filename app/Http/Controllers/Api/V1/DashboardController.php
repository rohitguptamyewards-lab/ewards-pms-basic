<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role->value ?? $user->role;

        if (in_array($role, ['manager', 'analyst_head', 'analyst'])) {
            return $this->renderManagerDashboard();
        }

        return $this->renderEmployeeDashboard($user->id);
    }

    private function renderManagerDashboard()
    {
        $totalProjects = DB::table('projects')->whereNull('deleted_at')->count();
        $activeProjects = DB::table('projects')->whereNull('deleted_at')->where('status', 'active')->count();
        $onHoldProjects = DB::table('projects')->whereNull('deleted_at')->where('status', 'on_hold')->count();

        $activeBlockers = DB::table('project_blockers')
            ->join('projects', 'project_blockers.project_id', '=', 'projects.id')
            ->leftJoin('team_members', 'project_blockers.created_by', '=', 'team_members.id')
            ->select('project_blockers.*', 'projects.name as project_name', 'team_members.name as creator_name')
            ->where('project_blockers.status', 'active')
            ->whereNull('projects.deleted_at')
            ->orderByDesc('project_blockers.created_at')
            ->limit(10)
            ->get();

        $overduePlanners = DB::table('project_planners')
            ->join('projects', 'project_planners.project_id', '=', 'projects.id')
            ->leftJoin('team_members', 'project_planners.assigned_to', '=', 'team_members.id')
            ->select('project_planners.*', 'projects.name as project_name', 'team_members.name as assignee_name')
            ->where('project_planners.status', '!=', 'done')
            ->whereNotNull('project_planners.due_date')
            ->where('project_planners.due_date', '<', now()->toDateString())
            ->whereNull('projects.deleted_at')
            ->orderBy('project_planners.due_date')
            ->limit(10)
            ->get();

        $recentProjects = DB::table('projects')
            ->leftJoin('team_members', 'projects.owner_id', '=', 'team_members.id')
            ->select(
                'projects.id', 'projects.name', 'projects.status', 'projects.priority',
                'team_members.name as owner_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->whereNull('projects.deleted_at')
            ->orderByDesc('projects.updated_at')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard/Manager', [
            'totalProjects'   => $totalProjects,
            'activeProjects'  => $activeProjects,
            'onHoldProjects'  => $onHoldProjects,
            'activeBlockers'  => $activeBlockers,
            'overduePlanners' => $overduePlanners,
            'recentProjects'  => $recentProjects,
        ]);
    }

    private function renderEmployeeDashboard(int $userId)
    {
        $myProjects = DB::table('projects')
            ->leftJoin('project_workers', function ($join) use ($userId) {
                $join->on('projects.id', '=', 'project_workers.project_id')
                     ->where('project_workers.user_id', '=', $userId);
            })
            ->leftJoin('team_members', 'projects.owner_id', '=', 'team_members.id')
            ->select(
                'projects.id', 'projects.name', 'projects.status', 'projects.priority',
                'projects.work_type', 'projects.developer_id', 'projects.analyst_testing_id',
                'project_workers.role as my_role',
                'team_members.name as owner_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->where(function ($q) use ($userId) {
                $q->where('project_workers.user_id', $userId)
                  ->orWhere('projects.owner_id', $userId)
                  ->orWhere('projects.analyst_id', $userId)
                  ->orWhere('projects.analyst_testing_id', $userId)
                  ->orWhere('projects.developer_id', $userId);
            })
            ->whereNull('projects.deleted_at')
            ->distinct()
            ->orderByDesc('projects.updated_at')
            ->get();

        $myPlanners = DB::table('project_planners')
            ->join('projects', 'project_planners.project_id', '=', 'projects.id')
            ->select('project_planners.*', 'projects.name as project_name')
            ->where('project_planners.assigned_to', $userId)
            ->where('project_planners.status', '!=', 'done')
            ->whereNull('projects.deleted_at')
            ->orderBy('project_planners.due_date')
            ->get();

        $myBlockers = DB::table('project_blockers')
            ->join('projects', 'project_blockers.project_id', '=', 'projects.id')
            ->select('project_blockers.*', 'projects.name as project_name')
            ->where('project_blockers.created_by', $userId)
            ->where('project_blockers.status', 'active')
            ->whereNull('projects.deleted_at')
            ->get();

        return Inertia::render('Dashboard/Employee', [
            'myProjects'  => $myProjects,
            'myPlanners'  => $myPlanners,
            'myBlockers'  => $myBlockers,
        ]);
    }

    public function manager(Request $request): JsonResponse
    {
        $role = auth()->user()->role->value ?? auth()->user()->role;
        abort_unless($role === 'manager', 403);
        return response()->json(['message' => 'Use web route']);
    }

    public function employee(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Use web route']);
    }
}
