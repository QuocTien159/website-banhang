import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Loader2, Plus, Search, X } from 'lucide-react';
import { useNavigate, useSearchParams } from 'react-router';
import { toast } from 'sonner';
import {
  adminService,
  type AdminCategory,
  type AdminManagedVariant,
  type AdminVariantGroup,
} from '../../services/orderService';
import { useAuth } from '../../store/AppContext';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type VariantDraft = {
  product_id: string;
  sku: string;
  list_price: number;
  price: number;
  low_stock_threshold: number;
  sell_status: 'active' | 'inactive' | 'incomplete';
  attributes: { name: string; value: string }[];
};

const emptyDraft = (productId = '', attributes: { name: string; value: string }[] = []): VariantDraft => ({
  product_id: productId,
  sku: '',
  list_price: 0,
  price: 0,
  low_stock_threshold: 5,
  sell_status: 'inactive',
  attributes,
});

const statusLabel: Record<AdminManagedVariant['stock_status'], string> = {
  in_stock: 'Còn hàng',
  low_stock: 'Sắp hết',
  out_of_stock: 'Hết hàng',
  inactive: 'Tạm ngừng',
  incomplete: 'Chưa hoàn thiện',
};

const statusClass: Record<AdminManagedVariant['stock_status'], string> = {
  in_stock: 'bg-green-100 text-green-700',
  low_stock: 'bg-orange-100 text-orange-700',
  out_of_stock: 'bg-red-100 text-red-700',
  inactive: 'bg-slate-100 text-slate-700',
  incomplete: 'bg-amber-100 text-amber-800',
};

const saleLabel: Record<VariantDraft['sell_status'], string> = {
  active: 'Đang bán',
  inactive: 'Tạm ngừng',
  incomplete: 'Chưa hoàn thiện',
};

const formatMoney = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

export function AdminVariants() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const isAdmin = user?.role === 'admin';
  const [groups, setGroups] = useState<AdminVariantGroup[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [attributeOptions, setAttributeOptions] = useState<Record<string, string[]>>({});
  const [search, setSearch] = useState(searchParams.get('search') ?? '');
  const [productId, setProductId] = useState(searchParams.get('product_id') ?? '');
  const [categoryId, setCategoryId] = useState('');
  const [sellStatus, setSellStatus] = useState('');
  const [stockStatus, setStockStatus] = useState(searchParams.get('stock_status') ?? '');
  const [imageMode, setImageMode] = useState('');
  const [loading, setLoading] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editing, setEditing] = useState<AdminManagedVariant | null>(null);
  const [draft, setDraft] = useState<VariantDraft>(emptyDraft());
  const [detail, setDetail] = useState<any>(null);
  const [saving, setSaving] = useState(false);

  const queryActive = Boolean(search || productId || categoryId || sellStatus || stockStatus || imageMode);
  const attributeNames = useMemo(() => Object.keys(attributeOptions).sort(), [attributeOptions]);

  useEffect(() => {
    if (!isAdmin) {
      navigate('/admin/products', { replace: true });
      return;
    }
    Promise.all([adminService.getCategories(), adminService.getProductOptions()])
      .then(([categoryData, optionData]) => {
        setCategories(categoryData);
        setAttributeOptions(Object.fromEntries(optionData.attributes.map((attribute) => [
          attribute.name,
          attribute.values.map((value) => typeof value === 'string' ? value : value.value),
        ])));
      })
      .catch(() => toast.error('Không thể tải dữ liệu lọc biến thể.'));
  }, [isAdmin, navigate]);

  const load = async () => {
    if (!queryActive) {
      setGroups([]);
      setLoaded(false);
      return;
    }
    setLoading(true);
    try {
      const response = await adminService.getVariants({
        ...(search ? { search } : {}),
        ...(productId ? { product_id: productId } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(sellStatus ? { sell_status: sellStatus } : {}),
        ...(stockStatus ? { stock_status: stockStatus } : {}),
        ...(imageMode ? { image_mode: imageMode } : {}),
        page,
      });
      setGroups(response.data ?? []);
      setLastPage(response.meta?.last_page ?? 1);
      setLoaded(true);
      if (productId && response.data?.length === 1) setExpanded({ [response.data[0].id]: true });
    } catch {
      toast.error('Không thể tải biến thể.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search, productId, categoryId, sellStatus, stockStatus, imageMode, page]);

  const clearFilters = () => {
    setSearch('');
    setProductId('');
    setCategoryId('');
    setSellStatus('');
    setStockStatus('');
    setImageMode('');
    setPage(1);
  };

  const openCreate = (group: AdminVariantGroup) => {
    const example = group.variants[0];
    setEditing(null);
    setDetail(null);
    setDraft(emptyDraft(group.id, example?.attributes ?? []));
    setEditorOpen(true);
  };

  const openEdit = async (variant: AdminManagedVariant) => {
    setEditing(variant);
    setDraft({
      product_id: variant.product_id,
      sku: variant.sku,
      list_price: variant.list_price,
      price: variant.price,
      low_stock_threshold: variant.low_stock_threshold,
      sell_status: variant.sell_status,
      attributes: variant.attributes,
    });
    setEditorOpen(true);
    try {
      const response = await adminService.getVariant(variant.id);
      setDetail(response);
    } catch {
      setDetail(null);
      toast.error('Không thể tải lịch sử biến thể.');
    }
  };

  const updateAttribute = (index: number, patch: Partial<VariantDraft['attributes'][number]>) => {
    setDraft((current) => ({
      ...current,
      attributes: current.attributes.map((attribute, attributeIndex) => attributeIndex === index ? { ...attribute, ...patch } : attribute),
    }));
  };

  const save = async () => {
    if (!draft.sku.trim()) {
      toast.error('Nhập SKU cho biến thể.');
      return;
    }
    setSaving(true);
    try {
      if (editing) {
        await adminService.updateVariant(editing.id, {
          sku: draft.sku,
          list_price: draft.list_price,
          price: draft.price,
          low_stock_threshold: draft.low_stock_threshold,
          sell_status: draft.sell_status,
          attributes: draft.attributes,
        });
        toast.success('Đã cập nhật biến thể.');
      } else {
        await adminService.createVariant(draft as any);
        toast.success('Đã tạo biến thể với tồn kho ban đầu bằng 0.');
      }
      setEditorOpen(false);
      await load();
    } catch (error: any) {
      const errors = error.response?.data?.errors;
      toast.error(errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể lưu biến thể.');
    } finally {
      setSaving(false);
    }
  };

  if (user?.role !== 'admin') return null;

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Biến thể</h2>
        <p className="text-sm text-muted-foreground">Tìm SKU để quản lý giá, trạng thái bán, ngưỡng cảnh báo và lịch sử nghiệp vụ. Tồn kho chỉ cập nhật tại quản lý kho.</p>
      </div>

      <div className="grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-2 lg:grid-cols-4">
        <div className="relative lg:col-span-2"><Search className="absolute left-3 top-2.5 size-4 text-muted-foreground" /><input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Tên sản phẩm, mã sản phẩm, SKU hoặc thuộc tính..." className="w-full rounded-md border py-2 pl-9 pr-3 text-sm" /></div>
        <select value={categoryId} onChange={(event) => { setCategoryId(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Tất cả danh mục</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select>
        <select value={stockStatus} onChange={(event) => { setStockStatus(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Tất cả tồn kho</option><option value="in_stock">Còn hàng</option><option value="low_stock">Sắp hết</option><option value="out_of_stock">Hết hàng</option></select>
        <select value={sellStatus} onChange={(event) => { setSellStatus(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Tất cả trạng thái bán</option><option value="active">Đang bán</option><option value="inactive">Tạm ngừng</option><option value="incomplete">Chưa hoàn thiện</option></select>
        <select value={imageMode} onChange={(event) => { setImageMode(event.target.value); setPage(1); }} className="rounded-md border px-3 py-2 text-sm"><option value="">Ảnh chung hoặc riêng</option><option value="own">Có ảnh riêng</option><option value="shared">Dùng ảnh chung</option></select>
        <div className="flex items-center"><Button variant="outline" size="sm" onClick={clearFilters} disabled={!queryActive}>Xóa bộ lọc</Button></div>
      </div>

      {!queryActive && (
        <div className="border border-dashed bg-white px-6 py-14 text-center">
          <Search className="mx-auto mb-3 size-8 text-muted-foreground" />
          <p className="font-medium">Tìm theo tên sản phẩm, mã sản phẩm hoặc SKU để quản lý biến thể</p>
          <p className="mt-1 text-sm text-muted-foreground">Hoặc chọn bộ lọc tồn kho để xem một danh sách xử lý ngắn.</p>
        </div>
      )}

      {queryActive && (
        <div className="space-y-3">
          {loading ? <div className="rounded-lg border bg-white p-10 text-center text-muted-foreground">Đang tải biến thể...</div> : groups.map((group) => {
            const open = expanded[group.id];
            return (
              <section key={group.id} className="overflow-hidden rounded-lg border bg-white">
                <div className="flex flex-wrap items-center gap-3 p-4">
                  <button type="button" onClick={() => setExpanded((current) => ({ ...current, [group.id]: !open }))} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                    {open ? <ChevronDown className="size-4 shrink-0" /> : <ChevronRight className="size-4 shrink-0" />}
                    <ImageWithFallback src={group.image ?? ''} alt={group.name} className="size-12 rounded-md bg-slate-100 object-cover" />
                    <span className="min-w-0"><span className="block truncate font-medium">{group.name}</span><span className="text-xs text-muted-foreground">{group.id} · {group.variant_count} biến thể · Tổng tồn {group.stock_total}</span></span>
                  </button>
                  {group.alert_count > 0 && <span className="rounded-full bg-orange-100 px-2 py-1 text-xs text-orange-800">{group.alert_count} cần xử lý</span>}
                  <Button size="sm" variant="outline" onClick={() => openCreate(group)}><Plus className="mr-1 size-4" />Thêm biến thể</Button>
                </div>
                {open && <div className="overflow-x-auto border-t">
                  <table className="w-full min-w-[900px] text-sm">
                    <thead className="bg-slate-50 text-muted-foreground"><tr><th className="px-4 py-3 text-left">SKU</th><th className="px-4 py-3 text-left">Thuộc tính</th><th className="px-4 py-3 text-right">Giá bán</th><th className="px-4 py-3 text-center">Tồn kho</th><th className="px-4 py-3 text-center">Ngưỡng</th><th className="px-4 py-3 text-center">Trạng thái</th><th className="px-4 py-3" /></tr></thead>
                    <tbody>{group.variants.map((variant) => <tr key={variant.id} className="border-t"><td className="px-4 py-3 font-medium">{variant.sku}<p className="text-xs font-normal text-muted-foreground">{variant.image_mode === 'own' ? 'Ảnh riêng' : 'Ảnh chung'}</p></td><td className="px-4 py-3">{variant.attributes.map((attribute) => attribute.value).join(' / ')}</td><td className="px-4 py-3 text-right">{formatMoney(variant.price)}</td><td className="px-4 py-3 text-center">{variant.stock}</td><td className="px-4 py-3 text-center">{variant.low_stock_threshold}</td><td className="px-4 py-3 text-center"><span className={'rounded-full px-2 py-1 text-xs ' + statusClass[variant.stock_status]}>{statusLabel[variant.stock_status]}</span><p className="mt-1 text-xs text-muted-foreground">{saleLabel[variant.sell_status]}</p></td><td className="px-4 py-3 text-right"><Button size="sm" variant="outline" onClick={() => openEdit(variant)}>Chỉnh sửa</Button></td></tr>)}</tbody>
                  </table>
                </div>}
              </section>
            );
          })}
          {!loading && groups.length === 0 && <div className="rounded-lg border bg-white p-10 text-center text-muted-foreground">Không tìm thấy biến thể phù hợp.</div>}
        </div>
      )}

      {loaded && lastPage > 1 && <div className="flex justify-end gap-2"><Button size="sm" variant="outline" disabled={page === 1} onClick={() => setPage((current) => current - 1)}>Trước</Button><span className="px-2 py-2 text-sm text-muted-foreground">Trang {page}/{lastPage}</span><Button size="sm" variant="outline" disabled={page === lastPage} onClick={() => setPage((current) => current + 1)}>Sau</Button></div>}

      {editorOpen && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 md:items-center"><section className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-5 shadow-xl"><div className="mb-5 flex items-start justify-between gap-3"><div><h3 className="text-lg font-semibold">{editing ? 'Chỉnh sửa biến thể' : 'Thêm biến thể'}</h3><p className="text-sm text-muted-foreground">Tồn kho ban đầu luôn bằng 0. Nhập kho hoặc điều chỉnh kho để thay đổi tồn.</p></div><button type="button" onClick={() => setEditorOpen(false)} title="Đóng"><X className="size-5" /></button></div><div className="grid gap-4 md:grid-cols-2"><label className="text-sm">SKU *<input value={draft.sku} onChange={(event) => setDraft({ ...draft, sku: event.target.value })} className="mt-1 w-full rounded-md border px-3 py-2" /></label><label className="text-sm">Trạng thái bán<select value={draft.sell_status} onChange={(event) => setDraft({ ...draft, sell_status: event.target.value as VariantDraft['sell_status'] })} className="mt-1 w-full rounded-md border px-3 py-2">{Object.entries(saleLabel).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label className="text-sm">Giá niêm yết<input type="number" min="0" value={draft.list_price || ''} onChange={(event) => setDraft({ ...draft, list_price: Number(event.target.value) })} className="mt-1 w-full rounded-md border px-3 py-2" /></label><label className="text-sm">Giá bán *<input type="number" min="0" value={draft.price || ''} onChange={(event) => setDraft({ ...draft, price: Number(event.target.value) })} className="mt-1 w-full rounded-md border px-3 py-2" /></label><label className="text-sm">Ngưỡng cảnh báo<input type="number" min="0" value={draft.low_stock_threshold} onChange={(event) => setDraft({ ...draft, low_stock_threshold: Number(event.target.value) })} className="mt-1 w-full rounded-md border px-3 py-2" /></label></div><div className="mt-5"><p className="mb-2 text-sm font-medium">Tổ hợp thuộc tính</p><div className="grid gap-3 md:grid-cols-2">{draft.attributes.map((attribute, index) => <label key={attribute.name} className="text-sm">{attribute.name}<select value={attribute.value} onChange={(event) => updateAttribute(index, { value: event.target.value })} className="mt-1 w-full rounded-md border px-3 py-2">{(attributeOptions[attribute.name] ?? []).map((value) => <option key={value}>{value}</option>)}</select></label>)}</div></div>{detail && <div className="mt-6 border-t pt-4"><h4 className="font-medium">Lịch sử gần đây</h4><div className="mt-2 space-y-2 text-sm">{detail.history?.movements?.length ? detail.history.movements.map((movement: any) => <p key={movement.id}>{movement.type} · {movement.stock_before} → {movement.stock_after} · {movement.actor ?? 'Hệ thống'}</p>) : <p className="text-muted-foreground">Chưa có biến động kho.</p>}</div></div>}<div className="mt-6 flex justify-end gap-2"><Button variant="outline" onClick={() => setEditorOpen(false)}>Hủy</Button><Button onClick={save} disabled={saving} className="bg-orange-600 hover:bg-orange-700">{saving && <Loader2 className="mr-2 size-4 animate-spin" />}Lưu biến thể</Button></div></section></div>}
    </div>
  );
}
