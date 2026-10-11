<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM modules of an app. The journey module is the one that moves through phases, stages and statuses.
        Schema::create('app_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_app_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_journey')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('app_module_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_module_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('label');
            $table->string('type', 20);
            $table->string('type_arg')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_module_fields');
        Schema::dropIfExists('app_modules');
    }
};
