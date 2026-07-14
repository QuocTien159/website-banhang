import { createBrowserRouter } from 'react-router';
import { UserLayout } from './components/layout/UserLayout';
import { AdminLayout } from './components/layout/AdminLayout';
import { HomePage } from './components/user/HomePage';
import { ProductsPage } from './components/user/ProductsPage';
import { ProductDetailPage } from './components/user/ProductDetailPage';
import { CartPage } from './components/user/CartPage';
import { CheckoutPage } from './components/user/CheckoutPage';
import { LoginPage } from './components/user/LoginPage';
import { RegisterPage } from './components/user/RegisterPage';
import { AccountPage } from './components/user/AccountPage';
import { OrderDetailPage } from './components/user/OrderDetailPage';
import { QrPaymentPage } from './components/user/QrPaymentPage';
import { PayosCancelPage, PayosReturnPage } from './components/user/PayosCallbackPage';
import { ProfilePage } from './components/user/ProfilePage';
import { AdminDashboard } from './components/admin/AdminDashboard';
import { AdminProducts } from './components/admin/AdminProducts';
import { AdminOrders } from './components/admin/AdminOrders';
import { AdminCustomers } from './components/admin/AdminCustomers';
import { AdminCategories } from './components/admin/AdminCategories';
import { AdminAttributes } from './components/admin/AdminAttributes';
import { WishlistPage } from './components/user/WishlistPage';
import { ReviewsPage } from './components/user/ReviewsPage';
import { AdminPromotions } from './components/admin/AdminPromotions';
import { AdminHomepagePromotion } from './components/admin/AdminHomepagePromotion';
import { AdminReviews } from './components/admin/AdminReviews';
import { AdminAnnouncements } from './components/admin/AdminAnnouncements';
import { AdminStockImport } from './components/admin/AdminStockImport';
import { AdminStockReceipts } from './components/admin/AdminStockReceipts';
import { AdminStockMovements } from './components/admin/AdminStockMovements';
import { AdminStockAlerts } from './components/admin/AdminStockAlerts';
import { AdminReturns } from './components/admin/AdminReturns';
import { AdminPaymentShippingSettings } from './components/admin/AdminPaymentShippingSettings';
import { AdminStaff } from './components/admin/AdminStaff';
import { GoogleAuthCallbackPage } from './components/user/GoogleAuthCallbackPage';

export const router = createBrowserRouter([
  {
    path: '/',
    Component: UserLayout,
    children: [
      { index: true, Component: HomePage },
      { path: 'products', Component: ProductsPage },
      { path: 'products/:id', Component: ProductDetailPage },
      { path: 'cart', Component: CartPage },
      { path: 'checkout', Component: CheckoutPage },
      { path: 'account', Component: AccountPage },
      { path: 'account/orders/:id', Component: OrderDetailPage },
      { path: 'account/orders/:id/qr-payment', Component: QrPaymentPage },
      { path: 'payment/payos/return', Component: PayosReturnPage },
      { path: 'payment/payos/cancel', Component: PayosCancelPage },
      { path: 'profile', Component: ProfilePage },
      { path: 'wishlist', Component: WishlistPage },
      { path: 'reviews', Component: ReviewsPage },
    ],
  },
  {
    path: '/login',
    Component: LoginPage,
  },
  {
    path: '/register',
    Component: RegisterPage,
  },
  {
    path: '/auth/google/callback',
    Component: GoogleAuthCallbackPage,
  },
  {
    path: '/admin',
    Component: AdminLayout,
    children: [
      { index: true, Component: AdminDashboard },
      { path: 'products', Component: AdminProducts },
      { path: 'categories', Component: AdminCategories },
      { path: 'attributes', Component: AdminAttributes },
      { path: 'stock-import', Component: AdminStockImport },
      { path: 'stock-receipts', Component: AdminStockReceipts },
      { path: 'stock-movements', Component: AdminStockMovements },
      { path: 'stock-alerts', Component: AdminStockAlerts },
      { path: 'promotions', Component: AdminPromotions },
      { path: 'homepage-promotion', Component: AdminHomepagePromotion },
      { path: 'reviews', Component: AdminReviews },
      { path: 'announcements', Component: AdminAnnouncements },
      { path: 'orders', Component: AdminOrders },
      { path: 'returns', Component: AdminReturns },
      { path: 'payment-shipping', Component: AdminPaymentShippingSettings },
      { path: 'customers', Component: AdminCustomers },
      { path: 'staff', Component: AdminStaff },
    ],
  },
]);
