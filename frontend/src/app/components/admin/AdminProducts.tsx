import { useEffect, useState } from 'react';
import { Edit2, EyeOff, ListTree, Plus, Search } from 'lucide-react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { adminService, type AdminCategory, type AdminProductSummary } from '../../services/orderService';
import { useAuth } from '../../store/AppContext';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Button } from '../ui/button';

const formatPrice = (price: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

export function AdminProducts() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const isAdmin = user?.role === 'admin';
  const [products, setProducts] = useState<AdminProductSummary[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const response = await adminService.getAdminProducts({
        ...(search ? { search } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(status ? { status } : {}),
        page,
      });
      setProducts(response.data ?? []);
      setLastPage(response.meta?.last_page ?? 1);
    } catch {
      toast.error('Không thể tải danh sách sản phẩm.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!isAdmin) return;
    adminService.getCategories().then(setCategories).catch(() => setCategories([]));
  }, [isAdmin]);

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search, categoryId, status, page]);

  const hide = async (product: AdminProductSummary) => {
    if (!confirm('Ẩn sản phẩm "' + product.name + '"?')) return;
    try {
      await adminService.hideProduct(product.id);
      toast.success('Đã ẩn sản phẩm.');
      await load();
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể ẩn sản phẩm.');
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-2xl font-semibold">Sản phẩm</h2>
          <p className="text-sm text-muted-foreground">Quản lý thông tin chung, thuộc tính và ảnh. SKU cùng tồn kho được quản lý riêng theo biến thể.</p>
        </div>
        {isAdmin && <Button onClick={() => navigate('/admin/products/new')} className="bg-orange-600 hover:bg-orange-700"><Plus className="mr-2 size-4" />Thêm sản phẩm</Button>}
      </div>

      <div className={'grid gap-3 rounded-lg border bg-white p-4 ' + (isAdmin ? 'md:grid-cols-[1fr_220px_180px]' : 'md:grid-cols-[1fr_180px]')}>
        <div className="relative"><Search className="absolute left-3 top-2.5 size-4 text-muted-foreground" /><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Tìm theo tên hoặc mã sản phẩm..." className="w-full rounded-md border py-2 pl-9 pr-3 text-sm" /></div>
        {isAdmin && <select value={categoryId} onChange={(event) => { setCategoryId(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Tất cả danh mục</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select>}
        <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Tất cả trạng thái</option><option value="active">Đang bán</option><option value="inactive">Đã ẩn</option></select>
      </div>

      <div className="overflow-x-auto rounded-lg border bg-white">
        <table className="w-full min-w-[860px] text-sm">
          <thead className="border-b bg-slate-50 text-muted-foreground">
            <tr>
              <th className="px-4 py-3 text-left font-medium">Sản phẩm</th>
              <th className="px-4 py-3 text-left font-medium">Danh mục</th>
              <th className="px-4 py-3 text-right font-medium">Giá hiển thị</th>
              <th className="px-4 py-3 text-center font-medium">Biến thể</th>
              <th className="px-4 py-3 text-center font-medium">Trạng thái</th>
              <th className="px-4 py-3 text-left font-medium">Cập nhật</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {loading ? <tr><td colSpan={7} className="p-10 text-center text-muted-foreground">Đang tải...</td></tr> : products.length === 0 ? <tr><td colSpan={7} className="p-10 text-center text-muted-foreground">Không có sản phẩm phù hợp.</td></tr> : products.map((product) => (
              <tr key={product.id} className="border-b last:border-0 hover:bg-slate-50">
                <td className="px-4 py-3"><div className="flex items-center gap-3"><ImageWithFallback src={product.image_urls?.thumbnail_url ?? product.image ?? ''} alt={product.name} className="size-11 rounded-md bg-slate-100 object-cover" /><div className="min-w-0"><p className="max-w-72 truncate font-medium">{product.name}</p><p className="text-xs text-muted-foreground">{product.id}</p></div></div></td>
                <td className="px-4 py-3">{product.category}</td>
                <td className="px-4 py-3 text-right font-medium text-orange-700">{formatPrice(product.min_price)}{product.max_price !== product.min_price ? ' - ' + formatPrice(product.max_price) : ''}</td>
                <td className="px-4 py-3 text-center"><p>{product.variant_count}</p>{(product.low_stock_variant_count ?? 0) > 0 && <button type="button" onClick={() => navigate('/admin/variants?product_id=' + product.id + '&stock_status=low_stock')} className="text-xs text-orange-700 underline">{product.low_stock_variant_count} cần xử lý</button>}</td>
                <td className="px-4 py-3 text-center"><span className={'rounded-full px-2 py-1 text-xs ' + (product.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700')}>{product.status === 'active' ? 'Đang bán' : 'Đã ẩn'}</span></td>
                <td className="px-4 py-3 text-xs text-muted-foreground">{product.updated_at ? new Date(product.updated_at).toLocaleString('vi-VN') : '—'}</td>
                <td className="px-4 py-3"><div className="flex justify-end gap-1">{isAdmin && <><button type="button" title="Chỉnh sửa sản phẩm" onClick={() => navigate('/admin/products/' + product.id)} className="rounded p-2 text-blue-600 hover:bg-blue-50"><Edit2 className="size-4" /></button><button type="button" title="Quản lý biến thể" onClick={() => navigate('/admin/variants?product_id=' + product.id)} className="rounded p-2 text-violet-700 hover:bg-violet-50"><ListTree className="size-4" /></button></>}{product.status === 'active' && <button type="button" title="Ẩn sản phẩm" onClick={() => hide(product)} className="rounded p-2 text-amber-700 hover:bg-amber-50"><EyeOff className="size-4" /></button>}</div></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {lastPage > 1 && <div className="flex justify-end gap-2"><Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage((current) => current - 1)}>Trước</Button><span className="px-2 py-2 text-sm text-muted-foreground">Trang {page}/{lastPage}</span><Button variant="outline" size="sm" disabled={page === lastPage} onClick={() => setPage((current) => current + 1)}>Sau</Button></div>}
    </div>
  );
}
