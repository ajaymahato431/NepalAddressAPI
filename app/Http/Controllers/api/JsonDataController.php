<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\AddressService;
use App\Services\Nepal\AddressPresenter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JsonDataController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    protected function cachedResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'ETag' => md5(json_encode($data)),
        ]);
    }

    /**
     * Resolve case/lang/detailed.
     *
     * `lang` defaults to 'en' in flat mode and 'both' in detailed mode: a flat
     * default of 'both' would change the shape of existing responses, while a
     * detailed default of 'en' would withhold the Nepali names that detailed
     * mode exists to expose. Detailed mode is opt-in, so no existing consumer
     * observes the difference.
     *
     * The ward, municipality-detail and category endpoints are new and carry
     * no legacy contract, so they pass $defaultLang = 'both': their whole
     * purpose is the bilingual and ward data, and nothing depends on them
     * returning English-only.
     */
    protected function params(Request $request, ?string $defaultLang = null, string $defaultCase = 'lower'): array
    {
        $case = (string) $request->query('case', $defaultCase);
        $detailed = filter_var($request->query('detailed', false), FILTER_VALIDATE_BOOLEAN);
        $lang = (string) $request->query('lang', $defaultLang ?? ($detailed ? 'both' : 'en'));

        if (! in_array($case, AddressPresenter::CASES, true)) {
            $this->reject('case', $case, AddressPresenter::CASES);
        }

        if (! in_array($lang, AddressPresenter::LANGS, true)) {
            $this->reject('lang', $lang, AddressPresenter::LANGS);
        }

        return ['case' => $case, 'lang' => $lang, 'detailed' => $detailed];
    }

    private function reject(string $name, string $value, array $accepted): never
    {
        throw new HttpResponseException(response()->json([
            'error' => "Invalid value \"{$value}\" for parameter \"{$name}\".",
            'accepted' => $accepted,
        ], 422));
    }

    public function getProvinces(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getProvinces($case, $lang, $detailed)
        );
    }

    public function getDistricts(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getDistricts($case, $lang, $detailed)
        );
    }

    public function getDistrictsByProvince(Request $request, string $provinceName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $data = $this->addressService->getDistrictsByProvince($provinceName, $case, $lang, $detailed);

        if ($data === null) {
            return response()->json(['error' => 'Province not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getMunicipalsByDistrict(Request $request, string $districtName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $data = $this->addressService->getMunicipalsByDistrict($districtName, $case, $lang, $detailed);

        if ($data === null) {
            return response()->json(['error' => 'District not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function search(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);
        $query = (string) $request->query('q', '');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        if (trim($query) === '') {
            return response()->json(['error' => 'Query parameter "q" is required.'], 422);
        }

        return $this->cachedResponse(
            $this->addressService->search($query, $case, $limit, $lang, $detailed)
        );
    }

    public function getAllHierarchy(Request $request): JsonResponse
    {
        ['case' => $case, 'lang' => $lang, 'detailed' => $detailed] = $this->params($request);

        return $this->cachedResponse(
            $this->addressService->getAllHierarchy($case, $lang, $detailed)
        );
    }

    public function getStats(Request $request): JsonResponse
    {
        ['lang' => $lang] = $this->params($request);

        return $this->cachedResponse($this->addressService->getStats($lang));
    }

    public function getWards(Request $request, string $districtName, string $municipalityName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang] = $this->params($request, 'both', 'title');
        $data = $this->addressService->getWards($districtName, $municipalityName, $lang, $case);

        if ($data === null) {
            return response()->json(['error' => 'Municipality not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getMunicipality(Request $request, string $districtName, string $municipalityName): JsonResponse
    {
        ['case' => $case, 'lang' => $lang] = $this->params($request, 'both', 'title');
        $data = $this->addressService->getMunicipality($districtName, $municipalityName, $lang, $case);

        if ($data === null) {
            return response()->json(['error' => 'Municipality not found'], 404);
        }

        return $this->cachedResponse($data);
    }

    public function getCategories(Request $request): JsonResponse
    {
        ['lang' => $lang] = $this->params($request, 'both');

        return $this->cachedResponse($this->addressService->getCategories($lang));
    }
}
