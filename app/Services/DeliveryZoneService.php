<?php
namespace App\Services;

class DeliveryZoneService
{
    public function findZone(?string $address): ?array
    {
        if (!is_string($address) || trim($address) === '') {
            return null;
        }

        $normalized = mb_strtolower(
            str_replace(
                ['ñ', 'Ñ'],
                ['n', 'n'],
                trim($address)
            )
        );

        $matches = [];

        foreach (config('delivery.zones', []) as $id => $zone) {
            foreach (($zone['keywords'] ?? []) as $keyword) {
                $keyword = mb_strtolower(
                    str_replace(['ñ', 'Ñ'], ['n', 'n'], trim($keyword))
                );

                if ($keyword === '') {
                    continue;
                }

                $pattern = '/(?<![\p{L}\p{N}])'
                    . preg_quote($keyword, '/')
                    . '(?![\p{L}\p{N}])/u';

                if (preg_match($pattern, $normalized) === 1) {
                    $matches[$id] = [
                        'zone_id' => $id,
                        'zone_name' => $zone['name'],
                        'fee' => (int) $zone['fee'],
                    ];

                    break;
                }
            }
        }

        // Do not guess when no zone or multiple zones match.
        if (count($matches) !== 1) {
            return null;
        }

        return array_values($matches)[0];
    }
}