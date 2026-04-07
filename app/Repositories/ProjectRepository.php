<?php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class ProjectRepository
{
    public function findAll(array $filters = [], int $perPage = 20)
    {
        $query = DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('team_members as analyst', 'projects.analyst_id', '=', 'analyst.id')
            ->leftJoin('team_members as tester', 'projects.analyst_testing_id', '=', 'tester.id')
            ->leftJoin('team_members as dev', 'projects.developer_id', '=', 'dev.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'analyst.name as analyst_name',
                'tester.name as analyst_testing_name',
                'dev.name as developer_name',
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id) as planner_count'),
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id AND project_planners.status = \'done\') as planner_done_count'),
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->whereNull('projects.deleted_at');

        if (!empty($filters['status'])) {
            $query->where('projects.status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('projects.priority', $filters['priority']);
        }
        if (!empty($filters['owner_id'])) {
            $query->where('projects.owner_id', $filters['owner_id']);
        }
        if (!empty($filters['work_type'])) {
            $query->where('projects.work_type', $filters['work_type']);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('projects.name', 'like', $s)
                  ->orWhere('projects.description', 'like', $s);
            });
        }

        return $query->orderByDesc('projects.created_at')->paginate($perPage);
    }

    public function findByWorker(int $userId, array $filters = [], int $perPage = 20)
    {
        $query = DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('team_members as analyst', 'projects.analyst_id', '=', 'analyst.id')
            ->leftJoin('team_members as tester', 'projects.analyst_testing_id', '=', 'tester.id')
            ->leftJoin('team_members as dev', 'projects.developer_id', '=', 'dev.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'analyst.name as analyst_name',
                'tester.name as analyst_testing_name',
                'dev.name as developer_name',
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id) as planner_count'),
                DB::raw('(SELECT COUNT(*) FROM project_planners WHERE project_planners.project_id = projects.id AND project_planners.status = \'done\') as planner_done_count'),
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->whereNull('projects.deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('projects.owner_id', $userId)
                  ->orWhere('projects.analyst_id', $userId)
                  ->orWhere('projects.analyst_testing_id', $userId)
                  ->orWhere('projects.developer_id', $userId)
                  ->orWhereExists(function ($sub) use ($userId) {
                      $sub->select(DB::raw(1))
                          ->from('project_workers')
                          ->whereColumn('project_workers.project_id', 'projects.id')
                          ->where('project_workers.user_id', $userId);
                  });
            });

        if (!empty($filters['status'])) {
            $query->where('projects.status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('projects.name', 'like', $s)
                  ->orWhere('projects.description', 'like', $s);
            });
        }

        return $query->orderByDesc('projects.created_at')->paginate($perPage);
    }

    public function findById(int $id): ?stdClass
    {
        return DB::table('projects')
            ->leftJoin('team_members as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('team_members as creator', 'projects.created_by', '=', 'creator.id')
            ->leftJoin('team_members as analyst', 'projects.analyst_id', '=', 'analyst.id')
            ->leftJoin('team_members as tester', 'projects.analyst_testing_id', '=', 'tester.id')
            ->leftJoin('team_members as dev', 'projects.developer_id', '=', 'dev.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'creator.name as created_by_name',
                'analyst.name as analyst_name',
                'tester.name as analyst_testing_name',
                'dev.name as developer_name',
                DB::raw('(SELECT stage_name FROM project_stages WHERE project_stages.project_id = projects.id ORDER BY project_stages.created_at DESC LIMIT 1) as current_stage')
            )
            ->where('projects.id', $id)
            ->whereNull('projects.deleted_at')
            ->first();
    }

    public function create(array $data): int
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        if (isset($data['linked_project_ids']) && is_array($data['linked_project_ids'])) {
            $data['linked_project_ids'] = json_encode($data['linked_project_ids']);
        }
        $data['created_at'] = now();
        $data['updated_at'] = now();
        return DB::table('projects')->insertGetId($data);
    }

    public function update(int $id, array $data): bool
    {
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        if (isset($data['linked_project_ids']) && is_array($data['linked_project_ids'])) {
            $data['linked_project_ids'] = json_encode($data['linked_project_ids']);
        }
        $data['updated_at'] = now();
        return DB::table('projects')->where('id', $id)->update($data) > 0;
    }
}
