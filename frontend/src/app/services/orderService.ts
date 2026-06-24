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
  payment_method: 'cod' | 'banking';
  shipping_info: { name: string; phone: string; address: string };
  note: string | null;
  items: OrderItem[];
}

export interface PlaceOrderPayload {
  ten_nguoi_nhan: string;
  so_dien_thoai: string;
  dia_chi_giao: string;
  phuong_thuc_tt: 'cod' | 'banking';
  ghi_chu?: string;
  coupon_code?: string;
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

export const orderService = {
  async getOrders(): Promise<ApiOrder[]> {
    const { data } = await apiClient.get<ApiOrder[]>('/orders');
    return data;
  },
  async getOrder(id: string): Promise<ApiOrder> {
    const { data } = await apiClient.get<ApiOrder>(`/orders/${id}`);
    return data;
  },
  async placeOrder(payload: PlaceOrderPayload): Promise<{ message: string; order: ApiOrder }> {
    const { data } = await apiClient.post('/orders', payload);
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

  async getAdminProducts(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/products', { params });
    return data;
  },
  async getAdminProduct(id: string): Promise<AdminProductDetail> {
    const { data } = await apiClient.get(`/admin/products/${id}`);
    return data;
  },
  async getProductOptions(): Promise<{ attributes: { name: string; values: string[] }[] }> {
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
  async updateVariantStock(variantId: string, so_luong_ton: number) {
    const { data } = await apiClient.put(`/admin/variants/${variantId}`, { so_luong_ton });
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
  async getCustomers(params: Record<string, string | number> = {}) {
    const { data } = await apiClient.get('/admin/customers', { params });
    return data;
  },
  async toggleCustomerStatus(customerId: string) {
    const { data } = await apiClient.put(`/admin/customers/${customerId}/status`);
    return data;
  },
};
