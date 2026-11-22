<?php

use App\Models\Property;
use App\Models\PropertyMedia;
use Database\Seeders\PropertiesModuleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('uploads');
    $this->seed(PropertiesModuleSeeder::class);
    $this->property = Property::factory()->create();
});

function addImage($test, Property $property, string $kind = 'image')
{
    return $test->post("/api/v1/properties/{$property->id}/media", [
        'kind' => $kind,
        'file' => UploadedFile::fake()->image('p.jpg', 1200, 800),
    ], ['Accept' => 'application/json']);
}

it('forbids media changes without properties.update', function () {
    actingAsRole('viewer', ['properties.view']);

    addImage($this, $this->property)->assertForbidden();
});

it('makes the first image the cover and keeps exactly one cover', function () {
    actingAsRole('editor', ['properties.update']);

    addImage($this, $this->property)->assertCreated()->assertJsonPath('data.is_cover', true);
    $second = addImage($this, $this->property)->assertCreated()->assertJsonPath('data.is_cover', false)->json('data.id');
    $plan = addImage($this, $this->property, 'floorplan')->assertCreated()->assertJsonPath('data.is_cover', false)->json('data.id');

    $this->patchJson("/api/v1/properties/{$this->property->id}/media/{$second}", ['is_cover' => true])
        ->assertOk()->assertJsonPath('data.is_cover', true);

    expect(PropertyMedia::where('is_cover', true)->pluck('id')->all())->toBe([$second]);

    // Only an image can be the cover.
    $bad = $this->patchJson("/api/v1/properties/{$this->property->id}/media/{$plan}", ['is_cover' => true])->assertStatus(422);
    expect(array_keys($bad->json('error.details')))->toEqual(['is_cover']);
    expect(PropertyMedia::where('is_cover', true)->pluck('id')->all())->toBe([$second]);
});

it('cannot hold two covers at the database level', function () {
    $this->property->media()->create(['kind' => 'image', 'path' => 'a', 'is_cover' => true]);

    expect(fn () => $this->property->media()->create(['kind' => 'image', 'path' => 'b', 'is_cover' => true]))
        ->toThrow(QueryException::class);
});

it('promotes the next image when the cover is removed and deletes the file', function () {
    actingAsRole('editor', ['properties.update']);
    $first = addImage($this, $this->property)->json('data.id');
    $second = addImage($this, $this->property)->json('data.id');
    $path = PropertyMedia::find($first)->path;
    Storage::disk('uploads')->assertExists($path);

    $this->deleteJson("/api/v1/properties/{$this->property->id}/media/{$first}")->assertNoContent();

    Storage::disk('uploads')->assertMissing($path);
    expect(PropertyMedia::find($second)->is_cover)->toBeTrue();
});

it('stores a PDF brochure and a video link, and rejects wrong types', function () {
    actingAsRole('editor', ['properties.update']);

    $this->post("/api/v1/properties/{$this->property->id}/media", [
        'kind' => 'brochure', 'file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.kind', 'brochure')->assertJsonPath('data.is_cover', false);

    $this->postJson("/api/v1/properties/{$this->property->id}/media", ['kind' => 'video_url', 'url' => 'https://youtu.be/abc'])
        ->assertCreated()->assertJsonPath('data.url', 'https://youtu.be/abc')->assertJsonPath('data.file', null);

    $this->post("/api/v1/properties/{$this->property->id}/media", [
        'kind' => 'brochure', 'file' => UploadedFile::fake()->image('x.jpg'),
    ], ['Accept' => 'application/json'])->assertStatus(422);
    $this->postJson("/api/v1/properties/{$this->property->id}/media", ['kind' => 'video_url', 'url' => 'javascript:alert(1)'])->assertStatus(422);
    $this->postJson("/api/v1/properties/{$this->property->id}/media", ['kind' => 'image'])->assertStatus(422);
});

it('does not touch media of another property', function () {
    $other = Property::factory()->create();
    $item = $other->media()->create(['kind' => 'image', 'path' => 'x/y.jpg']);
    actingAsRole('editor', ['properties.update']);

    $this->deleteJson("/api/v1/properties/{$this->property->id}/media/{$item->id}")->assertNotFound();
    $this->patchJson("/api/v1/properties/{$this->property->id}/media/{$item->id}", ['sort' => 5])->assertNotFound();
});
