import { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router';
import { Eye, EyeOff, ArrowLeft } from 'lucide-react';
import { useAuth } from '../../store/AppContext';
import { authService } from '../../services/authService';
import { Button } from '../ui/button';
import { toast } from 'sonner';

export function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [googleLoading, setGoogleLoading] = useState(false);
  const [error, setError] = useState('');

  const from = (location.state as { from?: string })?.from || '/';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const success = await login(email, password);
      if (success) {
        toast.success('Đăng nhập thành công!');
        // Re-fetch user to determine role
        const storedUser = authService.getStoredUser();
        navigate(['admin', 'staff'].includes(storedUser?.role) ? '/admin' : from, { replace: true });
      } else {
        setError('Email hoặc mật khẩu không đúng.');
      }
    } catch {
      setError('Có lỗi xảy ra. Vui lòng thử lại.');
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleLogin = () => {
    setGoogleLoading(true);
    window.location.assign(authService.googleLoginUrl(from));
  };

  return (
    <div className="h-screen flex bg-gray-50/50 overflow-hidden">
      {/* Left - Form */}
      <div className="flex-1 flex items-center justify-center p-6 lg:p-8 overflow-y-auto">
        <div className="w-full max-w-[380px] bg-white p-6 sm:p-8 rounded-3xl shadow-2xl shadow-gray-200/50 border border-gray-100 my-auto">
          <Link to="/" className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-orange-600 mb-6 transition-colors">
            <ArrowLeft className="w-4 h-4" /> Về trang chủ
          </Link>

          {/* Logo */}
          <div className="flex items-center gap-2.5 mb-6">
            <div className="w-8 h-8 rounded-xl flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-600 shadow-lg shadow-orange-500/20">
              <span className="text-white text-xs font-black tracking-wider">TPS</span>
            </div>
            <span className="text-xl font-extrabold tracking-tight text-[#030213]">
              TienPro<span className="text-orange-500">Sport</span>
            </span>
          </div>

          <h2 className="text-2xl font-bold text-gray-900 mb-1.5 tracking-tight">Đăng nhập</h2>
          <p className="text-sm text-gray-500 mb-6 font-medium">Chào mừng bạn quay lại! Vui lòng nhập thông tin.</p>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <label className="text-sm font-semibold text-gray-700">Email</label>
              <input
                type="email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                className="w-full px-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                placeholder="example@email.com"
                required
              />
            </div>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <label className="text-sm font-semibold text-gray-700">Mật khẩu</label>
                <a href="#" className="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">Quên mật khẩu?</a>
              </div>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  className="w-full px-4 py-3 pr-12 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                  placeholder="••••••••"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-orange-500 transition-colors"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {error && (
              <div className="p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                <span className="text-red-500 text-xs mt-0.5">⚠️</span>
                <p className="text-xs text-red-600 font-medium">{error}</p>
              </div>
            )}

            <Button
              type="submit"
              className="w-full py-5 mt-2 text-white text-base font-bold bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 rounded-xl shadow-lg shadow-orange-500/25 transition-all hover:-translate-y-0.5 border-0"
              disabled={loading}
            >
              {loading ? 'Đang xác thực...' : 'Đăng nhập'}
            </Button>
          </form>

          <div className="my-6 flex items-center gap-3 text-xs text-gray-400"><span className="h-px flex-1 bg-gray-200" />hoặc<span className="h-px flex-1 bg-gray-200" /></div>
          <Button type="button" variant="outline" onClick={handleGoogleLogin} disabled={loading || googleLoading} className="h-11 w-full border-gray-200 bg-white text-sm font-semibold hover:bg-gray-50">
            <span className="grid size-5 place-items-center rounded-full bg-white font-bold text-[#4285f4]">G</span>
            {googleLoading ? 'Đang chuyển đến Google...' : 'Tiếp tục với Google'}
          </Button>

          <p className="text-sm text-center text-gray-500 mt-6 font-medium">
            Chưa có tài khoản?{' '}
            <Link to="/register" className="text-orange-600 hover:text-orange-700 hover:underline font-bold transition-colors">
              Đăng ký ngay
            </Link>
          </p>
        </div>
      </div>

      {/* Right - Illustration */}
      <div className="hidden lg:flex flex-1 relative overflow-hidden">
        <div className="absolute inset-0 bg-black">
          <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1200&q=80" alt="Sports Shoes Equipment" className="w-full h-full object-cover opacity-60" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#030213]/90 via-[#030213]/40 to-transparent"></div>
        </div>
        
        <div className="relative z-10 w-full flex flex-col justify-end p-12 xl:p-16">
          <h3 className="text-white text-5xl font-black tracking-tight mb-4 leading-tight">
            Khám phá <br/>
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-orange-600">Sức Mạnh</span> Bên Trong
          </h3>
          <p className="text-gray-300 text-lg max-w-md mb-12 font-medium">
            Hàng ngàn sản phẩm thể thao cao cấp đang chờ bạn khám phá.
          </p>
          
          <div className="grid grid-cols-3 gap-6">
            {[
              { label: 'Sản phẩm chính hãng', value: '100%' },
              { label: 'Khách hàng tin dùng', value: '10K+' },
              { label: 'Đánh giá tích cực', value: '4.9/5' },
            ].map(stat => (
              <div key={stat.label} className="bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/10">
                <p className="text-2xl font-black text-white mb-1">{stat.value}</p>
                <p className="text-xs text-gray-300 font-medium">{stat.label}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
