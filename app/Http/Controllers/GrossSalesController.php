<?php

namespace App\Http\Controllers;

use App\Models\GrossSales;
use App\Http\Requests\UpsertGrossSalesRequest;

class GrossSalesController extends Controller
{
    public function upsert(UpsertGrossSalesRequest $request)
    {
        $data = $request->validated();

        $fields = array_filter([
            'amount'  => $data['amount'] ?? null,
            'vat_itr' => $data['vat_itr'] ?? null,
        ], fn($v) => $v !== null);

        $record = GrossSales::updateOrCreate(
            ['period_id' => $data['period_id'], 'branch_id' => $data['branch_id']],
            $fields
        );

        return response()->json($record);
    }
}
