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
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name', 128);
            $table->string('last_name', 128);
            $table->string('company_name', 256)->nullable();
            $table->string('address', 1024);
            $table->string('country', 128);
            $table->string('state', 128);
            $table->string('city', 256);
            $table->string('zip_code', 16);
            $table->string('email', 256);
            $table->string('phone_number', 20);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
