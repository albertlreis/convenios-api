<?php

namespace Tests\Unit;

use App\Support\NormalizeParcelaStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizeParcelaStatusTest extends TestCase
{
    #[DataProvider('paidStatusesProvider')]
    public function test_classifica_status_pago_com_variacoes(string $value): void
    {
        $this->assertSame(NormalizeParcelaStatus::PAGA, NormalizeParcelaStatus::classify($value));
        $this->assertSame('PAGA', NormalizeParcelaStatus::toParcelaSituacao($value));
    }

    #[DataProvider('openStatusesProvider')]
    public function test_classifica_status_em_aberto_com_variacoes(string $value): void
    {
        $this->assertSame(NormalizeParcelaStatus::EM_ABERTO, NormalizeParcelaStatus::classify($value));
        $this->assertSame('PREVISTA', NormalizeParcelaStatus::toParcelaSituacao($value));
    }

    #[DataProvider('unknownStatusesProvider')]
    public function test_classifica_status_desconhecido(mixed $value): void
    {
        $this->assertSame(NormalizeParcelaStatus::DESCONHECIDO, NormalizeParcelaStatus::classify($value));
        $this->assertSame('PREVISTA', NormalizeParcelaStatus::toParcelaSituacao($value));
    }

    public static function paidStatusesProvider(): array
    {
        return [
            ['PAGO'],
            ['pago'],
            [' PAGO '],
            ['pAgO'],
            ['PAGA'],
            ['paga'],
            ['Paga.'],
            ['PAGO/OK'],
            ['PAGA (FINALIZADO)'],
            ['PAGAMENTO EFETUADO'],
            ['QUITADO'],
            ['LIQUIDADO'],
            ['BAIXADO'],
        ];
    }

    public static function openStatusesProvider(): array
    {
        return [
            ['EM ABERTO'],
            ['em aberto'],
            ['ABERTO'],
            ['A PAGAR'],
            ['PENDENTE'],
            ['NÃO PAGO'],
            ['NAO PAGO'],
        ];
    }

    public static function unknownStatusesProvider(): array
    {
        return [
            [null],
            [''],
            ['   '],
            ['SEM INFORMACAO'],
        ];
    }
}
