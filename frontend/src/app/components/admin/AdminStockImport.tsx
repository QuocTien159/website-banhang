import { useEffect, useState } from 'react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { adminService } from '../../services/orderService';
import { Button } from '../ui/button';

type InventoryVariant = {
  id: string;
  sku: string;
  product_name: string;
  stock: number;
  low_stock_threshold: number;
  attributes: { name: string; value: string }[];
};

type ImportLine = { variant_id: string; quantity: number; note: string };

const today = () => new Date().toISOString().slice(0, 10);

const variantLabel = (variant?: InventoryVariant) =>
  variant
    ? `${variant.product_name} · ${variant.sku}${variant.attributes?.length ? ` · ${variant.attributes.map((a) => `${a.name}: ${a.value}`).join(', ')}` : ''}`
    : '';

const errorText = (error: any) => {
  const errors = error.response?.data?.errors;
  return errors ? Object.values(errors).flat().join(' ') : error.response?.data?.message ?? 'Không thể lưu phiếu nhập kho.';
};

export function AdminStockImport() {
  const [variants, setVariants] = useState<InventoryVariant[]>([]);
  const [code, setCode] = useState('');
  const [importDate, setImportDate] = useState(today());
  const [note, setNote] = useState('');
  const [items, setItems] = useState<ImportLine[]>([{ variant_id: '', quantity: 1, note: '' }]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    adminService.getInventoryVariants().then(setVariants).catch(() => toast.error('Không thể tải danh sách SKU.'));
  }, []);

  const selectedIds = items.map((item) => item.variant_id).filter(Boolean);
  const totalQuantity = items.reduce((sum, item) => sum + Math.max(0, Number(item.quantity) || 0), 0);

  const updateItem = (index: number, patch: Partial<ImportLine>) => {
    setItems((current) => current.map((item, itemIndex) => (itemIndex === index ? { ...item, ...patch } : item)));
  };

  const submit = async () => {
    if (items.some((item) => !item.variant_id || Number(item.quantity) <= 0)) {
      toast.error('Mỗi dòng nhập kho phải chọn SKU và nhập số lượng lớn hơn 0.');
      return;
    }
    if (new Set(selectedIds).size !== selectedIds.length) {
      toast.error('Không được nhập trùng cùng một SKU trong một phiếu.');
      return;
    }

    setSaving(true);
    try {
      const response = await adminService.createStockReceipt({
        ...(code.trim() ? { code: code.trim() } : {}),
        import_date: importDate,
        note: note.trim() || undefined,
        items: items.map((item) => ({
          variant_id: item.variant_id,
          quantity: Number(item.quantity),
          note: item.note.trim() || undefined,
        })),
      });
      toast.success(`Đã tạo phiếu nhập ${response.receipt?.code ?? ''}.`);
      setCode('');
      setImportDate(today());
      setNote('');
      setItems([{ variant_id: '', quantity: 1, note: '' }]);
      setVariants(await adminService.getInventoryVariants());
    } catch (error: any) {
      toast.error(errorText(error));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Nhập kho</h2>
        <p className="text-sm text-muted-foreground">Tạo phiếu nhập và tự động cộng tồn kho cho từng SKU/biến thể.</p>
      </div>

      <div className="bg-white border rounded-xl p-5 space-y-4">
        <div className="grid md:grid-cols-2 gap-4">
          <label className="space-y-1">
            <span className="text-sm">Mã phiếu nhập</span>
            <input value={code} onChange={(event) => setCode(event.target.value)} placeholder="Để trống để tự tạo" className="w-full border rounded-lg px-3 py-2" />
          </label>
          <label className="space-y-1">
            <span className="text-sm">Ngày nhập *</span>
            <input type="date" value={importDate} onChange={(event) => setImportDate(event.target.value)} className="w-full border rounded-lg px-3 py-2" />
          </label>
          <label className="space-y-1 md:col-span-2">
            <span className="text-sm">Ghi chú</span>
            <textarea value={note} onChange={(event) => setNote(event.target.value)} rows={3} className="w-full border rounded-lg px-3 py-2" />
          </label>
        </div>
      </div>

      <div className="bg-white border rounded-xl p-5 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="font-semibold">Danh sách sản phẩm nhập</h3>
            <p className="text-xs text-muted-foreground">{items.length} dòng, tổng {totalQuantity} sản phẩm</p>
          </div>
          <Button variant="outline" onClick={() => setItems([...items, { variant_id: '', quantity: 1, note: '' }])}>
            <Plus className="w-4 h-4 mr-2" />Thêm dòng
          </Button>
        </div>

        <div className="space-y-3">
          {items.map((item, index) => (
            <div key={index} className="grid lg:grid-cols-[1.5fr_140px_1fr_auto] gap-3 items-start border rounded-xl p-3">
              <label className="space-y-1">
                <span className="text-xs">Sản phẩm / SKU *</span>
                <select value={item.variant_id} onChange={(event) => updateItem(index, { variant_id: event.target.value })} className="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">Chọn SKU</option>
                  {variants.map((variant) => (
                    <option key={variant.id} value={variant.id} disabled={selectedIds.includes(variant.id) && item.variant_id !== variant.id}>
                      {variantLabel(variant)} · Tồn: {variant.stock}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1">
                <span className="text-xs">Số lượng *</span>
                <input type="number" min="1" value={item.quantity} onChange={(event) => updateItem(index, { quantity: Number(event.target.value) })} className="w-full border rounded-lg px-3 py-2 text-sm" />
              </label>
              <label className="space-y-1">
                <span className="text-xs">Ghi chú dòng</span>
                <input value={item.note} onChange={(event) => updateItem(index, { note: event.target.value })} className="w-full border rounded-lg px-3 py-2 text-sm" />
              </label>
              <button onClick={() => setItems(items.filter((_, itemIndex) => itemIndex !== index))} disabled={items.length === 1} className="mt-6 p-2 text-red-600 disabled:text-gray-300">
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>

        <div className="flex justify-end">
          <Button onClick={submit} disabled={saving} className="bg-orange-600 hover:bg-orange-700">
            {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}Lưu phiếu nhập
          </Button>
        </div>
      </div>
    </div>
  );
}
