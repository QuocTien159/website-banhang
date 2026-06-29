import { Link, useNavigate } from 'react-router';
import { Trash2, Plus, Minus, ShoppingCart, ArrowRight, Loader2 } from 'lucide-react';
import { useCart } from '../../store/AppContext';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { toast } from 'sonner';
import { formatCurrency } from '../../utils/formatters';

export function CartPage() {
  const { cart, totalItems, subtotal, shipping, total, isLoading, updateQuantity, removeFromCart } = useCart();
  const navigate = useNavigate();

  const handleQuantityChange = async (variantId: string, newQty: number) => {
    try {
      if (newQty <= 0) {
        await removeFromCart(variantId);
      } else {
        await updateQuantity(variantId, newQty);
      }
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error?.response?.data?.message ?? 'Không thể cập nhật giỏ hàng');
    }
  };

  if (cart.length === 0 && !isLoading) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 text-center">
        <div className="max-w-sm mx-auto">
          <div className="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 bg-orange-50">
            <ShoppingCart className="w-10 h-10" style={{ color: '#ea5c21' }} />
          </div>
          <h2 className="mb-2">Giỏ hàng trống</h2>
          <p className="text-muted-foreground text-sm mb-6">Bạn chưa có sản phẩm nào trong giỏ hàng</p>
          <Button onClick={() => navigate('/products')} style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
            Tiếp tục mua sắm
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <h2 className="mb-6">Giỏ hàng ({totalItems} sản phẩm)</h2>

      {isLoading && (
        <div className="flex items-center gap-2 text-sm text-muted-foreground mb-4">
          <Loader2 className="w-4 h-4 animate-spin" />
          Đang cập nhật...
        </div>
      )}

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Cart Items */}
        <div className="lg:col-span-2 space-y-3">
          {cart.map(item => (
            <div key={item.variantId} className="bg-white rounded-xl border border-border p-4 flex gap-4">
              <div
                className="w-20 h-20 rounded-lg overflow-hidden bg-gray-50 shrink-0 cursor-pointer"
                onClick={() => navigate(`/products/${item.productId}`)}
              >
                <ImageWithFallback
                  src={item.productImage ?? ''}
                  alt={item.productName}
                  className="w-full h-full object-cover"
                />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p
                      className="text-sm font-medium line-clamp-2 cursor-pointer hover:text-orange-600"
                      onClick={() => navigate(`/products/${item.productId}`)}
                    >
                      {item.productName}
                    </p>
                    {/* Attributes (Size, Color, etc.) */}
                    {item.attributes.length > 0 && (
                      <div className="flex flex-wrap gap-1 mt-1">
                        {item.attributes.map((a, i) => (
                          <span key={i} className="text-xs px-2 py-0.5 bg-gray-100 rounded-full text-muted-foreground">
                            {a.name}: {a.value}
                          </span>
                        ))}
                      </div>
                    )}
                    <p className="text-sm font-semibold mt-1" style={{ color: '#ea5c21' }}>
                      {formatCurrency(item.price)}
                    </p>
                  </div>
                  <button
                    onClick={() => handleQuantityChange(item.variantId, 0)}
                    className="p-1.5 rounded-lg hover:bg-red-50 hover:text-red-500 transition-colors text-muted-foreground"
                    disabled={isLoading}
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>

                <div className="flex items-center justify-between mt-3">
                  <div className="flex items-center border border-border rounded-lg overflow-hidden">
                    <button
                      onClick={() => handleQuantityChange(item.variantId, item.quantity - 1)}
                      className="w-8 h-8 flex items-center justify-center hover:bg-accent transition-colors"
                      disabled={isLoading}
                    >
                      <Minus className="w-3 h-3" />
                    </button>
                    <span className="w-10 text-center text-sm font-medium">{item.quantity}</span>
                    <button
                      onClick={() => handleQuantityChange(item.variantId, item.quantity + 1)}
                      className="w-8 h-8 flex items-center justify-center hover:bg-accent transition-colors"
                      disabled={isLoading || item.quantity >= item.stock}
                    >
                      <Plus className="w-3 h-3" />
                    </button>
                  </div>
                  <p className="text-sm font-semibold">
                    {formatCurrency(item.price * item.quantity)}
                  </p>
                </div>

                {/* Stock warning */}
                {item.stock <= 5 && item.stock > 0 && (
                  <p className="text-xs text-yellow-600 mt-1">⚠️ Chỉ còn {item.stock} sản phẩm</p>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* Order Summary */}
        <div className="space-y-4">
          <div className="bg-white rounded-xl border border-border p-4 space-y-3">
            <h3 className="font-semibold">Tóm tắt đơn hàng</h3>

            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Tạm tính ({totalItems} sản phẩm)</span>
                <span>{formatCurrency(subtotal)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Phí vận chuyển</span>
                {shipping === 0 ? (
                  <span className="text-green-600">Miễn phí</span>
                ) : (
                  <span>{formatCurrency(shipping)}</span>
                )}
              </div>
              {shipping > 0 && (
                <p className="text-xs text-muted-foreground">
                  Mua thêm {formatCurrency(500000 - subtotal)} để miễn phí ship
                </p>
              )}
            </div>

            <div className="border-t border-border pt-3 flex justify-between font-semibold">
              <span>Tổng cộng</span>
              <span style={{ color: '#ea5c21' }}>{formatCurrency(total)}</span>
            </div>

            <Button
              className="w-full gap-2 text-white"
              onClick={() => navigate('/checkout')}
              style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}
              disabled={isLoading || cart.length === 0}
            >
              Tiến hành thanh toán
              <ArrowRight className="w-4 h-4" />
            </Button>

            <Link
              to="/products"
              className="block text-center text-sm text-muted-foreground hover:text-foreground"
            >
              ← Tiếp tục mua sắm
            </Link>
          </div>

          {/* Free shipping notice */}
          <div className="bg-green-50 rounded-xl border border-green-200 p-3">
            <p className="text-xs text-green-700 font-medium">🚚 Miễn phí vận chuyển cho đơn từ 500.000đ</p>
          </div>
        </div>
      </div>
    </div>
  );
}
