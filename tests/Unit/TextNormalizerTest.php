<?php

namespace Tests\Unit;

use App\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_normaliza_texto_ignorando_acentos_e_espacos(): void
    {
        $this->assertSame('sao joao da ponta', TextNormalizer::normalizeForMatch('  São   João da Ponta  '));
        $this->assertSame('belem', TextNormalizer::normalizeForMatch('Belém'));
        $this->assertNull(TextNormalizer::normalizeForMatch('   '));
    }
}
