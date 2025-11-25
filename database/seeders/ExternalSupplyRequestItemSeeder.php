<?php

namespace Database\Seeders;

use App\Models\ExternalSupplyRequestItem;
use App\Models\ExternalSupplyRequest;
use App\Models\Drug;
use Illuminate\Database\Seeder;

class ExternalSupplyRequestItemSeeder extends Seeder
{
    public function run()
    {
        $request = ExternalSupplyRequest::first();
        $drug = Drug::first();

        if (! $request || ! $drug) {
            // parent request or drug missing — skip to avoid FK errors
            return;
        }

        ExternalSupplyRequestItem::create([
            'request_id'     => $request->id,
            'drug_id'        => $drug->id,
            'requested_qty'  => 200,
            'approved_qty'   => 150,
            'fulfilled_qty'  => 140,
        ]);
    }
}
