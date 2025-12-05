<?php

namespace App\Http\Controllers\StoreKeeper;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use App\Models\Drug;

class CategoryStoreKeeperController extends BaseApiController
{
    // GET /api/storekeeper/categories
    // يُرجع قائمة القيم المميزة لعمود category من جدول drug
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'warehouse_manager') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $categories = Drug::query()
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->get()
            ->map(function ($row) {
                return [
                    'id'   => $row->category,   // نستعمل القيمة نفسها كـ id
                    'name' => $row->category,
                ];
            })
            ->values();

        return response()->json($categories);
    }
}
