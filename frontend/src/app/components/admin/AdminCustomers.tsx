import { useState, useEffect } from 'react';
import { Users, Search } from 'lucide-react';
import { adminService } from '../../services/orderService';
import { toast } from 'sonner';

interface AdminCustomer {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  status: boolean;
  join_date: string;
  order_count: number;
  total_spent: number;
}

const formatPrice = (p: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p);

export function AdminCustomers() {
  const [customers, setCustomers] = useState<AdminCustomer[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [toggling, setToggling] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    adminService.getCustomers(search ? { search } : {})
      .then(data => setCustomers(data?.data ?? []))
      .catch(() => setCustomers([]))
      .finally(() => setLoading(false));
  }, [search]);

  const handleToggleStatus = async (id: string) => {
    setToggling(id);
    try {
      await adminService.toggleCustomerStatus(id);
      setCustomers(prev =>
        prev.map(c => c.id === id ? { ...c, status: !c.status } : c)
      );
      toast.success('Cập nhật trạng thái thành công');
    } catch {
      toast.error('Không thể cập nhật trạng thái');
    } finally {
      setToggling(null);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h2>Quản lý khách hàng</h2>
        <span className="text-sm text-muted-foreground">{customers.length} khách hàng</span>
      </div>

      {/* Search */}
      <div className="bg-white rounded-xl border border-border p-4">
        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <input
            type="text"
            placeholder="Tìm tên, email..."
            value={search}
            onChange={e => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-border rounded-lg focus:outline-none focus:border-orange-400"
          />
        </div>
      </div>

      {/* Customers Table */}
      <div className="bg-white rounded-xl border border-border overflow-hidden">
        {loading ? (
          <div className="divide-y divide-border">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="p-4 h-16 animate-pulse bg-gray-50" />
            ))}
          </div>
        ) : customers.length === 0 ? (
          <div className="text-center py-16">
            <Users className="w-12 h-12 mx-auto text-gray-300 mb-3" />
            <p className="text-muted-foreground">Không tìm thấy khách hàng</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 border-b border-border">
                <tr>
                  <th className="text-left p-4 text-xs font-medium text-muted-foreground">KHÁCH HÀNG</th>
                  <th className="text-left p-4 text-xs font-medium text-muted-foreground hidden md:table-cell">SĐT</th>
                  <th className="text-left p-4 text-xs font-medium text-muted-foreground hidden lg:table-cell">NGÀY THAM GIA</th>
                  <th className="text-center p-4 text-xs font-medium text-muted-foreground">ĐƠN HÀNG</th>
                  <th className="text-right p-4 text-xs font-medium text-muted-foreground hidden md:table-cell">TỔNG CHI</th>
                  <th className="text-center p-4 text-xs font-medium text-muted-foreground">TRẠNG THÁI</th>
                  <th className="p-4"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {customers.map(customer => (
                  <tr key={customer.id} className="hover:bg-gray-50 transition-colors">
                    <td className="p-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0" style={{ backgroundColor: '#ea5c21' }}>
                          {customer.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0">
                          <p className="font-medium truncate">{customer.name}</p>
                          <p className="text-xs text-muted-foreground truncate">{customer.email}</p>
                        </div>
                      </div>
                    </td>
                    <td className="p-4 text-muted-foreground hidden md:table-cell">{customer.phone ?? '—'}</td>
                    <td className="p-4 text-muted-foreground hidden lg:table-cell text-xs">
                      {new Date(customer.join_date).toLocaleDateString('vi-VN')}
                    </td>
                    <td className="p-4 text-center font-medium">{customer.order_count ?? 0}</td>
                    <td className="p-4 text-right font-medium hidden md:table-cell" style={{ color: '#ea5c21' }}>
                      {formatPrice(customer.total_spent ?? 0)}
                    </td>
                    <td className="p-4 text-center">
                      <span className={`text-xs px-2 py-1 rounded-full ${customer.status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {customer.status ? 'Hoạt động' : 'Khóa'}
                      </span>
                    </td>
                    <td className="p-4">
                      <button
                        onClick={() => handleToggleStatus(customer.id)}
                        disabled={toggling === customer.id}
                        className={`text-xs px-2 py-1 rounded-lg border transition-colors disabled:opacity-50 ${customer.status ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'}`}
                      >
                        {toggling === customer.id ? '...' : customer.status ? 'Khóa' : 'Mở khóa'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
