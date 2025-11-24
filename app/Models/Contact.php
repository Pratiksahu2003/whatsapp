<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number',
        'name',
        'email',
        'notes',
        'tags',
        'message_count',
        'last_contacted_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'last_contacted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'phone_number', 'phone_number');
    }
}
