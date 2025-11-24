<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['sent', 'received'])->index();
            $table->string('message_id')->nullable()->index();
            $table->string('phone_number')->index();
            $table->enum('message_type', ['text', 'image', 'video', 'audio', 'document', 'template', 'location'])->default('text');
            $table->text('content')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_id')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('template_name')->nullable();
            $table->json('template_parameters')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('whatsapp_timestamp')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
            $table->index(['direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
