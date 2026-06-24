import { useEffect, useState } from 'react';
import { Heart, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { commerceService, type WishlistItem } from '../../services/commerceService';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Button } from '../ui/button';

const money = (v: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);
export function WishlistPage() {
  const [items, setItems] = useState<WishlistItem[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const load = () => commerceService.wishlist().then(setItems).finally(() => setLoading(false));
  useEffect(() => { load(); }, []);
  if (loading) return <div className="max-w-7xl mx-auto p-10 text-center">Đang tải danh sách yêu thích...</div>;
  return <div className="max-w-7xl mx-auto px-4 py-8">
    <h2 className="text-2xl font-semibold mb-6 flex items-center gap-2"><Heart className="text-red-500" /> Sản phẩm yêu thích</h2>
    {!items.length ? <div className="border rounded-xl p-12 text-center"><p>Chưa có sản phẩm yêu thích.</p><Button className="mt-4" onClick={() => navigate('/products')}>Khám phá sản phẩm</Button></div> :
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">{items.map((item) => <article key={item.id} className="border rounded-xl overflow-hidden bg-white">
        <ImageWithFallback src={item.image ?? ''} alt={item.name} className="w-full aspect-square object-cover" />
        <div className="p-4"><p className="text-xs text-muted-foreground">{item.category}</p><h3 className="font-medium line-clamp-2">{item.name}</h3>
          <p className="text-orange-600 font-semibold my-2">{money(item.price)}</p>
          <p className={`text-xs ${item.available ? 'text-green-600' : 'text-red-600'}`}>{item.available ? `${item.stock} sản phẩm · ${item.variant_count} biến thể` : 'Ngừng bán hoặc hết hàng'}</p>
          <div className="flex gap-2 mt-3"><Button className="flex-1" disabled={!item.available} onClick={() => navigate(`/products/${item.id}`)}>Xem sản phẩm</Button>
            <Button variant="outline" onClick={async () => { await commerceService.toggleWishlist(item.id); setItems(items.filter((x) => x.id !== item.id)); toast.success('Đã xóa khỏi yêu thích.'); }}><Trash2 className="w-4 h-4" /></Button></div>
        </div></article>)}</div>}
  </div>;
}
