import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { Package, User as UserIcon, Phone, Calendar } from 'lucide-react';
import { useAuth } from '../../store/AppContext';
import { orderService, type ApiOrder } from '../../services/orderService';
import { Button } from '../ui/button';

const formatPrice = (p: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p);

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  confirmed: 'bg-blue-100 text-blue-700',
  shipping: 'bg-purple-100 text-purple-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

const STATUS_LABELS: Record<string, string> = {
  pending: 'Chờ xác nhận',
  confirmed: 'Đã xác nhận',
  shipping: 'Đang giao hàng',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
};

export function AccountPage() {
  const { user, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [orders, setOrders] = useState<ApiOrder[]>([]);
  const [loadingOrders, setLoadingOrders] = useState(true);

  useEffect(() => {
    if (!isAuthenticated) return;
    orderService.getOrders()
      .then(setOrders)
      .catch(() => setOrders([]))
      .finally(() => setLoadingOrders(false));
  }, [isAuthenticated]);

  if (!isAuthenticated || !user) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-20 text-center">
        <div className="max-w-sm mx-auto">
          <div className="text-5xl mb-4">🔐</div>
          <h2 className="mb-2">Đăng nhập để xem đơn hàng</h2>
          <p className="text-muted-foreground text-sm mb-6">Bạn cần đăng nhập để xem lịch sử đơn hàng và thông tin tài khoản</p>
          <div className="flex gap-3 justify-center">
            <Button onClick={() => navigate('/login')} style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
              Đăng nhập
            </Button>
            <Button variant="outline" onClick={() => navigate('/register')}>Đăng ký</Button>
          </div>
        </div>
      </div>
    );
  }

  const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('vi-VN', {
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <h2 className="mb-6">Tài khoản của tôi</h2>

      <div className="grid md:grid-cols-3 gap-6">
        {/* User Info */}
        <div className="md:col-span-1">
          <div className="bg-white rounded-xl border border-border p-6">
            <div className="flex flex-col items-center text-center mb-6">
              <div
                className="w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold mb-3"
                style={{ backgroundColor: '#ea5c21' }}
              >
                {user.name.charAt(0).toUpperCase()}
              </div>
              <h3 className="font-semibold">{user.name}</h3>
              <span className={`mt-1 text-xs px-2 py-0.5 rounded-full ${user.role === 'admin' ? 'bg-purple-100 text-purple-700' : user.role === 'staff' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'}`}>
                {user.role === 'admin' ? '👑 Quản trị viên' : '👤 Khách hàng'}
              </span>
            </div>

            <div className="space-y-3 text-sm">
              <div className="flex items-center gap-3">
                <UserIcon className="w-4 h-4 text-muted-foreground" />
                <span className="text-muted-foreground truncate">{user.email}</span>
              </div>
              {user.phone && (
                <div className="flex items-center gap-3">
                  <Phone className="w-4 h-4 text-muted-foreground" />
                  <span className="text-muted-foreground">{user.phone}</span>
                </div>
              )}
              {user.joinDate && (
                <div className="flex items-center gap-3">
                  <Calendar className="w-4 h-4 text-muted-foreground" />
                  <span className="text-muted-foreground">Tham gia {user.joinDate}</span>
                </div>
              )}
            </div>

            <div className="mt-6 pt-4 border-t border-border grid grid-cols-2 gap-3 text-center">
              <div>
                <p className="text-2xl font-bold" style={{ color: '#ea5c21' }}>{orders.length}</p>
                <p className="text-xs text-muted-foreground">Đơn hàng</p>
              </div>
              <div>
                <p className="text-2xl font-bold" style={{ color: '#ea5c21' }}>
                  {orders.filter(o => o.status === 'delivered').length}
                </p>
                <p className="text-xs text-muted-foreground">Đã nhận</p>
              </div>
            </div>
          </div>
        </div>

        {/* Order History */}
        <div className="md:col-span-2">
          <div className="bg-white rounded-xl border border-border p-6">
            <div className="flex items-center gap-2 mb-5">
              <Package className="w-5 h-5" style={{ color: '#ea5c21' }} />
              <h3 className="font-semibold">Lịch sử đơn hàng</h3>
            </div>

            {loadingOrders ? (
              <div className="space-y-3">
                {[1, 2, 3].map(i => (
                  <div key={i} className="animate-pulse h-24 bg-gray-100 rounded-xl" />
                ))}
              </div>
            ) : orders.length === 0 ? (
              <div className="text-center py-12">
                <Package className="w-12 h-12 mx-auto text-gray-300 mb-3" />
                <p className="text-muted-foreground">Bạn chưa có đơn hàng nào</p>
                <Button
                  className="mt-4 text-white"
                  onClick={() => navigate('/products')}
                  style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}
                >
                  Mua sắm ngay
                </Button>
              </div>
            ) : (
              <div className="space-y-3">
                {orders.map(order => (
                  <div
                    key={order.id}
                    className="border border-border rounded-xl p-4 hover:border-orange-300 transition-colors cursor-pointer"
                    onClick={() => navigate(`/account/orders/${order.id}`)}
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div>
                        <p className="text-sm font-semibold">#{order.id}</p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                          {formatDate(order.created_at)}
                        </p>
                      </div>
                      <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${STATUS_COLORS[order.status] || 'bg-gray-100 text-gray-600'}`}>
                        {STATUS_LABELS[order.status] || order.status}
                      </span>
                    </div>

                    {/* Items preview */}
                    <div className="text-xs text-muted-foreground mb-3 line-clamp-1">
                      {order.items.map(i => `${i.product.name} x${i.quantity}`).join(', ')}
                    </div>

                    <div className="flex items-center justify-between">
                      <span className="text-xs text-muted-foreground">
                        {order.items.length} sản phẩm • {order.payment_method === 'cod' ? '💵 COD' : '🏦 Chuyển khoản'}
                      </span>
                      <span className="text-sm font-bold" style={{ color: '#ea5c21' }}>
                        {formatPrice(order.total)}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
