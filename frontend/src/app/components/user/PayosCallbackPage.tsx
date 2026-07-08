import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router';
import { CheckCircle, Loader2, RefreshCcw, XCircle } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../../store/AppContext';
import { orderService, type ApiOrder } from '../../services/orderService';
import { Button } from '../ui/button';
import { PAYMENT_STATUS_LABELS } from '../../constants/status';
import { formatCurrency } from '../../utils/formatters';

export function PayosCallbackPage({ type }: { type: 'return' | 'cancel' }) {
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const { isAuthenticated, isLoading: authLoading } = useAuth();
  const orderCode = searchParams.get('orderCode') ?? '';
  const [order, setOrder] = useState<ApiOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadStatus = useCallback(async (silent = false) => {
    if (!orderCode) {
      setLoading(false);
      return;
    }

    silent ? setRefreshing(true) : setLoading(true);
    try {
      setOrder(await orderService.getPayosStatus(orderCode));
    } catch (error: any) {
      if (error.response?.status === 401) {
        navigate('/login', { replace: true, state: { from: `${location.pathname}${location.search}` } });
        return;
      }
      toast.error('Không thể kiểm tra trạng thái thanh toán payOS.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [location.pathname, location.search, navigate, orderCode]);

  useEffect(() => {
    if (authLoading) return;
    if (!isAuthenticated) {
      navigate('/login', { replace: true, state: { from: `${location.pathname}${location.search}` } });
      return;
    }
    loadStatus();
  }, [authLoading, isAuthenticated, loadStatus, location.pathname, location.search, navigate]);

  useEffect(() => {
    if (!order || order.payment_status !== 'pending_payment') return;

    const timer = window.setInterval(() => loadStatus(true), 4000);
    return () => window.clearInterval(timer);
  }, [loadStatus, order]);

  useEffect(() => {
    if (order?.payment_status !== 'paid') return;

    const timer = window.setTimeout(() => {
      navigate(`/account/orders/${order.id}`, { replace: true, state: { paymentSuccess: true } });
    }, 900);
    return () => window.clearTimeout(timer);
  }, [navigate, order]);

  const isPaid = order?.payment_status === 'paid';
  const isPending = order?.payment_status === 'pending_payment';
  const isCancel = type === 'cancel' && !isPaid;

  if (authLoading || loading) {
    return <div className="max-w-3xl mx-auto px-4 py-20 text-center">Đang kiểm tra thanh toán payOS...</div>;
  }

  if (!orderCode) {
    return (
      <PaymentShell tone="red" title="Thiếu mã thanh toán" message="Không tìm thấy orderCode trong đường dẫn trả về từ payOS.">
        <Button onClick={() => navigate('/account')}>Về đơn mua</Button>
      </PaymentShell>
    );
  }

  if (!order) {
    return (
      <PaymentShell tone="red" title="Không thể kiểm tra đơn hàng" message="Vui lòng mở lại lịch sử đơn hàng để kiểm tra trạng thái.">
        <Button onClick={() => navigate('/account')}>Về đơn mua</Button>
      </PaymentShell>
    );
  }

  return (
    <PaymentShell
      tone={isPaid ? 'green' : isCancel ? 'red' : 'blue'}
      title={isPaid ? 'Thanh toán đã được xác nhận' : isCancel ? 'Thanh toán chưa hoàn tất' : 'Đang xác nhận thanh toán'}
      message={isPaid
        ? 'payOS đã xác nhận giao dịch. Website đang chuyển bạn về chi tiết đơn hàng.'
        : isCancel
          ? 'Bạn đã hủy thanh toán trên payOS hoặc giao dịch chưa hoàn tất. Đơn hàng chưa bị đánh dấu đã thanh toán.'
          : 'Website đang hỏi lại payOS và chờ webhook xác nhận. Trang này sẽ tự cập nhật sau vài giây.'}
    >
      <div className="rounded-xl bg-white border p-4 text-sm space-y-2 text-left">
        <InfoRow label="Mã đơn hàng" value={order.id} />
        <InfoRow label="Mã thanh toán payOS" value={String(order.payos_order_code ?? orderCode)} />
        <InfoRow label="Số tiền" value={formatCurrency(order.total)} />
        <InfoRow label="Trạng thái" value={PAYMENT_STATUS_LABELS[order.payment_status ?? ''] ?? order.payment_status ?? 'Không xác định'} />
      </div>
      <div className="flex flex-wrap justify-center gap-2">
        {order.payment_checkout_url && !isPaid && (
          <Button onClick={() => { window.location.href = order.payment_checkout_url ?? ''; }} className="bg-orange-600 hover:bg-orange-700">
            Thanh toán lại
          </Button>
        )}
        {isPending && (
          <Button variant="outline" onClick={() => loadStatus(true)} disabled={refreshing}>
            {refreshing ? <Loader2 className="w-4 h-4 mr-2 animate-spin" /> : <RefreshCcw className="w-4 h-4 mr-2" />}
            Cập nhật
          </Button>
        )}
        <Button variant="outline" onClick={() => navigate(`/account/orders/${order.id}`)}>Xem chi tiết đơn</Button>
      </div>
    </PaymentShell>
  );
}

export function PayosReturnPage() {
  return <PayosCallbackPage type="return" />;
}

export function PayosCancelPage() {
  return <PayosCallbackPage type="cancel" />;
}

function PaymentShell({ tone, title, message, children }: { tone: 'blue' | 'green' | 'red'; title: string; message: string; children: ReactNode }) {
  const Icon = tone === 'green' ? CheckCircle : tone === 'red' ? XCircle : Loader2;
  const color = tone === 'green' ? 'text-green-600 bg-green-50' : tone === 'red' ? 'text-red-600 bg-red-50' : 'text-blue-600 bg-blue-50';

  return (
    <div className="max-w-2xl mx-auto px-4 py-16">
      <section className="bg-white rounded-2xl border p-8 text-center space-y-5">
        <div className={`mx-auto w-14 h-14 rounded-2xl grid place-items-center ${color}`}>
          <Icon className={`w-7 h-7 ${tone === 'blue' ? 'animate-spin' : ''}`} />
        </div>
        <div>
          <h1 className="text-2xl font-semibold">{title}</h1>
          <p className="text-sm text-muted-foreground mt-2">{message}</p>
        </div>
        {children}
      </section>
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value?: string | null }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-medium text-right">{value || 'Không xác định'}</span>
    </div>
  );
}
