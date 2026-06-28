import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router';
import { ShoppingCart, User, Menu, X, Search, LogOut, Package, LayoutDashboard, Heart, Star } from 'lucide-react';
import { useAuth, useCart } from '../../store/AppContext';
import { Badge } from '../ui/badge';
import { Button } from '../ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu';

export function Header() {
  const { user, isAuthenticated, logout } = useAuth();
  const { totalItems } = useCart();
  const navigate = useNavigate();
  const location = useLocation();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [search, setSearch] = useState('');

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (search.trim()) {
      navigate(`/products?q=${encodeURIComponent(search.trim())}`);
      setSearch('');
      setMobileOpen(false);
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const navLinks = [
    { label: 'Trang chủ', href: '/' },
    { label: 'Sản phẩm', href: '/products' },
    { label: 'Đơn mua', href: '/account' },
    { label: 'Hồ sơ', href: '/profile' },
  ];

  const isActive = (href: string) => {
    if (href === '/') return location.pathname === '/';
    return location.pathname.startsWith(href.split('?')[0]);
  };

  return (
    <header className="sticky top-0 z-50 bg-white/85 backdrop-blur-lg border-b border-gray-200/50 shadow-sm transition-all duration-300">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        <div className="flex items-center h-[72px] gap-6">
          {/* Logo */}
          <Link to="/" className="flex items-center gap-2.5 shrink-0 group">
            <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-600 shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform duration-300">
              <span className="text-white text-sm font-black tracking-wider">TPS</span>
            </div>
            <span className="text-xl font-extrabold tracking-tight text-[#030213]">
              TienPro<span className="text-orange-600">Sport</span>
            </span>
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-1.5 ml-4">
            {navLinks.map(link => (
              <Link
                key={link.href}
                to={link.href}
                className={`px-4 py-2 rounded-full text-sm font-semibold transition-all duration-300 ${
                  isActive(link.href)
                    ? 'bg-orange-50 text-orange-600 shadow-sm ring-1 ring-orange-500/10'
                    : 'text-gray-600 hover:text-orange-600 hover:bg-orange-50/50'
                }`}
              >
                {link.label}
              </Link>
            ))}
          </nav>

          {/* Search */}
          <form onSubmit={handleSearch} className="hidden md:flex flex-1 max-w-sm ml-auto group">
            <div className="relative w-full">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-orange-500 w-4 h-4 transition-colors duration-300" />
              <input
                type="text"
                placeholder="Tìm kiếm sản phẩm..."
                value={search}
                onChange={e => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 text-sm bg-gray-100/80 hover:bg-gray-100 rounded-full border border-transparent focus:bg-white focus:border-orange-400 focus:ring-4 focus:ring-orange-500/10 focus:outline-none transition-all duration-300"
              />
            </div>
          </form>

          {/* Actions */}
          <div className="flex items-center gap-1 ml-2">
            {isAuthenticated && (
              <Button variant="ghost" size="icon" className="rounded-full hover:bg-orange-50 hover:text-orange-600 transition-colors" onClick={() => navigate('/wishlist')}>
                <Heart className="w-5 h-5" />
              </Button>
            )}
            {/* Cart */}
            <Button variant="ghost" size="icon" className="relative rounded-full hover:bg-orange-50 hover:text-orange-600 transition-colors" onClick={() => navigate('/cart')}>
              <ShoppingCart className="w-5 h-5" />
              {totalItems > 0 && (
                <span className="absolute 0 -top-1 -right-1 w-5 h-5 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 text-white flex items-center justify-center text-[10px] font-bold shadow-sm ring-2 ring-white">
                  {totalItems > 99 ? '99+' : totalItems}
                </span>
              )}
            </Button>

            {/* User */}
            {isAuthenticated ? (
              <div className="flex items-center ml-1">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="rounded-full ring-2 ring-transparent hover:ring-orange-200 transition-all">
                      <div className="w-8 h-8 rounded-full flex items-center justify-center bg-gradient-to-br from-[#030213] to-gray-800 text-white text-sm font-bold shadow-sm">
                        {(user?.name || user?.email || '?').charAt(0).toUpperCase()}
                      </div>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56 rounded-2xl p-2 shadow-xl border-gray-100 z-[100]">
                    <div className="px-3 py-2.5 mb-1 bg-gray-50/50 rounded-xl">
                      <p className="text-sm font-semibold truncate">{user?.name || 'Người dùng'}</p>
                      <p className="text-xs text-gray-500 truncate mt-0.5">{user?.email}</p>
                    </div>
                    <DropdownMenuSeparator className="opacity-50" />
                    {(user?.role === 'admin' || user?.role === 'staff') && (
                      <DropdownMenuItem onClick={() => navigate('/admin')} className="rounded-lg cursor-pointer py-2.5 font-medium">
                        <LayoutDashboard className="w-4 h-4 mr-3 text-gray-400" />
                        Quản trị hệ thống
                      </DropdownMenuItem>
                    )}
                    <DropdownMenuItem onClick={() => navigate('/profile')} className="rounded-lg cursor-pointer py-2.5 font-medium">
                      <User className="w-4 h-4 mr-3 text-gray-400" />
                      Hồ sơ cá nhân
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => navigate('/account')} className="rounded-lg cursor-pointer py-2.5 font-medium">
                      <Package className="w-4 h-4 mr-3 text-gray-400" />
                      Đơn hàng của tôi
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => navigate('/wishlist')} className="rounded-lg cursor-pointer py-2.5 font-medium">
                      <Heart className="w-4 h-4 mr-3 text-gray-400" />
                      Danh sách yêu thích
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => navigate('/reviews')} className="rounded-lg cursor-pointer py-2.5 font-medium">
                      <Star className="w-4 h-4 mr-3 text-gray-400" />
                      Đánh giá của tôi
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="opacity-50" />
                    <DropdownMenuItem onClick={handleLogout} className="rounded-lg cursor-pointer py-2.5 text-red-600 font-medium focus:bg-red-50 focus:text-red-700">
                      <LogOut className="w-4 h-4 mr-3" />
                      Đăng xuất
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                {/* Standalone Logout Button */}
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={handleLogout}
                  title="Đăng xuất"
                  className="hidden md:flex ml-1.5 rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                >
                  <LogOut className="w-5 h-5" />
                </Button>
              </div>
            ) : (
              <Button
                onClick={() => navigate('/login')}
                className="hidden md:flex items-center gap-2 ml-2 rounded-full bg-[#030213] text-white hover:bg-gray-800 hover:shadow-md transition-all px-5"
              >
                <User className="w-4 h-4" />
                <span className="font-semibold">Đăng nhập</span>
              </Button>
            )}

            {/* Mobile menu toggle */}
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              onClick={() => setMobileOpen(!mobileOpen)}
            >
              {mobileOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </Button>
          </div>
        </div>

        {/* Mobile menu */}
        {mobileOpen && (
          <div className="md:hidden border-t border-border py-3 space-y-2">
            <form onSubmit={handleSearch} className="px-1 pb-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
                <input
                  type="text"
                  placeholder="Tìm sản phẩm..."
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  className="w-full pl-8 pr-3 py-2 text-sm bg-muted rounded-lg border border-transparent focus:border-orange-400 focus:outline-none"
                />
              </div>
            </form>
            {navLinks.map(link => (
              <Link
                key={link.href}
                to={link.href}
                onClick={() => setMobileOpen(false)}
                className={`block px-3 py-2 rounded-md text-sm ${
                  isActive(link.href) ? 'bg-orange-50 text-orange-600' : 'text-muted-foreground'
                }`}
              >
                {link.label}
              </Link>
            ))}
            {!isAuthenticated && (
              <Link
                to="/login"
                onClick={() => setMobileOpen(false)}
                className="block px-3 py-2 rounded-md text-sm text-muted-foreground"
              >
                Đăng nhập / Đăng ký
              </Link>
            )}
            {isAuthenticated && (
              <button
                onClick={() => {
                  setMobileOpen(false);
                  handleLogout();
                }}
                className="w-full text-left px-3 py-2 rounded-md text-sm text-red-600 flex items-center gap-2"
              >
                <LogOut className="w-4 h-4" />
                Đăng xuất
              </button>
            )}
          </div>
        )}
      </div>
    </header>
  );
}
