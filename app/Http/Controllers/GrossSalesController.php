<?php

namespace App\Http\Controllers;

use App\Models\GrossSales;
use App\Http\Requests\UpsertGrossSalesRequest;

class GrossSalesController extends Controller
{
    public function upsert(UpsertGrossSalesRequest $request)
    {
        $data = $request->validated();

        $record = GrossSales::updateOrCreate(
            ['period_id' => $data['period_id'], 'branch_id' => $data['branch_id']],
            ['amount'    => $data['amount']]
        );

        return response()->json($record);
    }
}
