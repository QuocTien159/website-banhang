import apiClient from './apiClient';

export interface ApiAttributeSummary {
  name: string;
  values: string[];
}

export interface ApiProduct {
  id: string;
  name: string;
  category: string;
  category_id: string;
  brand: string | null;
  price: number;
  original_price: number;
  image: string | null;
  stock: number;
  rating: number;
  review_count: number;
  featured: boolean;
  sold: number;
  attributes: ApiAttributeSummary[];
}

export interface ApiProductDetail extends ApiProduct {
  images: string[];
  description: string;
  specs: { label: string; value: string }[];
  required_attributes: string[];
  variants: ApiVariant[];
  reviews: ApiReview[];
}

export interface ApiVariant {
  id: string;
  sku: string;
  price: number;
  stock: number;
  image?: string | null;
  attributes: { name: string; value: string }[];
}

export interface ApiReview {
  id: string;
  name: string;
  rating: number;
  date: string;
  comment: string;
  images?: string[];
  admin_reply?: string | null;
}

export interface ApiCategory {
  id: string;
  name: string;
  count: number;
}

export interface ProductsResponse {
  data: ApiProduct[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    filters: Record<string, string[]>;
  };
}

export interface ProductFilters {
  search?: string;
  category?: string;
  category_id?: string;
  brand?: string;
  color?: string;
  size?: string;
  weight?: string;
  resistance?: string;
  min_price?: number;
  max_price?: number;
  sort?: 'newest' | 'price_asc' | 'price_desc' | 'name';
  page?: number;
  per_page?: number;
}

export const productService = {
  async getProducts(filters: ProductFilters = {}): Promise<ProductsResponse> {
    const params = Object.fromEntries(
      Object.entries(filters).filter(([, value]) => value !== undefined && value !== '')
    );
    const { data } = await apiClient.get<ProductsResponse>('/products', { params });
    return data;
  },

  async getProduct(id: string): Promise<ApiProductDetail> {
    const { data } = await apiClient.get<ApiProductDetail>(`/products/${id}`);
    return data;
  },

  async getCategories(): Promise<ApiCategory[]> {
    const { data } = await apiClient.get<ApiCategory[]>('/categories');
    return data;
  },

  async getReviews(productId: string, page = 1) {
    const { data } = await apiClient.get(`/products/${productId}/reviews`, { params: { page } });
    return data;
  },

  async postReview(payload: { product_id: string; so_sao: number; noi_dung: string }) {
    const { data } = await apiClient.post('/reviews', payload);
    return data;
  },
};
