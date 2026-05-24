<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Service\Geo\GeoPrivacy;
use PHPUnit\Framework\TestCase;

class GeoPrivacyTest extends TestCase
{
    public function testCoarsenRoundsToTwoDecimalsByDefault(): void
    {
        $r = GeoPrivacy::coarsen(43.604567, 1.443890);
        $this->assertEquals(43.60, $r['lat']);
        $this->assertEquals(1.44, $r['lng']);
    }

    public function testHaversineToulouseToParis(): void
    {
        // Toulouse 43.60, 1.44 -> Paris 48.85, 2.35 -> ~590 km
        $m = GeoPrivacy::haversineMeters(43.60, 1.44, 48.85, 2.35);
        $this->assertGreaterThan(580_000, $m);
        $this->assertLessThan(620_000, $m);
    }

    public function testHaversineSamePointIsZero(): void
    {
        $m = GeoPrivacy::haversineMeters(43.60, 1.44, 43.60, 1.44);
        $this->assertEquals(0.0, $m);
    }
}
