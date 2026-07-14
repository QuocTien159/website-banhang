import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { Loader2 } from 'lucide-react';
import { authService } from '../../services/authService';

const errorMessages: Record<string, string> = {
  cancelled: 'Bạn đã hủy đăng nhập bằng Google.',
  email_not_verified: 'Tài khoản Google cần có email đã xác minh.',
  account_locked: 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.',
  account_mismatch: 'Thông tin tài khoản Google không khớp với tài khoản đã liên kết.',
  account_sync: 'Không thể lưu hoặc liên kết tài khoản. Vui lòng thử lại sau.',
  configuration: 'Google đăng nhập chưa được cấu hình đầy đủ.',
  provider: 'Không thể xác thực với Google. Vui lòng thử lại.',
};

export function GoogleAuthCallbackPage() {
  const [searchParams] = useSearchParams();
  const [error, setError] = useState(() => searchParams.get('error') ?? '');

  useEffect(() => {
    const code = searchParams.get('code');
    if (!code || error) return;

    authService.completeGoogleLogin(code)
      .then(({ user, return_to }) => {
        const safeReturnTo = return_to.startsWith('/') && !return_to.startsWith('//') ? return_to : '/';
        const destination = ['admin', 'staff'].includes(user.role) ? '/admin' : safeReturnTo;
        window.location.replace(destination);
      })
      .catch((requestError: any) => {
        setError(requestError.response?.data?.message ?? 'Không thể hoàn tất đăng nhập Google. Vui lòng thử lại.');
      });
  }, [error, searchParams]);

  return (
    <main className="min-h-screen grid place-items-center bg-gray-50 p-5">
      <section className="w-full max-w-md rounded-xl border bg-white p-6 text-center shadow-sm">
        {error ? <>
          <h1 className="text-lg font-semibold">Đăng nhập Google chưa hoàn tất</h1>
          <p className="mt-2 text-sm text-muted-foreground">{errorMessages[error] ?? error}</p>
          <Link to="/login" className="mt-5 inline-flex h-9 items-center rounded-md bg-orange-600 px-4 text-sm font-medium text-white hover:bg-orange-700">Quay lại đăng nhập</Link>
        </> : <>
          <Loader2 className="mx-auto size-6 animate-spin text-orange-600" />
          <h1 className="mt-3 text-lg font-semibold">Đang hoàn tất đăng nhập</h1>
          <p className="mt-2 text-sm text-muted-foreground">Hệ thống đang xác thực tài khoản Google của bạn.</p>
        </>}
      </section>
    </main>
  );
}
