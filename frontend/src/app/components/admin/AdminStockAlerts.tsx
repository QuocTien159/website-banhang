import { useEffect, useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight, Search, SlidersHorizontal } from 'lucide-react';
import { toast } from 'sonner';
import { adminService } from '../../services/orderService';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type StockAlert = {
  id: string;
  sku: string;
  product_name: string;
  image: string | null;
  stock: number;
  low_stock_threshold: number;
  status: 'out_of_stock' | 'low_stock';
  attributes: { name: string; value: string }[];
};

const PAGE_SIZE = 10;

export function AdminStockAlerts() {
  const [alerts, setAlerts] = useState<StockAlert[]>([]);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const response = await adminService.getStockAlerts({ ...(search ? { search } : {}) });
      setAlerts(response.data ?? []);
      setPage(1);
    } catch {
      toast.error('Không thể tải cảnh báo tồn kho.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search]);

  const totalPages = Math.max(1, Math.ceil(alerts.length / PAGE_SIZE));
  const paginatedAlerts = useMemo(
    () => alerts.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE),
    [alerts, page]
  );

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Cảnh báo tồn kho</h2>
        <p className="text-sm text-muted-foreground">Các SKU có tồn kho nhỏ hơn hoặc bằng ngưỡng cảnh báo.</p>
      </div>

      <div className="grid md:grid-cols-3 gap-4">
        <div className="bg-white border rounded-xl p-4">
          <p className="text-sm text-muted-foreground">Tổng cảnh báo</p>
          <p className="text-2xl font-semibold">{alerts.length}</p>
        </div>
        <div className="bg-white border rounded-xl p-4">
          <p className="text-sm text-muted-foreground">Hết hàng</p>
          <p className="text-2xl font-semibold text-red-600">{alerts.filter((item) => item.status === 'out_of_stock').length}</p>
        </div>
        <div className="bg-white border rounded-xl p-4">
          <p className="text-sm text-muted-foreground">Sắp hết hàng</p>
          <p className="text-2xl font-semibold text-orange-600">{alerts.filter((item) => item.status === 'low_stock').length}</p>
        </div>
      </div>

      <div className="bg-white border rounded-xl p-4">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm sản phẩm hoặc SKU" className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
        </div>
      </div>

      <div className="bg-white border rounded-xl overflow-x-auto">
        {loading ? <div className="p-10 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-4">Sản phẩm</th>
                <th className="text-left p-4">SKU</th>
                <th className="text-center p-4">Tồn hiện tại</th>
                <th className="text-center p-4">Ngưỡng</th>
                <th className="text-center p-4">Trạng thái</th>
                <th className="text-left p-4">Gợi ý</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {paginatedAlerts.map((alert) => (
                <tr key={alert.id}>
                  <td className="p-4">
                    <div className="flex items-center gap-3">
                      <div className="w-12 h-12 rounded-lg overflow-hidden bg-gray-100">
                        <ImageWithFallback src={alert.image ?? ''} alt={alert.product_name} className="w-full h-full object-cover" />
                      </div>
                      <div>
                        <p className="font-medium">{alert.product_name}</p>
                        <p className="text-xs text-muted-foreground">{alert.attributes.map((a) => `${a.name}: ${a.value}`).join(', ')}</p>
                      </div>
                    </div>
                  </td>
                  <td className="p-4">{alert.sku}</td>
                  <td className={`p-4 text-center font-semibold ${alert.stock <= 0 ? 'text-red-600' : 'text-orange-600'}`}>{alert.stock}</td>
                  <td className="p-4 text-center">{alert.low_stock_threshold}</td>
                  <td className="p-4 text-center">
                    <span className={`px-2 py-1 rounded-full text-xs ${alert.status === 'out_of_stock' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'}`}>
                      {alert.status === 'out_of_stock' ? 'Hết hàng' : 'Sắp hết hàng'}
                    </span>
                  </td>
                  <td className="p-4 text-muted-foreground">
                    <span className="inline-flex items-center gap-1"><SlidersHorizontal className="w-3 h-3" />Tạo phiếu nhập hoặc điều chỉnh kho</span>
                  </td>
                </tr>
              ))}
              {alerts.length === 0 && <tr><td colSpan={6} className="p-10 text-center text-muted-foreground">Không có SKU nào sắp hết hàng.</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      {!loading && alerts.length > PAGE_SIZE && (
        <div className="flex items-center justify-between bg-white border rounded-xl p-3">
          <p className="text-sm text-muted-foreground">
            Hiển thị {(page - 1) * PAGE_SIZE + 1}–{Math.min(page * PAGE_SIZE, alerts.length)} / {alerts.length} sản phẩm
          </p>
          <div className="flex items-center gap-2">
            <button onClick={() => setPage((value) => Math.max(1, value - 1))} disabled={page === 1} className="inline-flex items-center gap-1 border rounded-lg px-3 py-2 text-sm disabled:opacity-50">
              <ChevronLeft className="w-4 h-4" />Trước
            </button>
            <span className="text-sm">Trang {page}/{totalPages}</span>
            <button onClick={() => setPage((value) => Math.min(totalPages, value + 1))} disabled={page === totalPages} className="inline-flex items-center gap-1 border rounded-lg px-3 py-2 text-sm disabled:opacity-50">
              Sau<ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
