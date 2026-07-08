export const ORDER_STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

export const ORDER_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-blue-100 text-blue-700',
  shipping: 'bg-purple-100 text-purple-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

export const ORDER_STATUS_FLOW = ['pending', 'confirmed', 'shipping', 'delivered'];

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
