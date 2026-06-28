import { useEffect, useMemo, useState } from 'react';
import { Edit2, Eye, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import {
  adminService,
  type AdminAttribute,
  type AdminAttributePayload,
  type AdminAttributeValue,
} from '../../services/orderService';
import { Button } from '../ui/button';

const emptyAttribute: AdminAttributePayload = {
  name: '',
  slug: '',
  active: true,
  description: '',
};

const emptyValue = {
  value: '',
  slug: '',
  color_code: '',
  sort_order: 0,
  active: true,
};

const errorMessage = (error: any, fallback: string) =>
  error.response?.data?.message ??
  Object.values(error.response?.data?.errors ?? {}).flat()[0] ??
  fallback;

const formatDate = (value?: string | null) => {
  if (!value) return 'Không xác định';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'Không xác định' : date.toLocaleDateString('vi-VN');
};

export function AdminAttributes() {
  const [attributes, setAttributes] = useState<AdminAttribute[]>([]);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<AdminAttribute | null>(null);
  const [form, setForm] = useState<AdminAttributePayload>(emptyAttribute);
  const [valueForm, setValueForm] = useState(emptyValue);
  const [editingValueId, setEditingValueId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingValue, setSavingValue] = useState(false);

  const isEditing = useMemo(() => Boolean(selected?.id), [selected?.id]);

  const load = async () => {
    setLoading(true);
    try {
      setAttributes(await adminService.getAttributes(search ? { search } : {}));
    } finally {
      setLoading(false);
    }
  };

  const loadDetail = async (id: string) => {
    const detail = await adminService.getAttribute(id);
    setSelected(detail);
    setForm({
      name: detail.name,
      slug: detail.slug,
      active: detail.active,
      description: detail.description ?? '',
    });
    setValueForm(emptyValue);
    setEditingValueId(null);
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const resetForm = () => {
    setSelected(null);
    setForm(emptyAttribute);
    setValueForm(emptyValue);
    setEditingValueId(null);
  };

  const saveAttribute = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        name: form.name.trim(),
        slug: form.slug?.trim(),
        description: form.description?.trim(),
      };
      const saved = selected?.id
        ? await adminService.updateAttribute(selected.id, payload)
        : await adminService.createAttribute(payload);

      toast.success(selected?.id ? 'Đã cập nhật thuộc tính.' : 'Đã thêm thuộc tính.');
      await load();
      await loadDetail(saved.id);
    } catch (error: any) {
      toast.error(errorMessage(error, 'Không thể lưu thuộc tính.'));
    } finally {
      setSaving(false);
    }
  };

  const saveValue = async () => {
    if (!selected?.id) return;
    setSavingValue(true);
    try {
      const payload = {
        value: valueForm.value.trim(),
        slug: valueForm.slug.trim(),
        color_code: valueForm.color_code.trim() || undefined,
        sort_order: Number(valueForm.sort_order) || 0,
        active: valueForm.active,
      };

      if (editingValueId) {
        await adminService.updateAttributeValue(selected.id, editingValueId, payload);
        toast.success('Đã cập nhật giá trị thuộc tính.');
      } else {
        await adminService.createAttributeValue(selected.id, payload);
        toast.success('Đã thêm giá trị thuộc tính.');
      }

      await loadDetail(selected.id);
      await load();
    } catch (error: any) {
      toast.error(errorMessage(error, 'Không thể lưu giá trị thuộc tính.'));
    } finally {
      setSavingValue(false);
    }
  };

  const editValue = (value: AdminAttributeValue) => {
    setEditingValueId(value.id);
    setValueForm({
      value: value.value,
      slug: value.slug,
      color_code: value.color_code ?? '',
      sort_order: value.sort_order,
      active: value.active,
    });
  };

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-2xl font-semibold">Quản lý thuộc tính</h2>
          <p className="text-sm text-muted-foreground">Quản lý size, màu sắc, chất liệu, thương hiệu và các giá trị dùng cho biến thể sản phẩm.</p>
        </div>
        <Button onClick={resetForm} className="bg-orange-600 hover:bg-orange-700">
          <Plus className="w-4 h-4 mr-2" />Thêm thuộc tính
        </Button>
      </div>

      <div className="grid xl:grid-cols-[1fr_420px] gap-5">
        <section className="bg-white border rounded-xl overflow-hidden">
          <div className="p-4 border-b">
            <div className="relative max-w-sm">
              <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Tìm thuộc tính..."
                className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm"
              />
            </div>
          </div>

          {loading ? (
            <div className="p-12 text-center text-sm text-muted-foreground">Đang tải...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    <th className="text-left p-4">Tên thuộc tính</th>
                    <th className="text-left p-4">Slug</th>
                    <th className="text-center p-4">Giá trị</th>
                    <th className="text-center p-4">Trạng thái</th>
                    <th className="text-left p-4">Ngày tạo</th>
                    <th className="p-4" />
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {attributes.map((attribute) => (
                    <tr key={attribute.id} className={selected?.id === attribute.id ? 'bg-orange-50/40' : ''}>
                      <td className="p-4 font-medium">{attribute.name}</td>
                      <td className="p-4 text-muted-foreground">{attribute.slug}</td>
                      <td className="p-4 text-center">{attribute.value_count}</td>
                      <td className="p-4 text-center">
                        <span className={`px-2 py-1 rounded-full text-xs ${attribute.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                          {attribute.active ? 'Đang dùng' : 'Tạm ẩn'}
                        </span>
                      </td>
                      <td className="p-4 text-muted-foreground">{formatDate(attribute.created_at)}</td>
                      <td className="p-4">
                        <div className="flex justify-end gap-1">
                          <button onClick={() => loadDetail(attribute.id)} className="p-2 text-blue-600" title="Xem/sửa">
                            <Eye className="w-4 h-4" />
                          </button>
                          <button
                            onClick={async () => {
                              if (!confirm(`Xóa thuộc tính "${attribute.name}"?`)) return;
                              try {
                                await adminService.deleteAttribute(attribute.id);
                                toast.success('Đã xóa thuộc tính.');
                                if (selected?.id === attribute.id) resetForm();
                                await load();
                              } catch (error: any) {
                                toast.error(errorMessage(error, 'Không thể xóa thuộc tính.'));
                              }
                            }}
                            className="p-2 text-red-600"
                            title="Xóa"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {attributes.length === 0 && (
                    <tr>
                      <td colSpan={6} className="p-10 text-center text-muted-foreground">Chưa có thuộc tính nào.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <aside className="space-y-5">
          <section className="bg-white border rounded-xl p-5">
            <h3 className="font-semibold mb-4">{isEditing ? 'Sửa thuộc tính' : 'Thêm thuộc tính'}</h3>
            <div className="space-y-3">
              <label className="block text-sm">
                Tên thuộc tính *
                <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="mt-1 w-full border rounded-lg px-3 py-2" />
              </label>
              <label className="block text-sm">
                Slug/mã
                <input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} placeholder="Để trống để tự tạo" className="mt-1 w-full border rounded-lg px-3 py-2" />
              </label>
              <label className="block text-sm">
                Trạng thái
                <select value={form.active ? '1' : '0'} onChange={(e) => setForm({ ...form, active: e.target.value === '1' })} className="mt-1 w-full border rounded-lg px-3 py-2">
                  <option value="1">Đang dùng</option>
                  <option value="0">Tạm ẩn</option>
                </select>
              </label>
              <label className="block text-sm">
                Mô tả
                <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} className="mt-1 w-full border rounded-lg px-3 py-2" />
              </label>
              <Button onClick={saveAttribute} disabled={saving || !form.name.trim()} className="w-full bg-orange-600 hover:bg-orange-700">
                {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
                {isEditing ? 'Lưu thuộc tính' : 'Tạo thuộc tính'}
              </Button>
            </div>
          </section>

          <section className="bg-white border rounded-xl p-5">
            <h3 className="font-semibold mb-1">Giá trị thuộc tính</h3>
            <p className="text-xs text-muted-foreground mb-4">Chọn một thuộc tính để thêm/sửa giá trị như S, M, Đen, Trắng.</p>
            {!selected?.id ? (
              <div className="text-sm text-muted-foreground border rounded-lg p-4">Hãy tạo hoặc chọn thuộc tính trước.</div>
            ) : (
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-2">
                  <input value={valueForm.value} onChange={(e) => setValueForm({ ...valueForm, value: e.target.value })} placeholder="Tên giá trị *" className="border rounded-lg px-3 py-2 text-sm" />
                  <input value={valueForm.slug} onChange={(e) => setValueForm({ ...valueForm, slug: e.target.value })} placeholder="Slug" className="border rounded-lg px-3 py-2 text-sm" />
                  <input value={valueForm.color_code} onChange={(e) => setValueForm({ ...valueForm, color_code: e.target.value })} placeholder="#000000 nếu là màu" className="border rounded-lg px-3 py-2 text-sm" />
                  <input type="number" min="0" value={valueForm.sort_order} onChange={(e) => setValueForm({ ...valueForm, sort_order: Number(e.target.value) })} placeholder="Thứ tự" className="border rounded-lg px-3 py-2 text-sm" />
                </div>
                <div className="flex items-center gap-2">
                  <label className="flex items-center gap-2 text-sm flex-1">
                    <input type="checkbox" checked={valueForm.active} onChange={(e) => setValueForm({ ...valueForm, active: e.target.checked })} />
                    Đang dùng
                  </label>
                  {editingValueId && <Button variant="outline" onClick={() => { setEditingValueId(null); setValueForm(emptyValue); }}>Hủy</Button>}
                  <Button onClick={saveValue} disabled={savingValue || !valueForm.value.trim()} className="bg-orange-600 hover:bg-orange-700">
                    {savingValue && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
                    {editingValueId ? 'Lưu giá trị' : 'Thêm giá trị'}
                  </Button>
                </div>

                <div className="divide-y border rounded-xl overflow-hidden">
                  {(selected.values ?? []).map((value) => (
                    <div key={value.id} className="p-3 flex items-center gap-3">
                      {value.color_code && <span className="w-5 h-5 rounded-full border" style={{ backgroundColor: value.color_code }} />}
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm">{value.value}</p>
                        <p className="text-xs text-muted-foreground">{value.slug} · thứ tự {value.sort_order}</p>
                      </div>
                      <span className={`px-2 py-1 rounded-full text-xs ${value.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                        {value.active ? 'Đang dùng' : 'Tạm ẩn'}
                      </span>
                      <button onClick={() => editValue(value)} className="p-2 text-blue-600"><Edit2 className="w-4 h-4" /></button>
                      <button
                        onClick={async () => {
                          if (!confirm(`Xóa giá trị "${value.value}"?`)) return;
                          try {
                            await adminService.deleteAttributeValue(selected.id, value.id);
                            toast.success('Đã xóa giá trị.');
                            await loadDetail(selected.id);
                            await load();
                          } catch (error: any) {
                            toast.error(errorMessage(error, 'Không thể xóa giá trị.'));
                          }
                        }}
                        className="p-2 text-red-600"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                  {(selected.values ?? []).length === 0 && <div className="p-4 text-sm text-muted-foreground">Chưa có giá trị.</div>}
                </div>
              </div>
            )}
          </section>
        </aside>
      </div>
    </div>
  );
}
