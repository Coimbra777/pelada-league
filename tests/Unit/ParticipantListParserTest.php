<?php

namespace Tests\Unit;

use App\Support\ParticipantListParser;
use PHPUnit\Framework\TestCase;

class ParticipantListParserTest extends TestCase
{
    public function test_parses_hyphenated_spaced_phone(): void
    {
        $p = ParticipantListParser::parseLine('João - 98 99999-9999');
        $this->assertSame('João', $p['name']);
        $this->assertSame('98999999999', $p['phone']);
    }

    public function test_parses_parentheses_phone(): void
    {
        $p = ParticipantListParser::parseLine('Maria (98) 98888-8888');
        $this->assertSame('Maria', $p['name']);
        $this->assertSame('98988888888', $p['phone']);
    }

    public function test_parses_simple_space_separated(): void
    {
        $p = ParticipantListParser::parseLine('Ze 61911112222');
        $this->assertSame('Ze', $p['name']);
        $this->assertSame('61911112222', $p['phone']);
    }
}
