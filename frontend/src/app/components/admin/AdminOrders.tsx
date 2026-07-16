import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  CheckCircle2,
  ClipboardCheck,
  Eye,
  Loader2,
  Package,
  PackageCheck,
  PackageX,
  RefreshCw,
  RotateCcw,
  Truck,
  XCircle,
} from 'lucide-react';
import { toast } from 'sonner';
import { adminService, type ShippingTracking } from '../../services/orderService';
import {
  ORDER_STATUS_COLORS,
  ORDER_STATUS_LABELS,
  PAYMENT_STATUS_LABELS,
  SHIPPING_STATUS_COLORS,
  SHIPPING_STATUS_LABELS,
} from '../../constants/status';
import { formatCurrency } from '../../utils/formatters';
import { Button } from '../ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../ui/dialog';

type RawAdminOrder = Record<string, unknown>;

interface AdminOrder {
  id: string;
  customerName: string;
  customerEmail: string;
  status: string;
  total: number;
  paymentMethod: string;
  paymentProvider?: string | null;
  paymentStatus: string;
  bankTransferContent?: string;
  customerPaidAt?: string | null;
  lastProcessedBy?: string | null;
  lastProcessedAt?: string | null;
  createdAt: string | null;
  createdAtFormatted?: string | null;
  productCount: number;
  shipping: ShippingTracking;
}

interface AdminOrderDetail extends AdminOrder {
  subtotal?: number;
  discount?: number;
  note?: string | null;
  shippingInfo?: { name?: string; phone?: string; address?: string };
  items?: Array<{ variant_id: string; sku?: string | null; product?: { name?: string | null }; quantity: number; price: number }>;
  internalHistory?: Array<{
    id: string;
    from_status?: string | null;
    to_status: string;
    source?: string;
    actor?: string | null;
    at?: string | null;
    note?: string | null;
  }>;
}

const legacyShipping: ShippingTracking = {
  mode: 'legacy',
  provider: 'ghn',
  tracking_code: null,
  status: null,
  events: [],
};

const parseVietnameseDate = (value: string): Date | null => {
  const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
  if (!match) return null;
  const [, day, month, year, hour = '00', minute = '00'] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatDateSafe = (isoOrFormatted?: string | null, formattedFallback?: string | null) => {
  if (!isoOrFormatted && formattedFallback) return formattedFallback;
  if (!isoOrFormatted) return 'Chưa có dữ liệu';
  const nativeDate = new Date(isoOrFormatted);
  const date = Number.isNaN(nativeDate.getTime()) ? parseVietnameseDate(isoOrFormatted) : nativeDate;
  if (!date) return formattedFallback || 'Chưa có dữ liệu';
  return date.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const normalizeShipping = (value: unknown): ShippingTracking => {
  if (!value || typeof value !== 'object') return legacyShipping;
  const shipping = value as Partial<ShippingTracking>;
  return {
    ...legacyShipping,
    ...shipping,
    events: Array.isArray(shipping.events) ? shipping.events : [],
  };
};

const normalizeOrder = (raw: RawAdminOrder): AdminOrder => {
  const items = raw.items ?? raw.order_items ?? raw.orderDetails ?? raw.products;
  const productCountFromItems = Array.isArray(items)
    ? items.reduce((sum, item) => sum + Number((item as Record<string, unknown>).quantity ?? (item as Record<string, unknown>).so_luong ?? 1), 0)
    : undefined;

  return {
    id: String(raw.id ?? raw.ma_dh ?? ''),
    customerName: String(raw.customer_name ?? raw.customer ?? 'N/A'),
    customerEmail: String(raw.customer_email ?? raw.email ?? ''),
    status: String(raw.status ?? raw.trang_thai ?? 'pending'),
    total: Number(raw.total ?? raw.tong_tien ?? 0),
    paymentMethod: String(raw.payment_method ?? raw.payment ?? raw.phuong_thuc_tt ?? ''),
    paymentProvider: typeof raw.payment_provider === 'string' ? raw.payment_provider : null,
    paymentStatus: String(raw.payment_status ?? raw.trang_thai_thanh_toan ?? ''),
    bankTransferContent: typeof raw.bank_transfer_content === 'string' ? raw.bank_transfer_content : '',
    customerPaidAt: typeof raw.customer_paid_at === 'string' ? raw.customer_paid_at : null,
    lastProcessedBy: typeof raw.last_processed_by === 'string' ? raw.last_processed_by : null,
    lastProcessedAt: typeof raw.last_processed_at === 'string' ? raw.last_processed_at : null,
    createdAt: typeof raw.created_at === 'string' ? raw.created_at : null,
    createdAtFormatted: typeof raw.created_at_formatted === 'string' ? raw.created_at_formatted : (typeof raw.date === 'string' ? raw.date : null),
    productCount: Number(raw.total_quantity ?? raw.items_count ?? raw.item_count ?? productCountFromItems ?? 0),
    shipping: normalizeShipping(raw.shipping),
  };
};

const normalizeDetail = (raw: RawAdminOrder): AdminOrderDetail => {
  const order = normalizeOrder(raw);
  const shippingInfo = raw.shipping_info as Record<string, unknown> | undefined;
  const history = raw.internal_history;
  return {
    ...order,
    subtotal: Number(raw.subtotal ?? 0),
    discount: Number(raw.discount ?? 0),
    note: typeof raw.note === 'string' ? raw.note : null,
    shippingInfo: shippingInfo ? {
      name: typeof shippingInfo.name === 'string' ? shippingInfo.name : '',
      phone: typeof shippingInfo.phone === 'string' ? shippingInfo.phone : '',
      address: typeof shippingInfo.address === 'string' ? shippingInfo.address : '',
    } : undefined,
    items: Array.isArray(raw.items) ? raw.items as AdminOrderDetail['items'] : [],
    internalHistory: Array.isArray(history) ? history as AdminOrderDetail['internalHistory'] : [],
  };
};

function ShippingBadge({ shipping }: { shipping: ShippingTracking }) {
  if (!shipping.tracking_code) return null;
  const status = shipping.status ?? 'unknown';
  return (
    <span className={`text-xs px-2 py-0.5 rounded-full ${SHIPPING_STATUS_COLORS[status] ?? 'bg-gray-100 text-gray-700'}`}>
      GHN: {SHIPPING_STATUS_LABELS[status] ?? status}
    </span>
  );
}

export function AdminOrders() {
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterStatus, setFilterStatus] = useState('');
  const [search, setSearch] = useState('');
  const [updating, setUpdating] = useState<string | null>(null);
  const [selectedOrderId, setSelectedOrderId] = useState<string | null>(null);
  const [selectedOrder, setSelectedOrder] = useState<AdminOrderDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const loadOrders = useCallback(() => {
    setLoading(true);
    adminService.getAdminOrders({
      ...(filterStatus ? { status: filterStatus } : {}),
      ...(search ? { search } : {}),
    })
      .then((data) => setOrders((data?.data ?? []).map(normalizeOrder)))
      .catch(() => setOrders([]))
      .finally(() => setLoading(false));
  }, [filterStatus, search]);

  const loadDetail = useCallback(async (orderId: string) => {
    setSelectedOrderId(orderId);
    setSelectedOrder(null);
    setDetailLoading(true);
    try {
      const data = await adminService.getAdminOrder(orderId);
      setSelectedOrder(normalizeDetail(data));
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể tải chi tiết đơn hàng.');
      setSelectedOrderId(null);
    } finally {
      setDetailLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(loadOrders, 250);
    return () => window.clearTimeout(timer);
  }, [loadOrders]);

  const refreshAfterAction = async (orderId: string) => {
    loadOrders();
    if (selectedOrderId === orderId) await loadDetail(orderId);
  };

  const runAction = async (orderId: string, label: string, action: () => Promise<unknown>) => {
    setUpdating(`${orderId}:${label}`);
    try {
      const response = await action() as { message?: string };
      toast.success(response?.message ?? 'Đã cập nhật đơn hàng.');
      await refreshAfterAction(orderId);
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể thực hiện thao tác này.');
    } finally {
      setUpdating(null);
    }
  };

  const handleCancelCarrier = (orderId: string) => {
    if (!window.confirm('Gửi yêu cầu hủy vận đơn đến GHN? Tồn kho sẽ không tự động hoàn.')) return;
    return runAction(orderId, 'cancel-ghn', () => adminService.requestGhnShipmentCancellation(orderId));
  };

  const handleFinalizeCancellation = (orderId: string) => {
    if (!window.confirm('Xác nhận hàng chưa rời kho và hoàn tồn cho đơn này?')) return;
    return runAction(orderId, 'cancel-order', () => adminService.updateOrderStatus(orderId, 'cancelled', { confirmStockReturn: true }));
  };

  const statsText = useMemo(() => `${orders.length} đơn hàng`, [orders.length]);

  const renderActions = (order: AdminOrder) => {
    const busy = updating?.startsWith(`${order.id}:`);
    const isPayosWaitingPayment = order.paymentProvider === 'payos' && order.paymentStatus !== 'paid';
    const isManualTransfer = order.paymentMethod === 'bank_transfer_qr' && order.paymentProvider !== 'payos';
    const carrierTerminal = ['delivered', 'cancelled', 'returned'].includes(order.shipping.status ?? '');
    const canCancelInternally = ['pending', 'confirmed', 'preparing', 'ready_to_ship'].includes(order.status) && !order.shipping.tracking_code;

    return (
      <div className="flex items-center gap-2 shrink-0 flex-wrap justify-end">
        <Button variant="outline" size="sm" onClick={() => loadDetail(order.id)} title="Xem chi tiết đơn hàng">
          <Eye className="w-4 h-4" />
          <span className="hidden sm:inline">Chi tiết</span>
        </Button>
        {isPayosWaitingPayment && <span className="text-xs px-2.5 py-1.5 rounded-md bg-blue-50 text-blue-700">Chờ payOS xác nhận</span>}
        {order.status === 'pending' && !isPayosWaitingPayment && (
          <Button size="sm" disabled={busy} onClick={() => runAction(order.id, 'confirm', () => adminService.updateOrderStatus(order.id, 'confirmed'))} className="bg-orange-600 hover:bg-orange-700">
            {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : <ClipboardCheck className="w-4 h-4" />} Xác nhận
          </Button>
        )}
        {order.status === 'confirmed' && !isPayosWaitingPayment && (
          <Button size="sm" disabled={busy} onClick={() => runAction(order.id, 'prepare', () => adminService.updateOrderStatus(order.id, 'preparing'))} className="bg-orange-600 hover:bg-orange-700">
            {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : <PackageCheck className="w-4 h-4" />} Chuẩn bị hàng
          </Button>
        )}
        {['preparing', 'ready_to_ship'].includes(order.status) && !isPayosWaitingPayment && !order.shipping.tracking_code && order.shipping.creation_state !== 'cho_xac_minh' && (
          <Button size="sm" disabled={busy} onClick={() => runAction(order.id, order.shipping.creation_state === 'that_bai' ? 'retry' : 'handoff', () => (
            order.shipping.creation_state === 'that_bai'
              ? adminService.retryGhnShipment(order.id)
              : adminService.handoffOrderToGhn(order.id)
          ))} className="bg-orange-600 hover:bg-orange-700">
            {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : order.shipping.creation_state === 'that_bai' ? <RotateCcw className="w-4 h-4" /> : <Truck className="w-4 h-4" />}
            {order.shipping.creation_state === 'that_bai' ? 'Tạo lại GHN' : 'Bàn giao GHN'}
          </Button>
        )}
        {order.shipping.creation_state === 'cho_xac_minh' && (
          <span className="text-xs px-2.5 py-1.5 rounded-md bg-amber-50 text-amber-800">Cần xác minh GHN</span>
        )}
        {order.shipping.tracking_code && !carrierTerminal && (
          <Button variant="outline" size="sm" disabled={busy} onClick={() => runAction(order.id, 'sync', () => adminService.syncGhnShipment(order.id))} title="Lấy trạng thái mới nhất từ GHN">
            {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />} Đồng bộ GHN
          </Button>
        )}
        {order.shipping.tracking_code && !carrierTerminal && order.status !== 'cancelled' && (
          <Button variant="outline" size="sm" disabled={busy} onClick={() => handleCancelCarrier(order.id)} className="border-red-200 text-red-700 hover:bg-red-50">
            <XCircle className="w-4 h-4" /> Hủy GHN
          </Button>
        )}
        {order.shipping.status === 'cancelled' && order.status !== 'cancelled' && (
          <Button variant="outline" size="sm" disabled={busy} onClick={() => handleFinalizeCancellation(order.id)} className="border-amber-200 text-amber-800 hover:bg-amber-50">
            <PackageX className="w-4 h-4" /> Hủy & hoàn tồn
          </Button>
        )}
        {canCancelInternally && (
          <Button variant="outline" size="sm" disabled={busy} onClick={() => runAction(order.id, 'cancel-order', () => adminService.updateOrderStatus(order.id, 'cancelled'))} className="border-red-200 text-red-700 hover:bg-red-50">
            <XCircle className="w-4 h-4" /> Hủy đơn
          </Button>
        )}
        {isManualTransfer && order.paymentStatus === 'waiting_admin_confirmation' && (
          <>
            <Button variant="outline" size="sm" disabled={busy} onClick={() => runAction(order.id, 'paid', () => adminService.updateOrderPaymentStatus(order.id, 'paid'))} className="border-green-200 text-green-700 hover:bg-green-50">
              <CheckCircle2 className="w-4 h-4" /> Đã nhận tiền
            </Button>
            <Button variant="outline" size="sm" disabled={busy} onClick={() => runAction(order.id, 'unpaid', () => adminService.updateOrderPaymentStatus(order.id, 'payment_not_received'))}>
              Chưa nhận tiền
            </Button>
          </>
        )}
      </div>
    );
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h2 className="text-2xl font-semibold">Quản lý đơn hàng</h2>
        </div>
        <span className="text-sm text-muted-foreground">{statsText}</span>
      </div>

      <div className="bg-white border border-border rounded-lg p-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Tìm mã đơn, mã vận đơn, tên khách..."
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          className="flex-1 min-w-56 px-3 py-2 text-sm border border-border rounded-md focus:outline-none focus:border-orange-400"
        />
        <select value={filterStatus} onChange={(event) => setFilterStatus(event.target.value)} className="px-3 py-2 text-sm border border-border rounded-md focus:outline-none focus:border-orange-400 bg-white">
          <option value="">Tất cả trạng thái</option>
          {Object.entries(ORDER_STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      </div>

      <div className="bg-white rounded-lg border border-border overflow-hidden">
        {loading ? (
          <div className="divide-y divide-border">{Array.from({ length: 5 }).map((_, index) => <div key={index} className="p-4 animate-pulse h-24 bg-gray-50" />)}</div>
        ) : orders.length === 0 ? (
          <div className="text-center py-16"><Package className="w-12 h-12 mx-auto text-gray-300 mb-3" /><p className="text-muted-foreground">Không có đơn hàng nào</p></div>
        ) : (
          <div className="divide-y divide-border">
            {orders.map((order) => (
              <div key={order.id} className="p-4 hover:bg-gray-50 transition-colors">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-semibold">#{order.id}</span>
                      <span className={`text-xs px-2 py-0.5 rounded-full ${ORDER_STATUS_COLORS[order.status] ?? 'bg-gray-100 text-gray-700'}`}>{ORDER_STATUS_LABELS[order.status] ?? order.status}</span>
                      <ShippingBadge shipping={order.shipping} />
                      {order.paymentStatus && <span className="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{PAYMENT_STATUS_LABELS[order.paymentStatus] ?? order.paymentStatus}</span>}
                    </div>
                    <p className="text-xs text-muted-foreground mt-1">{order.customerName}{order.customerEmail ? ` · ${order.customerEmail}` : ''}</p>
                    <p className="text-xs text-muted-foreground">{formatDateSafe(order.createdAt, order.createdAtFormatted)} · {order.productCount} sản phẩm</p>
                    {order.shipping.tracking_code && <p className="text-xs text-muted-foreground mt-1">Mã vận đơn GHN: <span className="font-medium text-foreground">{order.shipping.tracking_code}</span></p>}
                    {order.shipping.last_error && <p className="text-xs text-red-600 mt-1">GHN: {order.shipping.last_error}</p>}
                    {order.lastProcessedBy && <p className="text-xs text-muted-foreground mt-1">Xử lý nội bộ gần nhất: <span className="font-medium">{order.lastProcessedBy}</span>{order.lastProcessedAt ? ` · ${formatDateSafe(order.lastProcessedAt)}` : ''}</p>}
                  </div>
                  <div className="flex flex-col items-end gap-2 max-w-full">
                    <span className="text-sm font-bold" style={{ color: '#ea5c21' }}>{formatCurrency(order.total)}</span>
                    {renderActions(order)}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <Dialog open={Boolean(selectedOrderId)} onOpenChange={(open) => { if (!open) { setSelectedOrderId(null); setSelectedOrder(null); } }}>
        <DialogContent className="max-w-3xl max-h-[88vh] overflow-y-auto p-0 gap-0">
          {detailLoading || !selectedOrder ? (
            <div className="p-8 text-sm text-muted-foreground flex items-center gap-2"><Loader2 className="w-4 h-4 animate-spin" />Đang tải chi tiết đơn hàng...</div>
          ) : (
            <>
              <DialogHeader className="p-5 border-b pr-12">
                <DialogTitle>Đơn hàng #{selectedOrder.id}</DialogTitle>
                <DialogDescription>{selectedOrder.customerName} · {formatDateSafe(selectedOrder.createdAt, selectedOrder.createdAtFormatted)}</DialogDescription>
              </DialogHeader>
              <div className="p-5 space-y-6">
                <section className="grid md:grid-cols-2 gap-4 text-sm">
                  <div className="border rounded-lg p-4">
                    <p className="font-medium mb-2">Người nhận</p>
                    <p>{selectedOrder.shippingInfo?.name || selectedOrder.customerName}</p>
                    <p className="text-muted-foreground">{selectedOrder.shippingInfo?.phone}</p>
                    <p className="text-muted-foreground mt-1">{selectedOrder.shippingInfo?.address}</p>
                  </div>
                  <div className="border rounded-lg p-4">
                    <p className="font-medium mb-2">Thanh toán</p>
                    <p>{selectedOrder.paymentMethod === 'cod' ? 'COD' : selectedOrder.paymentProvider === 'payos' ? 'payOS' : 'Chuyển khoản QR'}</p>
                    <p className="text-muted-foreground mt-1">{PAYMENT_STATUS_LABELS[selectedOrder.paymentStatus] ?? selectedOrder.paymentStatus}</p>
                    {selectedOrder.note && <p className="text-muted-foreground mt-2">Ghi chú: {selectedOrder.note}</p>}
                  </div>
                </section>

                <section>
                  <div className="flex items-center justify-between gap-3 mb-3"><h3 className="font-semibold">Vận chuyển GHN</h3><ShippingBadge shipping={selectedOrder.shipping} /></div>
                  {selectedOrder.shipping.tracking_code ? (
                    <div className="border rounded-lg divide-y text-sm">
                      <div className="p-4 grid sm:grid-cols-2 gap-x-5 gap-y-2">
                        <p><span className="text-muted-foreground">Mã vận đơn:</span> <span className="font-medium">{selectedOrder.shipping.tracking_code}</span></p>
                        <p><span className="text-muted-foreground">Cập nhật:</span> {formatDateSafe(selectedOrder.shipping.status_updated_at)}</p>
                        <p><span className="text-muted-foreground">Phí GHN:</span> {selectedOrder.shipping.shipping_fee === null || selectedOrder.shipping.shipping_fee === undefined ? 'Chưa có' : formatCurrency(selectedOrder.shipping.shipping_fee)}</p>
                        <p><span className="text-muted-foreground">Dự kiến:</span> {formatDateSafe(selectedOrder.shipping.expected_delivery_at)}</p>
                      </div>
                      {selectedOrder.shipping.last_error && <p className="p-4 text-red-700 bg-red-50">{selectedOrder.shipping.last_error}</p>}
                      <div className="p-4">
                        <p className="font-medium mb-3">Lịch sử GHN</p>
                        {selectedOrder.shipping.events.length ? <ol className="space-y-3 border-l pl-4 ml-1">{selectedOrder.shipping.events.map((event, index) => <li key={`${event.id ?? index}-${event.at}`} className="relative"><span className="absolute -left-[21px] top-1.5 w-2 h-2 rounded-full bg-orange-500" /><p>{SHIPPING_STATUS_LABELS[event.status ?? 'unknown'] ?? event.status ?? 'Đang cập nhật'}</p><p className="text-xs text-muted-foreground">{formatDateSafe(event.at)}{event.note ? ` · ${event.note}` : ''}</p></li>)}</ol> : <p className="text-muted-foreground">Chưa có sự kiện GHN.</p>}
                      </div>
                    </div>
                  ) : (
                    <div className="border rounded-lg p-4 text-sm text-muted-foreground">Đơn chưa có vận đơn GHN. Các đơn cũ vẫn được giữ nguyên lịch sử thủ công.</div>
                  )}
                </section>

                <section>
                  <h3 className="font-semibold mb-3">Lịch sử xử lý nội bộ</h3>
                  {selectedOrder.internalHistory?.length ? <ol className="space-y-3 border-l pl-4 ml-1 text-sm">{selectedOrder.internalHistory.map((event) => <li key={event.id} className="relative"><span className="absolute -left-[21px] top-1.5 w-2 h-2 rounded-full bg-slate-400" /><p>{ORDER_STATUS_LABELS[event.to_status] ?? event.to_status}</p><p className="text-xs text-muted-foreground">{formatDateSafe(event.at)}{event.actor ? ` · ${event.actor}` : ''}{event.note ? ` · ${event.note}` : ''}</p></li>)}</ol> : <p className="text-sm text-muted-foreground">Chưa có lịch sử xử lý nội bộ.</p>}
                </section>

                <section>
                  <h3 className="font-semibold mb-3">Sản phẩm</h3>
                  <div className="border rounded-lg divide-y text-sm">{selectedOrder.items?.map((item) => <div key={item.variant_id} className="p-3 flex justify-between gap-4"><div><p className="font-medium">{item.product?.name ?? item.variant_id}</p><p className="text-xs text-muted-foreground">{item.sku ? `SKU: ${item.sku} · ` : ''}SL: {item.quantity}</p></div><span className="font-medium shrink-0">{formatCurrency(item.price * item.quantity)}</span></div>)}</div>
                </section>
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
