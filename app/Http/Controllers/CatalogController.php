<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

final class CatalogController
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::where('listed', true)->with(['inventory'])->withCount('venues')->latest('id')->paginate(9),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->listed, 404);

        return view('products.show', ['product' => $product->load(['venues', 'inventory'])]);
    }
}
