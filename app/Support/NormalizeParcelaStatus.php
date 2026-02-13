<?php

namespace App\Support;

use Illuminate\Support\Str;

class NormalizeParcelaStatus
{
    public const PAGA = 'PAGA';

    public const EM_ABERTO = 'EM_ABERTO';

    public const DESCONHECIDO = 'DESCONHECIDO';

    public static function classify(mixed $rawStatus): string
    {
        $normalized = self::normalize($rawStatus);
        if ($normalized === null) {
            return self::DESCONHECIDO;
        }

        if (self::matchesOpen($normalized)) {
            return self::EM_ABERTO;
        }

        if (self::matchesPaid($normalized)) {
            return self::PAGA;
        }

        return self::DESCONHECIDO;
    }

    public static function toParcelaSituacao(mixed $rawStatus): string
    {
        if (self::isCanceled($rawStatus)) {
            return 'CANCELADA';
        }

        return match (self::classify($rawStatus)) {
            self::PAGA => 'PAGA',
            default => 'PREVISTA',
        };
    }

    public static function normalize(mixed $rawStatus): ?string
    {
        if ($rawStatus === null) {
            return null;
        }

        $text = trim((string) $rawStatus);
        if ($text === '') {
            return null;
        }

        $ascii = Str::of($text)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        return $ascii !== '' ? $ascii : null;
    }

    public static function isCanceled(mixed $rawStatus): bool
    {
        $normalized = self::normalize($rawStatus);
        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/\bcancelad[oa]s?\b/u', $normalized);
    }

    private static function matchesPaid(string $normalized): bool
    {
        return (bool) preg_match('/\b(pag[ao]s?|pagamento efetuad[oa]s?|quitad[oa]s?|liquidad[oa]s?|baixad[oa]s?)\b/u', $normalized);
    }

    private static function matchesOpen(string $normalized): bool
    {
        return (bool) preg_match('/\b(em aberto|abert[oa]s?|pendente[s]?|a pagar|nao pago[s]?)\b/u', $normalized);
    }
}
