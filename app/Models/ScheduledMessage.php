<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'message',
        'message_type',
        'media_url',
        'template_name',
        'scheduled_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('scheduled_at', '<=', now());
    }
}
