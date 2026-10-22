<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_body',
        'response_status',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_body' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
