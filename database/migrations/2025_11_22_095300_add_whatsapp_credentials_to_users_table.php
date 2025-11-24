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
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id')->nullable()->after('password');
            $table->text('whatsapp_access_token')->nullable()->after('whatsapp_phone_number_id');
            $table->string('whatsapp_verify_token')->nullable()->after('whatsapp_access_token');
            $table->string('whatsapp_api_url')->default('https://graph.facebook.com/v18.0')->after('whatsapp_verify_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_access_token',
                'whatsapp_verify_token',
                'whatsapp_api_url'
            ]);
        });
    }
};
