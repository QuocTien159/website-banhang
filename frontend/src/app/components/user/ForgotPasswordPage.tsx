import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { ArrowLeft, CheckCircle2, Mail } from 'lucide-react';
import { authService } from '../../services/authService';
import { Button } from '../ui/button';

const RESEND_COOLDOWN_SECONDS = 60;

export function ForgotPasswordPage() {
  const [searchParams] = useSearchParams();
  const [email, setEmail] = useState(searchParams.get('email') ?? '');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);
  const [cooldown, setCooldown] = useState(0);
  const [error, setError] = useState('');

  useEffect(() => {
    if (cooldown <= 0) return;

    const timer = window.setInterval(() => {
      setCooldown((seconds) => Math.max(0, seconds - 1));
    }, 1000);

    return () => window.clearInterval(timer);
  }, [cooldown]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      await authService.requestPasswordReset(email.trim());
      setSent(true);
      setCooldown(RESEND_COOLDOWN_SECONDS);
    } catch {
      setError('Không thể gửi hướng dẫn lúc này. Vui lòng thử lại sau.');
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
          <Mail className="size-6" />
        </div>
        <h1 className="mt-5 text-2xl font-bold text-gray-900">Quên mật khẩu?</h1>
        <p className="mt-2 text-sm leading-6 text-gray-500">
          Nhập email đã dùng để đăng ký. Chúng tôi sẽ gửi liên kết đặt lại mật khẩu nếu tài khoản hợp lệ.
        </p>

        {sent ? (
          <div className="mt-6 rounded-xl border border-emerald-100 bg-emerald-50 p-4">
            <div className="flex gap-3">
              <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
              <div>
                <p className="text-sm font-semibold text-emerald-800">Đã tiếp nhận yêu cầu</p>
                <p className="mt-1 text-sm leading-5 text-emerald-700">
                  Hãy kiểm tra hộp thư của bạn, kể cả mục Spam. Liên kết có hiệu lực trong 60 phút.
                </p>
              </div>
            </div>
          </div>
        ) : null}

        <form onSubmit={handleSubmit} className="mt-6 space-y-5">
          <div className="space-y-1.5">
            <label htmlFor="reset-email" className="text-sm font-semibold text-gray-700">Email</label>
            <input
              id="reset-email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm outline-none transition-all focus:border-orange-500 focus:bg-white focus:ring-4 focus:ring-orange-500/10"
              placeholder="example@gmail.com"
              required
            />
          </div>

          {error ? <p className="rounded-xl border border-red-100 bg-red-50 p-3 text-sm font-medium text-red-600">{error}</p> : null}

          <Button type="submit" disabled={loading || cooldown > 0} className="h-11 w-full bg-orange-600 text-sm font-semibold hover:bg-orange-700">
            {loading ? 'Đang gửi...' : cooldown > 0 ? `Gửi lại sau ${cooldown}s` : sent ? 'Gửi lại email' : 'Gửi liên kết đặt lại'}
          </Button>
        </form>
      </section>
    </main>
  );
}
