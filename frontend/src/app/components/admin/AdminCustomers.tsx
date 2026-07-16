import { useEffect, useState } from 'react';
import { ChevronLeft, ChevronRight, Search, Users } from 'lucide-react';
import { useSearchParams } from 'react-router';
import { adminService } from '../../services/orderService';
import { toast } from 'sonner';

interface AdminCustomer {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  status: 'active' | 'blocked';
  join_date: string | null;
  order_count: number;
  total_spent: number;
}

interface CustomerMeta {
  total: number;
  current_page: number;
  last_page: number;
  period?: { from: string; to: string } | null;
}

const formatPrice = (value: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

export function AdminCustomers() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [customers, setCustomers] = useState<AdminCustomer[]>([]);
  const [meta, setMeta] = useState<CustomerMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [toggling, setToggling] = useState<string | null>(null);
  const search = searchParams.get('search') ?? '';
  const sort = searchParams.get('sort') ?? 'joined_at';
  const from = searchParams.get('from') ?? '';
  const to = searchParams.get('to') ?? '';
  const page = Number(searchParams.get('page') ?? '1');

  useEffect(() => {
    const params: Record<string, string | number> = { page };
    if (search) params.search = search;
    if (sort === 'total_spent') params.sort = sort;
    if (from && to) {
      params.from = from;
      params.to = to;
    }

    setLoading(true);
    adminService.getCustomers(params)
      .then((data) => {
        setCustomers(data?.data ?? []);
        setMeta(data?.meta ?? null);
      })
      .catch(() => {
        setCustomers([]);
        setMeta(null);
      })
      .finally(() => setLoading(false));
  }, [search, sort, from, to, page]);

  const updateQuery = (updates: Record<string, string | null>, resetPage = false) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(updates).forEach(([key, value]) => {
      if (value) {
        next.set(key, value);
      } else {
        next.delete(key);
      }
    });
    if (resetPage) {
      next.delete('page');
    }
    setSearchParams(next);
  };

  const handleToggleStatus = async (id: string) => {
    setToggling(id);
    try {
      await adminService.toggleCustomerStatus(id);
      setCustomers((current) => current.map((customer) => (
        customer.id === id
          ? { ...customer, status: customer.status === 'active' ? 'blocked' : 'active' }
          : customer
      )));
      toast.success('Cập nhật trạng thái thành công');
    } catch {
      toast.error('Không thể cập nhật trạng thái');
    } finally {
      setToggling(null);
    }
  };

  const hasSpendSort = sort === 'total_spent';
  const periodLabel = meta?.period
    ? `${new Date(`${meta.period.from}T00:00:00`).toLocaleDateString('vi-VN')} - ${new Date(`${meta.period.to}T00:00:00`).toLocaleDateString('vi-VN')}`
    : null;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2>Quản lý khách hàng</h2>
          {hasSpendSort && <p className="mt-1 text-sm text-muted-foreground">Đang xếp theo tổng chi tiêu hợp lệ{periodLabel ? ` (${periodLabel})` : ''}.</p>}
        </div>
        <span className="text-sm text-muted-foreground">{meta?.total ?? customers.length} khách hàng</span>
      </div>

      <div className="border border-border bg-white p-4">
        <div className="relative max-w-sm">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="search"
            placeholder="Tìm mã, tên, email, số điện thoại..."
            value={search}
            onChange={(event) => updateQuery({ search: event.target.value || null }, true)}
            className="w-full rounded-md border border-border py-2 pl-9 pr-3 text-sm outline-none focus:border-orange-400"
          />
        </div>
      </div>

      <div className="overflow-hidden rounded-lg border border-border bg-white">
        {loading ? (
          <div className="divide-y divide-border">
            {Array.from({ length: 6 }).map((_, index) => <div key={index} className="h-16 animate-pulse bg-gray-50 p-4" />)}
          </div>
        ) : customers.length === 0 ? (
          <div className="py-16 text-center">
            <Users className="mx-auto mb-3 size-12 text-gray-300" />
            <p className="text-muted-foreground">Không tìm thấy khách hàng</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-border bg-gray-50">
                <tr>
                  <th className="p-4 text-left text-xs font-medium text-muted-foreground">KHÁCH HÀNG</th>
                  <th className="hidden p-4 text-left text-xs font-medium text-muted-foreground md:table-cell">SĐT</th>
                  <th className="hidden p-4 text-left text-xs font-medium text-muted-foreground lg:table-cell">NGÀY THAM GIA</th>
                  <th className="p-4 text-center text-xs font-medium text-muted-foreground">ĐƠN HOÀN TẤT</th>
                  <th className="hidden p-4 text-right text-xs font-medium text-muted-foreground md:table-cell">TỔNG CHI</th>
                  <th className="p-4 text-center text-xs font-medium text-muted-foreground">TRẠNG THÁI</th>
                  <th className="p-4" />
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {customers.map((customer) => {
                  const active = customer.status === 'active';
                  return (
                    <tr key={customer.id} className="transition-colors hover:bg-gray-50">
                      <td className="p-4">
                        <div className="flex items-center gap-3">
                          <div className="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style={{ backgroundColor: '#ea5c21' }}>
                            {customer.name.charAt(0).toUpperCase()}
                          </div>
                          <div className="min-w-0">
                            <p className="truncate font-medium">{customer.name}</p>
                            <p className="truncate text-xs text-muted-foreground">{customer.email}</p>
                          </div>
                        </div>
                      </td>
                      <td className="hidden p-4 text-muted-foreground md:table-cell">{customer.phone ?? '—'}</td>
                      <td className="hidden p-4 text-xs text-muted-foreground lg:table-cell">
                        {customer.join_date ? new Date(`${customer.join_date}T00:00:00`).toLocaleDateString('vi-VN') : '—'}
                      </td>
                      <td className="p-4 text-center font-medium">{customer.order_count}</td>
                      <td className="hidden p-4 text-right font-medium text-orange-600 md:table-cell">{formatPrice(customer.total_spent)}</td>
                      <td className="p-4 text-center">
                        <span className={`rounded-full px-2 py-1 text-xs ${active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                          {active ? 'Hoạt động' : 'Khóa'}
                        </span>
                      </td>
                      <td className="p-4">
                        <button
                          onClick={() => handleToggleStatus(customer.id)}
                          disabled={toggling === customer.id}
                          className={`rounded-md border px-2 py-1 text-xs transition-colors disabled:opacity-50 ${active ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'}`}
                        >
                          {toggling === customer.id ? '...' : active ? 'Khóa' : 'Mở khóa'}
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
            <span className="text-muted-foreground">Trang {meta.current_page}/{meta.last_page}</span>
            <div className="flex gap-2">
              <button
                className="grid size-8 place-items-center rounded-md border disabled:opacity-40"
                disabled={meta.current_page <= 1}
                onClick={() => updateQuery({ page: String(meta.current_page - 1) })}
                aria-label="Trang trước"
              >
                <ChevronLeft className="size-4" />
              </button>
              <button
                className="grid size-8 place-items-center rounded-md border disabled:opacity-40"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => updateQuery({ page: String(meta.current_page + 1) })}
                aria-label="Trang sau"
              >
                <ChevronRight className="size-4" />
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
