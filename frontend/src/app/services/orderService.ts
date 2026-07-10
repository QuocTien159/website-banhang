import apiClient from './apiClient';

export interface OrderItem {
  variant_id: string;
  product: { id: string; name: string; image: string | null };
  attributes?: { name: string; value: string }[];
  price: number;
  quantity: number;
  subtotal: number;
}

export interface ApiOrder {
  id: string;
  status: 'pending' | 'confirmed' | 'shipping' | 'delivered' | 'cancelled';
  created_at: string;
  total: number;
  subtotal?: number;
  shipping?: number;
  discount?: number;
  coupon_code?: string | null;
  payment_method: 'cod' | 'banking' | 'bank_transfer_qr' | 'payos';
  payment_provider?: 'payos' | string | null;
  payos_order_code?: number | null;
  payment_link_id?: string | null;
  payment_checkout_url?: string | null;
  payment_status?: 'cod_pending' | 'pending_payment' | 'waiting_admin_confirmation' | 'paid' | 'payment_not_received' | 'failed' | 'cancelled' | 'expired';
  bank_transfer_content?: string | null;
  qr_code_url?: string | null;
  customer_paid_at?: string | null;
  payment_confirmed_at?: string | null;
  paid_at?: string | null;
  shipping_area_type?: string | null;
  shipping_provider?: 'manual' | 'ghn' | string | null;
  shipping_service_id?: string | null;
  shipping_service_type_id?: string | null;
  shipping_service_name?: string | null;
  shipping_order_code?: string | null;
  shipping_status?: string | null;
  bank?: BankInfo;
  shipping_info: {
    name: string;
    phone: string;
    address: string;
    province?: string;
    province_type?: 'ghn';
    district?: string;
    ward?: string;
    province_code?: string;
    district_code?: string;
    ward_code?: string;
    detail?: string;
  };
  note: string | null;
  items: OrderItem[];
}

export interface PlaceOrderPayload {
  ten_nguoi_nhan: string;
  so_dien_thoai: string;
  province_id: string;
  district_code: string;
  ward_code: string;
  address_detail: string;
  phuong_thuc_tt: 'cod' | 'banking' | 'bank_transfer_qr' | 'payos';
  ghi_chu?: string;
  coupon_code?: string;
}

export interface BankInfo {
  bank_code: string;
  bank_name: string;
  account_number: string;
  account_name: string;
  transfer_template?: string;
}

export interface ShippingCalculation {
  valid?: boolean;
  area_type?: 'ghn' | null;
  shipping_zone?: 'ghn' | null;
  provider?: 'manual' | 'ghn' | string | null;
  shipping_fee: number | null;
  base_shipping_fee?: number;
  free_shipping_applied: boolean;
  free_shipping_min_order_value?: number;
  service_id?: string | null;
  service_type_id?: string | number | null;
  service_name?: string | null;
  message?: string | null;
}

export interface AdministrativeUnit {
  code: string;
  id?: number | string;
  name: string;
  provider?: 'ghn' | string;
  province_id?: number;
  district_id?: number;
  shipping_zone?: 'ghn';
}

export interface AdminCategory {
  id: string;
  name: string;
  active: boolean;
  product_count: number;
}

export interface AdminImage {
  id?: string;
  url: string;
  path?: string;
  is_primary: boolean;
}

export interface AdminVariant {
  id?: string;
  sku: string;
  price: number;
  stock: number;
  low_stock_threshold?: number;
  active: boolean;
  attributes: { name: string; value: string }[];
}

export interface AdminProductSummary {
  id: string;
  name: string;
  category: string;
  category_id: string;
  image: string | null;
  min_price: number;
  max_price: number;
  base_price: number;
  stock: number;
  variant_count: number;
  status: 'active' | 'inactive' | 'out_of_stock';
}

export interface AdminProductDetail extends AdminProductSummary {
  description: string | null;
  images: AdminImage[];
  variants: AdminVariant[];
}

export interface AdminProductPayload {
  name: string;
  category_id: string;
  description: string;
  base_price: number;
  status: 'active' | 'inactive' | 'out_of_stock';
  images: AdminImage[];
  variants: AdminVariant[];
}

export interface AdminAttributeValue {
  id: string;
  attribute_id: string;
  value: string;
  slug: string;
  color_code?: string | null;
  sort_order: number;
  active: boolean;
  created_at?: string | null;
}

export interface AdminAttribute {
  id: string;
  name: string;
  slug: string;
  active: boolean;
  description?: string | null;
  value_count: number;
  created_at?: string | null;
  values?: AdminAttributeValue[];
}

export interface AdminAttributePayload {
  name: string;
  slug?: string;
  active: boolean;
  description?: string;
  values?: Omit<AdminAttributeValue, 'id' | 'attribute_id' | 'created_at'>[];
}

export interface AdminStaff {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  role: 'staff' | 'admin';
  active: boolean;
  created_at?: string | null;
}

export interface AdminStaffPayload {
  name: string;
  email: string;
  phone?: string;
  password?: string;
  role: 'staff' | 'admin';
  active: boolean;
}

export const orderService = {
  async getOrders(): Promise<ApiOrder[]> {
    const { data } = await apiClient.get<ApiOrder[]>('/orders');
    return data;
  },
  async getOrder(id: string): Promise<ApiOrder> {
    const { data } = await apiClient.get<ApiOrder>(`/orders/${id}`);
    return data;
  },
  async getPayosStatus(orderCode: string): Promise<ApiOrder> {
    const { data } = await apiClient.get<ApiOrder>(`/payment/payos/status/${orderCode}`);
    return data;
  },
  async placeOrder(payload: PlaceOrderPayload): Promise<{ message: string; order: ApiOrder }> {
    const { data } = await apiClient.post('/orders', payload);
    return data;
  },
  async calculateShipping(payload: { province_id: string; district_code: string; ward_code: string; address_detail: string }): Promise<ShippingCalculation> {
    const { data } = await apiClient.post('/shipping/calculate', payload);
    return data;
  },
  async getProvinces(): Promise<AdministrativeUnit[]> {
    const { data } = await apiClient.get('/address/provinces');
    return data.data ?? [];
  },
  async getDistricts(provinceId: string): Promise<AdministrativeUnit[]> {
    const { data } = await apiClient.get('/address/districts', { params: { province_id: provinceId } });
    return data.data ?? [];
  },
  async getWards(districtCode: string): Promise<AdministrativeUnit[]> {
    const { data } = await apiClient.get('/address/wards', { params: { district_code: districtCode } });
    return data.data ?? [];
  },
  async getBankInfo(): Promise<BankInfo> {
    const { data } = await apiClient.get('/payment/bank-info');
    return data;
  },
  async markBankTransferPaid(orderId: string): Promise<{ message: string; order: ApiOrder }> {
    const { data } = await apiClient.put(`/orders/${orderId}/bank-transfer-paid`);
    return data;
  },
};

export const wishlistService = {
  async getWishlist() {
    const { data } = await apiClient.get('/wishlist');
    return data;
  },
  async toggleWishlist(productId: string): Promise<{ wishlisted: boolean; message: string }> {
    const { data } = await apiClient.post(`/wishlist/${productId}`);
    return data;
  },
};

export const adminService = {
  async getSummary() {
    const { data } = await apiClient.get('/admin/reports/summary');
    return data;
  },
  async getRevenue(year?: number) {
    const { data } = await apiClient.get('/admin/reports/revenue', { params: { year } });
    return data;
  },
  async getInventoryAlerts() {
    const { data } = await apiClient.get('/admin/reports/inventory');
    return data;
  },
  async getDashboard(params: { from: string; to: string }) {
    const { data } = await apiClient.get('/admin/reports/dashboard', { params });
    return data;
  },

  async getAdminProducts(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/products', { params });
    return data;
  },
  async getAdminProduct(id: string): Promise<AdminProductDetail> {
    const { data } = await apiClient.get(`/admin/products/${id}`);
    return data;
  },
  async getProductOptions(): Promise<{ attributes: { id?: string; name: string; slug?: string; type?: string; values: Array<string | { id: string; value: string; slug?: string; color_code?: string | null }> }[] }> {
    const { data } = await apiClient.get('/admin/products/options');
    return data;
  },
  async uploadProductImages(files: File[]): Promise<AdminImage[]> {
    const formData = new FormData();
    files.forEach((file) => formData.append('images[]', file));
    const { data } = await apiClient.post('/admin/products/images', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.images;
  },
  async createProduct(payload: AdminProductPayload) {
    const { data } = await apiClient.post('/admin/products', payload);
    return data;
  },
  async updateProduct(id: string, payload: AdminProductPayload) {
    const { data } = await apiClient.put(`/admin/products/${id}`, payload);
    return data;
  },
  async deleteProduct(id: string) {
    const { data } = await apiClient.delete(`/admin/products/${id}`);
    return data;
  },
  async hideProduct(id: string) {
    const { data } = await apiClient.put(`/admin/products/${id}/hide`);
    return data;
  },
  async updateVariantStock(variantId: string, so_luong_ton: number) {
    const { data } = await apiClient.put(`/admin/variants/${variantId}`, { so_luong_ton });
    return data;
  },

  async getAttributes(params: Record<string, string | number | boolean> = {}): Promise<AdminAttribute[]> {
    const { data } = await apiClient.get('/admin/attributes', { params });
    return data;
  },
  async getAttribute(id: string): Promise<AdminAttribute> {
    const { data } = await apiClient.get(`/admin/attributes/${id}`);
    return data;
  },
  async createAttribute(payload: AdminAttributePayload): Promise<AdminAttribute> {
    const { data } = await apiClient.post('/admin/attributes', payload);
    return data;
  },
  async updateAttribute(id: string, payload: AdminAttributePayload): Promise<AdminAttribute> {
    const { data } = await apiClient.put(`/admin/attributes/${id}`, payload);
    return data;
  },
  async deleteAttribute(id: string) {
    const { data } = await apiClient.delete(`/admin/attributes/${id}`);
    return data;
  },
  async createAttributeValue(attributeId: string, payload: Partial<AdminAttributeValue> & { value: string }): Promise<AdminAttributeValue> {
    const { data } = await apiClient.post(`/admin/attributes/${attributeId}/values`, payload);
    return data;
  },
  async updateAttributeValue(attributeId: string, valueId: string, payload: Partial<AdminAttributeValue> & { value: string }): Promise<AdminAttributeValue> {
    const { data } = await apiClient.put(`/admin/attributes/${attributeId}/values/${valueId}`, payload);
    return data;
  },
  async deleteAttributeValue(attributeId: string, valueId: string) {
    const { data } = await apiClient.delete(`/admin/attributes/${attributeId}/values/${valueId}`);
    return data;
  },

  async getInventoryVariants(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/inventory/variants', { params });
    return data;
  },
  async getStockReceipts(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/inventory/receipts', { params });
    return data;
  },
  async createStockReceipt(payload: {
    code?: string;
    import_date: string;
    note?: string;
    items: { variant_id: string; quantity: number; note?: string }[];
  }) {
    const { data } = await apiClient.post('/admin/inventory/receipts', payload);
    return data;
  },
  async getStockReceipt(id: string) {
    const { data } = await apiClient.get(`/admin/inventory/receipts/${id}`);
    return data;
  },
  async approveStockReceipt(id: string, approval_note?: string) {
    const { data } = await apiClient.put(`/admin/inventory/receipts/${id}/approve`, { approval_note });
    return data;
  },
  async rejectStockReceipt(id: string, approval_note?: string) {
    const { data } = await apiClient.put(`/admin/inventory/receipts/${id}/reject`, { approval_note });
    return data;
  },
  async getStockMovements(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/inventory/movements', { params });
    return data;
  },
  async adjustStock(payload: { variant_id: string; stock: number; reason: string }) {
    const { data } = await apiClient.post('/admin/inventory/adjust', payload);
    return data;
  },
  async getStockAlerts(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/inventory/alerts', { params });
    return data;
  },

  async getCategories(params: Record<string, string> = {}): Promise<AdminCategory[]> {
    const { data } = await apiClient.get('/admin/categories', { params });
    return data;
  },
  async createCategory(name: string): Promise<AdminCategory> {
    const { data } = await apiClient.post('/admin/categories', { name });
    return data;
  },
  async updateCategory(id: string, payload: { name?: string; active?: boolean }): Promise<AdminCategory> {
    const { data } = await apiClient.put(`/admin/categories/${id}`, payload);
    return data;
  },
  async deleteCategory(id: string) {
    const { data } = await apiClient.delete(`/admin/categories/${id}`);
    return data;
  },

  async getAdminOrders(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/orders', { params });
    return data;
  },
  async updateOrderStatus(orderId: string, status: string) {
    const { data } = await apiClient.put(`/admin/orders/${orderId}/status`, { status });
    return data;
  },
  async updateOrderPaymentStatus(orderId: string, paymentStatus: 'paid' | 'payment_not_received') {
    const { data } = await apiClient.put(`/admin/orders/${orderId}/payment-status`, { payment_status: paymentStatus });
    return data;
  },
  async getPaymentShippingSettings() {
    const { data } = await apiClient.get('/admin/payment-shipping-settings');
    return data;
  },
  async updatePaymentShippingSettings(payload: Record<string, unknown>) {
    const { data } = await apiClient.put('/admin/payment-shipping-settings', payload);
    return data;
  },
  async getCustomers(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/customers', { params });
    return data;
  },
  async toggleCustomerStatus(customerId: string) {
    const { data } = await apiClient.put(`/admin/customers/${customerId}/status`);
    return data;
  },
  async getStaff(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/staff', { params });
    return data;
  },
  async createStaff(payload: AdminStaffPayload) {
    const { data } = await apiClient.post('/admin/staff', payload);
    return data;
  },
  async updateStaff(id: string, payload: AdminStaffPayload) {
    const { data } = await apiClient.put(`/admin/staff/${id}`, payload);
    return data;
  },
  async toggleStaffStatus(id: string) {
    const { data } = await apiClient.put(`/admin/staff/${id}/status`);
    return data;
  },
};
