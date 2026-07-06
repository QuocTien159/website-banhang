import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { AlertCircle, CalendarDays, Lock, Mail, Phone, RotateCcw, Save, ShieldCheck, User } from 'lucide-react';
import { useAuth } from '../../store/AppContext';
import { Alert, AlertDescription } from '../ui/alert';
import { Button } from '../ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Separator } from '../ui/separator';

type ProfileForm = {
  name: string;
  phone: string;
  current_password: string;
  new_password: string;
  new_password_confirmation: string;
};

type FieldErrors = Partial<Record<keyof ProfileForm, string>>;

const emptyPasswordFields = {
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
};

const phonePattern = /^[0-9]{10,11}$/;

export function ProfilePage() {
  const navigate = useNavigate();
  const { user, isAuthenticated, isLoading, updateProfile } = useAuth();
  const [form, setForm] = useState<ProfileForm>({
    name: '',
    phone: '',
    ...emptyPasswordFields,
  });
  const [errors, setErrors] = useState<FieldErrors>({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      navigate('/login', { replace: true, state: { from: '/profile' } });
    }
  }, [isAuthenticated, isLoading, navigate]);

  useEffect(() => {
    if (!user) return;
    setForm({
      name: user.name ?? '',
      phone: user.phone ?? '',
      ...emptyPasswordFields,
    });
    setErrors({});
  }, [user]);

  const hasPasswordInput = !!(form.current_password || form.new_password || form.new_password_confirmation);
  const isDirty = useMemo(() => {
    if (!user) return false;
    return (
      form.name !== (user.name ?? '') ||
      form.phone !== (user.phone ?? '') ||
      hasPasswordInput
    );
  }, [form, hasPasswordInput, user]);

  const updateField = (field: keyof ProfileForm, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: undefined }));
  };

  const resetForm = () => {
    if (!user) return;
    setForm({
      name: user.name ?? '',
      phone: user.phone ?? '',
      ...emptyPasswordFields,
    });
    setErrors({});
  };

  const validate = () => {
    const nextErrors: FieldErrors = {};
    if (!form.name.trim()) nextErrors.name = 'Vui lòng nhập họ tên.';
    if (form.phone.trim() && !phonePattern.test(form.phone.trim())) {
      nextErrors.phone = 'Số điện thoại cần gồm 10-11 chữ số.';
    }
    if (hasPasswordInput) {
      if (!form.current_password) nextErrors.current_password = 'Nhập mật khẩu hiện tại.';
      if (form.new_password.length < 6) nextErrors.new_password = 'Mật khẩu mới cần tối thiểu 6 ký tự.';
      if (form.new_password !== form.new_password_confirmation) {
        nextErrors.new_password_confirmation = 'Mật khẩu xác nhận chưa khớp.';
      }
    }
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!validate()) return;

    setSaving(true);
    try {
      await updateProfile({
        name: form.name.trim(),
        phone: form.phone.trim() || null,
        ...(hasPasswordInput ? {
          current_password: form.current_password,
          new_password: form.new_password,
          new_password_confirmation: form.new_password_confirmation,
        } : {}),
      });
      setForm((current) => ({ ...current, ...emptyPasswordFields }));
      toast.success('Đã lưu hồ sơ cá nhân.');
    } catch (error: any) {
      const apiErrors = error.response?.data?.errors;
      if (apiErrors) {
        setErrors(Object.fromEntries(
          Object.entries(apiErrors).map(([key, value]) => [key, (value as string[])[0]])
        ) as FieldErrors);
      }
      toast.error(error.response?.data?.message ?? 'Không thể lưu hồ sơ. Vui lòng kiểm tra lại thông tin.');
    } finally {
      setSaving(false);
    }
  };

  if (isLoading || !user) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-10">
        <div className="h-48 rounded-xl bg-gray-100 animate-pulse" />
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-8 md:py-10">
      <div className="mb-6">
        <p className="text-sm font-medium text-orange-600">Tài khoản</p>
        <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-gray-950">Hồ sơ cá nhân</h1>
        <p className="text-sm text-muted-foreground mt-2">
          Quản lý thông tin liên hệ và bảo mật tài khoản TienProSport của bạn.
        </p>
      </div>

      <div className="grid lg:grid-cols-[320px_1fr] gap-6 items-start">
        <aside className="bg-white border rounded-xl p-5">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 text-white grid place-items-center text-xl font-black shadow-lg shadow-orange-500/20">
              {user.name?.charAt(0).toUpperCase() || 'U'}
            </div>
            <div className="min-w-0">
              <h2 className="font-semibold text-gray-950 truncate">{user.name}</h2>
              <p className="text-sm text-muted-foreground truncate">{user.email}</p>
            </div>
          </div>

          <Separator className="my-5" />

          <div className="space-y-3 text-sm">
            <div className="flex items-center gap-3 text-muted-foreground">
              <ShieldCheck className="w-4 h-4 text-orange-600" />
              <span>{user.role === 'admin' ? 'Quản trị viên' : user.role === 'staff' ? 'Nhân viên' : 'Khách hàng'}</span>
            </div>
            {user.phone && (
              <div className="flex items-center gap-3 text-muted-foreground">
                <Phone className="w-4 h-4 text-orange-600" />
                <span>{user.phone}</span>
              </div>
            )}
            {user.joinDate && (
              <div className="flex items-center gap-3 text-muted-foreground">
                <CalendarDays className="w-4 h-4 text-orange-600" />
                <span>Tham gia từ {user.joinDate}</span>
              </div>
            )}
          </div>
        </aside>

        <form onSubmit={handleSubmit} className="space-y-5">
          <Card className="rounded-xl">
            <CardHeader className="pb-4">
              <CardTitle className="flex items-center gap-2 text-lg">
                <User className="w-5 h-5 text-orange-600" />
                Thông tin tài khoản
              </CardTitle>
              <CardDescription>Email dùng để đăng nhập và hiện chưa thể thay đổi.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
              <div className="grid md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Họ tên</Label>
                  <Input
                    id="name"
                    value={form.name}
                    onChange={(event) => updateField('name', event.target.value)}
                    placeholder="Nhập họ tên"
                    aria-invalid={!!errors.name}
                  />
                  {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="phone">Số điện thoại</Label>
                  <Input
                    id="phone"
                    value={form.phone}
                    onChange={(event) => updateField('phone', event.target.value)}
                    placeholder="Ví dụ: 0909123456"
                    inputMode="tel"
                    aria-invalid={!!errors.phone}
                  />
                  {errors.phone && <p className="text-xs text-red-600">{errors.phone}</p>}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                  <Input id="email" value={user.email} readOnly className="pl-9 bg-gray-50 text-muted-foreground" />
                </div>
              </div>

              <Alert className="bg-orange-50 border-orange-200 text-orange-900">
                <AlertCircle className="w-4 h-4" />
                <AlertDescription>
                  Nếu cần đổi email đăng nhập, vui lòng liên hệ bộ phận hỗ trợ để xác minh tài khoản.
                </AlertDescription>
              </Alert>
            </CardContent>
          </Card>

          <Card className="rounded-xl">
            <CardHeader className="pb-4">
              <CardTitle className="flex items-center gap-2 text-lg">
                <Lock className="w-5 h-5 text-orange-600" />
                Đổi mật khẩu
              </CardTitle>
              <CardDescription>Bỏ trống phần này nếu bạn không muốn thay đổi mật khẩu.</CardDescription>
            </CardHeader>
            <CardContent className="grid md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="current_password">Mật khẩu hiện tại</Label>
                <Input
                  id="current_password"
                  type="password"
                  value={form.current_password}
                  onChange={(event) => updateField('current_password', event.target.value)}
                  autoComplete="current-password"
                  aria-invalid={!!errors.current_password}
                />
                {errors.current_password && <p className="text-xs text-red-600">{errors.current_password}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="new_password">Mật khẩu mới</Label>
                <Input
                  id="new_password"
                  type="password"
                  value={form.new_password}
                  onChange={(event) => updateField('new_password', event.target.value)}
                  autoComplete="new-password"
                  aria-invalid={!!errors.new_password}
                />
                {errors.new_password && <p className="text-xs text-red-600">{errors.new_password}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="new_password_confirmation">Xác nhận mật khẩu</Label>
                <Input
                  id="new_password_confirmation"
                  type="password"
                  value={form.new_password_confirmation}
                  onChange={(event) => updateField('new_password_confirmation', event.target.value)}
                  autoComplete="new-password"
                  aria-invalid={!!errors.new_password_confirmation}
                />
                {errors.new_password_confirmation && <p className="text-xs text-red-600">{errors.new_password_confirmation}</p>}
              </div>
            </CardContent>
          </Card>

          <div className="sticky bottom-0 z-10 -mx-4 px-4 py-3 bg-white/90 backdrop-blur border-t md:static md:bg-transparent md:border-0 md:p-0">
            <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
              <Button type="button" variant="outline" onClick={resetForm} disabled={!isDirty || saving}>
                <RotateCcw className="w-4 h-4 mr-2" />
                Khôi phục
              </Button>
              <Button type="submit" disabled={!isDirty || saving} className="bg-orange-600 hover:bg-orange-700">
                <Save className="w-4 h-4 mr-2" />
                {saving ? 'Đang lưu...' : 'Lưu thay đổi'}
              </Button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
