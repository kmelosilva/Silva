<?php

namespace Tests\Unit;

use App\Support\AgentDay;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AgentDayTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_rf07_o_dia_e_o_de_brasilia_e_nao_o_de_utc(): void
    {
        // 02h UTC de 21/10 ainda são 23h de 20/10 em Brasília.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 02:00:00', 'UTC'));

        $this->assertSame('2026-10-20', AgentDay::today());
        $this->assertSame('2026-10-19', AgentDay::yesterday());
    }

    public function test_dias_desde_um_momento_contam_o_calendario_de_brasilia(): void
    {
        // 22h de 24/10 em Brasília, 01h de 25/10 em UTC.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25 01:00:00', 'UTC'));

        $this->assertSame(0, AgentDay::daysSince(CarbonImmutable::parse('2026-10-24 08:00:00', AgentDay::TIMEZONE)));
        $this->assertSame(6, AgentDay::daysSince(CarbonImmutable::parse('2026-10-18 23:59:00', AgentDay::TIMEZONE)));
        $this->assertSame(7, AgentDay::daysSince(CarbonImmutable::parse('2026-10-18 02:00:00', 'UTC')), '23h de 17/10 em Brasília');
    }

    public function test_rf07_depois_da_meia_noite_de_brasilia_o_dia_vira(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 03:00:00', 'UTC'));

        $this->assertSame('2026-10-21', AgentDay::today());
    }
}
