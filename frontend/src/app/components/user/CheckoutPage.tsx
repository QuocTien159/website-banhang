import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { CreditCard, Truck, CheckCircle, Loader2 } from 'lucide-react';
import { useCart, useAuth } from '../../store/AppContext';
import { orderService, type PlaceOrderPayload } from '../../services/orderService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { toast } from 'sonner';
import { commerceService } from '../../services/commerceService';

const formatPrice = (p: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p);

interface CheckoutForm {
  ten_nguoi_nhan: string;
  so_dien_thoai: string;
  dia_chi_giao: string;
  phuong_thuc_tt: 'cod' | 'banking';
  ghi_chu?: string;
}

export function CheckoutPage() {
  const navigate = useNavigate();
  const { cart, subtotal, shipping, total, refreshCart } = useCart();
  const { user } = useAuth();
  const [placing, setPlacing] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [coupon, setCoupon] = useState<{ code: string; discount: number } | null>(null);
  const [checkingCoupon, setCheckingCoupon] = useState(false);

  const { register, handleSubmit, formState: { errors }, watch } = useForm<CheckoutForm>({
    defaultValues: {
      ten_nguoi_nhan: user?.name || '',
      so_dien_thoai: user?.phone || '',
      dia_chi_giao: '',
      phuong_thuc_tt: 'cod',
    },
  });

  const paymentMethod = watch('phuong_thuc_tt');

  if (cart.length === 0) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 text-center">
        <h2>Giỏ hàng trống</h2>
        <Button className="mt-4" onClick={() => navigate('/products')} style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
          Tiếp tục mua sắm
        </Button>
      </div>
    );
  }

  const onSubmit = async (data: CheckoutForm) => {
    setPlacing(true);
    try {
      const payload: PlaceOrderPayload = {
        ten_nguoi_nhan: data.ten_nguoi_nhan,
        so_dien_thoai: data.so_dien_thoai,
        dia_chi_giao: data.dia_chi_giao,
        phuong_thuc_tt: data.phuong_thuc_tt,
        ghi_chu: data.ghi_chu || undefined,
        coupon_code: coupon?.code,
      };
      const { order } = await orderService.placeOrder(payload);
      // Refresh cart (will be empty after order)
      await refreshCart();
      toast.success(`Đặt hàng thành công! Mã đơn: ${order.id}`);
      navigate('/account', { state: { orderPlaced: order.id } });
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } };
      toast.error(error?.response?.data?.message ?? 'Đặt hàng thất bại. Vui lòng thử lại.');
    } finally {
      setPlacing(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Steps */}
      <div className="flex items-center gap-2 text-sm text-muted-foreground mb-6">
        <span className="text-foreground font-medium">Giỏ hàng</span>
        <span>→</span>
        <span className="font-semibold" style={{ color: '#ea5c21' }}>Thanh toán</span>
        <span>→</span>
        <span>Hoàn tất</span>
      </div>

      <form onSubmit={handleSubmit(onSubmit)}>
        <div className="grid lg:grid-cols-3 gap-6">
          {/* Left: Shipping + Payment */}
          <div className="lg:col-span-2 space-y-6">
            {/* Shipping Info */}
            <div className="bg-white rounded-xl border border-border p-6">
              <div className="flex items-center gap-2 mb-4">
                <Truck className="w-5 h-5" style={{ color: '#ea5c21' }} />
                <h3 className="font-semibold">Thông tin giao hàng</h3>
              </div>
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-1">Họ và tên *</label>
                  <input
                    {...register('ten_nguoi_nhan', { required: 'Vui lòng nhập họ tên' })}
                    className="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400"
                    placeholder="Nguyễn Văn A"
                  />
                  {errors.ten_nguoi_nhan && <p className="text-xs text-red-500 mt-1">{errors.ten_nguoi_nhan.message}</p>}
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Số điện thoại *</label>
                  <input
                    {...register('so_dien_thoai', {
                      required: 'Vui lòng nhập số điện thoại',
                      pattern: { value: /^[0-9]{10,11}$/, message: 'Số điện thoại không hợp lệ' },
                    })}
                    className="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400"
                    placeholder="0912 345 678"
                  />
                  {errors.so_dien_thoai && <p className="text-xs text-red-500 mt-1">{errors.so_dien_thoai.message}</p>}
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium mb-1">Địa chỉ giao hàng *</label>
                  <input
                    {...register('dia_chi_giao', { required: 'Vui lòng nhập địa chỉ' })}
                    className="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400"
                    placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành"
                  />
                  {errors.dia_chi_giao && <p className="text-xs text-red-500 mt-1">{errors.dia_chi_giao.message}</p>}
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium mb-1">Ghi chú (tuỳ chọn)</label>
                  <textarea
                    {...register('ghi_chu')}
                    rows={2}
                    className="w-full px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400 resize-none"
                    placeholder="Giao giờ hành chính, gọi trước khi giao..."
                  />
                </div>
              </div>
            </div>

            {/* Payment Method */}
            <div className="bg-white rounded-xl border border-border p-6">
              <div className="flex items-center gap-2 mb-4">
                <CreditCard className="w-5 h-5" style={{ color: '#ea5c21' }} />
                <h3 className="font-semibold">Phương thức thanh toán</h3>
              </div>
              <div className="space-y-3">
                {[
                  { value: 'cod', label: 'Thanh toán khi nhận hàng (COD)', icon: '💵', desc: 'Trả tiền mặt khi nhận được hàng' },
                  { value: 'banking', label: 'Chuyển khoản ngân hàng', icon: '🏦', desc: 'Chuyển khoản trước, xác nhận qua email' },
                ].map(opt => (
                  <label
                    key={opt.value}
                    className={`flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all
                      ${paymentMethod === opt.value ? 'border-orange-400 bg-orange-50' : 'border-border hover:border-orange-200'}`}
                  >
                    <input
                      type="radio"
                      value={opt.value}
                      {...register('phuong_thuc_tt')}
                      className="mt-0.5"
                    />
                    <div>
                      <p className="text-sm font-medium">{opt.icon} {opt.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{opt.desc}</p>
                    </div>
                  </label>
                ))}
              </div>

              {paymentMethod === 'banking' && (
                <div className="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-200">
                  <p className="text-sm font-medium text-blue-800 mb-2">🏦 Thông tin chuyển khoản:</p>
                  <div className="text-xs text-blue-700 space-y-1">
                    <p>Ngân hàng: <strong>Vietcombank</strong></p>
                    <p>Số tài khoản: <strong>1234567890</strong></p>
                    <p>Chủ tài khoản: <strong>TIENPROSPORT CO., LTD</strong></p>
                    <p>Nội dung: <strong>Thanh toan don hang [Mã đơn]</strong></p>
                  </div>
                  <p className="text-xs text-blue-600 mt-2 italic">* Đơn hàng sẽ được xác nhận sau khi chúng tôi nhận được thanh toán.</p>
                </div>
              )}
            </div>
          </div>

          {/* Right: Order summary */}
          <div>
            <div className="bg-white rounded-xl border border-border p-4 space-y-3 sticky top-4">
              <h3 className="font-semibold flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-500" />
                Đơn hàng ({cart.length} sản phẩm)
              </h3>

              <div className="space-y-3 max-h-60 overflow-y-auto">
                {cart.map(item => (
                  <div key={item.variantId} className="flex gap-3">
                    <div className="w-14 h-14 rounded-lg overflow-hidden bg-gray-50 shrink-0">
                      <ImageWithFallback src={item.productImage ?? ''} alt={item.productName} className="w-full h-full object-cover" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-medium line-clamp-2">{item.productName}</p>
                      {item.attributes.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                          {item.attributes.map(a => a.value).join(' / ')}
                        </p>
                      )}
                      <div className="flex justify-between mt-1">
                        <span className="text-xs text-muted-foreground">×{item.quantity}</span>
                        <span className="text-xs font-semibold" style={{ color: '#ea5c21' }}>
                          {formatPrice(item.price * item.quantity)}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <div className="border-t border-border pt-3 space-y-2 text-sm">
                <div>
                  <label className="font-medium">Mã khuyến mãi</label>
                  <div className="flex gap-2 mt-1">
                    <input value={couponCode} onChange={(e) => { setCouponCode(e.target.value.toUpperCase()); setCoupon(null); }} placeholder="Nhập mã" className="flex-1 border rounded-lg px-3 py-2 text-sm" />
                    <Button type="button" variant="outline" disabled={checkingCoupon || !couponCode} onClick={async () => {
                      setCheckingCoupon(true);
                      try {
                        const result = await commerceService.validateCoupon(couponCode);
                        setCoupon({ code: result.code, discount: result.discount });
                        toast.success('Áp dụng mã thành công.');
                      } catch (error: any) {
                        setCoupon(null);
                        toast.error(error.response?.data?.errors?.coupon_code?.[0] ?? error.response?.data?.message ?? 'Mã không hợp lệ.');
                      } finally { setCheckingCoupon(false); }
                    }}>Áp dụng</Button>
                  </div>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Tạm tính</span>
                  <span>{formatPrice(subtotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Vận chuyển</span>
                  <span className={shipping === 0 ? 'text-green-600' : ''}>
                    {shipping === 0 ? 'Miễn phí' : formatPrice(shipping)}
                  </span>
                </div>
                {coupon && <div className="flex justify-between text-green-600"><span>Giảm giá ({coupon.code})</span><span>-{formatPrice(coupon.discount)}</span></div>}
                <div className="flex justify-between font-bold text-base border-t border-border pt-2">
                  <span>Tổng cộng</span>
                  <span style={{ color: '#ea5c21' }}>{formatPrice(Math.max(0, total - (coupon?.discount ?? 0)))}</span>
                </div>
              </div>

              <Button
                type="submit"
                className="w-full gap-2 text-white"
                disabled={placing}
                style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}
              >
                {placing ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Đang đặt hàng...
                  </>
                ) : (
                  <>
                    <CheckCircle className="w-4 h-4" />
                    Xác nhận đặt hàng
                  </>
                )}
              </Button>
              <p className="text-xs text-center text-muted-foreground">
                Bằng cách đặt hàng, bạn đồng ý với điều khoản sử dụng của chúng tôi.
              </p>
            </div>
          </div>
        </div>
      </form>
    </div>
  );
}
