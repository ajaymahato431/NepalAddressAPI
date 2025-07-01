<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;


class JsonDataController extends Controller
{
    public function getProvinces()
    {
        $json = File::get(public_path('data/provinces.json'));
        return response()->json(json_decode($json, true));
    }

    public function getDistricts()
    {
        $json = File::get(public_path('data/districts.json'));
        return response()->json(json_decode($json, true));
    }

    public function getDistrictsByProvince($provinceName)
    {
        $file = public_path("data/districtsByProvince/{$provinceName}.json");

        if (!File::exists($file)) {
            return response()->json(['error' => 'Province not found'], 404);
        }

        $json = File::get($file);
        $data = json_decode($json, true);

        return response()->json($data);
    }

    public function getMunicipalsByDistrict($districtName)
    {
        $file = public_path("data/municipalsByDistrict/{$districtName}.json");

        if (!File::exists($file)) {
            return response()->json(['error' => 'District not found'], 404);
        }

        $json = File::get($file);
        $data = json_decode($json, true);

        return response()->json($data);
    }
}
