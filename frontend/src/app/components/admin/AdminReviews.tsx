import { useEffect, useState } from 'react';
import { Eye, ImageIcon, Star, X } from 'lucide-react';
import { toast } from 'sonner';
import { adminCommerceService } from '../../services/commerceService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type AdminReview = {
  id: string;
  customer: string;
  product: string;
  rating: number;
  comment: string;
  images: string[];
  status: 'pending' | 'approved' | 'rejected';
  reply?: string | null;
  created_at?: string;
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
};

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

export function AdminReviews() {
  const [items, setItems] = useState<AdminReview[]>([]);
  const [status, setStatus] = useState('');
  const [reply, setReply] = useState<Record<string, string>>({});
  const [previewImage, setPreviewImage] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const data = await adminCommerceService.reviews.list(status ? { status } : {});
      setItems(data);
    } catch {
      toast.error('Không thể tải danh sách đánh giá.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [status]);

  const moderate = async (id: string, nextStatus: 'approved' | 'rejected') => {
    await adminCommerceService.reviews.moderate(id, nextStatus);
    toast.success('Đã cập nhật trạng thái đánh giá.');
    await load();
  };

  const saveReply = async (review: AdminReview) => {
    const content = reply[review.id] ?? review.reply ?? '';
    if (!content.trim()) {
      toast.error('Vui lòng nhập nội dung phản hồi.');
      return;
    }
    await adminCommerceService.reviews.reply(review.id, content.trim());
    toast.success('Đã lưu phản hồi.');
    await load();
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold">Duyệt đánh giá</h2>
          <p className="text-sm text-muted-foreground">Xem nội dung, hình ảnh đánh giá và phản hồi khách hàng.</p>
        </div>
        <select value={status} onChange={(event) => setStatus(event.target.value)} className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả</option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="rejected">Từ chối</option>
        </select>
      </div>

      {loading ? (
        <div className="bg-white border rounded-xl p-10 text-center text-muted-foreground">Đang tải đánh giá...</div>
      ) : items.length === 0 ? (
        <div className="bg-white border rounded-xl p-10 text-center text-muted-foreground">Không có đánh giá phù hợp.</div>
      ) : (
        <div className="space-y-3">
          {items.map((review) => (
            <article key={review.id} className="bg-white border rounded-xl p-5 space-y-4">
              <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                  <h3 className="font-semibold">{review.product}</h3>
                  <p className="text-sm text-muted-foreground">{review.customer}</p>
                  <div className="flex items-center gap-1 mt-2">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <Star key={star} className={`w-4 h-4 ${star <= review.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                    ))}
                    <span className="text-xs text-muted-foreground ml-1">{review.rating}/5</span>
                  </div>
                </div>
                <span className={`w-fit px-2.5 py-1 rounded-full text-xs font-medium ${STATUS_STYLES[review.status] ?? 'bg-gray-100 text-gray-700'}`}>
                  {STATUS_LABELS[review.status] ?? review.status}
                </span>
              </div>

              <p className="text-sm leading-6">{review.comment}</p>

              <div>
                <div className="flex items-center gap-2 mb-2 text-sm font-medium">
                  <ImageIcon className="w-4 h-4" />
                  Ảnh đánh giá ({review.images?.length ?? 0})
                </div>
                {review.images && review.images.length > 0 ? (
                  <div className="flex flex-wrap gap-3">
                    {review.images.map((image) => (
                      <button
                        key={image}
                        onClick={() => setPreviewImage(image)}
                        className="relative group w-24 h-24 rounded-lg overflow-hidden border bg-gray-50"
                        title="Xem ảnh lớn"
                      >
                        <ImageWithFallback src={image} alt="Ảnh đánh giá" className="w-full h-full object-cover" />
                        <span className="absolute inset-0 bg-black/0 group-hover:bg-black/30 grid place-items-center transition-colors">
                          <Eye className="w-5 h-5 text-white opacity-0 group-hover:opacity-100" />
                        </span>
                      </button>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-muted-foreground">Khách hàng không gửi ảnh.</p>
                )}
              </div>

              <div className="flex flex-wrap gap-2">
                {review.status !== 'approved' && (
                  <Button onClick={() => moderate(review.id, 'approved')} className="bg-green-600 hover:bg-green-700">
                    Duyệt
                  </Button>
                )}
                {review.status !== 'rejected' && (
                  <Button variant="outline" onClick={() => moderate(review.id, 'rejected')}>
                    Từ chối
                  </Button>
                )}
                <Button
                  variant="outline"
                  onClick={async () => {
                    if (!confirm('Xóa đánh giá này?')) return;
                    await adminCommerceService.reviews.remove(review.id);
                    toast.success('Đã xóa đánh giá.');
                    await load();
                  }}
                >
                  Xóa
                </Button>
              </div>

              <div className="flex flex-col md:flex-row gap-2">
                <input
                  value={reply[review.id] ?? review.reply ?? ''}
                  onChange={(event) => setReply({ ...reply, [review.id]: event.target.value })}
                  placeholder="Phản hồi khách hàng..."
                  className="flex-1 border rounded-lg p-2 text-sm"
                />
                <Button onClick={() => saveReply(review)}>Phản hồi</Button>
                {review.reply && (
                  <Button
                    variant="outline"
                    onClick={async () => {
                      await adminCommerceService.reviews.deleteReply(review.id);
                      toast.success('Đã xóa phản hồi.');
                      await load();
                    }}
                  >
                    Xóa phản hồi
                  </Button>
                )}
              </div>
            </article>
          ))}
        </div>
      )}

      {previewImage && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/70" onClick={() => setPreviewImage(null)} />
          <div className="absolute inset-4 md:inset-10 grid place-items-center pointer-events-none">
            <button className="absolute top-0 right-0 p-2 rounded-full bg-white pointer-events-auto" onClick={() => setPreviewImage(null)}>
              <X className="w-5 h-5" />
            </button>
            <img src={previewImage} alt="Ảnh đánh giá" className="max-h-full max-w-full rounded-xl bg-white object-contain pointer-events-auto" />
          </div>
        </div>
      )}
    </div>
  );
}
