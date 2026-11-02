<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'client_name',
        'branch_code',
        'action_type',
        'target_id',
        'ticket_number',
        'status',
        'notes',
        'before_state',
        'after_state',
        'ip_address',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
