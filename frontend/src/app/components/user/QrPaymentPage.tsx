import { useCallback, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { useLocation, useNavigate, useParams, useSearchParams } from 'react-router';
import { ArrowLeft, CheckCircle, Clipboard, ExternalLink, Loader2, QrCode, RefreshCcw, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../../store/AppContext';
import { orderService, type ApiOrder } from '../../services/orderService';
import { Button } from '../ui/button';
import { PAYMENT_STATUS_LABELS } from '../../constants/status';
import { formatCurrency } from '../../utils/formatters';

const FAILURE_STATUSES = new Set(['failed', 'cancelled', 'expired', 'payment_not_received']);

export function QrPaymentPage() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, isLoading: authLoading } = useAuth();
  const [order, setOrder] = useState<ApiOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const payosResult = searchParams.get('payosResult');
  const isPaid = order?.payment_status === 'paid';
  const isPending = order?.payment_status === 'pending_payment';
  const isCancelReturn = payosResult === 'cancel' && isPending;
  const isConfirmingReturn = payosResult === 'return' && isPending;
  const isFailure = Boolean(order?.payment_status && FAILURE_STATUSES.has(order.payment_status));
  const shouldShowQr = Boolean(isPending && !isCancelReturn && !isConfirmingReturn);
  const isPayos = order?.payment_provider === 'payos';

  const qrIsImage = useMemo(() => {
    const value = order?.qr_code_url ?? '';
    return value.startsWith('http://') || value.startsWith('https://') || value.startsWith('data:image/');
  }, [order?.qr_code_url]);

  const loadOrder = useCallback(async (silent = false) => {
    if (!id) return;
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }

    try {
      setOrder(await orderService.getOrder(id));
    } catch (error: any) {
      if (error.response?.status === 401) {
        navigate('/login', { replace: true, state: { from: `${location.pathname}${location.search}` } });
      } else {
        toast.error('Không thể tải thông tin thanh toán.');
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, location.pathname, location.search, navigate]);

  useEffect(() => {
    if (authLoading) return;
    if (!isAuthenticated) {
      navigate('/login', { replace: true, state: { from: `${location.pathname}${location.search}` } });
      return;
    }
    loadOrder();
  }, [authLoading, isAuthenticated, loadOrder, location.pathname, location.search, navigate]);

  useEffect(() => {
    if (!id || !order || !isPending) return;

    const timer = window.setInterval(() => loadOrder(true), 4000);
    return () => window.clearInterval(timer);
  }, [id, isPending, loadOrder, order]);

  useEffect(() => {
    if (!order || !isPaid) return;

    toast.success('Thanh toán đã được xác nhận.');
    const timer = window.setTimeout(() => {
      navigate(`/account/orders/${order.id}`, { replace: true, state: { paymentSuccess: true } });
    }, 800);

    return () => window.clearTimeout(timer);
  }, [isPaid, navigate, order]);

  useEffect(() => {
    if (!order || order.payment_provider !== 'payos') return;

    const target = order.payos_order_code
      ? `/payment/payos/return?orderCode=${order.payos_order_code}`
      : `/account/orders/${order.id}`;
    navigate(target, { replace: true });
  }, [navigate, order]);

  const copy = async (value?: string | null, label = 'thông tin') => {
    if (!value) return;
    await navigator.clipboard.writeText(value);
    toast.success(`Đã sao chép ${label}.`);
  };

  const openCheckout = () => {
    if (!order?.payment_checkout_url) return;
    window.location.assign(order.payment_checkout_url);
  };

  if (authLoading || loading) {
    return <div className="max-w-3xl mx-auto px-4 py-20 text-center">Đang tải thông tin thanh toán...</div>;
  }

  if (order?.payment_provider === 'payos') {
    return <div className="max-w-3xl mx-auto px-4 py-20 text-center">Đang chuyển sang trang trạng thái payOS...</div>;
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
        <ArrowLeft className="w-4 h-4" />
        Quay lại chi tiết đơn hàng
      </button>

      <section className="bg-white rounded-2xl border p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-6">
          <div className="flex items-start gap-3">
            <div className={`w-10 h-10 rounded-xl grid place-items-center ${isCancelReturn || isFailure ? 'bg-red-100 text-red-600' : 'bg-orange-100 text-orange-600'}`}>
              {isPaid ? <CheckCircle className="w-5 h-5" /> : isCancelReturn || isFailure ? <XCircle className="w-5 h-5" /> : <QrCode className="w-5 h-5" />}
            </div>
            <div>
              <h1 className="text-2xl font-semibold">Thanh toán VietQR qua payOS</h1>
              <p className="text-sm text-muted-foreground">
                Đơn hàng #{order.id} · {PAYMENT_STATUS_LABELS[order.payment_status ?? ''] ?? order.payment_status}
              </p>
            </div>
          </div>
          <Button type="button" variant="outline" onClick={() => loadOrder(true)} disabled={refreshing}>
            {refreshing ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <RefreshCcw className="w-4 h-4 mr-2" />}
            Cập nhật
          </Button>
        </div>

        <div className="grid md:grid-cols-[280px,1fr] gap-6">
          <div className="rounded-2xl border bg-gray-50 p-4 text-center">
            {shouldShowQr && qrIsImage ? (
              <div className="mx-auto w-[220px] max-w-full rounded-xl bg-white p-3">
                <img
                  src={order.qr_code_url ?? ''}
                  alt={`QR thanh toán đơn ${order.id}`}
                  className="w-full aspect-square object-contain"
                />
              </div>
            ) : (
              <div className="aspect-square rounded-xl bg-white border grid place-items-center px-4 text-sm text-muted-foreground">
                {isCancelReturn || isFailure ? 'Thanh toán chưa hoàn tất.' : isConfirmingReturn ? 'Đang xác nhận thanh toán từ payOS.' : 'Mở link payOS để thanh toán an toàn.'}
              </div>
            )}
            <p className="text-xs text-muted-foreground mt-3">
              Trạng thái được cập nhật tự động sau khi payOS gửi webhook hợp lệ.
            </p>
          </div>

          <div className="space-y-3 text-sm">
            {isConfirmingReturn && (
              <StatusBox tone="blue">
                payOS đã đưa bạn về website. Hệ thống đang chờ webhook xác nhận từ payOS, trang này sẽ tự cập nhật sau vài giây.
              </StatusBox>
            )}
            {shouldShowQr && (
              <StatusBox tone="blue">
                Vui lòng thanh toán đúng số tiền trên payOS. Website không tự đánh dấu đã thanh toán nếu chưa có webhook hợp lệ.
              </StatusBox>
            )}
            {isCancelReturn && (
              <StatusBox tone="red">
                Bạn đã hủy thanh toán trên payOS. Đơn hàng chưa bị đánh dấu đã thanh toán, bạn có thể thanh toán lại hoặc quay về chi tiết đơn.
              </StatusBox>
            )}
            {isFailure && !isCancelReturn && (
              <StatusBox tone="red">
                Thanh toán chưa hoàn tất. Bạn có thể thử thanh toán lại nếu link payOS còn hiệu lực.
              </StatusBox>
            )}
            {isPaid && (
              <StatusBox tone="green">
                Đơn hàng đã được ghi nhận thanh toán thành công. Đang chuyển về chi tiết đơn hàng...
              </StatusBox>
            )}

            <InfoRow label="Mã đơn hàng" value={order.id} />
            <InfoRow label="Mã thanh toán payOS" value={order.payos_order_code ? String(order.payos_order_code) : ''} />
            <InfoRow label="Số tiền cần thanh toán" value={formatCurrency(order.total)} strong />
            <CopyRow label="Nội dung thanh toán" value={order.bank_transfer_content ?? ''} onCopy={() => copy(order.bank_transfer_content, 'nội dung thanh toán')} />
            {shouldShowQr && !qrIsImage && order.qr_code_url && (
              <CopyRow label="Mã QR payOS" value={order.qr_code_url} onCopy={() => copy(order.qr_code_url, 'mã QR payOS')} truncate />
            )}

            <div className="flex flex-wrap gap-2 pt-2">
              {order.payment_checkout_url && !isPaid && (
                <Button type="button" onClick={openCheckout} className="bg-orange-600 hover:bg-orange-700">
                  <ExternalLink className="w-4 h-4 mr-2" />
                  {isCancelReturn || isFailure ? 'Thanh toán lại' : 'Mở trang thanh toán payOS'}
                </Button>
              )}
              <Button variant="outline" onClick={() => navigate(`/account/orders/${order.id}`)}>Xem chi tiết đơn</Button>
              <Button variant="outline" onClick={() => navigate('/account')}>Xem lịch sử đơn hàng</Button>
            </div>

            {!isPayos && (
              <p className="text-xs text-muted-foreground">
                Đơn QR này không có provider payOS. Vui lòng liên hệ cửa hàng nếu cần kiểm tra thanh toán.
              </p>
            )}
          </div>
        </div>
      </section>
    </div>
  );
}

function StatusBox({ tone, children }: { tone: 'blue' | 'green' | 'red'; children: ReactNode }) {
  const className = tone === 'green'
    ? 'rounded-xl bg-green-50 border border-green-100 p-4 text-green-700'
    : tone === 'red'
      ? 'rounded-xl bg-red-50 border border-red-100 p-4 text-red-700'
      : 'rounded-xl bg-blue-50 border border-blue-100 p-4 text-blue-800';

  return <div className={className}>{children}</div>;
}

function InfoRow({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex justify-between gap-4 border-b pb-2">
      <span className="text-muted-foreground">{label}</span>
      <span className={strong ? 'font-semibold text-orange-600 text-right' : 'font-medium text-right'}>{value || 'Không xác định'}</span>
    </div>
  );
}

function CopyRow({ label, value, onCopy, truncate = false }: { label: string; value: string; onCopy: () => void; truncate?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b pb-2">
      <span className="text-muted-foreground shrink-0">{label}</span>
      <button onClick={onCopy} className="inline-flex items-center justify-end gap-2 text-right font-medium min-w-0">
        <span className={truncate ? 'truncate max-w-[220px] sm:max-w-[360px]' : ''}>{value || 'Không xác định'}</span>
        <Clipboard className="w-4 h-4 shrink-0" />
      </button>
    </div>
  );
}
