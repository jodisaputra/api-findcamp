<?php

namespace App\Http\Controllers\API;

use App\Models\Region;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use Illuminate\Support\Facades\Storage;

class RegionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $regions = Region::with('countries')->get();
        return RegionResource::collection($regions);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Handle image upload
            if ($request->hasFile('image')) {
                $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
                $request->image->storeAs('regions', $imageName);
            }

            $region = Region::create([
                'name' => $validated['name'],
                'image' => $imageName ?? null,
            ]);

            return new RegionResource($region);
        } catch (\Exception $e) {
            // If something goes wrong, delete the uploaded image if it exists
            if (isset($imageName) && Storage::exists('regions/' . $imageName)) {
                Storage::delete('regions/' . $imageName);
            }
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Region $region)
    {
        return new RegionResource($region->load('countries'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Region $region)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $data = [
                'name' => $validated['name'],
            ];

            // Handle image update
            if ($request->hasFile('image')) {
                // Store the old image name to delete later
                $oldImage = $region->image;

                // Upload new image
                $imageName = time() . '_' . uniqid() . '.' . $request->image->extension();
                $request->image->storeAs('regions', $imageName);
                $data['image'] = $imageName;

                // Delete old image if it exists
                if ($oldImage && Storage::exists('regions/' . $oldImage)) {
                    Storage::delete('regions/' . $oldImage);
                }
            }

            $region->update($data);
            return new RegionResource($region);
        } catch (\Exception $e) {
            // If something goes wrong and we uploaded a new image, delete it
            if (isset($imageName) && Storage::exists('regions/' . $imageName)) {
                Storage::delete('regions/' . $imageName);
            }
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region)
    {
        // Delete the image file if it exists
        if ($region->image && Storage::exists('regions/' . $region->image)) {
            Storage::delete('regions/' . $region->image);
        }

        $region->delete();
        return response()->noContent();
    }
}
