<?php

namespace Tests\Unit;

use App\Support\PtBrNumberParser;
use PHPUnit\Framework\TestCase;

class PtBrNumberParserTest extends TestCase
{
    public function test_parse_decimal_pt_br_variacoes(): void
    {
        $this->assertSame('1234567.89', PtBrNumberParser::parseDecimal('R$ 1.234.567,89'));
        $this->assertSame('1234567.89', PtBrNumberParser::parseDecimal('1.234.567,89'));
        $this->assertSame('1234.56', PtBrNumberParser::parseDecimal('1234,56'));
        $this->assertSame('0.00', PtBrNumberParser::parseDecimal('0,00'));
        $this->assertNull(PtBrNumberParser::parseDecimal(''));
        $this->assertNull(PtBrNumberParser::parseDecimal(null));
    }
}
