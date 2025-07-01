<?php

use App\Http\Controllers\api\JsonDataController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');


Route::get('/provinces', [JsonDataController::class, 'getProvinces']);
Route::get('/districts', [JsonDataController::class, 'getDistricts']);
Route::get('/districts/{provinceName}', [JsonDataController::class, 'getDistrictsByProvince']);
Route::get('/municipals/{districtName}', [JsonDataController::class, 'getMunicipalsByDistrict']);
