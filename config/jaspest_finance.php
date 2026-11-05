<?php

$path = database_path('seeders/data/finance-reference-data.json');

if (! is_file($path)) {
    return [
        'source_workbook' => 'BAHAN KEUANGAN NEW.xlsx',
        'bank_accounts' => [],
        'default_invoice_bank_account_id' => null,
        'chart_accounts' => [],
        'cash_bank_accounts' => [],
        'payment_methods' => [],
    ];
}

$data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

return is_array($data) ? $data : [];
