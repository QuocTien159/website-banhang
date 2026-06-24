<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanhMuc;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = DanhMuc::withCount('sanPhams')->get();

        return response()->json($categories->map(fn($dm) => [
            'id'    => $dm->ma_dm,
            'name'  => $dm->ten_dm,
            'count' => $dm->san_phams_count,
        ]));
    }
}
