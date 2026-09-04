<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JsonDataController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    /**
     * Set cache control headers for static response.
     */
    protected function cachedResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'ETag' => md5(json_encode($data)),
        ]);
    }

    /**
     * GET /api/provinces
     */
    public function getProvinces(Request $request): JsonResponse
    {
        $case = $request->query('case', 'lower');
        $data = $this->addressService->getProvinces($case);

        return $this->cachedResponse($data);
    }

    /**
     * GET /api/districts
     */
    public function getDistricts(Request $request): JsonResponse
    {
        $case = $request->query('case', 'lower');
        $data = $this->addressService->getDistricts($case);

        return $this->cachedResponse($data);
    }

    /**
     * GET /api/districts/{provinceName}
     */
    public function getDistrictsByProvince(Request $request, string $provinceName): JsonResponse
    {
        $case = $request->query('case', 'lower');
        $data = $this->addressService->getDistrictsByProvince($provinceName, $case);

        if ($data === null) {
            return response()->json(['error' => 'Province not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    /**
     * GET /api/municipals/{districtName}
     */
    public function getMunicipalsByDistrict(Request $request, string $districtName): JsonResponse
    {
        $case = $request->query('case', 'lower');
        $data = $this->addressService->getMunicipalsByDistrict($districtName, $case);

        if ($data === null) {
            return response()->json(['error' => 'District not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    /**
     * GET /api/search?q={term}
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $case = $request->query('case', 'lower');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        if (trim($query) === '') {
            return response()->json([
                'error' => 'Query parameter "q" is required.',
            ], 422);
        }

        $results = $this->addressService->search($query, $case, $limit);

        return $this->cachedResponse($results);
    }

    /**
     * GET /api/all or /api/hierarchy
     */
    public function getAllHierarchy(Request $request): JsonResponse
    {
        $case = $request->query('case', 'lower');
        $data = $this->addressService->getAllHierarchy($case);

        return $this->cachedResponse($data);
    }

    /**
     * GET /api/stats
     */
    public function getStats(): JsonResponse
    {
        $data = $this->addressService->getStats();

        return $this->cachedResponse($data);
    }
}
