<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Message extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'user_id',
        'direction',
        'message_id',
        'phone_number',
        'conversation_id',
        'message_type',
        'content',
        'media_url',
        'media_id',
        'mime_type',
        'template_name',
        'template_parameters',
        'status',
        'error_message',
        'metadata',
        'whatsapp_timestamp',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'retry_count',
    ];

    protected $casts = [
        'template_parameters' => 'array',
        'metadata' => 'array',
        'whatsapp_timestamp' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function scopeSent($query)
    {
        return $query->where('direction', 'sent');
    }

    public function scopeReceived($query)
    {
        return $query->where('direction', 'received');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    public function scopeByPhone($query, $phone)
    {
        return $query->where('phone_number', $phone);
    }

    public function scopeByConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Get the user that owns the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get conversation messages
     */
    public static function getConversation($conversationId, $userId = null)
    {
        $query = static::where('conversation_id', $conversationId)->orderBy('created_at', 'asc');
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query->get();
    }
}
