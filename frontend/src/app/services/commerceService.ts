import apiClient from './apiClient';

export interface WishlistItem {
  id: string; name: string; category: string; price: number; image: string | null;
  stock: number; variant_count: number; available: boolean; status: string;
}
export interface Announcement {
  id: string; title: string; content: string; type: string; published_at: string;
  images: { id?: string; url: string; path?: string; order?: number }[];
}
export interface ReviewCandidate {
  order_id: string; product_id: string; product_name: string; image: string | null;
}
export interface MyReview {
  id: string;
  rating: number;
  comment: string;
  images: string[];
  status: 'pending' | 'approved' | 'rejected';
  date?: string;
  created_at?: string;
  updated_at?: string | null;
  admin_reply?: string | null;
  admin_replied_at?: string | null;
  product?: { id: string; name: string; image: string | null };
  order_id?: string | null;
}
export interface ReturnRequestItemPayload {
  variant_id: string;
  quantity: number;
  reason: string;
  description?: string;
  images?: string[];
}

export const commerceService = {
  async wishlist(): Promise<WishlistItem[]> { return (await apiClient.get('/wishlist')).data; },
  async toggleWishlist(productId: string) { return (await apiClient.post(`/wishlist/${productId}`)).data; },
  async wishlistStatus(productId: string): Promise<boolean> { return (await apiClient.get(`/wishlist/${productId}/status`)).data.wishlisted; },
  async validateCoupon(code: string) { return (await apiClient.post('/promotions/validate', { code })).data; },
  async announcements(): Promise<Announcement[]> { return (await apiClient.get('/announcements')).data; },
  async eligibleReviews(): Promise<ReviewCandidate[]> { return (await apiClient.get('/reviews/eligible')).data; },
  async myReviews(): Promise<MyReview[]> { return (await apiClient.get('/reviews/mine')).data; },
  async uploadReviewImages(files: File[]): Promise<string[]> {
    const form = new FormData(); files.forEach((file) => form.append('images[]', file));
    return (await apiClient.post('/reviews/images', form, { headers: { 'Content-Type': 'multipart/form-data' } })).data.images;
  },
  async createReview(payload: { order_id: string; product_id: string; rating: number; comment: string; images: string[] }) {
    return (await apiClient.post('/reviews', payload)).data;
  },
  async updateReview(id: string, payload: { rating: number; comment: string; images: string[] }) {
    return (await apiClient.put(`/reviews/${id}`, payload)).data;
  },
  async returns() { return (await apiClient.get('/returns')).data; },
  async createReturn(payload: { order_id: string; reason: string; description?: string; items: ReturnRequestItemPayload[] }) {
    return (await apiClient.post('/returns', payload)).data;
  },
  async uploadReturnImages(files: File[]): Promise<string[]> {
    const form = new FormData(); files.forEach((file) => form.append('images[]', file));
    return (await apiClient.post('/returns/images', form, { headers: { 'Content-Type': 'multipart/form-data' } })).data.images;
  },
  async cancelReturn(id: string) { return (await apiClient.put(`/returns/${id}/cancel`)).data; },
};

export const adminCommerceService = {
  promotions: {
    list: (params = {}) => apiClient.get('/admin/promotions', { params }).then((r) => r.data),
    create: (data: any) => apiClient.post('/admin/promotions', data).then((r) => r.data),
    update: (id: string, data: any) => apiClient.put(`/admin/promotions/${id}`, data).then((r) => r.data),
    remove: (id: string) => apiClient.delete(`/admin/promotions/${id}`).then((r) => r.data),
  },
  reviews: {
    list: (params = {}) => apiClient.get('/admin/reviews', { params }).then((r) => r.data),
    moderate: (id: string, status: string) => apiClient.put(`/admin/reviews/${id}/status`, { status }),
    reply: (id: string, reply: string) => apiClient.put(`/admin/reviews/${id}/reply`, { reply }),
    deleteReply: (id: string) => apiClient.delete(`/admin/reviews/${id}/reply`),
    remove: (id: string) => apiClient.delete(`/admin/reviews/${id}`),
  },
  announcements: {
    list: (params = {}) => apiClient.get('/admin/announcements', { params }).then((r) => r.data),
    create: (data: any) => apiClient.post('/admin/announcements', data).then((r) => r.data),
    update: (id: string, data: any) => apiClient.put(`/admin/announcements/${id}`, data).then((r) => r.data),
    remove: (id: string) => apiClient.delete(`/admin/announcements/${id}`),
    uploadImages: async (files: File[]) => {
      const form = new FormData();
      files.forEach((file) => form.append('images[]', file));
      return (await apiClient.post('/admin/announcements/images', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })).data.images as { url: string; path?: string; upload_token?: string | null }[];
    },
    deleteUploadedImage: (data: { path?: string; upload_token?: string | null }) => apiClient.delete('/admin/announcements/images/uploaded', { data }),
  },
  returns: {
    list: (params = {}) => apiClient.get('/admin/returns', { params }).then((r) => r.data),
    show: (id: string) => apiClient.get(`/admin/returns/${id}`).then((r) => r.data),
    updateStatus: (id: string, data: { status: string; admin_note?: string; reject_reason?: string; refund_status?: string }) =>
      apiClient.put(`/admin/returns/${id}/status`, data).then((r) => r.data),
    updateRefund: (id: string, data: { refund_status: string; admin_note?: string }) =>
      apiClient.put(`/admin/returns/${id}/refund`, data).then((r) => r.data),
  },
};
