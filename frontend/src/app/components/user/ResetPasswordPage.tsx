import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { ArrowLeft, Eye, EyeOff, KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { authService } from '../../services/authService';
import { Button } from '../ui/button';

export function ResetPasswordPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const email = searchParams.get('email') ?? '';
  const token = searchParams.get('token') ?? '';
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const invalidLink = !email || !token;

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');

    if (password.length < 8) {
      setError('Mật khẩu mới cần có ít nhất 8 ký tự.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Xác nhận mật khẩu chưa khớp.');
      return;
    }

    setLoading(true);
    try {
      await authService.resetPassword({
        email,
        token,
        mat_khau: password,
        mat_khau_confirmation: passwordConfirmation,
      });
      toast.success('Mật khẩu đã được đặt lại. Vui lòng đăng nhập lại.');
      navigate('/login', { replace: true });
    } catch {
      setError('Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="min-h-screen bg-gray-50 px-6 py-10 sm:py-16">
      <section className="mx-auto w-full max-w-[420px] rounded-2xl border border-gray-100 bg-white p-6 shadow-xl shadow-gray-200/50 sm:p-8">
        <Link to="/login" className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-orange-600">
          <ArrowLeft className="size-4" /> Quay lại đăng nhập
        </Link>

        <div className="mt-7 flex size-12 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
          <KeyRound className="size-6" />
        </div>
        <h1 className="mt-5 text-2xl font-bold text-gray-900">Tạo mật khẩu mới</h1>
        <p className="mt-2 text-sm leading-6 text-gray-500">Mật khẩu mới cần có ít nhất 8 ký tự. Sau khi hoàn tất, bạn sẽ đăng nhập lại bằng mật khẩu này.</p>

        {invalidLink ? (
          <div className="mt-6 rounded-xl border border-red-100 bg-red-50 p-4 text-sm leading-5 text-red-700">
            Liên kết đặt lại mật khẩu không đầy đủ. Hãy yêu cầu một liên kết mới.
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="mt-6 space-y-5">
            <div className="space-y-1.5">
              <label htmlFor="new-password" className="text-sm font-semibold text-gray-700">Mật khẩu mới</label>
              <div className="relative">
                <input
                  id="new-password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 pr-12 text-sm outline-none transition-all focus:border-orange-500 focus:bg-white focus:ring-4 focus:ring-orange-500/10"
                  required
                />
                <button type="button" onClick={() => setShowPassword((value) => !value)} className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-orange-500" aria-label={showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'}>
                  {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
            </div>

            <div className="space-y-1.5">
              <label htmlFor="confirm-password" className="text-sm font-semibold text-gray-700">Xác nhận mật khẩu mới</label>
              <input
                id="confirm-password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="new-password"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition-all focus:border-orange-500 focus:bg-white focus:ring-4 focus:ring-orange-500/10"
                required
              />
            </div>

            {error ? <p className="rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-medium text-red-600">{error}</p> : null}

            <Button type="submit" disabled={loading} className="h-11 w-full bg-orange-600 text-sm font-semibold hover:bg-orange-700">
              {loading ? 'Đang đặt lại mật khẩu...' : 'Đặt lại mật khẩu'}
            </Button>
          </form>
        )}
      </section>
    </main>
  );
}
