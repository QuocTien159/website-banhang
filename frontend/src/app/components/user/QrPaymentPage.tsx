import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { ArrowLeft, CheckCircle, Clipboard, Loader2, QrCode } from 'lucide-react';
import { toast } from 'sonner';
import { orderService, type ApiOrder } from '../../services/orderService';
import { Button } from '../ui/button';
import { PAYMENT_STATUS_LABELS as SHARED_PAYMENT_STATUS_LABELS } from '../../constants/status';
import { formatCurrency } from '../../utils/formatters';

export function QrPaymentPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [order, setOrder] = useState<ApiOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    orderService.getOrder(id)
      .then(setOrder)
      .catch(() => toast.error('Không thể tải thông tin thanh toán QR.'))
      .finally(() => setLoading(false));
  }, [id]);

  const copy = async (value?: string | null, label = 'Thông tin') => {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    toast.success(`Đã sao chép ${label}.`);
  };

  const markPaid = async () => {
    if (!order) return;
    setSubmitting(true);
    try {
      const result = await orderService.markBankTransferPaid(order.id);
      setOrder(result.order);
      toast.success(result.message);
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể cập nhật trạng thái chuyển khoản.');
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <div className="max-w-3xl mx-auto px-4 py-20 text-center">Đang tải thông tin thanh toán...</div>;
  }

  if (!order || order.payment_method !== 'bank_transfer_qr') {
    return (
      <div className="max-w-3xl mx-auto px-4 py-20 text-center">
        <h1 className="text-2xl font-semibold mb-2">Không có thanh toán QR</h1>
        <p className="text-sm text-muted-foreground mb-6">Đơn hàng này không dùng phương thức chuyển khoản QR.</p>
        <Button onClick={() => navigate('/account')}>Quay lại tài khoản</Button>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
      <button onClick={() => navigate(`/account/orders/${order.id}`)} className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-orange-600">
        <ArrowLeft className="w-4 h-4" />Quay lại chi tiết đơn hàng
      </button>

      <section className="bg-white rounded-2xl border p-6">
        <div className="flex items-start gap-3 mb-6">
          <div className="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 grid place-items-center">
            <QrCode className="w-5 h-5" />
          </div>
          <div>
            <h1 className="text-2xl font-semibold">Thanh toán QR chuyển khoản</h1>
            <p className="text-sm text-muted-foreground">Đơn hàng #{order.id} · {SHARED_PAYMENT_STATUS_LABELS[order.payment_status ?? ''] ?? order.payment_status}</p>
          </div>
        </div>

        <div className="grid md:grid-cols-[260px,1fr] gap-6">
          <div className="rounded-2xl border bg-gray-50 p-4 text-center">
            {order.qr_code_url ? (
              <div className="mx-auto w-[220px] max-w-full rounded-xl bg-white p-3">
                <img
                  src={order.qr_code_url}
                  alt={`QR chuyển khoản đơn ${order.id}`}
                  className="w-full aspect-square object-contain"
                />
              </div>
            ) : (
              <div className="aspect-square grid place-items-center text-sm text-muted-foreground">Chưa có QR</div>
            )}
            <p className="text-xs text-muted-foreground mt-3">Quét bằng app ngân hàng Việt Nam hỗ trợ VietQR.</p>
          </div>

          <div className="space-y-3 text-sm">
            <InfoRow label="Mã đơn hàng" value={order.id} />
            <InfoRow label="Số tiền cần chuyển" value={formatCurrency(order.total)} strong />
            <InfoRow label="Ngân hàng" value={order.bank?.bank_name ?? ''} />
            <CopyRow label="Số tài khoản" value={order.bank?.account_number ?? ''} onCopy={() => copy(order.bank?.account_number, 'số tài khoản')} />
            <InfoRow label="Chủ tài khoản" value={order.bank?.account_name ?? ''} />
            <CopyRow label="Nội dung chuyển khoản" value={order.bank_transfer_content ?? ''} onCopy={() => copy(order.bank_transfer_content, 'nội dung chuyển khoản')} strong />

            <div className="rounded-xl bg-blue-50 border border-blue-100 p-4 text-blue-800">
              Vui lòng chuyển đúng số tiền và đúng nội dung để admin xác nhận nhanh hơn.
            </div>

            <div className="flex flex-wrap gap-2 pt-2">
              <Button onClick={markPaid} disabled={submitting || order.payment_status === 'waiting_admin_confirmation' || order.payment_status === 'paid'} className="bg-orange-600 hover:bg-orange-700">
                {submitting ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <CheckCircle className="w-4 h-4 mr-2" />}
                Tôi đã chuyển khoản
              </Button>
              <Button variant="outline" onClick={() => navigate('/account')}>Xem lịch sử đơn hàng</Button>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}

function InfoRow({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-4 border-b pb-2">
      <span className="text-muted-foreground">{label}</span>
      <span className={strong ? 'font-semibold text-orange-600 text-right' : 'font-medium text-right'}>{value || 'Không xác định'}</span>
    </div>
  );
}

function CopyRow({ label, value, onCopy, strong = false }: { label: string; value: string; onCopy: () => void; strong?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b pb-2">
      <span className="text-muted-foreground">{label}</span>
      <button onClick={onCopy} className={`inline-flex items-center gap-2 text-right ${strong ? 'font-semibold text-orange-600' : 'font-medium'}`}>
        {value || 'Không xác định'} <Clipboard className="w-4 h-4" />
      </button>
    </div>
  );
}
