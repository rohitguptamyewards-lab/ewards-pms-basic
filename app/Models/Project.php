<?php
namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'objective', 'tags', 'status', 'priority',
        'owner_id', 'created_by', 'linked_project_ids',
    ];

    protected function casts(): array
    {
        return [
            'status'             => ProjectStatus::class,
            'priority'           => ProjectPriority::class,
            'tags'               => 'array',
            'linked_project_ids' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by');
    }
}
