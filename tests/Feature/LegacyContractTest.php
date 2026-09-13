<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegacyContractTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/../fixtures/legacy-api-snapshot.json';

    /**
     * Every URI whose response must never change.
     */
    public static function legacyUris(): array
    {
        $uris = [
            '/api/provinces',
            '/api/provinces?case=title',
            '/api/districts',
            '/api/districts?case=title',
            '/api/stats',
            '/api/hierarchy',
            '/api/all',
            '/api/search?q=bharatpur',
            '/api/search?q=kathmandu',
            '/api/search?q=rural',
            '/api/districts/bagmati',
            '/api/districts/koshi',
            '/api/districts/pradesh-1',
            '/api/districts/BAGMATI',
            '/api/districts/sudurpaschim?case=title',
            '/api/municipals/eastern-rukum',
            '/api/municipals/eastern%20rukum',
            '/api/municipals/chitwan?case=title',
            '/api/municipals/tanahu',
            '/api/municipals/illam',
            '/api/municipals/tehrathum',
        ];

        // Every district, through the same slug rule the old test used.
        foreach (self::districtSlugs() as $slug) {
            $uris[] = "/api/municipals/{$slug}";
        }

        return array_map(fn ($u) => [$u], array_unique($uris));
    }

    /**
     * The 77 district slugs, hardcoded on purpose. Reading them from
     * public/data/districts.json would couple this test to a file whose
     * shape changes in Task 3, and a frozen contract must not move.
     */
    private static function districtSlugs(): array
    {
        return [
            'bhojpur', 'dhankuta', 'ilam', 'jhapa', 'khotang', 'morang', 'okhaldhunga',
            'panchthar', 'sankhuwasabha', 'solukhumbu', 'sunsari', 'taplejung',
            'terhathum', 'udayapur', 'bara', 'dhanusha', 'mahottari', 'parsa',
            'rautahat', 'saptari', 'sarlahi', 'siraha', 'bhaktapur', 'chitwan',
            'dhading', 'dolakha', 'kathmandu', 'kavrepalanchok', 'lalitpur',
            'makwanpur', 'nuwakot', 'ramechhap', 'rasuwa', 'sindhuli',
            'sindhupalchok', 'baglung', 'gorkha', 'kaski', 'lamjung', 'manang',
            'mustang', 'myagdi', 'nawalpur', 'parbat', 'syangja', 'tanahun',
            'arghakhanchi', 'banke', 'bardiya', 'dang', 'eastern-rukum', 'gulmi',
            'kapilvastu', 'parasi', 'palpa', 'pyuthan', 'rolpa', 'rupandehi',
            'dailekh', 'dolpa', 'humla', 'jajarkot', 'jumla', 'kalikot', 'mugu',
            'salyan', 'surkhet', 'western-rukum', 'achham', 'baitadi', 'bajhang',
            'bajura', 'dadeldhura', 'darchula', 'doti', 'kailali', 'kanchanpur',
        ];
    }

    /**
     * @dataProvider legacyUris
     */
    public function test_legacy_response_is_unchanged(string $uri): void
    {
        $response = $this->getJson($uri);
        $this->assertSame(200, $response->status(), "{$uri} did not return 200");

        $snapshot = json_decode(file_get_contents(self::SNAPSHOT), true);

        $this->assertArrayHasKey(
            $uri,
            $snapshot,
            "No recorded snapshot for {$uri}. Re-record with RECORD_LEGACY_SNAPSHOT=1."
        );

        $this->assertEquals(
            $this->canonicalize($snapshot[$uri]),
            $this->canonicalize($response->json()),
            "Response for {$uri} changed. The legacy contract is broken."
        );
    }

    /**
     * Sort list-shaped arrays recursively before comparing.
     *
     * Array ORDER is not part of the contract: legacy arrays were alphabetical,
     * the canonical dataset follows upstream ids. Membership, spelling and
     * counts are still compared exactly, which is where a romanization change
     * would surface.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $mapped = array_map(fn ($v) => $this->canonicalize($v), $value);

        if (array_is_list($mapped)) {
            usort($mapped, fn ($a, $b) => json_encode($a) <=> json_encode($b));
        } else {
            ksort($mapped);
        }

        return $mapped;
    }
}
