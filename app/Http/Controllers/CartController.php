<?php
namespace App\Http\Controllers;

use App\Http\Requests\{CartAddRequest, CartUpdateRequest};
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(CartService $cart)
    {
        return view('site.cart', ['items'=>$cart->items(), 'subtotal'=>$cart->subtotal()]);
    }

    public function add(CartAddRequest $request, Product $product, CartService $cart)
    {
        abort_unless($product->is_active, 404);
        $data=$request->validated();
        $variant=null;
        if (!empty($data['variant_id'])) {
            $variant=$product->variants()->whereKey($data['variant_id'])->where('is_active',true)->firstOrFail();
        }
        $available=$variant?->stock ?? $product->stock;
        abort_if($available < $data['quantity'], 422, 'Requested quantity is unavailable.');
        $options=array_filter(['colour'=>$data['colour']??null,'size'=>$data['size']??null]);
        $cart->add($product,$data['quantity'],$variant,$options);
        return redirect()->route('cart')->with('success','Product added to cart.');
    }

    public function update(CartUpdateRequest $request, string $key, CartService $cart)
    {
        $data=$request->validated();
        if ($data['quantity']>0) {
            $item=$cart->items()[$key]??null;
            if ($item) {
                $available=$item['variant_id']?ProductVariant::find($item['variant_id'])?->stock:Product::find($item['product_id'])?->stock;
                abort_if($available!==null && $data['quantity']>$available,422,'Requested quantity is unavailable.');
            }
        }
        $cart->update($key,$data['quantity']);
        return redirect()->route('cart')->with('success','Cart updated.');
    }

    public function remove(string $key, CartService $cart)
    {
        $cart->remove($key);
        return redirect()->route('cart')->with('success','Item removed from cart.');
    }
}
