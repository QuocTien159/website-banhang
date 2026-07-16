export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  preparing: 'Đang chuẩn bị hàng',
  ready_to_ship: 'Sẵn sàng bàn giao',
  handed_to_carrier: 'Đã bàn giao GHN',
  completed: 'Hoàn tất',
  returning: 'Đang hoàn hàng',
  returned: 'Đã hoàn hàng',
  shipping: 'Đang giao (cũ)',
  delivered: 'Đã giao (cũ)',
  cancelled: 'Đã hủy',
};

export const ORDER_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-blue-100 text-blue-700',
  preparing: 'bg-indigo-100 text-indigo-700',
  ready_to_ship: 'bg-cyan-100 text-cyan-700',
  handed_to_carrier: 'bg-violet-100 text-violet-700',
  completed: 'bg-green-100 text-green-700',
  returning: 'bg-amber-100 text-amber-700',
  returned: 'bg-slate-100 text-slate-700',
  shipping: 'bg-purple-100 text-purple-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

export const ORDER_STATUS_FLOW = ['pending', 'confirmed', 'preparing', 'ready_to_ship', 'handed_to_carrier', 'completed'];

export const SHIPPING_STATUS_LABELS: Record<string, string> = {
  waiting_pickup: 'Chờ GHN lấy hàng',
  picking: 'GHN đang lấy hàng',
  in_transit: 'Đang trung chuyển',
  delivering: 'Đang giao hàng',
  delivered: 'GHN đã giao thành công',
  delivery_failed: 'GHN giao chưa thành công',
  cancelled: 'GHN đã hủy vận đơn',
  returning: 'Đang hoàn hàng',
  returned: 'Đã hoàn về kho',
  exception: 'GHN đang xử lý sự cố',
  unknown: 'Đang chờ GHN cập nhật',
};

export const SHIPPING_STATUS_COLORS: Record<string, string> = {
  waiting_pickup: 'bg-sky-50 text-sky-700',
  picking: 'bg-blue-50 text-blue-700',
  in_transit: 'bg-violet-50 text-violet-700',
  delivering: 'bg-orange-50 text-orange-700',
  delivered: 'bg-green-50 text-green-700',
  delivery_failed: 'bg-red-50 text-red-700',
  cancelled: 'bg-slate-100 text-slate-700',
  returning: 'bg-amber-50 text-amber-700',
  returned: 'bg-slate-100 text-slate-700',
  exception: 'bg-red-50 text-red-700',
  unknown: 'bg-gray-100 text-gray-700',
};

export const PAYMENT_STATUS_LABELS: Record<string, string> = {
  cod_pending: 'Thanh toán COD',
  pending_payment: 'Chờ thanh toán',
  waiting_admin_confirmation: 'Chờ admin xác nhận',
  paid: 'Đã thanh toán',
  payment_not_received: 'Chưa nhận được tiền',
  failed: 'Thanh toán thất bại',
  cancelled: 'Đã hủy thanh toán',
  expired: 'Thanh toán hết hạn',
};

export const USER_ROLES = {
  CUSTOMER: 'customer',
  STAFF: 'staff',
  ADMIN: 'admin',
} as const;
