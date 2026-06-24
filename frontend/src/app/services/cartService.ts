import apiClient from './apiClient';

export interface CartItemApi {
  variant_id: string;
  product: { id: string; name: string; image: string | null };
  attributes: { name: string; value: string }[];
  price: number;
  quantity: number;
  subtotal: number;
  stock: number;
}

export interface CartResponse {
  cart_id: string;
  items: CartItemApi[];
  subtotal: number;
  shipping: number;
  total: number;
}

export const cartService = {
  async getCart(): Promise<CartResponse> {
    const { data } = await apiClient.get<CartResponse>('/cart');
    return data;
  },

  async addItem(variant_id: string, quantity: number): Promise<CartResponse> {
    const { data } = await apiClient.post<CartResponse>('/cart/items', {
      variant_id,
      quantity,
    });
    return data;
  },

  async updateItem(variantId: string, quantity: number): Promise<CartResponse> {
    const { data } = await apiClient.put<CartResponse>(
      `/cart/items/${variantId}`,
      { quantity }
    );
    return data;
  },

  async removeItem(variantId: string): Promise<CartResponse> {
    const { data } = await apiClient.delete<CartResponse>(
      `/cart/items/${variantId}`
    );
    return data;
  },

  async clearCart(): Promise<CartResponse> {
    const { data } = await apiClient.delete<CartResponse>('/cart');
    return data;
  },
};
