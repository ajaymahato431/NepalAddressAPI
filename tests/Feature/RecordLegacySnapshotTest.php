<?php

namespace Tests\Feature;

use Tests\TestCase;

class RecordLegacySnapshotTest extends TestCase
{
    public function test_record_snapshot(): void
    {
        if (getenv('RECORD_LEGACY_SNAPSHOT') !== '1') {
            $this->markTestSkipped('Set RECORD_LEGACY_SNAPSHOT=1 to record.');
        }

        $snapshot = [];

        foreach (LegacyContractTest::legacyUris() as [$uri]) {
            $response = $this->getJson($uri);
            $this->assertSame(200, $response->status(), "{$uri} returned {$response->status()}");
            $snapshot[$uri] = $response->json();
        }

        $dir = __DIR__.'/../fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/legacy-api-snapshot.json',
            json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->assertGreaterThanOrEqual(90, count($snapshot));
    }
}
