import { useEffect, useMemo, useState } from 'react';
import { Edit3, ImagePlus, Loader2, MessageSquare, Star } from 'lucide-react';
import { toast } from 'sonner';
import { commerceService, type MyReview, type ReviewCandidate } from '../../services/commerceService';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Button } from '../ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';

type ReviewDraft = {
  rating: number;
  comment: string;
  images: string[];
};

type EditingTarget =
  | { mode: 'create'; candidate: ReviewCandidate }
  | { mode: 'edit'; review: MyReview };

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Bị từ chối',
};

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
};

const initialDraft: ReviewDraft = { rating: 5, comment: '', images: [] };

export function ReviewsPage() {
  const [eligible, setEligible] = useState<ReviewCandidate[]>([]);
  const [reviews, setReviews] = useState<MyReview[]>([]);
  const [target, setTarget] = useState<EditingTarget | null>(null);
  const [draft, setDraft] = useState<ReviewDraft>(initialDraft);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const [eligibleData, reviewData] = await Promise.all([
        commerceService.eligibleReviews(),
        commerceService.myReviews(),
      ]);
      setEligible(eligibleData);
      setReviews(reviewData);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const formTitle = useMemo(() => {
    if (!target) return '';
    return target.mode === 'create'
      ? `Đánh giá ${target.candidate.product_name}`
      : `Sửa đánh giá ${target.review.product?.name ?? ''}`;
  }, [target]);

  const openCreate = (candidate: ReviewCandidate) => {
    setTarget({ mode: 'create', candidate });
    setDraft(initialDraft);
    setError('');
  };

  const openEdit = (review: MyReview) => {
    setTarget({ mode: 'edit', review });
    setDraft({
      rating: review.rating,
      comment: review.comment,
      images: review.images ?? [],
    });
    setError('');
  };

  const uploadImages = async (files: File[]) => {
    if (!files.length) return;
    setUploading(true);
    try {
      const uploaded = await commerceService.uploadReviewImages(files);
      setDraft((current) => ({ ...current, images: [...current.images, ...uploaded].slice(0, 5) }));
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Không thể tải ảnh đánh giá.');
    } finally {
      setUploading(false);
    }
  };

  const submit = async () => {
    if (!target) return;
    if (draft.comment.trim().length < 10) {
      setError('Nội dung đánh giá cần tối thiểu 10 ký tự.');
      return;
    }

    setSaving(true);
    setError('');
    try {
      if (target.mode === 'create') {
        await commerceService.createReview({
          order_id: target.candidate.order_id,
          product_id: target.candidate.product_id,
          rating: draft.rating,
          comment: draft.comment.trim(),
          images: draft.images,
        });
        toast.success('Đánh giá đang chờ duyệt.');
      } else {
        await commerceService.updateReview(target.review.id, {
          rating: draft.rating,
          comment: draft.comment.trim(),
          images: draft.images,
        });
        toast.success('Đã gửi lại đánh giá, vui lòng chờ duyệt.');
      }

      setTarget(null);
      setDraft(initialDraft);
      await load();
    } catch (err: any) {
      const message = err.response?.data?.message ?? 'Không thể lưu đánh giá.';
      setError(message);
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-8 space-y-6">
      <div>
        <p className="text-sm font-medium text-orange-600">TienProSport</p>
        <h2 className="text-2xl font-semibold">Đánh giá của tôi</h2>
        <p className="text-sm text-muted-foreground mt-1">
          Mỗi sản phẩm chỉ có một đánh giá. Bạn có thể sửa đánh giá cũ để gửi duyệt lại.
        </p>
      </div>

      <Tabs defaultValue="pending">
        <TabsList>
          <TabsTrigger value="pending">Chờ đánh giá ({eligible.length})</TabsTrigger>
          <TabsTrigger value="history">Lịch sử đánh giá ({reviews.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="pending" className="mt-5">
          {loading ? (
            <div className="border rounded-xl p-8 text-center text-muted-foreground">Đang tải...</div>
          ) : !eligible.length ? (
            <div className="border rounded-xl p-8 text-center text-muted-foreground">Không có sản phẩm đang chờ đánh giá.</div>
          ) : (
            <div className="grid md:grid-cols-2 gap-3">
              {eligible.map((item) => (
                <button
                  key={item.product_id}
                  onClick={() => openCreate(item)}
                  className="text-left border rounded-xl p-4 hover:border-orange-400 transition-colors bg-white"
                >
                  <div className="flex gap-3">
                    <div className="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                      <ImageWithFallback src={item.image ?? ''} alt={item.product_name} className="w-full h-full object-cover" />
                    </div>
                    <div>
                      <p className="font-medium">{item.product_name}</p>
                      <p className="text-xs text-muted-foreground mt-1">Đơn #{item.order_id}</p>
                      <p className="text-sm text-orange-600 mt-2">Viết đánh giá</p>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          )}
        </TabsContent>

        <TabsContent value="history" className="mt-5">
          {loading ? (
            <div className="border rounded-xl p-8 text-center text-muted-foreground">Đang tải...</div>
          ) : !reviews.length ? (
            <div className="border rounded-xl p-8 text-center text-muted-foreground">Bạn chưa gửi đánh giá nào.</div>
          ) : (
            <div className="space-y-3">
              {reviews.map((review) => (
                <article key={review.id} className="border rounded-xl p-4 bg-white">
                  <div className="flex flex-col md:flex-row gap-4">
                    <div className="w-20 h-20 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                      <ImageWithFallback src={review.product?.image ?? ''} alt={review.product?.name ?? ''} className="w-full h-full object-cover" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <h3 className="font-semibold">{review.product?.name}</h3>
                          <div className="flex gap-1 mt-1">
                            {[1, 2, 3, 4, 5].map((star) => (
                              <Star key={star} className={`w-4 h-4 ${star <= review.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                            ))}
                          </div>
                        </div>
                        <span className={`text-xs px-2 py-1 rounded-full ${STATUS_STYLES[review.status] ?? 'bg-gray-100 text-gray-700'}`}>
                          {STATUS_LABELS[review.status] ?? review.status}
                        </span>
                      </div>
                      <p className="text-sm mt-3 leading-6">{review.comment}</p>
                      {review.images?.length > 0 && (
                        <div className="flex flex-wrap gap-2 mt-3">
                          {review.images.map((image) => (
                            <a key={image} href={image} target="_blank" rel="noreferrer" className="w-16 h-16 rounded-lg overflow-hidden border bg-gray-50">
                              <ImageWithFallback src={image} alt="Ảnh đánh giá" className="w-full h-full object-cover" />
                            </a>
                          ))}
                        </div>
                      )}
                      <div className="flex flex-wrap items-center gap-3 mt-3 text-xs text-muted-foreground">
                        {review.date && <span>Ngày gửi: {review.date}</span>}
                        {review.updated_at && <span>Đã cập nhật</span>}
                      </div>
                      {review.admin_reply && (
                        <div className="mt-3 bg-orange-50 border border-orange-100 p-3 rounded-lg text-sm">
                          <p className="font-semibold text-orange-700 flex items-center gap-2">
                            <MessageSquare className="w-4 h-4" />
                            Phản hồi từ Admin
                          </p>
                          <p className="mt-1">{review.admin_reply}</p>
                        </div>
                      )}
                      <Button variant="outline" size="sm" onClick={() => openEdit(review)} className="mt-4">
                        <Edit3 className="w-4 h-4 mr-2" />
                        {review.status === 'rejected' ? 'Đánh giá lại' : 'Sửa đánh giá'}
                      </Button>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
        </TabsContent>
      </Tabs>

      {target && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => !saving && setTarget(null)} />
          <div className="absolute inset-x-4 top-8 md:top-16 mx-auto max-w-2xl bg-white rounded-xl shadow-xl border p-5 space-y-4">
            <div>
              <h3 className="font-semibold text-lg">{formTitle}</h3>
              <p className="text-sm text-muted-foreground">Đánh giá sau khi sửa sẽ quay lại trạng thái chờ duyệt.</p>
            </div>
            <div className="flex gap-1">
              {[1, 2, 3, 4, 5].map((star) => (
                <button key={star} onClick={() => setDraft((current) => ({ ...current, rating: star }))} type="button">
                  <Star className={`w-7 h-7 ${star <= draft.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                </button>
              ))}
            </div>
            <textarea
              value={draft.comment}
              onChange={(event) => setDraft((current) => ({ ...current, comment: event.target.value }))}
              rows={5}
              placeholder="Chia sẻ trải nghiệm của bạn..."
              className="w-full border rounded-lg p-3 text-sm focus:outline-none focus:border-orange-400"
            />
            {error && <p className="text-sm text-red-600">{error}</p>}
            <div className="space-y-3">
              <label className="inline-flex items-center px-3 py-2 border rounded-lg text-sm cursor-pointer hover:bg-gray-50">
                {uploading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <ImagePlus className="w-4 h-4 mr-2" />}
                Thêm ảnh
                <input type="file" multiple accept="image/*" className="hidden" onChange={(event) => uploadImages(Array.from(event.target.files ?? []))} />
              </label>
              {draft.images.length > 0 && (
                <div className="flex flex-wrap gap-2">
                  {draft.images.map((image) => (
                    <button
                      key={image}
                      type="button"
                      onClick={() => setDraft((current) => ({ ...current, images: current.images.filter((item) => item !== image) }))}
                      className="w-16 h-16 rounded-lg overflow-hidden border bg-gray-50"
                      title="Bấm để bỏ ảnh"
                    >
                      <ImageWithFallback src={image} alt="Ảnh đánh giá" className="w-full h-full object-cover" />
                    </button>
                  ))}
                </div>
              )}
            </div>
            <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2">
              <Button type="button" variant="outline" disabled={saving} onClick={() => setTarget(null)}>Hủy</Button>
              <Button type="button" disabled={saving || uploading} onClick={submit} className="bg-orange-600 hover:bg-orange-700">
                {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
                {target.mode === 'create' ? 'Gửi đánh giá' : 'Gửi lại đánh giá'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
