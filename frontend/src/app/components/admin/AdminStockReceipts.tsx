import { useEffect, useState } from 'react';
import { Eye, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import { adminService } from '../../services/orderService';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type ReceiptSummary = {
  id: string;
  code: string;
  import_date: string;
  importer: string;
  item_count: number;
  total_quantity: number;
  note: string | null;
};

type ReceiptDetail = ReceiptSummary & {
  items: {
    variant: {
      id: string;
      sku: string;
      product_name: string;
      image: string | null;
      stock: number;
      attributes: { name: string; value: string }[];
    };
    quantity: number;
    note: string | null;
  }[];
};

export function AdminStockReceipts() {
  const [receipts, setReceipts] = useState<ReceiptSummary[]>([]);
  const [search, setSearch] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [loading, setLoading] = useState(true);
  const [detail, setDetail] = useState<ReceiptDetail | null>(null);

  const load = async () => {
    setLoading(true);
    try {
      const response = await adminService.getStockReceipts({
        ...(search ? { search } : {}),
        ...(from ? { from } : {}),
        ...(to ? { to } : {}),
      });
      setReceipts(response.data ?? []);
    } catch {
      toast.error('Không thể tải lịch sử nhập kho.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search, from, to]);

  const openDetail = async (id: string) => {
    try {
      setDetail(await adminService.getStockReceipt(id));
    } catch {
      toast.error('Không thể tải chi tiết phiếu nhập.');
    }
  };

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Lịch sử nhập kho</h2>
        <p className="text-sm text-muted-foreground">Tra cứu các phiếu nhập kho đã tạo.</p>
      </div>

      <div className="bg-white border rounded-xl p-4 grid md:grid-cols-[1fr_180px_180px] gap-3">
        <div className="relative">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm mã phiếu hoặc ghi chú" className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" />
        </div>
        <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="border rounded-lg px-3 py-2 text-sm" />
        <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="border rounded-lg px-3 py-2 text-sm" />
      </div>

      <div className="bg-white border rounded-xl overflow-x-auto">
        {loading ? <div className="p-10 text-center">Đang tải...</div> : (
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left p-4">Mã phiếu</th>
                <th className="text-left p-4">Ngày nhập</th>
                <th className="text-left p-4">Người nhập</th>
                <th className="text-center p-4">Số sản phẩm</th>
                <th className="text-center p-4">Tổng SL</th>
                <th className="text-left p-4">Ghi chú</th>
                <th className="p-4" />
              </tr>
            </thead>
            <tbody className="divide-y">
              {receipts.map((receipt) => (
                <tr key={receipt.id}>
                  <td className="p-4 font-medium">{receipt.code}</td>
                  <td className="p-4">{receipt.import_date}</td>
                  <td className="p-4">{receipt.importer}</td>
                  <td className="p-4 text-center">{receipt.item_count}</td>
                  <td className="p-4 text-center">{receipt.total_quantity}</td>
                  <td className="p-4 max-w-xs truncate">{receipt.note}</td>
                  <td className="p-4 text-right">
                    <button onClick={() => openDetail(receipt.id)} className="p-2 text-blue-600"><Eye className="w-4 h-4" /></button>
                  </td>
                </tr>
              ))}
              {receipts.length === 0 && <tr><td colSpan={7} className="p-10 text-center text-muted-foreground">Chưa có phiếu nhập kho.</td></tr>}
            </tbody>
          </table>
        )}
      </div>

      {detail && (
        <div className="fixed inset-0 z-50">
          <button className="absolute inset-0 bg-black/50" onClick={() => setDetail(null)} />
          <div className="absolute inset-y-0 right-0 w-full max-w-4xl bg-white shadow-xl overflow-y-auto">
            <div className="sticky top-0 bg-white border-b p-5 flex justify-between">
              <div>
                <h3 className="text-xl font-semibold">Phiếu nhập {detail.code}</h3>
                <p className="text-sm text-muted-foreground">{detail.import_date} · {detail.importer}</p>
              </div>
              <button onClick={() => setDetail(null)}><X /></button>
            </div>
            <div className="p-5 space-y-4">
              {detail.note && <div className="bg-gray-50 border rounded-xl p-3 text-sm">{detail.note}</div>}
              <div className="space-y-3">
                {detail.items.map((item) => (
                  <div key={item.variant.id} className="border rounded-xl p-3 flex gap-3">
                    <div className="w-14 h-14 rounded-lg bg-gray-100 overflow-hidden">
                      <ImageWithFallback src={item.variant.image ?? ''} alt={item.variant.product_name} className="w-full h-full object-cover" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium">{item.variant.product_name}</p>
                      <p className="text-xs text-muted-foreground">{item.variant.sku} · {item.variant.attributes.map((a) => `${a.name}: ${a.value}`).join(', ')}</p>
                      {item.note && <p className="text-xs mt-1">{item.note}</p>}
                    </div>
                    <div className="text-right">
                      <p className="font-semibold">+{item.quantity}</p>
                      <p className="text-xs text-muted-foreground">Tồn hiện tại: {item.variant.stock}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
