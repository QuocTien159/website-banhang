import {
  createContext,
  useContext,
  useReducer,
  ReactNode,
  useEffect,
  useCallback,
} from 'react';
import { authService, type ApiUser } from '../services/authService';
import { cartService, type CartResponse, type CartItemApi } from '../services/cartService';

// ─── Types ────────────────────────────────────────────────────────
export interface User {
  id: string;
  name: string;
  email: string;
  role: 'customer' | 'staff' | 'admin';
  phone: string;
  address: string;
  joinDate: string;
}

export type { CartItemApi };
export type { CartResponse };

export interface CartItem {
  variantId: string;
  productId: string;
  productName: string;
  productImage: string | null;
  attributes: { name: string; value: string }[];
  price: number;
  quantity: number;
  stock: number;
}

interface AppState {
  user: User | null;
  cart: CartItem[];
  cartTotal: number;
  cartSubtotal: number;
  cartShipping: number;
  isCartLoading: boolean;
  isAuthLoading: boolean;
}

type Action =
  | { type: 'SET_USER'; payload: User | null }
  | { type: 'SET_AUTH_LOADING'; payload: boolean }
  | { type: 'SET_CART'; payload: CartResponse }
  | { type: 'SET_CART_LOADING'; payload: boolean }
  | { type: 'CLEAR_CART' };

// ─── Helpers ──────────────────────────────────────────────────────
function apiUserToUser(u: ApiUser): User {
  return {
    id: u.id,
    name: u.name,
    email: u.email,
    role: u.role,
    phone: u.phone ?? '',
    address: '',
    joinDate: u.join_date,
  };
}

function cartResponseToItems(cart: CartResponse): CartItem[] {
  return cart.items.map((item) => ({
    variantId: item.variant_id,
    productId: item.product.id,
    productName: item.product.name,
    productImage: item.product.image,
    attributes: item.attributes,
    price: item.price,
    quantity: item.quantity,
    stock: item.stock,
  }));
}

// ─── Reducer ──────────────────────────────────────────────────────
const initialState: AppState = {
  user: null,
  cart: [],
  cartTotal: 0,
  cartSubtotal: 0,
  cartShipping: 0,
  isCartLoading: false,
  isAuthLoading: true,
};

function reducer(state: AppState, action: Action): AppState {
  switch (action.type) {
    case 'SET_USER':
      return { ...state, user: action.payload };
    case 'SET_AUTH_LOADING':
      return { ...state, isAuthLoading: action.payload };
    case 'SET_CART':
      return {
        ...state,
        cart: cartResponseToItems(action.payload),
        cartSubtotal: action.payload.subtotal,
        cartShipping: action.payload.shipping,
        cartTotal: action.payload.total,
        isCartLoading: false,
      };
    case 'SET_CART_LOADING':
      return { ...state, isCartLoading: action.payload };
    case 'CLEAR_CART':
      return { ...state, cart: [], cartTotal: 0, cartSubtotal: 0, cartShipping: 0 };
    default:
      return state;
  }
}

// ─── Context ──────────────────────────────────────────────────────
interface AppContextValue {
  state: AppState;
  dispatch: React.Dispatch<Action>;
  refreshCart: () => Promise<void>;
}

const AppContext = createContext<AppContextValue | null>(null);

export function AppProvider({ children }: { children: ReactNode }) {
  const [state, dispatch] = useReducer(reducer, initialState);

  const refreshCart = useCallback(async () => {
    if (!authService.isAuthenticated()) return;
    try {
      dispatch({ type: 'SET_CART_LOADING', payload: true });
      const cart = await cartService.getCart();
      dispatch({ type: 'SET_CART', payload: cart });
    } catch {
      dispatch({ type: 'SET_CART_LOADING', payload: false });
    }
  }, []);

  // On mount: verify session token before restoring user.
  useEffect(() => {
    let active = true;

    const bootstrapAuth = async () => {
      if (!authService.isAuthenticated()) {
        if (active) {
          dispatch({ type: 'SET_USER', payload: null });
          dispatch({ type: 'CLEAR_CART' });
          dispatch({ type: 'SET_AUTH_LOADING', payload: false });
        }
        return;
      }

      try {
        const user = await authService.me();
        if (!active) return;
        dispatch({ type: 'SET_USER', payload: apiUserToUser(user) });
        await refreshCart();
      } catch {
        authService.clearAuth();
        if (active) {
          dispatch({ type: 'SET_USER', payload: null });
          dispatch({ type: 'CLEAR_CART' });
        }
      } finally {
        if (active) dispatch({ type: 'SET_AUTH_LOADING', payload: false });
      }
    };

    bootstrapAuth();
    return () => { active = false; };
  }, [refreshCart]);

  return (
    <AppContext.Provider value={{ state, dispatch, refreshCart }}>
      {children}
    </AppContext.Provider>
  );
}

// ─── Hooks ────────────────────────────────────────────────────────
export function useApp() {
  const ctx = useContext(AppContext);
  if (!ctx) throw new Error('useApp must be used within AppProvider');
  return ctx;
}

export function useCart() {
  const { state, dispatch, refreshCart } = useApp();
  const totalItems = state.cart.reduce((sum, item) => sum + item.quantity, 0);

  const addToCart = async (variantId: string, quantity = 1) => {
    dispatch({ type: 'SET_CART_LOADING', payload: true });
    const cart = await cartService.addItem(variantId, quantity);
    dispatch({ type: 'SET_CART', payload: cart });
  };

  const removeFromCart = async (variantId: string) => {
    dispatch({ type: 'SET_CART_LOADING', payload: true });
    const cart = await cartService.removeItem(variantId);
    dispatch({ type: 'SET_CART', payload: cart });
  };

  const updateQuantity = async (variantId: string, quantity: number) => {
    dispatch({ type: 'SET_CART_LOADING', payload: true });
    const cart = await cartService.updateItem(variantId, quantity);
    dispatch({ type: 'SET_CART', payload: cart });
  };

  const clearCart = async () => {
    dispatch({ type: 'SET_CART_LOADING', payload: true });
    const cart = await cartService.clearCart();
    dispatch({ type: 'SET_CART', payload: cart });
  };

  return {
    cart: state.cart,
    totalItems,
    subtotal: state.cartSubtotal,
    shipping: state.cartShipping,
    total: state.cartTotal,
    isLoading: state.isCartLoading,
    addToCart,
    removeFromCart,
    updateQuantity,
    clearCart,
    refreshCart,
    dispatch,
  };
}

export function useAuth() {
  const { state, dispatch, refreshCart } = useApp();

  const login = async (email: string, password: string): Promise<boolean> => {
    try {
      const { user } = await authService.login(email, password);
      dispatch({ type: 'SET_USER', payload: apiUserToUser(user) });
      await refreshCart();
      return true;
    } catch {
      return false;
    }
  };

  const register = async (
    name: string,
    email: string,
    password: string,
    phone?: string
  ): Promise<{ success: boolean; message?: string }> => {
    try {
      const { user } = await authService.register({
        ten_kh: name,
        email,
        mat_khau: password,
        mat_khau_confirmation: password,
        dien_thoai: phone,
      });
      dispatch({ type: 'SET_USER', payload: apiUserToUser(user) });
      await refreshCart();
      return { success: true };
    } catch (err: unknown) {
      const axiosError = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errors = axiosError?.response?.data?.errors;
      const msg = errors
        ? Object.values(errors).flat().join('. ')
        : (axiosError?.response?.data?.message ?? 'Đăng ký thất bại');
      return { success: false, message: msg };
    }
  };

  const logout = async () => {
    await authService.logout();
    dispatch({ type: 'SET_USER', payload: null });
    dispatch({ type: 'CLEAR_CART' });
  };

  const updateProfile = async (payload: {
    name: string;
    phone?: string | null;
    current_password?: string;
    new_password?: string;
    new_password_confirmation?: string;
  }) => {
    const { user } = await authService.updateProfile(payload);
    const mapped = apiUserToUser(user);
    dispatch({ type: 'SET_USER', payload: mapped });
    return mapped;
  };

  return {
    user: state.user,
    isAuthenticated: !!state.user,
    isLoading: state.isAuthLoading,
    login,
    register,
    updateProfile,
    logout,
  };
}
