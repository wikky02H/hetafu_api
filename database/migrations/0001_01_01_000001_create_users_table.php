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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('email', 256)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('name', 128)->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('image', 1024)->nullable();
            $table->unsignedBigInteger('role');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('role')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
