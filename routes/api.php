<?php

use App\Http\Controllers\api\JsonDataController;
use Illuminate\Support\Facades\Route;

// Core Location Endpoints
Route::get('/provinces', [JsonDataController::class, 'getProvinces']);
Route::get('/districts', [JsonDataController::class, 'getDistricts']);
Route::get('/districts/{provinceName}', [JsonDataController::class, 'getDistrictsByProvince']);
Route::get('/municipals/{districtName}', [JsonDataController::class, 'getMunicipalsByDistrict']);

// Search & Advanced Endpoints
Route::get('/search', [JsonDataController::class, 'search']);
Route::get('/all', [JsonDataController::class, 'getAllHierarchy']);
Route::get('/hierarchy', [JsonDataController::class, 'getAllHierarchy']);
Route::get('/stats', [JsonDataController::class, 'getStats']);

// Ward & Detail Endpoints
Route::get('/wards/{districtName}/{municipalityName}', [JsonDataController::class, 'getWards']);
Route::get('/municipality/{districtName}/{municipalityName}', [JsonDataController::class, 'getMunicipality']);
Route::get('/categories', [JsonDataController::class, 'getCategories']);
