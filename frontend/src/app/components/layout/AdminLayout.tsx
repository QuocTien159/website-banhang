import { useState } from 'react';
import { Link, Outlet, useLocation, useNavigate } from 'react-router';
import {
  AlertTriangle,
  Bell,
  ChevronDown,
  ChevronRight,
  Gift,
  History,
  LayoutDashboard,
  LogOut,
  Menu,
  MessageSquare,
  Package,
  PackageCheck,
  PackagePlus,
  Settings,
  ShoppingBag,
  SlidersHorizontal,
  Tags,
  Users,
  Warehouse,
} from 'lucide-react';
import { useAuth } from '../../store/AppContext';
import { Toaster } from '../ui/sonner';

const mainItems = [
  { label: 'Tổng quan', href: '/admin', icon: LayoutDashboard, exact: true },
  { label: 'Sản phẩm', href: '/admin/products', icon: Package },
  { label: 'Danh mục', href: '/admin/categories', icon: Tags },
  { label: 'Thuộc tính', href: '/admin/attributes', icon: SlidersHorizontal },
];

const inventoryItems = [
  { label: 'Nhập kho', href: '/admin/stock-import', icon: PackagePlus },
  { label: 'Lịch sử nhập kho', href: '/admin/stock-receipts', icon: History },
  { label: 'Lịch sử kho', href: '/admin/stock-movements', icon: History },
  { label: 'Cảnh báo tồn kho', href: '/admin/stock-alerts', icon: AlertTriangle },
];

const orderItems = [
  { label: 'Đơn hàng', href: '/admin/orders', icon: ShoppingBag },
  { label: 'Trả hàng', href: '/admin/returns', icon: PackageCheck },
];

const configItems = [
  { label: 'Thông báo', href: '/admin/announcements', icon: Bell },
  { label: 'Vận chuyển & thanh toán', href: '/admin/payment-shipping', icon: Settings },
];

const websiteItems = [
  { label: 'Khuyến mãi', href: '/admin/promotions', icon: Gift },
  { label: 'Đánh giá', href: '/admin/reviews', icon: MessageSquare },
  { label: 'Khách hàng', href: '/admin/customers', icon: Users },
];

const allItems = [...mainItems, ...inventoryItems, ...orderItems, ...configItems, ...websiteItems];

export function AdminLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [inventoryOpen, setInventoryOpen] = useState(() => location.pathname.startsWith('/admin/stock-'));
  const [ordersOpen, setOrdersOpen] = useState(() => orderItems.some((item) => location.pathname.startsWith(item.href)));
  const [configOpen, setConfigOpen] = useState(() => configItems.some((item) => location.pathname.startsWith(item.href)));
  const [websiteOpen, setWebsiteOpen] = useState(() => websiteItems.some((item) => location.pathname.startsWith(item.href)));

  const isActive = (href: string, exact?: boolean) => exact ? location.pathname === href : location.pathname.startsWith(href);
  const currentLabel = allItems.find((item) => isActive(item.href, item.exact))?.label ?? 'Admin';

  const renderItem = (item: typeof allItems[number], child = false) => (
    <Link
      key={item.href}
      to={item.href}
      className={`flex items-center gap-3 px-3 py-2.5 rounded-lg ${
        isActive(item.href, item.exact)
          ? 'bg-orange-500 text-white'
          : 'text-gray-400 hover:bg-white/10 hover:text-white'
      } ${!sidebarOpen ? 'justify-center' : ''} ${child && sidebarOpen ? 'ml-5 py-2' : ''}`}
      title={!sidebarOpen ? item.label : undefined}
    >
      <item.icon className="w-4 h-4 shrink-0" />
      {sidebarOpen && <span className="text-sm">{item.label}</span>}
    </Link>
  );

  const renderGroup = (
    label: string,
    icon: typeof Warehouse,
    items: typeof allItems,
    open: boolean,
    setOpen: (value: boolean | ((current: boolean) => boolean)) => void,
  ) => {
    const Icon = icon;
    const active = items.some((item) => isActive(item.href));

    return (
      <div>
        <button
          type="button"
          onClick={() => setOpen((value) => !value)}
          className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg ${
            active ? 'bg-white/10 text-white' : 'text-gray-400 hover:bg-white/10 hover:text-white'
          } ${!sidebarOpen ? 'justify-center' : ''}`}
          title={!sidebarOpen ? label : undefined}
        >
          <Icon className="w-4 h-4 shrink-0" />
          {sidebarOpen && (
            <>
              <span className="text-sm flex-1 text-left">{label}</span>
              {open ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
            </>
          )}
        </button>
        {open && sidebarOpen && (
          <div className="mt-1 space-y-1">
            {items.map((item) => renderItem(item, true))}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="min-h-screen flex bg-[#f5f5f7]">
      <aside className={`${sidebarOpen ? 'w-64' : 'w-16'} h-screen sticky top-0 transition-all bg-[#030213] flex flex-col shrink-0`}>
        <div className="h-16 flex items-center px-4 border-b border-white/10 shrink-0">
          <Link to="/" className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-md bg-orange-600 text-white grid place-items-center text-xs font-bold">TPS</div>
            {sidebarOpen && <span className="text-white text-sm font-bold">TienProSport Admin</span>}
          </Link>
        </div>

        <nav className="flex-1 py-4 space-y-1 px-2 overflow-y-auto min-h-0">
          {mainItems.map((item) => renderItem(item))}
          {renderGroup('Quản lý kho', Warehouse, inventoryItems, inventoryOpen, setInventoryOpen)}
          {renderGroup('Quản lý đơn', ShoppingBag, orderItems, ordersOpen, setOrdersOpen)}
          {renderGroup('Cấu hình & thông báo', Settings, configItems, configOpen, setConfigOpen)}
          {renderGroup('Quản lý Website', Users, websiteItems, websiteOpen, setWebsiteOpen)}
        </nav>

        <div className="p-3 border-t border-white/10 flex items-center gap-2 shrink-0">
          {sidebarOpen && (
            <div className="flex-1 min-w-0">
              <p className="text-xs text-white truncate">{user?.name}</p>
              <p className="text-xs text-gray-500 truncate">{user?.email}</p>
            </div>
          )}
          <button
            onClick={async () => { await logout(); navigate('/'); }}
            className="text-gray-400 hover:text-white"
            title="Đăng xuất"
          >
            <LogOut className="w-4 h-4" />
          </button>
        </div>
      </aside>

      <div className="flex-1 min-w-0">
        <header className="h-16 bg-white border-b flex items-center px-6 gap-4 sticky top-0 z-10">
          <button onClick={() => setSidebarOpen(!sidebarOpen)} className="text-muted-foreground">
            {sidebarOpen ? <ChevronRight className="w-5 h-5 rotate-180" /> : <Menu className="w-5 h-5" />}
          </button>
          <span className="text-sm text-muted-foreground flex-1">{currentLabel}</span>
          <Link to="/" className="text-sm text-muted-foreground">← Về trang khách hàng</Link>
        </header>
        <main className="p-6 overflow-auto"><Outlet /></main>
      </div>

      <Toaster richColors position="top-right" />
    </div>
  );
}
