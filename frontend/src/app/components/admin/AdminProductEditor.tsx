import { useEffect, useMemo, useState } from 'react';
import { ArrowLeft, ImagePlus, Loader2, Plus } from 'lucide-react';
import { useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import {
  adminService,
  type AdminCategory,
  type AdminImage,
  type AdminProductPayload,
} from '../../services/orderService';
import { useAuth } from '../../store/AppContext';
import { validateImageFiles } from '../../utils/imageUpload';
import { ImageCropDialog, type ImageCrop } from '../ui/ImageCropDialog';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Button } from '../ui/button';

type AttributePair = { name: string; value: string };
type VariantAxis = { name: string; values: string[] };

const emptyForm = (): AdminProductPayload => ({
  name: '',
  category_id: '',
  description: '',
  base_price: 0,
  status: 'active',
  images: [],
  shared_attributes: [],
  variant_axes: [],
});

const isVisualAttribute = (name: string) => /màu|color|kiểu|style|họa tiết|pattern/i.test(name);

const cartesian = (axes: VariantAxis[]) => axes.reduce<AttributePair[][]>(
  (rows, axis) => rows.flatMap((row) => axis.values.map((value) => [...row, { name: axis.name, value }])),
  [[]],
);

const errorText = (error: any) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể lưu dữ liệu.';
};

export function AdminProductEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const editing = Boolean(id);
  const [form, setForm] = useState<AdminProductPayload>(emptyForm);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [attributeOptions, setAttributeOptions] = useState<Record<string, string[]>>({});
  const [shared, setShared] = useState<AttributePair[]>([]);
  const [axes, setAxes] = useState<VariantAxis[]>([]);
  const [existingVariantAttributes, setExistingVariantAttributes] = useState<AttributePair[][]>([]);
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [cropQueue, setCropQueue] = useState<File[]>([]);
  const [cropSource, setCropSource] = useState<string | null>(null);

  const attributeNames = useMemo(() => Object.keys(attributeOptions).sort(), [attributeOptions]);
  const projectedCount = useMemo(
    () => axes.reduce((total, axis) => total * Math.max(axis.values.length, 1), 1),
    [axes],
  );
  const imageTargets = useMemo(() => {
    const visualAxes = axes.filter((axis) => isVisualAttribute(axis.name) && axis.values.length > 0);

    return visualAxes.flatMap((axis) => axis.values.map((value) => {
      const fallbackAttributes = [
        ...shared,
        ...axes.map((item) => ({ name: item.name, value: item.name === axis.name ? value : item.values[0] })).filter((item) => item.value),
      ];
      const existingAttributes = existingVariantAttributes.find((attributes) => attributes.some((attribute) =>
        attribute.name === axis.name && attribute.value === value,
      ));

      return {
        key: axis.name + '|' + value,
        label: 'Theo ' + axis.name + ': ' + value,
        attributes: existingAttributes ?? fallbackAttributes,
      };
    }));
  }, [axes, existingVariantAttributes, shared]);

  useEffect(() => {
    if (user && user.role !== 'admin') {
      navigate('/admin/products', { replace: true });
    }
  }, [navigate, user]);

  useEffect(() => {
    const load = async () => {
      try {
        const [categoryData, optionData] = await Promise.all([
          adminService.getCategories(),
          adminService.getProductOptions(),
        ]);
        setCategories(categoryData);
        setAttributeOptions(Object.fromEntries(optionData.attributes.map((attribute) => [
          attribute.name,
          attribute.values.map((value) => typeof value === 'string' ? value : value.value),
        ])));

        if (id) {
          const product = await adminService.getAdminProduct(id);
          setForm({
            name: product.name,
            category_id: product.category_id,
            description: product.description ?? '',
            base_price: product.base_price,
            status: product.status === 'inactive' ? 'inactive' : 'active',
            images: product.images,
            shared_attributes: product.configuration?.shared_attributes ?? [],
            variant_axes: product.configuration?.variant_axes ?? [],
          });
          setShared(product.configuration?.shared_attributes ?? []);
          setAxes(product.configuration?.variant_axes ?? []);
          setExistingVariantAttributes(product.variants.map((variant) => variant.attributes.map((attribute) => ({
            name: attribute.name,
            value: attribute.value,
          }))));
        }
      } catch {
        toast.error('Không thể tải dữ liệu sản phẩm.');
        navigate('/admin/products', { replace: true });
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id, navigate]);

  const addAxis = () => {
    const name = attributeNames.find((attribute) => !axes.some((axis) => axis.name === attribute) && !shared.some((item) => item.name === attribute));
    if (!name) {
      toast.error('Không còn thuộc tính nào để tạo biến thể.');
      return;
    }
    setAxes((current) => [...current, { name, values: [] }]);
  };

  const toggleAxisValue = (axisName: string, value: string) => {
    const currentAxis = axes.find((axis) => axis.name === axisName);
    if (editing && currentAxis?.values.includes(value)) {
      toast.error('Không thể bỏ giá trị đang được dùng bởi biến thể hiện có. Hãy ngừng bán biến thể liên quan nếu cần.');
      return;
    }
    setAxes((current) => current.map((axis) => axis.name === axisName
      ? { ...axis, values: axis.values.includes(value) ? axis.values.filter((item) => item !== value) : [...axis.values, value] }
      : axis));
  };

  const addShared = () => {
    const name = attributeNames.find((attribute) => !axes.some((axis) => axis.name === attribute) && !shared.some((item) => item.name === attribute));
    if (!name) {
      toast.error('Không còn thuộc tính dùng chung để thêm.');
      return;
    }
    setShared((current) => [...current, { name, value: attributeOptions[name]?.[0] ?? '' }]);
  };

  const updateShared = (index: number, value: string) => {
    if (editing) {
      toast.error('Thuộc tính đã có trong biến thể không thể đổi trực tiếp. Hãy thêm biến thể mới nếu cần cấu hình khác.');
      return;
    }
    setShared((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, value } : item));
  };

  const startCropQueue = async (files: File[]) => {
    try {
      await validateImageFiles(files, 'product');
      setUploading(true);
      setCropQueue(files);
      setCropSource(URL.createObjectURL(files[0]));
    } catch (error: any) {
      toast.error(error.message);
    }
  };

  const confirmCrop = async (crop: ImageCrop) => {
    const file = cropQueue[0];
    if (!file) return;
    try {
      const uploaded = await adminService.uploadProductImages([file]);
      setForm((current) => ({
        ...current,
        images: [...current.images, ...uploaded.map((image, index) => ({
          ...image,
          crop,
          is_primary: current.images.length === 0 && index === 0,
        }))],
      }));
      if (cropSource) URL.revokeObjectURL(cropSource);
      const next = cropQueue.slice(1);
      setCropQueue(next);
      setCropSource(next[0] ? URL.createObjectURL(next[0]) : null);
      if (!next.length) setUploading(false);
    } catch (error: any) {
      toast.error(errorText(error));
      setUploading(false);
      setCropQueue([]);
      setCropSource(null);
    }
  };

  const setImageTarget = (index: number, key: string) => {
    const target = imageTargets.find((item) => item.key === key);
    setForm((current) => ({
      ...current,
      images: current.images.map((image, imageIndex) => imageIndex === index
        ? { ...image, variant_id: undefined, variant_sku: undefined, variant_attributes: target?.attributes }
        : image),
    }));
  };

  const save = async () => {
    if (uploading) {
      toast.error('Hãy chờ ảnh tải hoàn tất.');
      return;
    }
    if (!shared.length && !axes.length) {
      toast.error('Chọn ít nhất một thuộc tính dùng chung hoặc thuộc tính tạo biến thể.');
      return;
    }
    if (axes.some((axis) => axis.values.length === 0)) {
      toast.error('Mỗi thuộc tính tạo biến thể cần có ít nhất một giá trị.');
      return;
    }

    setSaving(true);
    setFieldErrors({});
    try {
      const payload: AdminProductPayload = {
        ...form,
        shared_attributes: shared,
        variant_axes: axes,
      };
      if (id) await adminService.updateProduct(id, payload);
      else await adminService.createProduct(payload);
      toast.success(id ? 'Đã lưu sản phẩm. Các biến thể mới đang ở trạng thái chưa hoàn thiện.' : 'Đã tạo sản phẩm và các biến thể nháp.');
      navigate('/admin/products');
    } catch (error: any) {
      const errors = error.response?.data?.errors ?? {};
      setFieldErrors(Object.fromEntries(Object.entries(errors).map(([key, value]) => [key, (value as string[])[0]])));
      toast.error(errorText(error));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="p-10 text-center">Đang tải sản phẩm...</div>;
  if (user?.role !== 'admin') return null;

  return (
    <div className="mx-auto max-w-5xl space-y-5 pb-10">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <button type="button" onClick={() => navigate('/admin/products')} className="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft className="size-4" />Sản phẩm
          </button>
          <h2 className="text-2xl font-semibold">{editing ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm'}</h2>
          <p className="text-sm text-muted-foreground">SKU, giá bán, ngưỡng cảnh báo và trạng thái bán được quản lý trong màn Biến thể.</p>
        </div>
        <div className="flex gap-2">
          {editing && <Button variant="outline" onClick={() => navigate('/admin/variants?product_id=' + id)}>Quản lý biến thể</Button>}
          <Button onClick={save} disabled={saving || uploading} className="bg-orange-600 hover:bg-orange-700">
            {saving && <Loader2 className="mr-2 size-4 animate-spin" />}Lưu sản phẩm
          </Button>
        </div>
      </div>

      <section className="rounded-lg border bg-white p-5">
        <h3 className="mb-4 font-semibold">Thông tin chung</h3>
        <div className="grid gap-4 md:grid-cols-2">
          <label className="space-y-1 text-sm">Tên sản phẩm *
            <input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} className="w-full rounded-md border px-3 py-2" />
            {fieldErrors.name && <span className="block text-xs text-red-600">{fieldErrors.name}</span>}
          </label>
          <label className="space-y-1 text-sm">Danh mục *
            <select value={form.category_id} onChange={(event) => setForm({ ...form, category_id: event.target.value })} className="w-full rounded-md border px-3 py-2">
              <option value="">Chọn danh mục</option>
              {categories.filter((category) => category.active || category.id === form.category_id).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
            </select>
          </label>
          <label className="space-y-1 text-sm">Giá niêm yết tham chiếu *
            <input type="number" min="0" value={form.base_price || ''} onChange={(event) => setForm({ ...form, base_price: Number(event.target.value) })} className="w-full rounded-md border px-3 py-2" />
          </label>
          <label className="space-y-1 text-sm">Trạng thái hiển thị
            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value as AdminProductPayload['status'] })} className="w-full rounded-md border px-3 py-2">
              <option value="active">Đang hiển thị</option>
              <option value="inactive">Đã ẩn</option>
            </select>
          </label>
          <label className="space-y-1 text-sm md:col-span-2">Mô tả
            <textarea rows={4} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} className="w-full rounded-md border px-3 py-2" />
          </label>
        </div>
      </section>

      <section className="space-y-4 rounded-lg border bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div><h3 className="font-semibold">Thuộc tính dùng chung</h3><p className="text-sm text-muted-foreground">Ví dụ thương hiệu hoặc chất liệu, áp dụng cho mọi biến thể.</p></div>
          {!editing && <Button type="button" variant="outline" onClick={addShared}><Plus className="mr-2 size-4" />Thêm thuộc tính</Button>}
        </div>
        {shared.length === 0 ? <p className="text-sm text-muted-foreground">Chưa có thuộc tính dùng chung.</p> : (
          <div className="grid gap-3 md:grid-cols-2">
            {shared.map((attribute, index) => (
              <label key={attribute.name} className="text-sm">{attribute.name}
                <select value={attribute.value} disabled={editing} onChange={(event) => updateShared(index, event.target.value)} className="mt-1 w-full rounded-md border px-3 py-2">
                  {(attributeOptions[attribute.name] ?? []).map((value) => <option key={value}>{value}</option>)}
                </select>
              </label>
            ))}
          </div>
        )}
      </section>

      <section className="space-y-4 rounded-lg border bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div><h3 className="font-semibold">Thuộc tính tạo biến thể</h3><p className="text-sm text-muted-foreground">Ví dụ màu sắc, kích thước hoặc dung tích. Biến thể mới được tạo với tồn kho 0 và trạng thái chưa hoàn thiện.</p></div>
          <Button type="button" variant="outline" onClick={addAxis}><Plus className="mr-2 size-4" />Thêm thuộc tính</Button>
        </div>
        {axes.length === 0 ? <p className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">Chưa chọn thuộc tính tạo biến thể.</p> : axes.map((axis) => (
          <div key={axis.name} className="rounded-md border p-4">
            <p className="mb-3 text-sm font-medium">{axis.name}</p>
            <div className="flex flex-wrap gap-2">
              {(attributeOptions[axis.name] ?? []).map((value) => {
                const selected = axis.values.includes(value);
                return <button key={value} type="button" onClick={() => toggleAxisValue(axis.name, value)} className={'rounded-md border px-3 py-1.5 text-sm ' + (selected ? 'border-orange-500 bg-orange-50 text-orange-700' : 'bg-white')}>{value}</button>;
              })}
            </div>
          </div>
        ))}
        <div className="rounded-md bg-slate-50 p-3 text-sm">
          {shared.map((attribute) => attribute.name + ': ' + attribute.value).join(' | ')}
          {shared.length > 0 && axes.length > 0 && ' | '}
          {axes.map((axis) => axis.name + ': ' + axis.values.join(', ')).join(' | ')}
          <strong className="ml-2">Dự kiến: {projectedCount} biến thể</strong>
        </div>
      </section>

      <section className="rounded-lg border bg-white p-5">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div><h3 className="font-semibold">Hình ảnh</h3><p className="text-sm text-muted-foreground">Ảnh chung được dùng mặc định. Có thể gán một ảnh cho màu sắc hoặc kiểu dáng để khách đổi đúng ảnh.</p></div>
          <label className="cursor-pointer">
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={async (event) => {
              const files = Array.from(event.target.files ?? []);
              if (files.length) await startCropQueue(files);
              event.target.value = '';
            }} />
            <span className="inline-flex items-center rounded-md border px-3 py-2 text-sm">{uploading ? <Loader2 className="mr-2 size-4 animate-spin" /> : <ImagePlus className="mr-2 size-4" />}Tải ảnh</span>
          </label>
        </div>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          {form.images.map((image, index) => {
            const selectedTarget = imageTargets.find((target) => JSON.stringify(target.attributes) === JSON.stringify(image.variant_attributes));
            return (
              <div key={image.id ?? image.url} className={'overflow-hidden rounded-md border-2 ' + (image.is_primary ? 'border-orange-500' : 'border-transparent')}>
                <ImageWithFallback src={image.thumbnail_url ?? image.url} alt="" className="aspect-square w-full object-cover" />
                <div className="space-y-2 p-2">
                  <select value={selectedTarget?.key ?? ''} onChange={(event) => setImageTarget(index, event.target.value)} className="w-full rounded border px-1 py-1 text-xs">
                    <option value="">Ảnh chung</option>
                    {imageTargets.map((target) => <option key={target.key} value={target.key}>{target.label}</option>)}
                  </select>
                  <div className="flex gap-2">
                    <button type="button" onClick={() => setForm((current) => ({ ...current, images: current.images.map((item, itemIndex) => ({ ...item, is_primary: itemIndex === index })) }))} className="flex-1 text-xs text-orange-700">{image.is_primary ? 'Ảnh chính' : 'Đặt ảnh chính'}</button>
                    <button type="button" onClick={() => setForm((current) => ({ ...current, images: current.images.filter((_, itemIndex) => itemIndex !== index).map((item, itemIndex) => ({ ...item, is_primary: itemIndex === 0 ? true : item.is_primary })) }))} className="text-xs text-red-600">Xóa</button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <div className="flex justify-end gap-3">
        <Button variant="outline" onClick={() => navigate('/admin/products')}>Hủy</Button>
        <Button onClick={save} disabled={saving || uploading} className="bg-orange-600 hover:bg-orange-700">{saving && <Loader2 className="mr-2 size-4 animate-spin" />}Lưu sản phẩm</Button>
      </div>
      {cropSource && <ImageCropDialog image={cropSource} aspect={1} title="Căn chỉnh ảnh sản phẩm" onCancel={() => {
        URL.revokeObjectURL(cropSource);
        setCropSource(null);
        setCropQueue([]);
        setUploading(false);
      }} onConfirm={confirmCrop} />}
    </div>
  );
}
