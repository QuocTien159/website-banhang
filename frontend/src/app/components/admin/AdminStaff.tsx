import { useEffect, useState } from 'react';
import { Edit2, Loader2, Plus, Search, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { adminService, type AdminStaff as Staff, type AdminStaffPayload } from '../../services/orderService';
import { Button } from '../ui/button';

const emptyForm: AdminStaffPayload = {
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'staff',
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

export function AdminStaff() {
  const [items, setItems] = useState<Staff[]>([]);
  const [search, setSearch] = useState('');
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<AdminStaffPayload>(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const response = await adminService.getStaff(search ? { search } : {});
      setItems(response.data ?? []);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const reset = () => {
    setEditingId(null);
    setForm(emptyForm);
  };

  const edit = (staff: Staff) => {
    setEditingId(staff.id);
    setForm({
      name: staff.name,
      email: staff.email,
      phone: staff.phone ?? '',
      password: '',
      role: staff.role,
      active: staff.active,
    });
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone?.trim() || undefined,
        password: form.password?.trim() || undefined,
      };
      if (editingId) {
        await adminService.updateStaff(editingId, payload);
        toast.success('Đã cập nhật nhân viên.');
      } else {
        if (!payload.password) {
          toast.error('Vui lòng nhập mật khẩu ban đầu.');
          return;
        }
        await adminService.createStaff(payload);
        toast.success('Đã tạo nhân viên.');
      }
      reset();
      await load();
    } catch (error: any) {
      toast.error(errorMessage(error, 'Không thể lưu nhân viên.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-2xl font-semibold">Quản lý nhân viên</h2>
          <p className="text-sm text-muted-foreground">Chỉ admin được tạo, sửa, khóa/mở khóa và gán vai trò nhân viên/admin.</p>
        </div>
        <Button onClick={reset} className="bg-orange-600 hover:bg-orange-700">
          <Plus className="w-4 h-4 mr-2" />Thêm nhân viên
        </Button>
      </div>

      <div className="grid xl:grid-cols-[1fr_380px] gap-5">
        <section className="bg-white border rounded-xl overflow-hidden">
          <div className="p-4 border-b">
            <div className="relative max-w-sm">
              <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Tìm tên, email, số điện thoại..." className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
            </div>
          </div>

          {loading ? (
            <div className="p-12 text-center text-sm text-muted-foreground">Đang tải...</div>
          ) : items.length === 0 ? (
            <div className="p-12 text-center text-muted-foreground">
              <ShieldCheck className="w-10 h-10 mx-auto mb-3 text-gray-300" />
              Chưa có nhân viên.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    <th className="text-left p-4">Nhân viên</th>
                    <th className="text-left p-4">SĐT</th>
                    <th className="text-center p-4">Vai trò</th>
                    <th className="text-center p-4">Trạng thái</th>
                    <th className="text-left p-4">Ngày tạo</th>
                    <th className="p-4" />
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {items.map((staff) => (
                    <tr key={staff.id}>
                      <td className="p-4">
                        <p className="font-medium">{staff.name}</p>
                        <p className="text-xs text-muted-foreground">{staff.email}</p>
                      </td>
                      <td className="p-4 text-muted-foreground">{staff.phone || '—'}</td>
                      <td className="p-4 text-center">
                        <span className={`px-2 py-1 rounded-full text-xs ${staff.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                          {staff.role === 'admin' ? 'Admin' : 'Nhân viên'}
                        </span>
                      </td>
                      <td className="p-4 text-center">
                        <span className={`px-2 py-1 rounded-full text-xs ${staff.active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                          {staff.active ? 'Hoạt động' : 'Đã khóa'}
                        </span>
                      </td>
                      <td className="p-4 text-muted-foreground">{formatDate(staff.created_at)}</td>
                      <td className="p-4">
                        <div className="flex justify-end gap-2">
                          <button onClick={() => edit(staff)} className="p-2 text-blue-600"><Edit2 className="w-4 h-4" /></button>
                          <button
                            onClick={async () => {
                              if (!confirm(`${staff.active ? 'Khóa' : 'Mở khóa'} tài khoản "${staff.name}"?`)) return;
                              try {
                                await adminService.toggleStaffStatus(staff.id);
                                await load();
                              } catch (error: any) {
                                toast.error(errorMessage(error, 'Không thể đổi trạng thái nhân viên.'));
                              }
                            }}
                            className={`text-xs px-2 py-1 rounded-lg border ${staff.active ? 'border-red-200 text-red-600' : 'border-green-200 text-green-700'}`}
                          >
                            {staff.active ? 'Khóa' : 'Mở khóa'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <aside className="bg-white border rounded-xl p-5 h-fit">
          <h3 className="font-semibold mb-4">{editingId ? 'Sửa nhân viên' : 'Thêm nhân viên'}</h3>
          <div className="space-y-3">
            <label className="block text-sm">Họ tên *<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label className="block text-sm">Email *<input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label className="block text-sm">Số điện thoại<input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label className="block text-sm">{editingId ? 'Mật khẩu mới' : 'Mật khẩu ban đầu *'}<input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder={editingId ? 'Để trống nếu không đổi' : ''} className="mt-1 w-full border rounded-lg px-3 py-2" /></label>
            <label className="block text-sm">Vai trò<select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as AdminStaffPayload['role'] })} className="mt-1 w-full border rounded-lg px-3 py-2"><option value="staff">Nhân viên</option><option value="admin">Admin</option></select></label>
            <label className="block text-sm">Trạng thái<select value={form.active ? '1' : '0'} onChange={(e) => setForm({ ...form, active: e.target.value === '1' })} className="mt-1 w-full border rounded-lg px-3 py-2"><option value="1">Hoạt động</option><option value="0">Khóa</option></select></label>
            <div className="flex gap-2">
              {editingId && <Button variant="outline" onClick={reset}>Hủy</Button>}
              <Button onClick={save} disabled={saving || !form.name.trim() || !form.email.trim()} className="flex-1 bg-orange-600 hover:bg-orange-700">
                {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
                {editingId ? 'Lưu thay đổi' : 'Tạo nhân viên'}
              </Button>
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}
