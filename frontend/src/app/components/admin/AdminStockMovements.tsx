import { useEffect, useState } from 'react';
import { Loader2, Search, SlidersHorizontal, X } from 'lucide-react';
import { toast } from 'sonner';
import { adminService } from '../../services/orderService';
import { Button } from '../ui/button';

type InventoryVariant = {
  id: string;
  sku: string;
  product_name: string;
  stock: number;
  attributes: { name: string; value: string }[];
};

type Movement = {
  id: string;
  variant: InventoryVariant;
  type: string;
  quantity_change: number;
  stock_before: number;
  stock_after: number;
  actor: string | null;
  time: string;
  note: string | null;
  reference: string | null;
};

const TYPE_LABELS: Record<string, string> = {
  stock_import: 'Nhập kho',
  sale: 'Bán hàng',
  order_cancelled: 'Hủy đơn',
  return: 'Trả hàng',
  manual_adjustment: 'Điều chỉnh',
};

const errorText = (error: any) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể xử lý yêu cầu.';
};

export function AdminStockMovements() {
  const [movements, setMovements] = useState<Movement[]>([]);
  const [variants, setVariants] = useState<InventoryVariant[]>([]);
  const [search, setSearch] = useState('');
  const [type, setType] = useState('');
  const [loading, setLoading] = useState(true);
  const [adjustOpen, setAdjustOpen] = useState(false);
  const [adjustVariantId, setAdjustVariantId] = useState('');
  const [adjustStock, setAdjustStock] = useState(0);
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const response = await adminService.getStockMovements({
        ...(search ? { search } : {}),
        ...(type ? { type } : {}),
      });
      setMovements(response.data ?? []);
    } catch {
      toast.error('Không thể tải lịch sử kho.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    adminService.getInventoryVariants().then(setVariants).catch(() => {});
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search, type]);

  const submitAdjust = async () => {
    setSaving(true);
    try {
      await adminService.adjustStock({ variant_id: adjustVariantId, stock: adjustStock, reason });
      toast.success('Đã điều chỉnh tồn kho.');
      setAdjustOpen(false);
      setAdjustVariantId('');
      setAdjustStock(0);
      setReason('');
      await Promise.all([load(), adminService.getInventoryVariants().then(setVariants)]);
    } catch (error: any) {
      toast.error(errorText(error));
    } finally {
      setSaving(false);
    }
  };

  const selectedVariant = variants.find((variant) => variant.id === adjustVariantId);

  return (
    <div className="space-y-5">
      <div className="flex items-start justify-between">
        <div>
          <h2 className="text-2xl font-semibold">Lịch sử kho</h2>
          <p className="text-sm text-muted-foreground">Theo dõi mọi biến động tồn kho: nhập kho, bán hàng, hủy đơn và điều chỉnh thủ công.</p>
        </div>
        <Button onClick={() => setAdjustOpen(true)} className="bg-orange-600 hover:bg-orange-700">
          <SlidersHorizontal className="w-4 h-4 mr-2" />Điều chỉnh kho
        </Button>
      </div>

      <div className="bg-white border rounded-xl p-4 grid md:grid-cols-[1fr_220px] gap-3">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm sản phẩm, SKU, mã tham chiếu" className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
        </div>
        <select value={type} onChange={(event) => setType(event.target.value)} className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả biến động</option>
          {Object.entries(TYPE_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
        </select>
      </div>

      <div className="bg-white border rounded-xl overflow-x-auto">
        {loading ? <div className="p-10 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-4">Sản phẩm</th>
                <th className="text-left p-4">Loại</th>
                <th className="text-center p-4">Thay đổi</th>
                <th className="text-center p-4">Trước</th>
                <th className="text-center p-4">Sau</th>
                <th className="text-left p-4">Người thực hiện</th>
                <th className="text-left p-4">Tham chiếu</th>
                <th className="text-left p-4">Thời gian</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {movements.map((movement) => (
                <tr key={movement.id}>
                  <td className="p-4">
                    <p className="font-medium">{movement.variant?.product_name}</p>
                    <p className="text-xs text-muted-foreground">{movement.variant?.sku}</p>
                    {movement.note && <p className="text-xs mt-1 max-w-xs truncate">{movement.note}</p>}
                  </td>
                  <td className="p-4">{TYPE_LABELS[movement.type] ?? movement.type}</td>
                  <td className={`p-4 text-center font-semibold ${movement.quantity_change > 0 ? 'text-green-600' : 'text-red-600'}`}>{movement.quantity_change > 0 ? '+' : ''}{movement.quantity_change}</td>
                  <td className="p-4 text-center">{movement.stock_before}</td>
                  <td className="p-4 text-center">{movement.stock_after}</td>
                  <td className="p-4">{movement.actor ?? 'Hệ thống'}</td>
                  <td className="p-4">{movement.reference}</td>
                  <td className="p-4">{new Date(movement.time).toLocaleString('vi-VN')}</td>
                </tr>
              ))}
              {movements.length === 0 && <tr><td colSpan={8} className="p-10 text-center text-muted-foreground">Chưa có biến động kho.</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      {adjustOpen && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setAdjustOpen(false)} />
          <div className="absolute top-1/2 left-1/2 w-[min(560px,calc(100%-24px))] -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl">
            <div className="p-5 border-b flex justify-between">
              <div>
                <h3 className="text-lg font-semibold">Điều chỉnh kho thủ công</h3>
                <p className="text-sm text-muted-foreground">Bắt buộc nhập lý do; hệ thống sẽ ghi lịch sử biến động.</p>
              </div>
              <button onClick={() => setAdjustOpen(false)}><X /></button>
            </div>
            <div className="p-5 space-y-4">
              <label className="space-y-1 block">
                <span className="text-sm">SKU *</span>
                <select value={adjustVariantId} onChange={(event) => {
                  const id = event.target.value;
                  const variant = variants.find((item) => item.id === id);
                  setAdjustVariantId(id);
                  setAdjustStock(variant?.stock ?? 0);
                }} className="w-full border rounded-lg px-3 py-2">
                  <option value="">Chọn SKU</option>
                  {variants.map((variant) => <option key={variant.id} value={variant.id}>{variant.product_name} · {variant.sku} · Tồn: {variant.stock}</option>)}
                </select>
              </label>
              {selectedVariant && <p className="text-xs text-muted-foreground">Tồn hiện tại: {selectedVariant.stock}</p>}
              <label className="space-y-1 block">
                <span className="text-sm">Tồn kho mới *</span>
                <input type="number" min="0" value={adjustStock} onChange={(event) => setAdjustStock(Number(event.target.value))} className="w-full border rounded-lg px-3 py-2" />
              </label>
              <label className="space-y-1 block">
                <span className="text-sm">Lý do điều chỉnh *</span>
                <textarea value={reason} onChange={(event) => setReason(event.target.value)} rows={4} className="w-full border rounded-lg px-3 py-2" placeholder="Ví dụ: kiểm kê thực tế lệch 2 sản phẩm..." />
              </label>
            </div>
            <div className="p-5 border-t flex justify-end gap-2">
              <Button variant="outline" onClick={() => setAdjustOpen(false)}>Hủy</Button>
              <Button onClick={submitAdjust} disabled={saving || !adjustVariantId || reason.trim().length < 5} className="bg-orange-600 hover:bg-orange-700">
                {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}Lưu điều chỉnh
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
