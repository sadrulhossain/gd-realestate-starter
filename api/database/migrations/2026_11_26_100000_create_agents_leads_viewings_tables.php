<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->string('title', 150)->nullable();
            $table->string('title_2nd', 150)->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_2nd')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('license_no', 60)->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        // R2 left properties.agent_id as a plain column.
        Schema::table('properties', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('agents')->restrictOnDelete();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('message')->nullable();
            $table->string('source', 20)->default('web'); // web | phone | walk_in | referral | other
            $table->string('status', 20)->default('new'); // new | contacted | qualified | viewing | negotiation | won | lost
            $table->string('lost_reason', 255)->nullable();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->restrictOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['agent_id', 'status']);
            $table->index('property_id');
            $table->index('project_id');
        });

        // Timeline: free notes and status changes.
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 10)->default('note'); // note | status | assign
            $table->text('body');
            $table->timestamps();
            $table->index(['lead_id', 'id']);
        });

        Schema::create('viewings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 20)->default('scheduled'); // scheduled | confirmed | completed | cancelled | no_show
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'starts_at']);
            $table->index(['property_id', 'starts_at']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewings');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
        });
        Schema::dropIfExists('agents');
    }
};
