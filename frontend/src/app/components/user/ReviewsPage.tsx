import { useEffect, useState } from 'react';
import { Star } from 'lucide-react';
import { toast } from 'sonner';
import { commerceService, type ReviewCandidate } from '../../services/commerceService';
import { Button } from '../ui/button';

export function ReviewsPage() {
  const [eligible, setEligible] = useState<ReviewCandidate[]>([]);
  const [reviews, setReviews] = useState<any[]>([]);
  const [selected, setSelected] = useState<ReviewCandidate | null>(null);
  const [rating, setRating] = useState(5); const [comment, setComment] = useState(''); const [images, setImages] = useState<string[]>([]);
  const load = () => Promise.all([commerceService.eligibleReviews(), commerceService.myReviews()]).then(([a, b]) => { setEligible(a); setReviews(b); });
  useEffect(() => { load(); }, []);
  return <div className="max-w-5xl mx-auto px-4 py-8 space-y-8"><h2 className="text-2xl font-semibold">Đánh giá của tôi</h2>
    <section><h3 className="font-semibold mb-3">Sản phẩm có thể đánh giá</h3>{!eligible.length ? <p className="text-sm text-muted-foreground">Không có sản phẩm đang chờ đánh giá.</p> :
      <div className="grid md:grid-cols-2 gap-3">{eligible.map((item) => <button key={`${item.order_id}-${item.product_id}`} onClick={() => setSelected(item)} className="text-left border rounded-xl p-4 hover:border-orange-400"><p className="font-medium">{item.product_name}</p><p className="text-xs text-muted-foreground">Đơn #{item.order_id}</p></button>)}</div>}</section>
    {selected && <section className="border rounded-xl p-5 space-y-4"><h3 className="font-semibold">Đánh giá {selected.product_name}</h3>
      <div className="flex gap-1">{[1,2,3,4,5].map((n) => <button key={n} onClick={() => setRating(n)}><Star className={`w-6 h-6 ${n <= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} /></button>)}</div>
      <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={4} placeholder="Chia sẻ trải nghiệm của bạn..." className="w-full border rounded-lg p-3" />
      <input type="file" multiple accept="image/*" onChange={async (e) => { const files = Array.from(e.target.files ?? []); if (files.length) setImages(await commerceService.uploadReviewImages(files)); }} />
      <Button onClick={async () => { try { await commerceService.createReview({ order_id: selected.order_id, product_id: selected.product_id, rating, comment, images }); toast.success('Đánh giá đang chờ Admin duyệt.'); setSelected(null); setComment(''); setImages([]); await load(); } catch (e: any) { toast.error(e.response?.data?.message ?? 'Không thể gửi đánh giá.'); } }}>Gửi đánh giá</Button>
    </section>}
    <section><h3 className="font-semibold mb-3">Lịch sử đánh giá</h3><div className="space-y-3">{reviews.map((r) => <article key={r.id} className="border rounded-xl p-4"><div className="flex justify-between"><b>{r.product?.name}</b><span className="text-xs uppercase">{r.status}</span></div><p className="text-sm mt-2">{r.comment}</p>{r.admin_reply && <div className="mt-3 bg-orange-50 p-3 rounded-lg text-sm"><b>Phản hồi từ Admin:</b> {r.admin_reply}</div>}</article>)}</div></section>
  </div>;
}
