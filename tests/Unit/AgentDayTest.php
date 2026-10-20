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

    public function test_rf07_depois_da_meia_noite_de_brasilia_o_dia_vira(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-21 03:00:00', 'UTC'));

        $this->assertSame('2026-10-21', AgentDay::today());
    }
}
