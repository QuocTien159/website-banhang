import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { ArrowLeft, Loader2, Star, Upload, X } from 'lucide-react';
import { toast } from 'sonner';
import { orderService, type ApiOrder, type OrderItem } from '../../services/orderService';
import { commerceService } from '../../services/commerceService';
import { useAuth } from '../../store/AppContext';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-blue-100 text-blue-700',
  shipping: 'bg-purple-100 text-purple-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  shipping: 'Đang giao hàng',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

type ReviewFormState = {
  item: OrderItem;
  rating: number;
  comment: string;
  files: File[];
  previews: string[];
};

type ReturnLineState = {
  selected: boolean;
  quantity: number;
  reason: string;
  description: string;
  files: File[];
  previews: string[];
};

const reviewKey = (orderId: string, productId?: string | null) => `${orderId}-${productId ?? ''}`;

export function OrderDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const [order, setOrder] = useState<ApiOrder | null>(null);
  const [reviewedKeys, setReviewedKeys] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reviewForm, setReviewForm] = useState<ReviewFormState | null>(null);
  const [returnOpen, setReturnOpen] = useState(false);
  const [returnReason, setReturnReason] = useState('');
  const [returnDescription, setReturnDescription] = useState('');
  const [returnLines, setReturnLines] = useState<Record<string, ReturnLineState>>({});
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }
    if (!id) return;

    setLoading(true);
    setError('');
    Promise.all([
      orderService.getOrder(id),
      commerceService.myReviews().catch(() => []),
    ])
      .then(([orderData, reviews]) => {
        setOrder(orderData);
        setReviewedKeys(new Set((reviews as any[]).map((review) => reviewKey(review.order_id, review.product?.id))));
      })
      .catch((err) => {
        const status = err.response?.status;
        setError(status === 404 ? 'Không tìm thấy đơn hàng hoặc bạn không có quyền xem đơn này.' : 'Không thể tải chi tiết đơn hàng.');
      })
      .finally(() => setLoading(false));
  }, [id, isAuthenticated, navigate]);

  useEffect(() => {
    return () => {
      reviewForm?.previews.forEach((url) => URL.revokeObjectURL(url));
    };
  }, [reviewForm?.previews]);

  const canReview = order?.status === 'delivered';
  const canReturn = order?.status === 'delivered';
  const subtotal = useMemo(() => order?.items.reduce((sum, item) => sum + item.subtotal, 0) ?? 0, [order]);

  const openReturn = () => {
    if (!order) return;
    setReturnReason('');
    setReturnDescription('');
    setReturnLines(Object.fromEntries(order.items.map((item) => [item.variant_id, {
      selected: false,
      quantity: 1,
      reason: '',
      description: '',
      files: [],
      previews: [],
    }])));
    setReturnOpen(true);
  };

  const openReview = (item: OrderItem) => {
    setReviewForm({
      item,
      rating: 5,
      comment: '',
      files: [],
      previews: [],
    });
  };

  const updateReviewFiles = (files: File[]) => {
    if (!reviewForm) return;
    if (files.length > 5) {
      toast.error('Chỉ được tải tối đa 5 ảnh.');
      return;
    }
    const invalid = files.find((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
    if (invalid) {
      toast.error('Ảnh đánh giá chỉ hỗ trợ JPG, PNG hoặc WEBP.');
      return;
    }
    reviewForm.previews.forEach((url) => URL.revokeObjectURL(url));
    setReviewForm({
      ...reviewForm,
      files,
      previews: files.map((file) => URL.createObjectURL(file)),
    });
  };

  const submitReview = async () => {
    if (!order || !reviewForm) return;
    const productId = reviewForm.item.product.id;
    if (!productId) {
      toast.error('Không xác định được sản phẩm cần đánh giá.');
      return;
    }
    if (reviewForm.comment.trim().length < 10) {
      toast.error('Bình luận cần ít nhất 10 ký tự.');
      return;
    }

    setSubmitting(true);
    try {
      const uploadedImages = reviewForm.files.length
        ? await commerceService.uploadReviewImages(reviewForm.files)
        : [];
      await commerceService.createReview({
        order_id: order.id,
        product_id: productId,
        rating: reviewForm.rating,
        comment: reviewForm.comment.trim(),
        images: uploadedImages,
      });
      setReviewedKeys((current) => new Set([...current, reviewKey(order.id, productId)]));
      toast.success('Đánh giá đã gửi và đang chờ Admin duyệt.');
      setReviewForm(null);
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Không thể gửi đánh giá.');
    } finally {
      setSubmitting(false);
    }
  };

  const updateReturnFiles = (variantId: string, files: File[]) => {
    const line = returnLines[variantId];
    if (!line) return;
    if (files.length > 5) {
      toast.error('Chỉ được tải tối đa 5 ảnh minh chứng.');
      return;
    }
    const invalid = files.find((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
    if (invalid) {
      toast.error('Ảnh minh chứng chỉ hỗ trợ JPG, PNG hoặc WEBP.');
      return;
    }
    line.previews.forEach((url) => URL.revokeObjectURL(url));
    setReturnLines({
      ...returnLines,
      [variantId]: { ...line, files, previews: files.map((file) => URL.createObjectURL(file)) },
    });
  };

  const submitReturn = async () => {
    if (!order) return;
    const selected = order.items
      .map((item) => ({ item, line: returnLines[item.variant_id] }))
      .filter(({ line }) => line?.selected);
    if (!selected.length) {
      toast.error('Vui lòng chọn ít nhất một sản phẩm cần trả.');
      return;
    }
    if (!returnReason.trim()) {
      toast.error('Vui lòng nhập lý do chung của yêu cầu trả hàng.');
      return;
    }
    for (const { item, line } of selected) {
      if (!line.reason.trim()) {
        toast.error(`Vui lòng nhập lý do trả hàng cho ${item.product.name}.`);
        return;
      }
      if (line.quantity < 1 || line.quantity > item.quantity) {
        toast.error(`Số lượng trả của ${item.product.name} không hợp lệ.`);
        return;
      }
    }

    setSubmitting(true);
    try {
      const items = [];
      for (const { item, line } of selected) {
        const images = line.files.length ? await commerceService.uploadReturnImages(line.files) : [];
        items.push({
          variant_id: item.variant_id,
          quantity: line.quantity,
          reason: line.reason.trim(),
          description: line.description.trim() || undefined,
          images,
        });
      }
      await commerceService.createReturn({
        order_id: order.id,
        reason: returnReason.trim(),
        description: returnDescription.trim() || undefined,
        items,
      });
      toast.success('Đã gửi yêu cầu trả hàng. Yêu cầu đang chờ xử lý.');
      setReturnOpen(false);
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Không thể gửi yêu cầu trả hàng.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div className="max-w-5xl mx-auto px-4 py-20 text-center">Đang tải chi tiết đơn hàng...</div>;
  }

  if (error || !order) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-20 text-center">
        <h2 className="text-2xl font-semibold mb-2">Không thể mở đơn hàng</h2>
        <p className="text-sm text-muted-foreground mb-6">{error || 'Không tìm thấy đơn hàng.'}</p>
        <Button onClick={() => navigate('/account')} className="bg-orange-600 hover:bg-orange-700">Quay lại lịch sử đơn hàng</Button>
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-8 space-y-6">
      <button onClick={() => navigate('/account')} className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-orange-600">
        <ArrowLeft className="w-4 h-4" />Quay lại lịch sử đơn hàng
      </button>

      <section className="bg-white border rounded-xl p-5">
        <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">Đơn hàng #{order.id}</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Đặt lúc {new Date(order.created_at).toLocaleString('vi-VN')}
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <span className={`w-fit text-sm px-3 py-1 rounded-full font-medium ${STATUS_COLORS[order.status] ?? 'bg-gray-100 text-gray-700'}`}>
              {STATUS_LABELS[order.status] ?? order.status}
            </span>
            {canReturn && (
              <Button variant="outline" size="sm" onClick={openReturn}>
                Yêu cầu trả hàng
              </Button>
            )}
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4 mt-5 text-sm">
          <div className="bg-gray-50 rounded-xl p-4">
            <p className="font-medium mb-2">Người nhận</p>
            <p>{order.shipping_info.name}</p>
            <p className="text-muted-foreground">{order.shipping_info.phone}</p>
            <p className="text-muted-foreground">{order.shipping_info.address}</p>
          </div>
          <div className="bg-gray-50 rounded-xl p-4">
            <p className="font-medium mb-2">Thanh toán</p>
            <p>{order.payment_method === 'cod' ? 'Thanh toán khi nhận hàng' : 'Chuyển khoản'}</p>
            {order.note && <p className="text-muted-foreground mt-1">Ghi chú: {order.note}</p>}
          </div>
        </div>
      </section>

      <section className="bg-white border rounded-xl">
        <div className="p-5 border-b">
          <h2 className="font-semibold">Sản phẩm trong đơn</h2>
          {!canReview && <p className="text-xs text-muted-foreground mt-1">Chỉ có thể đánh giá sau khi đơn hàng đã giao thành công.</p>}
        </div>
        <div className="divide-y">
          {order.items.map((item) => {
            const key = reviewKey(order.id, item.product.id);
            const reviewed = reviewedKeys.has(key);
            return (
              <div key={`${item.variant_id}-${item.product.id}`} className="p-5 flex flex-col md:flex-row md:items-center gap-4">
                <Link to={`/products/${item.product.id}`} className="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                  <ImageWithFallback src={item.product.image ?? ''} alt={item.product.name} className="w-full h-full object-cover" />
                </Link>
                <div className="flex-1 min-w-0">
                  <Link to={`/products/${item.product.id}`} className="font-medium hover:text-orange-600">{item.product.name}</Link>
                  <p className="text-xs text-muted-foreground mt-1">
                    {item.attributes?.map((attribute) => `${attribute.name}: ${attribute.value}`).join(' · ')}
                  </p>
                  <p className="text-sm mt-2">{formatPrice(item.price)} × {item.quantity}</p>
                </div>
                <div className="md:text-right space-y-2">
                  <p className="font-semibold text-orange-600">{formatPrice(item.subtotal)}</p>
                  {canReview && (
                    reviewed ? (
                      <span className="inline-block text-xs px-3 py-1 rounded-full bg-green-100 text-green-700">Đã đánh giá</span>
                    ) : (
                      <Button variant="outline" size="sm" onClick={() => openReview(item)}>
                        Đánh giá
                      </Button>
                    )
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <section className="bg-white border rounded-xl p-5 ml-auto max-w-md space-y-2 text-sm">
        <div className="flex justify-between"><span>Tạm tính</span><span>{formatPrice(subtotal)}</span></div>
        {'shipping' in order && <div className="flex justify-between"><span>Phí vận chuyển</span><span>{formatPrice((order as any).shipping ?? 0)}</span></div>}
        {'discount' in order && (order as any).discount > 0 && <div className="flex justify-between text-green-600"><span>Giảm giá</span><span>-{formatPrice((order as any).discount)}</span></div>}
        <div className="flex justify-between pt-2 border-t font-semibold text-base">
          <span>Tổng tiền</span><span className="text-orange-600">{formatPrice(order.total)}</span>
        </div>
      </section>

      {reviewForm && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setReviewForm(null)} />
          <div className="absolute top-1/2 left-1/2 w-[min(620px,calc(100%-24px))] -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
            <div className="p-5 border-b flex items-start justify-between">
              <div>
                <h3 className="text-lg font-semibold">Đánh giá sản phẩm</h3>
                <p className="text-sm text-muted-foreground">{reviewForm.item.product.name}</p>
              </div>
              <button onClick={() => setReviewForm(null)}><X className="w-5 h-5" /></button>
            </div>
            <div className="p-5 space-y-4">
              <div>
                <p className="text-sm font-medium mb-2">Số sao *</p>
                <div className="flex gap-1">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button key={star} onClick={() => setReviewForm({ ...reviewForm, rating: star })}>
                      <Star className={`w-7 h-7 ${star <= reviewForm.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                    </button>
                  ))}
                </div>
              </div>
              <label className="block space-y-2">
                <span className="text-sm font-medium">Bình luận *</span>
                <textarea
                  value={reviewForm.comment}
                  onChange={(event) => setReviewForm({ ...reviewForm, comment: event.target.value })}
                  rows={4}
                  maxLength={1000}
                  placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..."
                  className="w-full border rounded-lg p-3 text-sm"
                />
                <span className="text-xs text-muted-foreground">{reviewForm.comment.length}/1000 ký tự</span>
              </label>
              <div>
                <label className="inline-flex items-center gap-2 px-3 py-2 border rounded-lg text-sm cursor-pointer">
                  <Upload className="w-4 h-4" />
                  Tải ảnh đánh giá
                  <input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => updateReviewFiles(Array.from(event.target.files ?? []))}
                  />
                </label>
                <p className="text-xs text-muted-foreground mt-2">Tối đa 5 ảnh, định dạng JPG/PNG/WEBP.</p>
                {reviewForm.previews.length > 0 && (
                  <div className="grid grid-cols-5 gap-2 mt-3">
                    {reviewForm.previews.map((preview) => (
                      <img key={preview} src={preview} alt="Preview đánh giá" className="aspect-square object-cover rounded-lg border" />
                    ))}
                  </div>
                )}
              </div>
            </div>
            <div className="p-5 border-t flex justify-end gap-2">
              <Button variant="outline" onClick={() => setReviewForm(null)}>Hủy</Button>
              <Button onClick={submitReview} disabled={submitting} className="bg-orange-600 hover:bg-orange-700">
                {submitting && <Loader2 className="w-4 h-4 animate-spin mr-2" />}Gửi đánh giá
              </Button>
            </div>
          </div>
        </div>
      )}

      {returnOpen && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setReturnOpen(false)} />
          <div className="absolute top-1/2 left-1/2 w-[min(860px,calc(100%-24px))] -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
            <div className="p-5 border-b flex items-start justify-between">
              <div>
                <h3 className="text-lg font-semibold">Yêu cầu trả hàng</h3>
                <p className="text-sm text-muted-foreground">Chọn sản phẩm cần trả trong đơn #{order.id}</p>
              </div>
              <button onClick={() => setReturnOpen(false)}><X className="w-5 h-5" /></button>
            </div>

            <div className="p-5 space-y-5">
              <div className="grid md:grid-cols-2 gap-3">
                <label className="block space-y-2 md:col-span-2">
                  <span className="text-sm font-medium">Lý do chung *</span>
                  <input
                    value={returnReason}
                    onChange={(event) => setReturnReason(event.target.value)}
                    placeholder="Ví dụ: sản phẩm không vừa, lỗi, giao nhầm..."
                    className="w-full border rounded-lg px-3 py-2 text-sm"
                  />
                </label>
                <label className="block space-y-2 md:col-span-2">
                  <span className="text-sm font-medium">Mô tả thêm</span>
                  <textarea
                    value={returnDescription}
                    onChange={(event) => setReturnDescription(event.target.value)}
                    rows={3}
                    placeholder="Mô tả chi tiết tình trạng sản phẩm nếu cần..."
                    className="w-full border rounded-lg px-3 py-2 text-sm"
                  />
                </label>
              </div>

              <div className="space-y-3">
                {order.items.map((item) => {
                  const line = returnLines[item.variant_id];
                  if (!line) return null;
                  return (
                    <div key={item.variant_id} className={`border rounded-xl p-4 ${line.selected ? 'border-orange-300 bg-orange-50/30' : ''}`}>
                      <div className="flex flex-col md:flex-row gap-4">
                        <label className="flex items-start gap-3 flex-1 cursor-pointer">
                          <input
                            type="checkbox"
                            checked={line.selected}
                            onChange={(event) => setReturnLines({
                              ...returnLines,
                              [item.variant_id]: { ...line, selected: event.target.checked },
                            })}
                            className="mt-1"
                          />
                          <div className="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                            <ImageWithFallback src={item.product.image ?? ''} alt={item.product.name} className="w-full h-full object-cover" />
                          </div>
                          <div className="min-w-0">
                            <p className="font-medium">{item.product.name}</p>
                            <p className="text-xs text-muted-foreground">{item.attributes?.map((attr) => `${attr.name}: ${attr.value}`).join(' · ')}</p>
                            <p className="text-xs text-muted-foreground mt-1">Đã mua: {item.quantity} · SKU: {item.variant_id}</p>
                          </div>
                        </label>
                        <label className="text-sm">
                          Số lượng trả
                          <input
                            type="number"
                            min={1}
                            max={item.quantity}
                            disabled={!line.selected}
                            value={line.quantity}
                            onChange={(event) => setReturnLines({
                              ...returnLines,
                              [item.variant_id]: { ...line, quantity: Math.min(item.quantity, Math.max(1, Number(event.target.value))) },
                            })}
                            className="mt-1 w-28 border rounded-lg px-3 py-2 text-sm disabled:bg-gray-100"
                          />
                        </label>
                      </div>

                      {line.selected && (
                        <div className="mt-4 grid md:grid-cols-2 gap-3">
                          <label className="space-y-1">
                            <span className="text-sm">Lý do trả sản phẩm *</span>
                            <input
                              value={line.reason}
                              onChange={(event) => setReturnLines({
                                ...returnLines,
                                [item.variant_id]: { ...line, reason: event.target.value },
                              })}
                              className="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                          </label>
                          <label className="space-y-1">
                            <span className="text-sm">Ảnh minh chứng</span>
                            <input
                              type="file"
                              multiple
                              accept="image/jpeg,image/png,image/webp"
                              onChange={(event) => updateReturnFiles(item.variant_id, Array.from(event.target.files ?? []))}
                              className="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                          </label>
                          <label className="space-y-1 md:col-span-2">
                            <span className="text-sm">Mô tả chi tiết</span>
                            <textarea
                              rows={2}
                              value={line.description}
                              onChange={(event) => setReturnLines({
                                ...returnLines,
                                [item.variant_id]: { ...line, description: event.target.value },
                              })}
                              className="w-full border rounded-lg px-3 py-2 text-sm"
                            />
                          </label>
                          {line.previews.length > 0 && (
                            <div className="md:col-span-2 flex flex-wrap gap-2">
                              {line.previews.map((preview) => (
                                <img key={preview} src={preview} alt="Ảnh minh chứng trả hàng" className="w-16 h-16 rounded-lg border object-cover" />
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="p-5 border-t flex justify-end gap-2">
              <Button variant="outline" onClick={() => setReturnOpen(false)}>Hủy</Button>
              <Button onClick={submitReturn} disabled={submitting} className="bg-orange-600 hover:bg-orange-700">
                {submitting && <Loader2 className="w-4 h-4 animate-spin mr-2" />}Gửi yêu cầu
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
