<?php

namespace Tests\Unit;

use App\Services\Builder\JourneyOutline;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class JourneyOutlineTest extends TestCase
{
    public function test_it_reads_phases_stages_and_final_statuses(): void
    {
        $phases = (new JourneyOutline())->parse("Onboarding\n- Offer: Drafted, Sent, Accepted*, Declined*\nExit\n- Handover: Pending, Done*");

        $this->assertSame(['Onboarding', 'Exit'], array_column($phases, 'name'));
        $this->assertSame('Offer', $phases[0]['stages'][0]['name']);
        $this->assertSame(
            [['name' => 'Drafted', 'final' => false], ['name' => 'Sent', 'final' => false], ['name' => 'Accepted', 'final' => true], ['name' => 'Declined', 'final' => true]],
            $phases[0]['stages'][0]['statuses'],
        );
    }

    public function test_every_stage_needs_a_final_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a final status');

        (new JourneyOutline())->parse("Onboarding\n- Offer: Drafted, Sent");
    }

    public function test_a_stage_needs_a_phase_above_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a phase line above it');

        (new JourneyOutline())->parse('- Offer: Sent*');
    }

    public function test_duplicate_stage_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('two stages called');

        (new JourneyOutline())->parse("Plan\n- Intake: New*\n- intake: Old*");
    }
}
