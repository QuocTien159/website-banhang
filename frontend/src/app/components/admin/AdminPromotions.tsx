import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { adminCommerceService } from '../../services/commerceService';
import { Button } from '../ui/button';

const empty = { code: '', type: 'percent', value: 10, minimum_order: 0, maximum_discount: 0, starts_at: '', ends_at: '', usage_limit: 100, active: true };
export function AdminPromotions() {
  const [items, setItems] = useState<any[]>([]); const [form, setForm] = useState<any>(empty); const [editing, setEditing] = useState<string | null>(null);
  const load = () => adminCommerceService.promotions.list().then(setItems); useEffect(() => { load(); }, []);
  const field = (label: string, key: string, type = 'text') => <label className="text-sm">{label}<input type={type} value={form[key] ?? ''} onChange={(e) => setForm({ ...form, [key]: type === 'number' ? Number(e.target.value) : e.target.value })} className="mt-1 w-full border rounded-lg p-2" /></label>;
  return <div className="space-y-5"><h2 className="text-2xl font-semibold">Mã khuyến mãi</h2><section className="bg-white border rounded-xl p-5 grid md:grid-cols-4 gap-3">
    {field('Mã', 'code')}{field('Giá trị', 'value', 'number')}{field('Đơn tối thiểu', 'minimum_order', 'number')}{field('Giảm tối đa', 'maximum_discount', 'number')}
    <label className="text-sm">Loại<select value={form.type} onChange={(e) => setForm({...form,type:e.target.value})} className="mt-1 w-full border rounded-lg p-2"><option value="percent">Phần trăm</option><option value="fixed">Số tiền</option></select></label>
    {field('Bắt đầu', 'starts_at', 'datetime-local')}{field('Kết thúc', 'ends_at', 'datetime-local')}{field('Giới hạn lượt', 'usage_limit', 'number')}
    <Button onClick={async () => { try { editing ? await adminCommerceService.promotions.update(editing, form) : await adminCommerceService.promotions.create(form); toast.success('Đã lưu mã.'); setForm(empty); setEditing(null); load(); } catch(e:any){toast.error(e.response?.data?.message ?? 'Không thể lưu mã.');} }}>{editing?'Lưu thay đổi':'Tạo mã'}</Button>
  </section><div className="bg-white border rounded-xl divide-y">{items.map((x) => <div key={x.ma_km} className="p-4 flex gap-4 items-center"><b className="w-28">{x.code}</b><span className="flex-1">{x.loai_giam === 'percent' ? `${x.gia_tri}%` : x.gia_tri} · Đã dùng {x.da_su_dung}/{x.gioi_han_su_dung ?? '∞'}</span><button className="text-blue-600" onClick={() => {setEditing(x.ma_km);setForm({code:x.code,type:x.loai_giam,value:Number(x.gia_tri),minimum_order:Number(x.don_toi_thieu),maximum_discount:Number(x.giam_toi_da??0),starts_at:x.bat_dau?.slice(0,16),ends_at:x.ket_thuc?.slice(0,16),usage_limit:x.gioi_han_su_dung,active:x.trang_thai});}}>Sửa</button><button className="text-red-600" onClick={async()=>{if(confirm('Vô hiệu hóa mã này?')){await adminCommerceService.promotions.remove(x.ma_km);load();}}}>Vô hiệu hóa</button></div>)}</div></div>;
}
