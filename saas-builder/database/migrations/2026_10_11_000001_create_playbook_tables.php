<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constitutions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->text('reason')->nullable();
            $table->text('example')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_core')->default(false);
            $table->timestamps();
        });

        Schema::create('laws', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('category', 20);
            $table->string('title');
            $table->text('example')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('checked_by_core')->default(false);
            $table->timestamps();
        });

        Schema::create('saas_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->string('template', 40)->default('blank');
            $table->text('core_vision')->nullable();
            $table->text('team_vision')->nullable();
            $table->text('vision_before')->nullable();
            $table->text('vision_after')->nullable();
            $table->string('current_step', 20)->default('basics');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saas_app_constitution', function (Blueprint $table) {
            $table->foreignId('saas_app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('constitution_id')->constrained()->cascadeOnDelete();
            $table->primary(['saas_app_id', 'constitution_id']);
        });

        Schema::create('saas_app_law', function (Blueprint $table) {
            $table->foreignId('saas_app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('law_id')->constrained()->cascadeOnDelete();
            $table->primary(['saas_app_id', 'law_id']);
        });

        Schema::create('app_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_app_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('app_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_phase_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('app_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_stage_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_final')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('acfcm_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_app_id')->constrained()->cascadeOnDelete();
            $table->string('pillar', 20);
            $table->string('item_key', 40)->nullable();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acfcm_choices');
        Schema::dropIfExists('app_statuses');
        Schema::dropIfExists('app_stages');
        Schema::dropIfExists('app_phases');
        Schema::dropIfExists('saas_app_law');
        Schema::dropIfExists('saas_app_constitution');
        Schema::dropIfExists('saas_apps');
        Schema::dropIfExists('laws');
        Schema::dropIfExists('constitutions');
    }
};
