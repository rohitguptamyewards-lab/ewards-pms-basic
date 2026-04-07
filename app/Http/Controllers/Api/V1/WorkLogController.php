<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogRequest;
use App\Repositories\WorkLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WorkLogController extends Controller
{
    public function __construct(
        private readonly WorkLogRepository $workLogRepository,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $this->authRole();
        $filters = $request->only(['user_id', 'project_id', 'date_from', 'date_to', 'status']);

        // Non-managers can only see their own logs
        if (!in_array($role, ['manager', 'analyst_head', 'analyst'])) {
            $filters['user_id'] = $user->id;
        }

        $workLogs = $this->workLogRepository->findAll($filters);

        // Get all projects for the dropdown (show project names)
        $projects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        // Get team members for filter dropdown (managers/analysts only)
        $teamMembers = in_array($role, ['manager', 'analyst_head', 'analyst'])
            ? DB::table('team_members')
                ->select('id', 'name', 'role')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        // Calculate week total for current user
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $weekTotal = $this->workLogRepository->sumHoursForUser(
            $filters['user_id'] ?? $user->id,
            $weekStart,
            $weekEnd
        );

        if ($request->wantsJson()) {
            return response()->json($workLogs);
        }

        return Inertia::render('WorkLogs/Index', [
            'workLogs'    => $workLogs,
            'projects'    => $projects,
            'teamMembers' => $teamMembers,
            'weekTotal'   => round($weekTotal, 2),
            'filters'     => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $projects = DB::table('projects')
            ->select('id', 'name')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        // Auto-fill last end time as new start time
        $lastEndTime = $this->workLogRepository->getLastEndTime(
            auth()->id(),
            now()->toDateString()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'projects'     => $projects,
                'lastEndTime'  => $lastEndTime,
            ]);
        }

        return Inertia::render('WorkLogs/Create', [
            'projects'    => $projects,
            'lastEndTime' => $lastEndTime,
        ]);
    }

    public function store(StoreWorkLogRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        $id = $this->workLogRepository->create($data);

        if ($request->wantsJson()) {
            return response()->json($this->workLogRepository->findById($id), 201);
        }

        return redirect()->route('work-logs.index')
            ->with('success', 'Work log added successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $log = $this->workLogRepository->findById($id);

        if (!$log) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Only own logs or manager
        $role = $this->authRole();
        if ($log->user_id !== auth()->id() && !in_array($role, ['manager', 'analyst_head', 'analyst'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'log_date'   => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'date_format:H:i'],
            'status'     => ['sometimes', 'string'],
            'note'       => ['nullable', 'string'],
            'blocker'    => ['nullable', 'string'],
        ]);

        $this->workLogRepository->update($id, $data);

        return response()->json($this->workLogRepository->findById($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $log = $this->workLogRepository->findById($id);

        if (!$log) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $role = $this->authRole();
        if ($log->user_id !== auth()->id() && !in_array($role, ['manager', 'analyst_head', 'analyst'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->workLogRepository->delete($id);

        return response()->json(['message' => 'Deleted']);
    }

    private function authRole(): string
    {
        $role = auth()->user()->role;
        return $role instanceof \App\Enums\Role ? $role->value : (string) $role;
    }
}
