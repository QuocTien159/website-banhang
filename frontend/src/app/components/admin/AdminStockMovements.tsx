import { useEffect, useState } from 'react';
import { Check, Loader2, Search, SlidersHorizontal, X, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../../store/AppContext';
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

type StockAdjustment = {
  id: string;
  status: 'pending' | 'approved' | 'rejected';
  requested_stock: number;
  stock_at_request: number;
  reason: string;
  created_at: string | null;
  approved_at: string | null;
  approval_note: string | null;
  requester: string | null;
  approver: string | null;
  variant: InventoryVariant | null;
};

const TYPE_LABELS: Record<string, string> = {
  stock_import: 'Nhập kho',
  sale: 'Bán hàng',
  order_cancelled: 'Hủy đơn',
  return: 'Trả hàng',
  manual_adjustment: 'Điều chỉnh',
};

const ADJUSTMENT_STATUS: Record<StockAdjustment['status'], string> = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Đã từ chối',
};

const statusClass = (status: StockAdjustment['status']) => {
  if (status === 'approved') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  if (status === 'rejected') return 'bg-rose-50 text-rose-700 border-rose-200';
  return 'bg-amber-50 text-amber-700 border-amber-200';
};

const errorText = (error: any) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể xử lý yêu cầu.';
};

const formatDate = (value: string | null | undefined) => value ? new Date(value).toLocaleString('vi-VN') : '-';

export function AdminStockMovements() {
  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';
  const [movements, setMovements] = useState<Movement[]>([]);
  const [variants, setVariants] = useState<InventoryVariant[]>([]);
  const [adjustments, setAdjustments] = useState<StockAdjustment[]>([]);
  const [search, setSearch] = useState('');
  const [type, setType] = useState('');
  const [loading, setLoading] = useState(true);
  const [adjustmentsLoading, setAdjustmentsLoading] = useState(true);
  const [adjustOpen, setAdjustOpen] = useState(false);
  const [adjustVariantId, setAdjustVariantId] = useState('');
  const [adjustStock, setAdjustStock] = useState(0);
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);
  const [decisionId, setDecisionId] = useState<string | null>(null);

  const loadMovements = async () => {
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

  const loadAdjustments = async () => {
    setAdjustmentsLoading(true);
    try {
      const response = await adminService.getStockAdjustments({ per_page: 15 });
      setAdjustments(response.data ?? []);
    } catch {
      toast.error('Không thể tải yêu cầu điều chỉnh kho.');
    } finally {
      setAdjustmentsLoading(false);
    }
  };

  const loadVariants = async () => {
    try {
      setVariants(await adminService.getInventoryVariants());
    } catch {
      // The movement screen remains useful even if the selector cannot load.
    }
  };

  useEffect(() => {
    void loadVariants();
    void loadAdjustments();
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => void loadMovements(), 250);
    return () => window.clearTimeout(timer);
  }, [search, type]);

  const closeAdjustment = () => {
    if (saving) return;
    setAdjustOpen(false);
  };

  const submitAdjust = async () => {
    setSaving(true);
    try {
      const response = await adminService.adjustStock({ variant_id: adjustVariantId, stock: adjustStock, reason });
      toast.success(isAdmin ? 'Đã điều chỉnh tồn kho.' : response.message ?? 'Đã gửi yêu cầu điều chỉnh chờ duyệt.');
      setAdjustOpen(false);
      setAdjustVariantId('');
      setAdjustStock(0);
      setReason('');
      await Promise.all([loadMovements(), loadAdjustments(), loadVariants()]);
    } catch (error: any) {
      toast.error(errorText(error));
    } finally {
      setSaving(false);
    }
  };

  const decideAdjustment = async (adjustment: StockAdjustment, action: 'approve' | 'reject') => {
    const note = window.prompt(
      action === 'approve' ? 'Ghi chú duyệt (không bắt buộc):' : 'Lý do từ chối (không bắt buộc):',
      adjustment.approval_note ?? '',
    );
    if (note === null) return;

    setDecisionId(adjustment.id);
    try {
      if (action === 'approve') {
        await adminService.approveStockAdjustment(adjustment.id, note.trim() || undefined);
        toast.success('Đã duyệt yêu cầu và cập nhật tồn kho.');
      } else {
        await adminService.rejectStockAdjustment(adjustment.id, note.trim() || undefined);
        toast.success('Đã từ chối yêu cầu điều chỉnh kho.');
      }
      await Promise.all([loadMovements(), loadAdjustments(), loadVariants()]);
    } catch (error: any) {
      toast.error(errorText(error));
    } finally {
      setDecisionId(null);
    }
  };

  const selectedVariant = variants.find((variant) => variant.id === adjustVariantId);

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-2xl font-semibold">Lịch sử kho</h2>
          <p className="text-sm text-muted-foreground">
            Theo dõi nhập kho, bán hàng, trả hàng và điều chỉnh tồn kho.
          </p>
        </div>
        <Button onClick={() => setAdjustOpen(true)} className="bg-orange-600 hover:bg-orange-700">
          <SlidersHorizontal className="mr-2 h-4 w-4" />
          {isAdmin ? 'Điều chỉnh kho' : 'Gửi yêu cầu điều chỉnh'}
        </Button>
      </div>

      <div className="grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-[1fr_220px]">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm sản phẩm, SKU, mã tham chiếu" className="w-full rounded-lg border py-2 pl-9 pr-3 text-sm" />
        </div>
        <select value={type} onChange={(event) => setType(event.target.value)} className="rounded-lg border px-3 py-2 text-sm">
          <option value="">Tất cả biến động</option>
          {Object.entries(TYPE_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
        </select>
      </div>

      <section className="overflow-x-auto rounded-xl border bg-white">
        {loading ? <div className="p-10 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50">
              <tr>
                <th className="p-4 text-left">Sản phẩm</th>
                <th className="p-4 text-left">Loại</th>
                <th className="p-4 text-center">Thay đổi</th>
                <th className="p-4 text-center">Trước</th>
                <th className="p-4 text-center">Sau</th>
                <th className="p-4 text-left">Người thực hiện</th>
                <th className="p-4 text-left">Tham chiếu</th>
                <th className="p-4 text-left">Thời gian</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {movements.map((movement) => (
                <tr key={movement.id}>
                  <td className="p-4">
                    <p className="font-medium">{movement.variant?.product_name}</p>
                    <p className="text-xs text-muted-foreground">{movement.variant?.sku}</p>
                    {movement.note && <p className="mt-1 max-w-xs truncate text-xs">{movement.note}</p>}
                  </td>
                  <td className="p-4">{TYPE_LABELS[movement.type] ?? movement.type}</td>
                  <td className={`p-4 text-center font-semibold ${movement.quantity_change > 0 ? 'text-emerald-600' : 'text-rose-600'}`}>{movement.quantity_change > 0 ? '+' : ''}{movement.quantity_change}</td>
                  <td className="p-4 text-center">{movement.stock_before}</td>
                  <td className="p-4 text-center">{movement.stock_after}</td>
                  <td className="p-4">{movement.actor ?? 'Hệ thống'}</td>
                  <td className="p-4">{movement.reference ?? '-'}</td>
                  <td className="p-4">{formatDate(movement.time)}</td>
                </tr>
              ))}
              {movements.length === 0 && <tr><td colSpan={8} className="p-10 text-center text-muted-foreground">Chưa có biến động kho.</td></tr>}
            </tbody>
          </table>
        )}
      </section>

      <section className="overflow-x-auto rounded-xl border bg-white">
        <div className="flex flex-col gap-1 border-b px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 className="font-semibold">{isAdmin ? 'Yêu cầu điều chỉnh kho' : 'Yêu cầu điều chỉnh của bạn'}</h3>
            <p className="text-sm text-muted-foreground">
              {isAdmin ? 'Chỉ yêu cầu đang chờ duyệt mới có thể thay đổi tồn kho.' : 'Tồn kho chỉ được cập nhật sau khi admin duyệt yêu cầu.'}
            </p>
          </div>
        </div>
        {adjustmentsLoading ? <div className="p-8 text-center text-sm text-muted-foreground">Đang tải yêu cầu...</div> : (
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50">
              <tr>
                <th className="p-4 text-left">SKU</th>
                {isAdmin && <th className="p-4 text-left">Người gửi</th>}
                <th className="p-4 text-center">Tồn khi gửi</th>
                <th className="p-4 text-center">Tồn đề xuất</th>
                <th className="p-4 text-left">Lý do</th>
                <th className="p-4 text-left">Trạng thái</th>
                <th className="p-4 text-left">Xử lý</th>
                {isAdmin && <th className="p-4" />}
              </tr>
            </thead>
            <tbody className="divide-y">
              {adjustments.map((adjustment) => (
                <tr key={adjustment.id}>
                  <td className="p-4">
                    <p className="font-medium">{adjustment.variant?.product_name ?? 'SKU đã xóa'}</p>
                    <p className="text-xs text-muted-foreground">{adjustment.variant?.sku ?? adjustment.id}</p>
                  </td>
                  {isAdmin && <td className="p-4">{adjustment.requester ?? '-'}</td>}
                  <td className="p-4 text-center">{adjustment.stock_at_request}</td>
                  <td className="p-4 text-center font-medium">{adjustment.requested_stock}</td>
                  <td className="max-w-xs p-4"><p className="line-clamp-2">{adjustment.reason}</p></td>
                  <td className="p-4">
                    <span className={`inline-flex rounded-full border px-2 py-1 text-xs font-medium ${statusClass(adjustment.status)}`}>{ADJUSTMENT_STATUS[adjustment.status]}</span>
                  </td>
                  <td className="p-4 text-xs text-muted-foreground">
                    {adjustment.approver ? <><p>{adjustment.approver}</p><p>{formatDate(adjustment.approved_at)}</p></> : <p>{formatDate(adjustment.created_at)}</p>}
                    {adjustment.approval_note && <p className="mt-1 max-w-xs text-foreground">{adjustment.approval_note}</p>}
                  </td>
                  {isAdmin && <td className="p-4 text-right">
                    {adjustment.status === 'pending' && (
                      <div className="flex justify-end gap-1">
                        <Button size="icon" variant="outline" title="Duyệt yêu cầu" onClick={() => decideAdjustment(adjustment, 'approve')} disabled={decisionId === adjustment.id}>
                          {decisionId === adjustment.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4 text-emerald-600" />}
                        </Button>
                        <Button size="icon" variant="outline" title="Từ chối yêu cầu" onClick={() => decideAdjustment(adjustment, 'reject')} disabled={decisionId === adjustment.id}>
                          <XCircle className="h-4 w-4 text-rose-600" />
                        </Button>
                      </div>
                    )}
                  </td>}
                </tr>
              ))}
              {adjustments.length === 0 && <tr><td colSpan={isAdmin ? 8 : 6} className="p-8 text-center text-muted-foreground">Chưa có yêu cầu điều chỉnh kho.</td></tr>}
            </tbody>
          </table>
        )}
      </section>

      {adjustOpen && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={closeAdjustment} aria-label="Đóng" />
          <div className="absolute left-1/2 top-1/2 w-[min(560px,calc(100%-24px))] -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white shadow-xl">
            <div className="flex justify-between border-b p-5">
              <div>
                <h3 className="text-lg font-semibold">{isAdmin ? 'Điều chỉnh kho thủ công' : 'Gửi yêu cầu điều chỉnh kho'}</h3>
                <p className="text-sm text-muted-foreground">
                  {isAdmin ? 'Bắt buộc nhập lý do; hệ thống sẽ ghi lịch sử biến động.' : 'Yêu cầu chỉ có hiệu lực sau khi admin phê duyệt.'}
                </p>
              </div>
              <button onClick={closeAdjustment} disabled={saving} aria-label="Đóng"><X /></button>
            </div>
            <div className="space-y-4 p-5">
              <label className="block space-y-1">
                <span className="text-sm">SKU *</span>
                <select value={adjustVariantId} onChange={(event) => {
                  const id = event.target.value;
                  const variant = variants.find((item) => item.id === id);
                  setAdjustVariantId(id);
                  setAdjustStock(variant?.stock ?? 0);
                }} className="w-full rounded-lg border px-3 py-2">
                  <option value="">Chọn SKU</option>
                  {variants.map((variant) => <option key={variant.id} value={variant.id}>{variant.product_name} · {variant.sku} · Tồn: {variant.stock}</option>)}
                </select>
              </label>
              {selectedVariant && <p className="text-xs text-muted-foreground">Tồn hiện tại: {selectedVariant.stock}</p>}
              <label className="block space-y-1">
                <span className="text-sm">Tồn kho sau điều chỉnh *</span>
                <input type="number" min="0" value={adjustStock} onChange={(event) => setAdjustStock(Number(event.target.value))} className="w-full rounded-lg border px-3 py-2" />
              </label>
              <label className="block space-y-1">
                <span className="text-sm">Lý do điều chỉnh *</span>
                <textarea value={reason} onChange={(event) => setReason(event.target.value)} rows={4} className="w-full rounded-lg border px-3 py-2" placeholder="Ví dụ: kiểm kê thực tế lệch 2 sản phẩm..." />
              </label>
            </div>
            <div className="flex justify-end gap-2 border-t p-5">
              <Button variant="outline" onClick={closeAdjustment} disabled={saving}>Hủy</Button>
              <Button onClick={submitAdjust} disabled={saving || !adjustVariantId || reason.trim().length < 5} className="bg-orange-600 hover:bg-orange-700">
                {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {isAdmin ? 'Lưu điều chỉnh' : 'Gửi chờ duyệt'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
