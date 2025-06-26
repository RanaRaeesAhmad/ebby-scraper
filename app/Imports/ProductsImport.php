<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToArray, WithHeadingRow
{
    public function array(array $array)
    {
        // Filter out empty rows and ensure data structure
        return array_filter(array_map(function($row) {
            if (empty($row['asin']) && empty($row['ebay_price'])) {
                return null;
            }
            
            return [
                'asin' => $row['asin'] ?? $row['ASIN'] ?? null,
                'ebay_price' => $row['ebay_price'] ?? $row['Ebay price'] ?? null
            ];
        }, $array));
    }
}