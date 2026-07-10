import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useNavigate } from 'react-router';
import { useForm } from 'react-hook-form';
import { CheckCircle, CreditCard, Loader2, QrCode, Truck } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth, useCart } from '../../store/AppContext';
import { commerceService } from '../../services/commerceService';
import { orderService, type AdministrativeUnit, type PlaceOrderPayload, type ShippingCalculation } from '../../services/orderService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

interface CheckoutForm {
  ten_nguoi_nhan: string;
  so_dien_thoai: string;
  province_id: string;
  district_code: string;
  ward_code: string;
  address_detail: string;
  phuong_thuc_tt: 'cod' | 'payos';
  ghi_chu?: string;
}

export function CheckoutPage() {
  const navigate = useNavigate();
  const { cart, subtotal, refreshCart } = useCart();
  const { user } = useAuth();
  const [placing, setPlacing] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [coupon, setCoupon] = useState<{ code: string; discount: number } | null>(null);
  const [checkingCoupon, setCheckingCoupon] = useState(false);
  const [shippingResult, setShippingResult] = useState<ShippingCalculation | null>(null);
  const [shippingLoading, setShippingLoading] = useState(false);
  const [provinces, setProvinces] = useState<AdministrativeUnit[]>([]);
  const [districts, setDistricts] = useState<AdministrativeUnit[]>([]);
  const [wards, setWards] = useState<AdministrativeUnit[]>([]);
  const [addressError, setAddressError] = useState('');

  const { register, handleSubmit, formState: { errors }, watch, setValue } = useForm<CheckoutForm>({
    defaultValues: {
      ten_nguoi_nhan: user?.name || '',
      so_dien_thoai: user?.phone || '',
      province_id: '',
      district_code: '',
      ward_code: '',
      address_detail: '',
      phuong_thuc_tt: 'cod',
    },
  });

  const paymentMethod = watch('phuong_thuc_tt');
  const provinceId = watch('province_id');
  const districtCode = watch('district_code');
  const wardCode = watch('ward_code');
  const addressDetail = watch('address_detail');
  const shippingFee = shippingResult?.shipping_fee ?? 0;
  const total = Math.max(0, subtotal + shippingFee - (coupon?.discount ?? 0));
  const addressComplete = Boolean(provinceId && districtCode && wardCode && addressDetail.trim());
  const canSubmit = addressComplete && shippingResult?.valid && shippingResult.shipping_fee !== null;

  useEffect(() => {
    orderService.getProvinces()
      .then((items) => {
        setProvinces(items);
        if (items.length === 0) setAddressError('Không thể tải địa chỉ GHN. Vui lòng thử lại sau.');
      })
      .catch(() => {
        setProvinces([]);
        setAddressError('Không thể tải địa chỉ GHN. Vui lòng thử lại sau.');
      });
  }, []);

  useEffect(() => {
    setDistricts([]);
    setWards([]);
    setValue('district_code', '');
    setValue('ward_code', '');
    if (!provinceId) return;

    setAddressError('');
    orderService.getDistricts(provinceId)
      .then((items) => {
        setDistricts(items);
        if (items.length === 0) setAddressError('Không thể tải quận/huyện từ GHN. Vui lòng chọn lại tỉnh/thành hoặc thử lại sau.');
      })
      .catch(() => {
        setDistricts([]);
        setAddressError('Không thể tải quận/huyện từ GHN. Vui lòng thử lại sau.');
      });
  }, [provinceId, setValue]);

  useEffect(() => {
    setWards([]);
    setValue('ward_code', '');
    if (!districtCode) return;

    setAddressError('');
    orderService.getWards(districtCode)
      .then((items) => {
        setWards(items);
        if (items.length === 0) setAddressError('Không thể tải phường/xã từ GHN. Vui lòng chọn lại quận/huyện hoặc thử lại sau.');
      })
      .catch(() => {
        setWards([]);
        setAddressError('Không thể tải phường/xã từ GHN. Vui lòng thử lại sau.');
      });
  }, [districtCode, setValue]);

  useEffect(() => {
    if (!addressComplete) {
      setShippingResult({
        valid: false,
        shipping_fee: null,
        base_shipping_fee: null,
        free_shipping_applied: false,
        message: 'Chọn địa chỉ để tính phí vận chuyển',
      });
      return;
    }

    const timer = window.setTimeout(async () => {
      setShippingLoading(true);
      try {
        setShippingResult(await orderService.calculateShipping({
          province_id: provinceId,
          district_code: districtCode,
          ward_code: wardCode,
          address_detail: addressDetail,
        }));
      } catch (error: any) {
        setShippingResult({
          valid: false,
          shipping_fee: null,
          free_shipping_applied: false,
          message: error.response?.data?.message ?? 'Không thể tính phí GHN, vui lòng thử lại',
        });
      } finally {
        setShippingLoading(false);
      }
    }, 350);

    return () => window.clearTimeout(timer);
  }, [addressComplete, provinceId, districtCode, wardCode, addressDetail]);

  const cartCount = useMemo(() => cart.reduce((sum, item) => sum + item.quantity, 0), [cart]);

  if (cart.length === 0) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 text-center">
        <h2 className="text-2xl font-semibold">Giỏ hàng trống</h2>
        <Button className="mt-4" onClick={() => navigate('/products')} style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
          Tiếp tục mua sắm
        </Button>
      </div>
    );
  }

  const onSubmit = async (data: CheckoutForm) => {
    if (!canSubmit) {
      toast.error('Vui lòng chọn đầy đủ địa chỉ giao hàng để tính phí vận chuyển.');
      return;
    }

    setPlacing(true);
    try {
      const payload: PlaceOrderPayload = {
        ten_nguoi_nhan: data.ten_nguoi_nhan,
        so_dien_thoai: data.so_dien_thoai,
        province_id: data.province_id,
        district_code: data.district_code,
        ward_code: data.ward_code,
        address_detail: data.address_detail,
        phuong_thuc_tt: data.phuong_thuc_tt,
        ghi_chu: data.ghi_chu || undefined,
        coupon_code: coupon?.code,
      };

      const { order } = await orderService.placeOrder(payload);
      await refreshCart();
      toast.success(`Đặt hàng thành công! Mã đơn: ${order.id}`);
      if (order.payment_method === 'payos' && order.payment_checkout_url) {
        window.location.href = order.payment_checkout_url;
        return;
      }

      navigate(order.payment_method === 'bank_transfer_qr' ? `/account/orders/${order.id}/qr-payment` : '/account', {
        state: { orderPlaced: order.id },
      });
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Đặt hàng thất bại. Vui lòng thử lại.');
    } finally {
      setPlacing(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-2 text-sm text-muted-foreground mb-6">
        <span className="text-foreground font-medium">Giỏ hàng</span>
        <span>→</span>
        <span className="font-semibold" style={{ color: '#ea5c21' }}>Thanh toán</span>
        <span>→</span>
        <span>Hoàn tất</span>
      </div>

      <form onSubmit={handleSubmit(onSubmit)}>
        <div className="grid lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <section className="bg-white rounded-xl border border-border p-6">
              <div className="flex items-center gap-2 mb-4">
                <Truck className="w-5 h-5" style={{ color: '#ea5c21' }} />
                <h3 className="font-semibold">Thông tin giao hàng</h3>
              </div>
              <div className="grid sm:grid-cols-2 gap-4">
                <TextInput label="Họ và tên *" error={errors.ten_nguoi_nhan?.message}>
                  <input {...register('ten_nguoi_nhan', { required: 'Vui lòng nhập họ tên' })} className="w-full px-3 py-2 text-sm border rounded-lg" />
                </TextInput>
                <TextInput label="Số điện thoại *" error={errors.so_dien_thoai?.message}>
                  <input
                    {...register('so_dien_thoai', {
                      required: 'Vui lòng nhập số điện thoại',
                      pattern: { value: /^[0-9]{10,11}$/, message: 'Số điện thoại không hợp lệ' },
                    })}
                    className="w-full px-3 py-2 text-sm border rounded-lg"
                  />
                </TextInput>
                <TextInput label="Tỉnh/thành phố *" error={errors.province_id?.message}>
                  <select
                    {...register('province_id', { required: 'Vui lòng chọn tỉnh/thành phố' })}
                    className="w-full px-3 py-2 text-sm border rounded-lg bg-white"
                  >
                    <option value="">Chọn tỉnh/thành phố</option>
                    {provinces.map((province) => <option key={province.code} value={province.code}>{province.name}</option>)}
                  </select>
                </TextInput>
                    <TextInput label="Quận/huyện *" error={errors.district_code?.message}>
                      <select {...register('district_code', { required: 'Vui lòng chọn quận/huyện' })} disabled={!provinceId} className="w-full px-3 py-2 text-sm border rounded-lg bg-white disabled:bg-gray-100">
                        <option value="">Chọn quận/huyện</option>
                        {districts.map((district) => <option key={district.code} value={district.code}>{district.name}</option>)}
                      </select>
                    </TextInput>
                    <TextInput label="Phường/xã *" error={errors.ward_code?.message}>
                      <select {...register('ward_code', { required: 'Vui lòng chọn phường/xã' })} disabled={!districtCode} className="w-full px-3 py-2 text-sm border rounded-lg bg-white disabled:bg-gray-100">
                        <option value="">Chọn phường/xã</option>
                        {wards.map((ward) => <option key={ward.code} value={ward.code}>{ward.name}</option>)}
                      </select>
                    </TextInput>
                <TextInput label="Địa chỉ nhà/số nhà/tên đường *" error={errors.address_detail?.message}>
                  <input {...register('address_detail', { required: 'Vui lòng nhập địa chỉ nhà/số nhà/tên đường' })} placeholder="VD: 12 Nguyễn Trãi, hẻm 5, chung cư ABC..." className="w-full px-3 py-2 text-sm border rounded-lg" />
                </TextInput>
                <label className="block sm:col-span-2">
                  <span className="block text-sm font-medium mb-1">Ghi chú</span>
                  <textarea {...register('ghi_chu')} rows={2} className="w-full px-3 py-2 text-sm border rounded-lg resize-none" />
                </label>
              </div>
              {addressError && <p className="mt-3 text-sm text-red-600">{addressError}</p>}
            </section>

            <section className="bg-white rounded-xl border border-border p-6">
              <div className="flex items-center gap-2 mb-4">
                <CreditCard className="w-5 h-5" style={{ color: '#ea5c21' }} />
                <h3 className="font-semibold">Phương thức thanh toán</h3>
              </div>
              <div className="space-y-3">
                {[
                  { value: 'cod', label: 'Thanh toán khi nhận hàng (COD)', icon: '💵', desc: 'Trả tiền mặt khi nhận được hàng' },
                  { value: 'payos', label: 'Thanh toán payOS', icon: '🏦', desc: 'Chuyển sang trang checkout payOS để thanh toán VietQR an toàn' },
                ].map((option) => (
                  <label key={option.value} className={`flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer ${paymentMethod === option.value ? 'border-orange-400 bg-orange-50' : 'border-border hover:border-orange-200'}`}>
                    <input type="radio" value={option.value} {...register('phuong_thuc_tt')} className="mt-0.5" />
                    <div>
                      <p className="text-sm font-medium">{option.icon} {option.label}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{option.desc}</p>
                    </div>
                  </label>
                ))}
              </div>
              {paymentMethod === 'payos' && (
                <div className="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-200 text-sm text-blue-800">
                  Sau khi đặt hàng, bạn sẽ được chuyển thẳng sang trang thanh toán payOS. Website chỉ cập nhật đã thanh toán khi payOS gửi xác nhận hợp lệ.
                </div>
              )}
            </section>
          </div>

          <aside>
            <div className="bg-white rounded-xl border border-border p-4 space-y-3 sticky top-4">
              <h3 className="font-semibold flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-500" />
                Đơn hàng ({cartCount} sản phẩm)
              </h3>

              <div className="space-y-3 max-h-60 overflow-y-auto">
                {cart.map((item) => (
                  <div key={item.variantId} className="flex gap-3">
                    <div className="w-14 h-14 rounded-lg overflow-hidden bg-gray-50 shrink-0">
                      <ImageWithFallback src={item.productImage ?? ''} alt={item.productName} className="w-full h-full object-cover" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-xs font-medium line-clamp-2">{item.productName}</p>
                      {item.attributes.length > 0 && <p className="text-xs text-muted-foreground">{item.attributes.map((attribute) => attribute.value).join(' / ')}</p>}
                      <div className="flex justify-between mt-1">
                        <span className="text-xs text-muted-foreground">×{item.quantity}</span>
                        <span className="text-xs font-semibold" style={{ color: '#ea5c21' }}>{formatPrice(item.price * item.quantity)}</span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              <div className="border-t border-border pt-3 space-y-2 text-sm">
                <div>
                  <label className="font-medium">Mã khuyến mãi</label>
                  <div className="flex gap-2 mt-1">
                    <input value={couponCode} onChange={(event) => { setCouponCode(event.target.value.toUpperCase()); setCoupon(null); }} placeholder="Nhập mã" className="flex-1 border rounded-lg px-3 py-2 text-sm" />
                    <Button type="button" variant="outline" disabled={checkingCoupon || !couponCode} onClick={async () => {
                      setCheckingCoupon(true);
                      try {
                        const result = await commerceService.validateCoupon(couponCode);
                        setCoupon({ code: result.code, discount: result.discount });
                        toast.success('Áp dụng mã thành công.');
                      } catch (error: any) {
                        setCoupon(null);
                        toast.error(error.response?.data?.errors?.coupon_code?.[0] ?? error.response?.data?.message ?? 'Mã không hợp lệ.');
                      } finally {
                        setCheckingCoupon(false);
                      }
                    }}>Áp dụng</Button>
                  </div>
                </div>
                <div className="flex justify-between"><span className="text-muted-foreground">Tạm tính</span><span>{formatPrice(subtotal)}</span></div>
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Vận chuyển</span>
                  <span className={shippingFee === 0 && shippingResult?.valid ? 'text-green-600' : ''}>
                    {shippingLoading ? 'Đang tính phí GHN…' : shippingResult?.shipping_fee === null ? 'Chọn địa chỉ để tính phí vận chuyển' : shippingFee === 0 ? 'Miễn phí' : formatPrice(shippingFee)}
                  </span>
                </div>
                {shippingResult?.valid && shippingResult.base_shipping_fee !== null && shippingResult.free_shipping_applied && (
                  <p className="text-xs text-muted-foreground line-through">Phí gốc: {formatPrice(shippingResult.base_shipping_fee ?? 0)}</p>
                )}
                {shippingResult?.message && <p className={`text-xs ${shippingResult.valid ? 'text-green-600' : 'text-orange-600'}`}>{shippingResult.message}</p>}
                {coupon && <div className="flex justify-between text-green-600"><span>Giảm giá ({coupon.code})</span><span>-{formatPrice(coupon.discount)}</span></div>}
                <div className="flex justify-between font-bold text-base border-t border-border pt-2">
                  <span>Tổng cộng</span>
                  <span style={{ color: '#ea5c21' }}>{formatPrice(total)}</span>
                </div>
              </div>

              <Button type="submit" className="w-full gap-2 text-white" disabled={placing || !canSubmit} style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
                {placing ? <><Loader2 className="w-4 h-4 animate-spin" />Đang đặt hàng...</> : <><QrCode className="w-4 h-4" />Xác nhận đặt hàng</>}
              </Button>
              <p className="text-xs text-center text-muted-foreground">
                Backend sẽ validate địa chỉ hành chính và tính lại phí ship khi tạo đơn.
              </p>
            </div>
          </aside>
        </div>
      </form>
    </div>
  );
}

function TextInput({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="block text-sm font-medium mb-1">{label}</span>
      {children}
      {error && <p className="text-xs text-red-500 mt-1">{error}</p>}
    </label>
  );
}
