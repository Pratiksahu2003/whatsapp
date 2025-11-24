<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_verify_token',
        'whatsapp_api_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp_access_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the messages for the user.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Check if user has WhatsApp credentials configured
     */
    public function hasWhatsAppCredentials(): bool
    {
        return !empty($this->whatsapp_phone_number_id) && !empty($this->whatsapp_access_token);
    }
}
