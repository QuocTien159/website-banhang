import { useEffect, useMemo, useState } from 'react';
import { Edit2, ImagePlus, Loader2, Plus, Search, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import {
  adminService,
  type AdminCategory,
  type AdminImage,
  type AdminProductPayload,
  type AdminProductSummary,
  type AdminVariant,
} from '../../services/orderService';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Button } from '../ui/button';

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

const CATEGORY_ATTRIBUTES: Record<string, string[]> = {
  'Áo': ['Thương hiệu', 'Màu sắc', 'Kích thước'],
  'Quần': ['Thương hiệu', 'Màu sắc', 'Kích thước'],
  'Giày': ['Thương hiệu', 'Màu sắc', 'Kích thước'],
  'Phụ kiện': ['Thương hiệu', 'Màu sắc', 'Khối lượng', 'Độ đàn hồi'],
};

const emptyForm = (): AdminProductPayload => ({
  name: '',
  category_id: '',
  description: '',
  base_price: 0,
  status: 'active',
  images: [],
  variants: [],
});

const cartesian = (groups: { name: string; values: string[] }[]) =>
  groups.reduce<{ name: string; value: string }[][]>(
    (rows, group) => rows.flatMap((row) => group.values.map((value) => [...row, { name: group.name, value }])),
    [[]]
  );

const errorText = (error: any) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể lưu dữ liệu.';
};

export function AdminProducts() {
  const [products, setProducts] = useState<AdminProductSummary[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [attributeOptions, setAttributeOptions] = useState<Record<string, string[]>>({});
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<AdminProductPayload>(emptyForm);
  const [selectedValues, setSelectedValues] = useState<Record<string, string[]>>({});
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const selectedCategory = categories.find((category) => category.id === form.category_id);
  const generatorAttributes = selectedCategory
    ? (CATEGORY_ATTRIBUTES[selectedCategory.name] ?? ['Thương hiệu'])
    : [];

  const loadProducts = async () => {
    setLoading(true);
    try {
      const response = await adminService.getAdminProducts({
        ...(search ? { search } : {}),
        ...(categoryFilter ? { category_id: categoryFilter } : {}),
        ...(statusFilter ? { status: statusFilter } : {}),
        page,
      });
      setProducts(response.data ?? []);
      setLastPage(response.meta?.last_page ?? 1);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    Promise.all([adminService.getCategories(), adminService.getProductOptions()]).then(([categoryData, optionData]) => {
      setCategories(categoryData);
      setAttributeOptions(Object.fromEntries(optionData.attributes.map((attribute) => [
        attribute.name,
        attribute.values.map((value) => typeof value === 'string' ? value : value.value),
      ])));
    });
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(loadProducts, 250);
    return () => window.clearTimeout(timer);
  }, [search, categoryFilter, statusFilter, page]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setSelectedValues({});
    setFieldErrors({});
    setEditorOpen(true);
  };

  const openEdit = async (id: string) => {
    setEditingId(id);
    setEditorOpen(true);
    setFieldErrors({});
    try {
      const product = await adminService.getAdminProduct(id);
      setForm({
        name: product.name,
        category_id: product.category_id,
        description: product.description ?? '',
        base_price: product.base_price,
        status: product.status,
        images: product.images,
        variants: product.variants,
      });
    } catch {
      setEditorOpen(false);
      toast.error('Không thể tải chi tiết sản phẩm.');
    }
  };

  const updateVariant = (index: number, patch: Partial<AdminVariant>) => {
    setForm((current) => ({
      ...current,
      variants: current.variants.map((variant, variantIndex) =>
        variantIndex === index ? { ...variant, ...patch } : variant
      ),
    }));
  };

  const generateVariants = () => {
    const groups = generatorAttributes
      .map((name) => ({ name, values: selectedValues[name] ?? [] }))
      .filter((group) => group.values.length > 0);
    if (groups.length === 0) {
      toast.error('Hãy chọn ít nhất một giá trị thuộc tính.');
      return;
    }

    const generated = cartesian(groups).map((attributes, index) => ({
      sku: `SKU-${Date.now().toString().slice(-6)}-${index + 1}`,
      price: form.base_price,
      stock: 0,
      low_stock_threshold: 5,
      active: true,
      attributes,
    }));
    setForm((current) => ({ ...current, variants: generated }));
  };

  const save = async () => {
    setSaving(true);
    setFieldErrors({});
    try {
      if (editingId) await adminService.updateProduct(editingId, form);
      else await adminService.createProduct(form);
      toast.success(editingId ? 'Đã cập nhật sản phẩm.' : 'Đã tạo sản phẩm.');
      setEditorOpen(false);
      await loadProducts();
    } catch (error: any) {
      const errors = error.response?.data?.errors ?? {};
      setFieldErrors(Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, (value as string[])[0]])));
      toast.error(errorText(error));
    } finally {
      setSaving(false);
    }
  };

  const priceDifference = (price: number) => {
    const difference = price - form.base_price;
    const percentage = form.base_price ? Math.round((difference / form.base_price) * 100) : 0;
    return `${difference >= 0 ? '+' : ''}${formatPrice(difference)} (${percentage >= 0 ? '+' : ''}${percentage}%)`;
  };

  const firstFieldError = (prefix: string) =>
    Object.entries(fieldErrors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1];

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-2xl font-semibold">Quản lý sản phẩm</h2>
          <p className="text-sm text-muted-foreground">Quản lý thông tin, hình ảnh, giá và ngưỡng cảnh báo. Tồn kho được cập nhật qua Nhập kho hoặc Điều chỉnh kho.</p>
        </div>
        <Button onClick={openCreate} className="bg-orange-600 hover:bg-orange-700"><Plus className="w-4 h-4 mr-2" />Thêm sản phẩm</Button>
      </div>

      <div className="bg-white border rounded-xl p-4 grid md:grid-cols-[1fr_220px_180px] gap-3">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
          <input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} placeholder="Tìm sản phẩm..." className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
        </div>
        <select value={categoryFilter} onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả danh mục</option>
          {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
        </select>
        <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả trạng thái</option>
          <option value="active">Đang bán</option>
          <option value="inactive">Ngừng bán</option>
          <option value="out_of_stock">Hết hàng</option>
        </select>
      </div>

      <div className="bg-white border rounded-xl overflow-x-auto">
        {loading ? <div className="p-12 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-4">Sản phẩm</th><th className="text-left p-4">Danh mục</th>
                <th className="text-left p-4">Khoảng giá</th><th className="text-center p-4">Tồn kho</th>
                <th className="text-center p-4">Biến thể</th><th className="text-center p-4">Trạng thái</th><th className="p-4" />
              </tr>
            </thead>
            <tbody className="divide-y">
              {products.map((product) => (
                <tr key={product.id}>
                  <td className="p-4"><div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-lg overflow-hidden bg-gray-100"><ImageWithFallback src={product.image ?? ''} alt={product.name} className="w-full h-full object-cover" /></div>
                    <div><p className="font-medium max-w-64 truncate">{product.name}</p><p className="text-xs text-muted-foreground">{product.id}</p></div>
                  </div></td>
                  <td className="p-4">{product.category}</td>
                  <td className="p-4 text-orange-600 font-medium">{formatPrice(product.min_price)}{product.max_price !== product.min_price && ` – ${formatPrice(product.max_price)}`}</td>
                  <td className="p-4 text-center">{product.stock}</td>
                  <td className="p-4 text-center">{product.variant_count}</td>
                  <td className="p-4 text-center"><span className={`px-2 py-1 rounded-full text-xs ${product.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{product.status === 'active' ? 'Đang bán' : 'Ngừng bán'}</span></td>
                  <td className="p-4"><div className="flex justify-end gap-1">
                    <button onClick={() => openEdit(product.id)} className="p-2 text-blue-600"><Edit2 className="w-4 h-4" /></button>
                    <button onClick={async () => {
                      if (!confirm(`Ngừng bán "${product.name}"?`)) return;
                      await adminService.deleteProduct(product.id); toast.success('Đã ngừng bán sản phẩm.'); await loadProducts();
                    }} className="p-2 text-red-600"><Trash2 className="w-4 h-4" /></button>
                  </div></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {lastPage > 1 && <div className="flex justify-center gap-2">{Array.from({ length: lastPage }, (_, i) => i + 1).map((number) => <button key={number} onClick={() => setPage(number)} className={`w-9 h-9 border rounded-lg ${page === number ? 'bg-orange-50 border-orange-500' : ''}`}>{number}</button>)}</div>}

      {editorOpen && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setEditorOpen(false)} />
          <div className="absolute inset-y-0 right-0 w-full max-w-6xl bg-gray-50 shadow-xl overflow-y-auto">
            <div className="sticky top-0 z-10 bg-white border-b px-6 py-4 flex items-center justify-between">
              <div><h3 className="text-xl font-semibold">{editingId ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm'}</h3><p className="text-sm text-muted-foreground">Thông tin chung, hình ảnh và biến thể</p></div>
              <button onClick={() => setEditorOpen(false)}><X /></button>
            </div>

            <div className="p-6 space-y-6">
              <section className="bg-white border rounded-xl p-5">
                <h4 className="font-semibold mb-4">1. Thông tin chung</h4>
                <div className="grid md:grid-cols-2 gap-4">
                  <label className="space-y-1"><span className="text-sm">Tên sản phẩm *</span><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" />{fieldErrors.name && <small className="text-red-600">{fieldErrors.name}</small>}</label>
                  <label className="space-y-1"><span className="text-sm">Danh mục *</span><select value={form.category_id} onChange={(e) => { setForm({ ...form, category_id: e.target.value }); setSelectedValues({}); }} className="w-full border rounded-lg px-3 py-2"><option value="">Chọn danh mục</option>{categories.filter((c) => c.active || c.id === form.category_id).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>{fieldErrors.category_id && <small className="text-red-600">{fieldErrors.category_id}</small>}</label>
                  <label className="space-y-1"><span className="text-sm">Giá niêm yết *</span><input type="number" min="0" value={form.base_price || ''} onChange={(e) => setForm({ ...form, base_price: Number(e.target.value) })} className="w-full border rounded-lg px-3 py-2" /></label>
                  <label className="space-y-1"><span className="text-sm">Trạng thái</span><select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as AdminProductPayload['status'] })} className="w-full border rounded-lg px-3 py-2"><option value="active">Đang bán</option><option value="inactive">Ngừng bán</option><option value="out_of_stock">Hết hàng</option></select></label>
                  <label className="space-y-1 md:col-span-2"><span className="text-sm">Mô tả</span><textarea rows={4} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></label>
                </div>
              </section>

              <section className="bg-white border rounded-xl p-5">
                <div className="flex justify-between mb-4"><div><h4 className="font-semibold">2. Hình ảnh</h4><p className="text-xs text-muted-foreground">JPG, PNG hoặc WebP; tối đa 5 MB, kích thước từ 300×300 px.</p></div>
                  <label className="cursor-pointer"><input type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={async (e) => {
                    const files = Array.from(e.target.files ?? []); if (!files.length) return;
                    setUploading(true);
                    try {
                      const uploaded = await adminService.uploadProductImages(files);
                      setForm((current) => ({ ...current, images: [...current.images, ...uploaded.map((image, index) => ({ ...image, is_primary: current.images.length === 0 && index === 0 }))] }));
                    } catch (error: any) { toast.error(errorText(error)); } finally { setUploading(false); e.target.value = ''; }
                  }} /><span className="inline-flex items-center px-3 py-2 border rounded-lg text-sm">{uploading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <ImagePlus className="w-4 h-4 mr-2" />}Tải ảnh</span></label>
                </div>
                {firstFieldError('images') && <p className="text-sm text-red-600 mb-3">{firstFieldError('images')}</p>}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                  {form.images.map((image, index) => <div key={image.id ?? image.url} className={`relative border-2 rounded-xl overflow-hidden ${image.is_primary ? 'border-orange-500' : 'border-transparent'}`}>
                    <ImageWithFallback src={image.url} alt="" className="w-full aspect-square object-cover" />
                    <div className="absolute inset-x-0 bottom-0 p-2 bg-black/60 flex gap-2">
                      <button onClick={() => setForm({ ...form, images: form.images.map((item, i) => ({ ...item, is_primary: i === index })) })} className="flex-1 text-xs text-white">{image.is_primary ? 'Ảnh đại diện' : 'Đặt đại diện'}</button>
                      <button onClick={() => {
                        const remaining = form.images.filter((_, i) => i !== index);
                        if (image.is_primary && remaining[0]) remaining[0] = { ...remaining[0], is_primary: true };
                        setForm({ ...form, images: remaining });
                      }} className="text-white"><Trash2 className="w-4 h-4" /></button>
                    </div>
                  </div>)}
                </div>
              </section>

              <section className="bg-white border rounded-xl p-5">
                <div className="flex justify-between items-start mb-4"><div><h4 className="font-semibold">3. Biến thể</h4><p className="text-xs text-muted-foreground">Mỗi SKU có giá, trạng thái và ngưỡng cảnh báo riêng. Không sửa tồn kho trực tiếp tại đây.</p></div>
                  <Button variant="outline" onClick={() => setForm({ ...form, variants: [...form.variants, { sku: '', price: form.base_price, stock: 0, low_stock_threshold: 5, active: true, attributes: [] }] })}><Plus className="w-4 h-4 mr-2" />Thêm dòng</Button>
                </div>
                {form.category_id && <div className="bg-gray-50 border rounded-xl p-4 mb-5">
                  <p className="text-sm font-medium mb-3">Tạo nhanh tổ hợp</p>
                  <div className="grid md:grid-cols-3 gap-3">
                    {generatorAttributes.map((name) => <div key={name}><p className="text-xs mb-2">{name}</p><div className="flex flex-wrap gap-1">{(attributeOptions[name] ?? []).map((value) => {
                      const selected = selectedValues[name]?.includes(value);
                      return <button key={value} onClick={() => setSelectedValues((current) => ({ ...current, [name]: selected ? (current[name] ?? []).filter((item) => item !== value) : [...(current[name] ?? []), value] }))} className={`px-2 py-1 border rounded text-xs ${selected ? 'bg-orange-50 border-orange-500 text-orange-700' : 'bg-white'}`}>{value}</button>;
                    })}</div></div>)}
                  </div>
                  <Button type="button" onClick={generateVariants} className="mt-4 bg-orange-600 hover:bg-orange-700">Tạo các tổ hợp</Button>
                </div>}
                {firstFieldError('variants') && <p className="text-sm text-red-600 mb-3">{firstFieldError('variants')}</p>}
                <div className="space-y-3">
                  {form.variants.map((variant, index) => <div key={variant.id ?? index} className="border rounded-xl p-4">
                    <div className="grid lg:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto] gap-3 items-end">
                      <label className="text-xs">SKU<input value={variant.sku} onChange={(e) => updateVariant(index, { sku: e.target.value })} className="mt-1 w-full border rounded-lg px-2 py-2 text-sm" /></label>
                      <label className="text-xs">Giá bán<input type="number" value={variant.price || ''} onChange={(e) => updateVariant(index, { price: Number(e.target.value) })} className="mt-1 w-full border rounded-lg px-2 py-2 text-sm" /><span className="text-[11px] text-muted-foreground">{priceDifference(variant.price)}</span></label>
                      <label className="text-xs">Tồn kho hiện tại<input type="number" min="0" value={variant.stock} disabled className="mt-1 w-full border rounded-lg px-2 py-2 text-sm bg-gray-100 text-gray-500" /><span className="text-[11px] text-muted-foreground">Cập nhật ở module kho</span></label>
                      <label className="text-xs">Ngưỡng cảnh báo<input type="number" min="0" value={variant.low_stock_threshold ?? 5} onChange={(e) => updateVariant(index, { low_stock_threshold: Number(e.target.value) })} className="mt-1 w-full border rounded-lg px-2 py-2 text-sm" /></label>
                      <label className="text-xs">Trạng thái<select value={variant.active ? '1' : '0'} onChange={(e) => updateVariant(index, { active: e.target.value === '1' })} className="mt-1 w-full border rounded-lg px-2 py-2 text-sm"><option value="1">Đang bán</option><option value="0">Ngừng bán</option></select></label>
                      <button onClick={() => setForm({ ...form, variants: form.variants.filter((_, i) => i !== index) })} className="p-2 text-red-600"><Trash2 className="w-4 h-4" /></button>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-3">
                      {variant.attributes.map((attribute, attributeIndex) => <div key={`${attribute.name}-${attributeIndex}`} className="flex items-center gap-1 bg-gray-50 border rounded-lg p-1">
                        <select value={attribute.name} onChange={(e) => {
                          const attributes = variant.attributes.map((item, i) => i === attributeIndex ? { name: e.target.value, value: '' } : item); updateVariant(index, { attributes });
                        }} className="bg-transparent text-xs p-1"><option value="">Thuộc tính</option>{Object.keys(attributeOptions).map((name) => <option key={name}>{name}</option>)}</select>
                        <input list={`values-${index}-${attributeIndex}`} value={attribute.value} onChange={(e) => {
                          const attributes = variant.attributes.map((item, i) => i === attributeIndex ? { ...item, value: e.target.value } : item); updateVariant(index, { attributes });
                        }} placeholder="Giá trị" className="bg-transparent text-xs p-1 w-28" />
                        <datalist id={`values-${index}-${attributeIndex}`}>{(attributeOptions[attribute.name] ?? []).map((value) => <option key={value} value={value} />)}</datalist>
                        <button onClick={() => updateVariant(index, { attributes: variant.attributes.filter((_, i) => i !== attributeIndex) })}><X className="w-3 h-3" /></button>
                      </div>)}
                      <button onClick={() => updateVariant(index, { attributes: [...variant.attributes, { name: '', value: '' }] })} className="text-xs text-blue-600">+ Thuộc tính</button>
                    </div>
                  </div>)}
                </div>
              </section>
            </div>
            <div className="sticky bottom-0 bg-white border-t p-4 flex justify-end gap-3">
              <Button variant="outline" onClick={() => setEditorOpen(false)}>Hủy</Button>
              <Button onClick={save} disabled={saving} className="bg-orange-600 hover:bg-orange-700">{saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}{editingId ? 'Lưu thay đổi' : 'Tạo sản phẩm'}</Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
