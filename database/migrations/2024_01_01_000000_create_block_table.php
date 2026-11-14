<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blockableId');
            $table->string('blockableType');
            $table->index(['blockableType', 'blockableId']);
            $table->string('type');
            $table->json('data');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('createdAt')->nullable();
            $table->timestamp('updatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block');
    }
};
