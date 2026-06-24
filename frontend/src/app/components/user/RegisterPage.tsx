import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { Eye, EyeOff, ArrowLeft } from 'lucide-react';
import { useAuth } from '../../store/AppContext';
import { Button } from '../ui/button';
import { toast } from 'sonner';

export function RegisterPage() {
  const navigate = useNavigate();
  const { register: registerUser } = useAuth();
  const [formData, setFormData] = useState({ name: '', email: '', phone: '', password: '', confirmPassword: '' });
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const errs: Record<string, string> = {};
    if (!formData.name.trim()) errs.name = 'Vui lòng nhập họ tên';
    if (!formData.email) errs.email = 'Vui lòng nhập email';
    else if (!/\S+@\S+\.\S+/.test(formData.email)) errs.email = 'Email không hợp lệ';
    if (formData.password.length < 6) errs.password = 'Mật khẩu tối thiểu 6 ký tự';
    if (formData.password !== formData.confirmPassword) errs.confirmPassword = 'Mật khẩu không khớp';
    return errs;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length > 0) { setErrors(errs); return; }
    setErrors({});
    setLoading(true);
    const result = await registerUser(formData.name, formData.email, formData.password, formData.phone || undefined);
    setLoading(false);
    if (result.success) {
      toast.success('Đăng ký thành công! Chào mừng bạn đến với TienProSport!');
      navigate('/');
    } else {
      setErrors({ general: result.message || 'Đăng ký thất bại' });
    }
  };


  const handleChange = (field: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData(prev => ({ ...prev, [field]: e.target.value }));
    if (errors[field]) setErrors(prev => ({ ...prev, [field]: '' }));
  };

  return (
    <div className="h-screen flex bg-gray-50/50 overflow-hidden">
      {/* Left - Illustration */}
      <div className="hidden lg:flex flex-1 relative overflow-hidden">
        <div className="absolute inset-0 bg-black">
          <img src="https://images.unsplash.com/photo-1579952363873-27f3bade9f55?w=1200&q=80" alt="Soccer Equipment Background" className="w-full h-full object-cover opacity-60" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#030213]/90 via-[#030213]/40 to-transparent"></div>
        </div>
        
        <div className="relative z-10 w-full flex flex-col justify-end p-12 xl:p-16">
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 mb-6 self-start">
            <span className="text-xs font-semibold text-white uppercase tracking-wider">Thành viên VIP</span>
          </div>
          <h3 className="text-white text-5xl font-black tracking-tight mb-4 leading-tight">
            Gia Nhập <br/>
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 to-orange-600">Cộng Đồng</span> Thể Thao
          </h3>
          <p className="text-gray-300 text-lg max-w-md mb-8 font-medium">
            Trở thành hội viên để tận hưởng những đặc quyền chưa từng có.
          </p>
          
          <div className="space-y-4 max-w-md">
            {[
              { icon: '🎁', text: 'Voucher 20% cho lần mua sắm đầu tiên' },
              { icon: '🚀', text: 'Miễn phí vận chuyển cho đơn từ 500k' },
              { icon: '⭐', text: 'Tích điểm đổi quà sau mỗi đơn hàng' },
            ].map(benefit => (
              <div key={benefit.text} className="flex items-center gap-4 bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/5 hover:bg-white/20 transition-colors">
                <span className="text-2xl">{benefit.icon}</span>
                <span className="text-sm text-gray-200 font-medium">{benefit.text}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Right - Form */}
      <div className="flex-1 flex items-center justify-center p-6 lg:p-8 overflow-y-auto">
        <div className="w-full max-w-[380px] bg-white p-6 sm:p-8 rounded-3xl shadow-2xl shadow-gray-200/50 border border-gray-100 my-auto">
          <Link to="/" className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-orange-600 mb-6 transition-colors">
            <ArrowLeft className="w-4 h-4" /> Về trang chủ
          </Link>

          <div className="flex items-center gap-2.5 mb-6">
            <div className="w-8 h-8 rounded-xl flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-600 shadow-lg shadow-orange-500/20">
              <span className="text-white text-xs font-black tracking-wider">TPS</span>
            </div>
            <span className="text-xl font-extrabold tracking-tight text-[#030213]">
              TienPro<span className="text-orange-500">Sport</span>
            </span>
          </div>

          <h2 className="text-2xl font-bold text-gray-900 mb-1.5 tracking-tight">Tạo tài khoản mới</h2>
          <p className="text-sm text-gray-500 mb-6 font-medium">Điền thông tin bên dưới để trở thành hội viên.</p>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-sm font-semibold text-gray-700">Họ và tên <span className="text-orange-500">*</span></label>
              <input
                type="text"
                value={formData.name}
                onChange={handleChange('name')}
                className="w-full px-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                placeholder="Nguyễn Văn A"
              />
              {errors.name && <p className="text-xs text-red-500 mt-1 font-medium">{errors.name}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-semibold text-gray-700">Email <span className="text-orange-500">*</span></label>
              <input
                type="email"
                value={formData.email}
                onChange={handleChange('email')}
                className="w-full px-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                placeholder="example@email.com"
              />
              {errors.email && <p className="text-xs text-red-500 mt-1 font-medium">{errors.email}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-semibold text-gray-700">Mật khẩu <span className="text-orange-500">*</span></label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={formData.password}
                  onChange={handleChange('password')}
                  className="w-full px-4 py-3 pr-12 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                  placeholder="Tối thiểu 6 ký tự"
                />
                <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-orange-500 transition-colors">
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              {errors.password && <p className="text-xs text-red-500 mt-1 font-medium">{errors.password}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-sm font-semibold text-gray-700">Xác nhận mật khẩu <span className="text-orange-500">*</span></label>
              <input
                type="password"
                value={formData.confirmPassword}
                onChange={handleChange('confirmPassword')}
                className="w-full px-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:outline-none focus:border-orange-500 focus:ring-4 focus:ring-orange-500/10 transition-all"
                placeholder="Nhập lại mật khẩu"
              />
              {errors.confirmPassword && <p className="text-xs text-red-500 mt-1 font-medium">{errors.confirmPassword}</p>}
            </div>

            <div className="flex items-start gap-3 mt-6">
              <input type="checkbox" required className="mt-1 w-4 h-4 accent-orange-500 rounded cursor-pointer" />
              <p className="text-xs text-gray-500 font-medium leading-relaxed">
                Tôi đồng ý với{' '}
                <a href="#" className="text-orange-600 hover:text-orange-700 hover:underline">Điều khoản dịch vụ</a>
                {' '}và{' '}
                <a href="#" className="text-orange-600 hover:text-orange-700 hover:underline">Chính sách bảo mật</a>
                {' '}của TienProSport.
              </p>
            </div>

            {errors.general && (
              <div className="p-3 bg-red-50 border border-red-100 rounded-xl flex items-start gap-2">
                <span className="text-red-500 text-xs mt-0.5">⚠️</span>
                <p className="text-xs text-red-600 font-medium">{errors.general}</p>
              </div>
            )}

            <Button
              type="submit"
              className="w-full py-5 mt-2 text-white text-base font-bold bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 rounded-xl shadow-lg shadow-orange-500/25 transition-all hover:-translate-y-0.5 border-0"
              disabled={loading}
            >
              {loading ? 'Đang xử lý...' : 'Đăng ký tài khoản'}
            </Button>
          </form>

          <p className="text-sm text-center text-gray-500 mt-6 font-medium">
            Đã có tài khoản?{' '}
            <Link to="/login" className="text-orange-600 hover:text-orange-700 hover:underline font-bold transition-colors">
              Đăng nhập ngay
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
