import { useEffect, useMemo, useState } from 'react';
import { Package } from 'lucide-react';
import { toast } from 'sonner';
import { adminService } from '../../services/orderService';
import {
  ORDER_STATUS_COLORS as SHARED_ORDER_STATUS_COLORS,
  ORDER_STATUS_FLOW as SHARED_ORDER_STATUS_FLOW,
  ORDER_STATUS_LABELS as SHARED_ORDER_STATUS_LABELS,
  PAYMENT_STATUS_LABELS as SHARED_PAYMENT_STATUS_LABELS,
} from '../../constants/status';
import { formatCurrency } from '../../utils/formatters';

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

const PAYMENT_STATUS_LABELS: Record<string, string> = {
  cod_pending: 'COD',
  pending_payment: 'Chờ thanh toán',
  waiting_admin_confirmation: 'Chờ admin xác nhận',
  paid: 'Đã thanh toán',
  payment_not_received: 'Chưa nhận được tiền',
};

const STATUS_FLOW = ['pending', 'confirmed', 'shipping', 'delivered'];

type RawAdminOrder = Record<string, any>;

interface AdminOrder {
  id: string;
  customerName: string;
  customerEmail: string;
  status: string;
  total: number;
  paymentMethod: string;
  paymentStatus: string;
  bankTransferContent?: string;
  customerPaidAt?: string | null;
  createdAt: string | null;
  createdAtFormatted?: string | null;
  productCount: number;
}

const parseVietnameseDate = (value: string): Date | null => {
  const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
  if (!match) return null;
  const [, day, month, year, hour = '00', minute = '00'] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatDateSafe = (isoOrFormatted?: string | null, formattedFallback?: string | null) => {
  if (!isoOrFormatted && formattedFallback) return formattedFallback;
  if (!isoOrFormatted) return 'Không xác định';

  const nativeDate = new Date(isoOrFormatted);
  const date = Number.isNaN(nativeDate.getTime()) ? parseVietnameseDate(isoOrFormatted) : nativeDate;
  if (!date) return formattedFallback || 'Không xác định';

  return date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const normalizeOrder = (raw: RawAdminOrder): AdminOrder => {
  const items = raw.items ?? raw.order_items ?? raw.orderDetails ?? raw.products;
  const productCountFromItems = Array.isArray(items)
    ? items.reduce((sum, item) => sum + Number(item.quantity ?? item.so_luong ?? 1), 0)
    : undefined;

  return {
    id: String(raw.id ?? raw.ma_dh ?? ''),
    customerName: raw.customer_name ?? raw.customer ?? raw.khach_hang?.ten_kh ?? 'N/A',
    customerEmail: raw.customer_email ?? raw.email ?? raw.khach_hang?.email ?? '',
    status: raw.status ?? raw.trang_thai ?? 'pending',
    total: Number(raw.total ?? raw.tong_tien ?? 0),
    paymentMethod: raw.payment_method ?? raw.payment ?? raw.phuong_thuc_tt ?? '',
    paymentStatus: raw.payment_status ?? raw.trang_thai_thanh_toan ?? '',
    bankTransferContent: raw.bank_transfer_content ?? raw.noi_dung_chuyen_khoan ?? '',
    customerPaidAt: raw.customer_paid_at ?? raw.khach_bao_da_chuyen_at ?? null,
    createdAt: raw.created_at ?? raw.createdAt ?? raw.order_date ?? raw.ngay_dat ?? raw.date ?? null,
    createdAtFormatted: raw.created_at_formatted ?? raw.date ?? null,
    productCount: Number(raw.total_quantity ?? raw.items_count ?? raw.item_count ?? productCountFromItems ?? 0),
  };
};

export function AdminOrders() {
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterStatus, setFilterStatus] = useState('');
  const [search, setSearch] = useState('');
  const [updating, setUpdating] = useState<string | null>(null);

  const loadOrders = () => {
    setLoading(true);
    adminService.getAdminOrders({
      ...(filterStatus ? { status: filterStatus } : {}),
      ...(search ? { search } : {}),
    })
      .then((data) => setOrders((data?.data ?? []).map(normalizeOrder)))
      .catch(() => setOrders([]))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    const timer = window.setTimeout(loadOrders, 250);
    return () => window.clearTimeout(timer);
  }, [filterStatus, search]);

  const statsText = useMemo(() => `${orders.length} đơn hàng`, [orders.length]);

  const getNextStatus = (current: string) => {
    const index = SHARED_ORDER_STATUS_FLOW.indexOf(current);
    return index >= 0 && index < SHARED_ORDER_STATUS_FLOW.length - 1 ? SHARED_ORDER_STATUS_FLOW[index + 1] : null;
  };

  const handleStatusUpdate = async (orderId: string, newStatus: string) => {
    setUpdating(orderId);
    try {
      await adminService.updateOrderStatus(orderId, newStatus);
      setOrders((previous) => previous.map((order) => order.id === orderId ? { ...order, status: newStatus } : order));
      toast.success('Cập nhật trạng thái thành công.');
    } catch {
      toast.error('Không thể cập nhật trạng thái.');
    } finally {
      setUpdating(null);
    }
  };

  const handlePaymentUpdate = async (orderId: string, paymentStatus: 'paid' | 'payment_not_received') => {
    setUpdating(orderId);
    try {
      await adminService.updateOrderPaymentStatus(orderId, paymentStatus);
      setOrders((previous) => previous.map((order) => order.id === orderId ? { ...order, paymentStatus } : order));
      toast.success(paymentStatus === 'paid' ? 'Đã xác nhận thanh toán.' : 'Đã đánh dấu chưa nhận được tiền.');
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể cập nhật thanh toán.');
    } finally {
      setUpdating(null);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h2 className="text-2xl font-semibold">Quản lý đơn hàng</h2>
        <span className="text-sm text-muted-foreground">{statsText}</span>
      </div>

      <div className="bg-white rounded-xl border border-border p-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Tìm mã đơn, tên khách, nội dung chuyển khoản..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          className="flex-1 min-w-48 px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400"
        />
        <select
          value={filterStatus}
          onChange={(event) => setFilterStatus(event.target.value)}
          className="px-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400 bg-white"
        >
          <option value="">Tất cả trạng thái</option>
          {Object.entries(SHARED_ORDER_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>{label}</option>
          ))}
        </select>
      </div>

      <div className="bg-white rounded-xl border border-border overflow-hidden">
        {loading ? (
          <div className="divide-y divide-border">
            {Array.from({ length: 5 }).map((_, index) => (
              <div key={index} className="p-4 animate-pulse h-20 bg-gray-50" />
            ))}
          </div>
        ) : orders.length === 0 ? (
          <div className="text-center py-16">
            <Package className="w-12 h-12 mx-auto text-gray-300 mb-3" />
            <p className="text-muted-foreground">Không có đơn hàng nào</p>
          </div>
        ) : (
          <div className="divide-y divide-border">
            {orders.map((order) => {
              const nextStatus = getNextStatus(order.status);
              const productCount = Number.isFinite(order.productCount) ? order.productCount : 0;
              const isQrOrder = order.paymentMethod === 'bank_transfer_qr' || order.paymentMethod === 'banking';

              return (
                <div key={order.id} className="p-4 hover:bg-gray-50 transition-colors">
                  <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-sm font-semibold">#{order.id}</span>
                        <span className={`text-xs px-2 py-0.5 rounded-full ${SHARED_ORDER_STATUS_COLORS[order.status] ?? 'bg-gray-100 text-gray-700'}`}>
                          {SHARED_ORDER_STATUS_LABELS[order.status] ?? order.status}
                        </span>
                        <span className="text-xs text-muted-foreground">
                          {order.paymentMethod === 'cod' ? 'COD' : isQrOrder ? 'QR chuyển khoản' : 'Không xác định'}
                        </span>
                        {order.paymentStatus && (
                          <span className="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">
                            {SHARED_PAYMENT_STATUS_LABELS[order.paymentStatus] ?? order.paymentStatus}
                          </span>
                        )}
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">
                        {order.customerName}{order.customerEmail ? ` · ${order.customerEmail}` : ''}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {formatDateSafe(order.createdAt, order.createdAtFormatted)} · {productCount} sản phẩm
                      </p>
                      {isQrOrder && (
                        <p className="text-xs text-muted-foreground mt-1">
                          Nội dung CK: <span className="font-medium">{order.bankTransferContent || 'Chưa có'}</span>
                          {order.customerPaidAt ? ` · Khách báo: ${formatDateSafe(order.customerPaidAt)}` : ''}
                        </p>
                      )}
                    </div>
                    <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                      <span className="text-sm font-bold" style={{ color: '#ea5c21' }}>{formatCurrency(order.total)}</span>
                      {nextStatus && order.status !== 'cancelled' && (
                        <button
                          onClick={() => handleStatusUpdate(order.id, nextStatus)}
                          disabled={updating === order.id}
                          className="text-xs px-3 py-1.5 rounded-lg text-white transition-opacity disabled:opacity-50"
                          style={{ backgroundColor: '#ea5c21' }}
                        >
                          {updating === order.id ? '...' : `→ ${SHARED_ORDER_STATUS_LABELS[nextStatus]}`}
                        </button>
                      )}
                      {order.status === 'pending' && (
                        <button
                          onClick={() => handleStatusUpdate(order.id, 'cancelled')}
                          disabled={updating === order.id}
                          className="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 disabled:opacity-50"
                        >
                          Hủy
                        </button>
                      )}
                      {isQrOrder && order.paymentStatus === 'waiting_admin_confirmation' && (
                        <>
                          <button
                            onClick={() => handlePaymentUpdate(order.id, 'paid')}
                            disabled={updating === order.id}
                            className="text-xs px-3 py-1.5 rounded-lg border border-green-200 text-green-600 hover:bg-green-50 disabled:opacity-50"
                          >
                            Đã nhận tiền
                          </button>
                          <button
                            onClick={() => handlePaymentUpdate(order.id, 'payment_not_received')}
                            disabled={updating === order.id}
                            className="text-xs px-3 py-1.5 rounded-lg border border-yellow-200 text-yellow-700 hover:bg-yellow-50 disabled:opacity-50"
                          >
                            Chưa nhận tiền
                          </button>
                        </>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
