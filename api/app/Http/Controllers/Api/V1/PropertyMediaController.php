<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePropertyMediaRequest;
use App\Http\Requests\Api\V1\UpdatePropertyMediaRequest;
use App\Http\Resources\Api\V1\PropertyMediaResource;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\MediaService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PropertyMediaController extends Controller
{
    /**
     * Add media to a property
     *
     * `kind` is image, floorplan (image files), brochure (PDF) or video_url (send `url`, no file).
     * Send files as multipart `file`. The first image becomes the cover.
     */
    public function store(StorePropertyMediaRequest $request, Property $property, MediaService $media): PropertyMediaResource
    {
        $kind = $request->validated('kind');

        $path = match ($kind) {
            'video_url' => $request->validated('url'),
            'brochure' => $media->storeDocument($request->file('file'), 'properties'),
            default => $media->store($request->file('file'), 'properties'),
        };

        $item = DB::transaction(function () use ($property, $kind, $path): PropertyMedia {
            $property->newQuery()->whereKey($property->id)->lockForUpdate()->first();

            $item = $property->media()->create([
                'kind' => $kind,
                'path' => $path,
                'sort' => ((int) $property->media()->max('sort')) + 10,
                'is_cover' => $kind === 'image' && ! $property->media()->where('is_cover', true)->exists(),
            ]);

            return $item->refresh();
        });

        return new PropertyMediaResource($item);
    }

    /**
     * Update media
     *
     * `sort` reorders; `is_cover: true` makes this image the cover and clears the previous one. Only images can be the cover.
     */
    public function update(UpdatePropertyMediaRequest $request, Property $property, PropertyMedia $media): PropertyMediaResource
    {
        abort_unless($media->property_id === $property->id, 404);

        DB::transaction(function () use ($request, $property, $media): void {
            if ($request->has('sort')) {
                $media->sort = $request->integer('sort');
            }

            if ($request->has('is_cover')) {
                if ($media->kind !== 'image') {
                    throw ValidationException::withMessages(['is_cover' => ['Only an image can be the cover.']]);
                }

                $property->media()->where('is_cover', true)->whereKeyNot($media->id)->update(['is_cover' => false]);
                $media->is_cover = true;
            }

            $media->save();
        });

        return new PropertyMediaResource($media->refresh());
    }

    /**
     * Remove media
     *
     * Removing the cover promotes the next image.
     */
    public function destroy(Property $property, PropertyMedia $media, MediaService $files): Response
    {
        Gate::authorize('update', $property);
        abort_unless($media->property_id === $property->id, 404);

        DB::transaction(function () use ($property, $media): void {
            $wasCover = $media->is_cover;
            $media->delete();

            if ($wasCover) {
                $property->media()->where('kind', 'image')->first()?->update(['is_cover' => true]);
            }
        });

        if ($media->isFile()) {
            $files->delete($media->path);
        }

        return response()->noContent();
    }
}
