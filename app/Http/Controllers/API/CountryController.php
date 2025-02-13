<?php

namespace App\Http\Controllers\API;

use App\Models\Country;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use Illuminate\Support\Facades\Storage;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Country::with('region');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhereHas('region', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
        }

        if ($request->has('region')) {
            $query->whereHas('region', function ($q) use ($request) {
                $q->where('name', $request->get('region'));
            });
        }

        $countries = $query->get();
        return CountryResource::collection($countries);
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
                'region_id' => 'required|exists:regions,id',
                'flag' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'rating' => 'required|numeric|min:0|max:5',
            ]);

            // Handle flag upload
            if ($request->hasFile('flag')) {
                $flagName = time() . '_' . uniqid() . '.' . $request->flag->extension();
                $request->flag->storeAs('countries', $flagName);
            }

            $country = Country::create([
                'name' => $validated['name'],
                'region_id' => $validated['region_id'],
                'flag' => $flagName ?? null,
                'rating' => $validated['rating'],
            ]);

            return new CountryResource($country);
        } catch (\Exception $e) {
            // If something goes wrong, delete the uploaded image if it exists
            if (isset($flagName) && Storage::exists('countries/' . $flagName)) {
                Storage::delete('countries/' . $flagName);
            }
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Country $country)
    {
        return new CountryResource($country->load('region'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Country $country)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Country $country)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'region_id' => 'required|exists:regions,id',
                'flag' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'rating' => 'required|numeric|min:0|max:5',
            ]);

            $data = [
                'name' => $validated['name'],
                'region_id' => $validated['region_id'],
                'rating' => $validated['rating'],
            ];

            // Handle flag update
            if ($request->hasFile('flag')) {
                // Store the old flag name to delete later
                $oldFlag = $country->flag;

                // Upload new flag
                $flagName = time() . '_' . uniqid() . '.' . $request->flag->extension();
                $request->flag->storeAs('countries', $flagName);
                $data['flag'] = $flagName;

                // Delete old flag if it exists
                if ($oldFlag && Storage::exists('countries/' . $oldFlag)) {
                    Storage::delete('countries/' . $oldFlag);
                }
            }

            $country->update($data);
            return new CountryResource($country);
        } catch (\Exception $e) {
            // If something goes wrong and we uploaded a new image, delete it
            if (isset($flagName) && Storage::exists('countries/' . $flagName)) {
                Storage::delete('countries/' . $flagName);
            }
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Country $country)
    {
        // Delete the flag file if it exists
        if ($country->flag && Storage::exists('countries/' . $country->flag)) {
            Storage::delete('countries/' . $country->flag);
        }

        $country->delete();
        return response()->noContent();
    }
}
