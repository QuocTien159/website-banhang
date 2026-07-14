import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { adminCommerceService } from '../../services/commerceService';
import { Button } from '../ui/button';

const empty = {
  enabled: false,
  voucher_id: '',
  label: '',
  title: '',
  description: '',
  cta_text: '',
  cta_url: '/products',
  starts_at: '',
  ends_at: '',
};

export function AdminHomepagePromotion() {
  const [form, setForm] = useState<any>(empty);
  const [vouchers, setVouchers] = useState<any[]>([]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    Promise.all([
      adminCommerceService.homepagePromotion.show(),
      adminCommerceService.homepagePromotion.vouchers(),
    ]).then(([data, codes]) => {
      setVouchers(codes);
      if (data) setForm({ ...empty, ...data, voucher_id: data.voucher_id ?? '' });
    });
  }, []);

  const selected = vouchers.find((voucher) => voucher.id === form.voucher_id);

  const save = async () => {
    setSaving(true);
    try {
      const data = await adminCommerceService.homepagePromotion.update(form);
      setForm({ ...form, ...data, voucher_id: data.voucher_id ?? '' });
      toast.success('Đã lưu ưu đãi trang chủ.');
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể lưu ưu đãi.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-4xl space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Ưu đãi trang chủ</h2>
        <p className="text-sm text-muted-foreground">
          Chỉ một ưu đãi đang bật được hiển thị khi voucher còn hiệu lực.
        </p>
      </div>

      <section className="space-y-5 rounded-lg border bg-white p-5">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.enabled}
            onChange={(event) => setForm({ ...form, enabled: event.target.checked })}
          />
          Hiển thị ưu đãi
        </label>

        <div className="grid gap-4 md:grid-cols-2">
          <label className="text-sm">
            Voucher
            <select
              className="mt-1 w-full rounded-lg border p-2"
              value={form.voucher_id}
              onChange={(event) => setForm({ ...form, voucher_id: event.target.value })}
            >
              <option value="">Chọn voucher hợp lệ</option>
              {vouchers.map((voucher) => (
                <option key={voucher.id} value={voucher.id}>{voucher.code}</option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            Nhãn
            <input className="mt-1 w-full rounded-lg border p-2" value={form.label ?? ''} onChange={(event) => setForm({ ...form, label: event.target.value })} />
          </label>
          <label className="text-sm">
            Tiêu đề
            <input className="mt-1 w-full rounded-lg border p-2" value={form.title ?? ''} onChange={(event) => setForm({ ...form, title: event.target.value })} />
          </label>
          <label className="text-sm">
            CTA
            <input className="mt-1 w-full rounded-lg border p-2" value={form.cta_text ?? ''} onChange={(event) => setForm({ ...form, cta_text: event.target.value })} />
          </label>
          <label className="text-sm">
            Đường dẫn CTA
            <input className="mt-1 w-full rounded-lg border p-2" value={form.cta_url ?? ''} onChange={(event) => setForm({ ...form, cta_url: event.target.value })} />
          </label>
          <label className="text-sm">
            Bắt đầu
            <input type="datetime-local" className="mt-1 w-full rounded-lg border p-2" value={form.starts_at?.slice(0, 16) ?? ''} onChange={(event) => setForm({ ...form, starts_at: event.target.value })} />
          </label>
          <label className="text-sm">
            Kết thúc
            <input type="datetime-local" className="mt-1 w-full rounded-lg border p-2" value={form.ends_at?.slice(0, 16) ?? ''} onChange={(event) => setForm({ ...form, ends_at: event.target.value })} />
          </label>
        </div>

        <label className="block text-sm">
          Mô tả
          <textarea className="mt-1 w-full rounded-lg border p-2" rows={3} value={form.description ?? ''} onChange={(event) => setForm({ ...form, description: event.target.value })} />
        </label>

        {selected && (
          <div className="rounded-lg bg-orange-50 p-3 text-sm">
            {selected.code} · {selected.type === 'percent' ? selected.value + '%' : selected.value.toLocaleString('vi-VN') + ' đ'} · Đơn từ {selected.minimum_order.toLocaleString('vi-VN')} đ · hết hạn {new Date(selected.ends_at).toLocaleString('vi-VN')}
          </div>
        )}

        <Button disabled={saving || (form.enabled && !form.voucher_id)} className="bg-orange-600 hover:bg-orange-700" onClick={save}>
          {saving && <Loader2 className="mr-2 size-4 animate-spin" />}
          Lưu cấu hình
        </Button>
      </section>
    </div>
  );
}
