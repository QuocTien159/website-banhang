import { useEffect, useState } from 'react';
import { Eye, PackageCheck, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import { adminCommerceService } from '../../services/commerceService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type ReturnRequest = {
  id: string;
  code: string;
  order_id: string;
  customer?: string;
  status: string;
  refund_status: string;
  reason: string;
  description?: string;
  admin_note?: string;
  reject_reason?: string;
  last_processed_by?: string;
  last_processed_at?: string;
  processing_history?: { action: string; old_value?: string; new_value?: string; processed_by?: string; processed_at?: string; note?: string }[];
  created_at?: string;
  received_at?: string;
  refunded_at?: string;
  items_count: number;
  total_refund_estimate?: number;
  items: {
    variant_id: string;
    product_name: string;
    sku: string;
    image: string | null;
    quantity: number;
    reason: string;
    description?: string;
    images: string[];
  }[];
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xử lý',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
  received: 'Đã nhận hàng trả',
  completed: 'Hoàn tất',
  cancelled: 'Đã hủy',
};

const REFUND_LABELS: Record<string, string> = {
  not_refunded: 'Chưa hoàn tiền',
  refunding: 'Đang hoàn tiền',
  refunded: 'Đã hoàn tiền',
  refund_failed: 'Hoàn tiền thất bại',
};

const HISTORY_ACTION_LABELS: Record<string, string> = {
  cap_nhat_trang_thai: 'Cập nhật trạng thái',
  cap_nhat_hoan_tien: 'Cập nhật hoàn tiền',
};

const REFUND_TRANSITIONS: Record<string, string[]> = {
  not_refunded: ['refunding'],
  refunding: ['refunded', 'refund_failed'],
  refund_failed: ['refunding'],
  refunded: [],
};

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

const formatDate = (value?: string) => value ? new Date(value).toLocaleString('vi-VN') : 'Không xác định';

export function AdminReturns() {
  const [items, setItems] = useState<ReturnRequest[]>([]);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [detail, setDetail] = useState<ReturnRequest | null>(null);
  const [adminNote, setAdminNote] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [refundStatus, setRefundStatus] = useState('not_refunded');
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setItems(await adminCommerceService.returns.list({
        ...(status ? { status } : {}),
        ...(search ? { search } : {}),
      }));
    } catch {
      toast.error('Không thể tải yêu cầu trả hàng.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [status, search]);

  const openDetail = async (id: string) => {
    try {
      const data = await adminCommerceService.returns.show(id);
      setDetail(data);
      setAdminNote(data.admin_note ?? '');
      setRejectReason(data.reject_reason ?? '');
      setRefundStatus(data.refund_status ?? 'not_refunded');
    } catch {
      toast.error('Không thể tải chi tiết yêu cầu trả hàng.');
    }
  };

  const updateStatus = async (nextStatus: string) => {
    if (!detail) return;
    if (nextStatus === 'rejected' && !rejectReason.trim()) {
      toast.error('Vui lòng nhập lý do từ chối.');
      return;
    }
    if (nextStatus === 'received' && !confirm('Xác nhận đã nhận hàng trả và cộng lại tồn kho?')) return;
    setSaving(true);
    try {
      const response = await adminCommerceService.returns.updateStatus(detail.id, {
        status: nextStatus,
        admin_note: adminNote.trim() || undefined,
        reject_reason: rejectReason.trim() || undefined,
      });
      toast.success(response.message ?? 'Đã cập nhật yêu cầu trả hàng.');
      setDetail(response.return_request);
      await load();
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể cập nhật yêu cầu.');
    } finally {
      setSaving(false);
    }
  };

  const updateRefund = async () => {
    if (!detail) return;
    setSaving(true);
    try {
      await adminCommerceService.returns.updateRefund(detail.id, {
        refund_status: refundStatus,
        admin_note: adminNote.trim() || undefined,
      });
      toast.success('Đã cập nhật trạng thái hoàn tiền thủ công.');
      await openDetail(detail.id);
      await load();
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể cập nhật trạng thái hoàn tiền.');
    } finally {
      setSaving(false);
    }
  };

  const refundTransitions = detail ? REFUND_TRANSITIONS[detail.refund_status] ?? [] : [];
  const canUpdateRefund = !!detail
    && ['received', 'completed'].includes(detail.status)
    && refundTransitions.length > 0;

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Yêu cầu trả hàng</h2>
        <p className="text-sm text-muted-foreground">Xử lý trả hàng theo sản phẩm, chưa hoàn tiền tự động.</p>
      </div>

      <div className="bg-white border rounded-xl p-4 grid md:grid-cols-[1fr_220px] gap-3">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm mã yêu cầu, mã đơn, khách hàng" className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
        </div>
        <select value={status} onChange={(event) => setStatus(event.target.value)} className="border rounded-lg px-3 py-2 text-sm">
          <option value="">Tất cả trạng thái</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </div>

      <div className="bg-white border rounded-xl overflow-x-auto">
        {loading ? <div className="p-10 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-4">Mã yêu cầu</th>
                <th className="text-left p-4">Đơn hàng</th>
                <th className="text-left p-4">Khách hàng</th>
                <th className="text-center p-4">SL trả</th>
                <th className="text-left p-4">Trạng thái</th>
                <th className="text-left p-4">Hoàn tiền</th>
                <th className="text-left p-4">Ngày tạo</th>
                <th className="p-4" />
              </tr>
            </thead>
            <tbody className="divide-y">
              {items.map((item) => (
                <tr key={item.id}>
                  <td className="p-4 font-medium">{item.code}</td>
                  <td className="p-4">#{item.order_id}</td>
                  <td className="p-4">{item.customer ?? 'N/A'}</td>
                  <td className="p-4 text-center">{item.items_count}</td>
                  <td className="p-4">{STATUS_LABELS[item.status] ?? item.status}</td>
                  <td className="p-4">{REFUND_LABELS[item.refund_status] ?? item.refund_status}</td>
                  <td className="p-4">{formatDate(item.created_at)}</td>
                  <td className="p-4 text-right">
                    <button onClick={() => openDetail(item.id)} className="p-2 text-blue-600"><Eye className="w-4 h-4" /></button>
                  </td>
                </tr>
              ))}
              {!items.length && <tr><td colSpan={8} className="p-10 text-center text-muted-foreground">Chưa có yêu cầu trả hàng.</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      {detail && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setDetail(null)} />
          <div className="absolute inset-y-0 right-0 w-full max-w-5xl bg-white shadow-xl overflow-y-auto">
            <div className="sticky top-0 bg-white border-b p-5 flex items-start justify-between">
              <div>
                <h3 className="text-xl font-semibold">Yêu cầu {detail.code}</h3>
                <p className="text-sm text-muted-foreground">Đơn #{detail.order_id} · {detail.customer} · {formatDate(detail.created_at)}</p>
              </div>
              <button onClick={() => setDetail(null)}><X /></button>
            </div>
            <div className="p-5 space-y-5">
              <section className="grid md:grid-cols-3 gap-3">
                <div className="border rounded-xl p-3"><p className="text-xs text-muted-foreground">Trạng thái</p><p className="font-medium">{STATUS_LABELS[detail.status] ?? detail.status}</p></div>
                <div className="border rounded-xl p-3"><p className="text-xs text-muted-foreground">Hoàn tiền</p><p className="font-medium">{REFUND_LABELS[detail.refund_status] ?? detail.refund_status}</p></div>
                <div className="border rounded-xl p-3"><p className="text-xs text-muted-foreground">Ước tính hoàn hàng</p><p className="font-medium">{formatPrice(detail.total_refund_estimate ?? 0)}</p><p className="mt-1 text-xs text-muted-foreground">Đã phân bổ giảm giá, chưa gồm phí giao hàng.</p></div>
              </section>

              <section className="border rounded-xl p-4">
                <p className="font-medium">Lý do khách gửi</p>
                <p className="text-sm mt-2">{detail.reason}</p>
                {detail.description && <p className="text-sm text-muted-foreground mt-1">{detail.description}</p>}
                {detail.reject_reason && <p className="text-sm text-red-600 mt-2">Lý do từ chối: {detail.reject_reason}</p>}
                {detail.last_processed_by && <p className="text-sm text-muted-foreground mt-2">Xử lý gần nhất: {detail.last_processed_by} · {formatDate(detail.last_processed_at)}</p>}
                {detail.received_at && <p className="text-sm text-muted-foreground mt-1">Đã nhận hàng trả: {formatDate(detail.received_at)}</p>}
                {detail.refunded_at && <p className="text-sm text-muted-foreground mt-1">Đã hoàn tiền: {formatDate(detail.refunded_at)}</p>}
              </section>

              {detail.processing_history && detail.processing_history.length > 0 && (
                <section className="border rounded-xl p-4">
                  <h4 className="font-semibold mb-3">Lịch sử xử lý</h4>
                  <div className="space-y-2">
                    {detail.processing_history.map((history, index) => (
                      <div key={index} className="text-sm border-b last:border-0 pb-2 last:pb-0">
                        <p className="font-medium">{HISTORY_ACTION_LABELS[history.action] ?? history.action}: {history.old_value ?? '-'} → {history.new_value ?? '-'}</p>
                        <p className="text-xs text-muted-foreground">{history.processed_by ?? 'N/A'} · {formatDate(history.processed_at)}</p>
                        {history.note && <p className="text-xs mt-1">{history.note}</p>}
                      </div>
                    ))}
                  </div>
                </section>
              )}

              <section className="space-y-3">
                <h4 className="font-semibold">Sản phẩm trả</h4>
                {detail.items.map((item) => (
                  <div key={item.variant_id} className="border rounded-xl p-4">
                    <div className="flex gap-3">
                      <div className="w-16 h-16 rounded-lg overflow-hidden bg-gray-100">
                        <ImageWithFallback src={item.image ?? ''} alt={item.product_name} className="w-full h-full object-cover" />
                      </div>
                      <div className="flex-1">
                        <p className="font-medium">{item.product_name}</p>
                        <p className="text-xs text-muted-foreground">SKU: {item.sku} · SL trả: {item.quantity}</p>
                        <p className="text-sm mt-2">Lý do: {item.reason}</p>
                        {item.description && <p className="text-sm text-muted-foreground">{item.description}</p>}
                      </div>
                    </div>
                    {item.images?.length > 0 && (
                      <div className="flex flex-wrap gap-2 mt-3">
                        {item.images.map((image) => (
                          <a key={image} href={image} target="_blank" rel="noreferrer">
                            <img src={image} alt="Ảnh minh chứng" className="w-20 h-20 rounded-lg border object-cover" />
                          </a>
                        ))}
                      </div>
                    )}
                  </div>
                ))}
              </section>

              <section className="border rounded-xl p-4 space-y-3">
                <h4 className="font-semibold">Xử lý nội bộ</h4>
                <label className="block space-y-1">
                  <span className="text-sm">Ghi chú admin</span>
                  <textarea value={adminNote} onChange={(event) => setAdminNote(event.target.value)} rows={3} className="w-full border rounded-lg p-2 text-sm" />
                </label>
                {detail.status === 'pending' && <label className="block space-y-1">
                  <span className="text-sm">Lý do từ chối</span>
                  <input value={rejectReason} onChange={(event) => setRejectReason(event.target.value)} className="w-full border rounded-lg p-2 text-sm" />
                </label>}
                {canUpdateRefund && <label className="block space-y-1">
                  <span className="text-sm">Trạng thái hoàn tiền thủ công</span>
                  <select value={refundStatus} onChange={(event) => setRefundStatus(event.target.value)} className="w-full border rounded-lg p-2 text-sm">
                    <option value={detail.refund_status}>{REFUND_LABELS[detail.refund_status] ?? detail.refund_status}</option>
                    {refundTransitions.map((value) => <option key={value} value={value}>{REFUND_LABELS[value] ?? value}</option>)}
                  </select>
                </label>}
                <div className="flex flex-wrap gap-2">
                  {detail.status === 'pending' && <Button onClick={() => updateStatus('approved')} disabled={saving}>Duyệt</Button>}
                  {detail.status === 'pending' && <Button variant="outline" onClick={() => updateStatus('rejected')} disabled={saving}>Từ chối</Button>}
                  {detail.status === 'approved' && <Button className="bg-orange-600 hover:bg-orange-700" onClick={() => updateStatus('received')} disabled={saving}><PackageCheck className="w-4 h-4 mr-2" />Đã nhận hàng trả</Button>}
                  {detail.status === 'received' && <Button onClick={() => updateStatus('completed')} disabled={saving}>Hoàn tất</Button>}
                  {canUpdateRefund && <Button variant="outline" onClick={updateRefund} disabled={saving}>Lưu trạng thái hoàn tiền</Button>}
                </div>
                {!['received', 'completed'].includes(detail.status) && <p className="text-xs text-muted-foreground">Chỉ có thể cập nhật hoàn tiền sau khi đã nhận hàng trả về kho.</p>}
              </section>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
