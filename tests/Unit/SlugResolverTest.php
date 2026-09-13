<?php

namespace Tests\Unit;

use App\Services\Nepal\DatasetRepository;
use App\Services\Nepal\SlugResolver;
use Tests\TestCase;

class SlugResolverTest extends TestCase
{
    private SlugResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SlugResolver(new DatasetRepository());
    }

    public function test_normalizes_input(): void
    {
        $this->assertSame('eastern-rukum', $this->resolver->normalize('Eastern Rukum'));
        $this->assertSame('eastern-rukum', $this->resolver->normalize('  EASTERN_RUKUM  '));
        $this->assertSame('kathmandu', $this->resolver->normalize('Kathmandu'));
    }

    public function test_normalization_strips_path_traversal(): void
    {
        $this->assertSame('etcpasswd', $this->resolver->normalize('../../etc/passwd'));
        $this->assertSame('', $this->resolver->normalize('../..'));
    }

    public function test_resolves_provinces_including_aliases(): void
    {
        $this->assertSame('koshi', $this->resolver->resolveProvince('koshi')['slug']);
        $this->assertSame('koshi', $this->resolver->resolveProvince('pradesh-1')['slug']);
        $this->assertSame('koshi', $this->resolver->resolveProvince('province-1')['slug']);
        $this->assertSame('bagmati', $this->resolver->resolveProvince('BAGMATI')['slug']);
        $this->assertSame('sudurpaschim', $this->resolver->resolveProvince('sudurpashchim')['slug']);
        $this->assertNull($this->resolver->resolveProvince('atlantis'));
    }

    public function test_resolves_districts_including_aliases(): void
    {
        $this->assertSame('ilam', $this->resolver->resolveDistrict('illam')['slug']);
        $this->assertSame('terhathum', $this->resolver->resolveDistrict('tehrathum')['slug']);
        $this->assertSame('tanahun', $this->resolver->resolveDistrict('tanahu')['slug']);
        $this->assertSame('kavrepalanchok', $this->resolver->resolveDistrict('kavre')['slug']);
        $this->assertSame('eastern-rukum', $this->resolver->resolveDistrict('eastern rukum')['slug']);
        $this->assertNull($this->resolver->resolveDistrict('gotham'));
    }

    public function test_resolves_districts_by_nepali_name(): void
    {
        $this->assertSame('kathmandu', $this->resolver->resolveDistrict('काठमाडौँ')['slug']);
    }

    public function test_resolves_municipalities_within_a_district(): void
    {
        $chitwan = $this->resolver->resolveDistrict('chitwan');

        $found = $this->resolver->resolveMunicipality($chitwan['id'], 'bharatpur');
        $this->assertSame('Bharatpur', $found['name']);

        $byLegacy = $this->resolver->resolveMunicipality($chitwan['id'], 'bharatpur-metropolitan-city');
        $this->assertSame('Bharatpur', $byLegacy['name']);

        $this->assertNull($this->resolver->resolveMunicipality($chitwan['id'], 'springfield'));
    }
}
