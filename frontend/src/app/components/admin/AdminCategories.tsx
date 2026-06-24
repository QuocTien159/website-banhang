import { useEffect, useState } from 'react';
import { Edit2, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { adminService, type AdminCategory } from '../../services/orderService';
import { Button } from '../ui/button';

const errorMessage = (error: any, fallback: string) =>
  error.response?.data?.message ??
  Object.values(error.response?.data?.errors ?? {}).flat()[0] ??
  fallback;

export function AdminCategories() {
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [search, setSearch] = useState('');
  const [name, setName] = useState('');
  const [editId, setEditId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setCategories(await adminService.getCategories(search ? { search } : {}));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const save = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      if (editId) {
        await adminService.updateCategory(editId, { name: name.trim() });
        toast.success('Đã cập nhật danh mục.');
      } else {
        await adminService.createCategory(name.trim());
        toast.success('Đã thêm danh mục.');
      }
      setName('');
      setEditId(null);
      await load();
    } catch (error: any) {
      toast.error(errorMessage(error, 'Không thể lưu danh mục.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Quản lý danh mục</h2>
        <p className="text-sm text-muted-foreground">Tạo, đổi tên, tìm kiếm hoặc ẩn danh mục.</p>
      </div>
      <div className="grid lg:grid-cols-[360px_1fr] gap-5">
        <section className="bg-white border rounded-xl p-5 h-fit">
          <h3 className="font-semibold mb-4">{editId ? 'Sửa danh mục' : 'Thêm danh mục'}</h3>
          <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Tên danh mục" className="w-full border rounded-lg px-3 py-2 text-sm" />
          <div className="flex gap-2 mt-4">
            {editId && <Button variant="outline" onClick={() => { setEditId(null); setName(''); }}>Hủy</Button>}
            <Button onClick={save} disabled={saving || !name.trim()} className="bg-orange-600 hover:bg-orange-700">
              {saving ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Plus className="w-4 h-4 mr-2" />}
              {editId ? 'Lưu thay đổi' : 'Thêm danh mục'}
            </Button>
          </div>
        </section>
        <section className="bg-white border rounded-xl overflow-hidden">
          <div className="p-4 border-b">
            <div className="relative max-w-sm">
              <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Tìm danh mục..." className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
            </div>
          </div>
          {loading ? <div className="p-10 text-center">Đang tải...</div> : (
            <div className="divide-y">
              {categories.map((category) => (
                <div key={category.id} className="p-4 flex items-center gap-3">
                  <div className="flex-1">
                    <p className="font-medium">{category.name}</p>
                    <p className="text-xs text-muted-foreground">{category.product_count} sản phẩm</p>
                  </div>
                  <button
                    onClick={async () => {
                      try {
                        await adminService.updateCategory(category.id, { active: !category.active });
                        await load();
                      } catch (error: any) { toast.error(errorMessage(error, 'Không thể đổi trạng thái.')); }
                    }}
                    className={`px-3 py-1 rounded-full text-xs ${category.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}
                  >
                    {category.active ? 'Đang hiển thị' : 'Đã ẩn'}
                  </button>
                  <button onClick={() => { setEditId(category.id); setName(category.name); }} className="p-2 text-blue-600"><Edit2 className="w-4 h-4" /></button>
                  <button
                    onClick={async () => {
                      if (!confirm(`Xóa danh mục "${category.name}"?`)) return;
                      try {
                        await adminService.deleteCategory(category.id);
                        toast.success('Đã xóa danh mục.');
                        await load();
                      } catch (error: any) { toast.error(errorMessage(error, 'Không thể xóa danh mục.')); }
                    }}
                    className="p-2 text-red-600"
                  ><Trash2 className="w-4 h-4" /></button>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
