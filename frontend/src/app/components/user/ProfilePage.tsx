import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Button } from '../ui/button';
import { useAuth } from '../../store/AppContext';
import { User, Mail, Phone, MapPin, Lock } from 'lucide-react';

export function ProfilePage() {
  const { user } = useAuth();
  
  const [formData, setFormData] = useState({
    name: user?.name || 'Nguyễn Văn A',
    email: user?.email || 'nguyenvana@example.com',
    phone: '0987654321',
    address: '123 Đường Lê Lợi, Quận 1, TP Hồ Chí Minh',
    password: 'password123',
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Logic cập nhật hồ sơ sẽ được xử lý sau
  };

  return (
    <div className="max-w-3xl mx-auto py-10 px-4">
      <Card className="border-0 shadow-sm ring-1 ring-black/5 rounded-2xl overflow-hidden">
        <div className="bg-orange-50/50 p-6 border-b border-orange-100">
          <CardTitle className="text-2xl font-bold text-gray-900">Hồ sơ cá nhân</CardTitle>
          <CardDescription className="text-gray-500 mt-1">
            Quản lý thông tin cá nhân và bảo mật tài khoản của bạn
          </CardDescription>
        </div>
        
        <CardContent className="p-8">
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Họ và tên */}
              <div className="space-y-2">
                <Label htmlFor="name" className="text-sm font-medium flex items-center gap-2">
                  <User className="w-4 h-4 text-orange-500" />
                  Họ và tên
                </Label>
                <Input
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  className="bg-gray-50/50 focus:bg-white transition-colors"
                />
              </div>
              
              {/* Email */}
              <div className="space-y-2">
                <Label htmlFor="email" className="text-sm font-medium flex items-center gap-2">
                  <Mail className="w-4 h-4 text-orange-500" />
                  Email
                </Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  value={formData.email}
                  onChange={handleChange}
                  className="bg-gray-50/50 focus:bg-white transition-colors"
                />
              </div>

              {/* Số điện thoại */}
              <div className="space-y-2">
                <Label htmlFor="phone" className="text-sm font-medium flex items-center gap-2">
                  <Phone className="w-4 h-4 text-orange-500" />
                  Số điện thoại
                </Label>
                <Input
                  id="phone"
                  name="phone"
                  type="tel"
                  value={formData.phone}
                  onChange={handleChange}
                  className="bg-gray-50/50 focus:bg-white transition-colors"
                />
              </div>

              {/* Mật khẩu */}
              <div className="space-y-2">
                <Label htmlFor="password" className="text-sm font-medium flex items-center gap-2">
                  <Lock className="w-4 h-4 text-orange-500" />
                  Mật khẩu
                </Label>
                <Input
                  id="password"
                  name="password"
                  type="password"
                  value={formData.password}
                  onChange={handleChange}
                  className="bg-gray-50/50 focus:bg-white transition-colors"
                />
              </div>
            </div>
            
            {/* Địa chỉ nhà */}
            <div className="space-y-2">
              <Label htmlFor="address" className="text-sm font-medium flex items-center gap-2">
                <MapPin className="w-4 h-4 text-orange-500" />
                Địa chỉ nhà
              </Label>
              <Input
                id="address"
                name="address"
                value={formData.address}
                onChange={handleChange}
                className="bg-gray-50/50 focus:bg-white transition-colors"
              />
            </div>

            <div className="pt-4 flex justify-end">
              <Button type="submit" className="w-full sm:w-auto px-8" style={{ backgroundColor: '#ea5c21' }}>
                Lưu thay đổi
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
