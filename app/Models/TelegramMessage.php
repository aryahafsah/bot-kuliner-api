<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramMessage extends Model
{
    protected $fillable = [
        'telegram_update_id',
        'chat_id',
        'message_type',
        'message_metadata',
    ];

    protected function casts(): array
    {
        return [
            'message_metadata' => 'array',
        ];
    }
}
