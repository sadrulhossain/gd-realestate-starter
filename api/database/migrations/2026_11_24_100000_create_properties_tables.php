<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('ref_code', 20)->unique();
            $table->string('slug', 180)->unique();
            $table->string('title');
            $table->string('title_2nd')->nullable();
            $table->text('description')->nullable();
            $table->text('description_2nd')->nullable();
            $table->string('listing_type', 10);
            $table->foreignId('property_type_id')->constrained('property_types')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('address_line')->nullable();
            // double precision, so the earthdistance index on ll_to_earth(lat, lng) needs no casts.
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->unsignedBigInteger('price')->default(0); // minor units
            $table->string('rent_period', 10)->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('area_unit', 10)->default('sqft');
            $table->unsignedSmallInteger('beds')->nullable();
            $table->unsignedSmallInteger('baths')->nullable();
            $table->smallInteger('floor')->nullable();
            $table->unsignedSmallInteger('total_floors')->nullable();
            $table->string('facing', 20)->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->string('furnished', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('featured')->default(false);
            // Plain columns for now: the agents (R4), projects and units (R3) tables do not exist yet.
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'listing_type', 'published_at']);
            $table->index('property_type_id');
            $table->index('location_id');
            $table->index('price');
            $table->index('beds');
            $table->index('baths');
            $table->index('area');
            $table->index('furnished');
            $table->index(['featured', 'status']);
            $table->index('agent_id');
        });

        // Owned by the table, so migrate:fresh (which only drops tables) drops it too.
        DB::statement('CREATE SEQUENCE property_ref_seq OWNED BY properties.id');

        DB::statement(<<<'SQL'
            ALTER TABLE properties ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                to_tsvector('simple',
                    coalesce(title, '') || ' ' || coalesce(title_2nd, '') || ' ' ||
                    coalesce(description, '') || ' ' || coalesce(description_2nd, '') || ' ' ||
                    coalesce(address_line, ''))
            ) STORED
        SQL);
        DB::statement('CREATE INDEX properties_search_vector_gin ON properties USING gin (search_vector)');
        DB::statement('CREATE INDEX properties_title_trgm ON properties USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX properties_earth_gist ON properties USING gist (ll_to_earth(lat, lng))');

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['property_id', 'amenity_id']);
            $table->index('amenity_id');
        });

        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // image | floorplan | brochure | video_url
            $table->string('path', 500); // stored file path, or the URL for video_url
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'kind', 'sort']);
        });
        DB::statement('CREATE UNIQUE INDEX property_media_one_cover ON property_media (property_id) WHERE is_cover');

        Schema::create('property_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_status_history');
        Schema::dropIfExists('property_media');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('properties');
        DB::statement('DROP SEQUENCE IF EXISTS property_ref_seq');
    }
};
