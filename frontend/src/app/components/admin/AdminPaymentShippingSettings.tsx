import { useEffect, useState } from 'react';
import { Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import { adminService, orderService, type AdministrativeUnit } from '../../services/orderService';
import { Button } from '../ui/button';

type SettingsState = {
  inner_city_fee: number;
  outer_city_fee: number;
  other_province_fee: number;
  free_shipping_enabled: boolean;
  free_shipping_min_order_value: number;
  shop_province: string;
  shop_province_code: string;
  inner_city_districts: string[];
  inner_city_district_codes: string[];
  bank_code: string;
  bank_name: string;
  account_number: string;
  account_name: string;
  transfer_template: string;
};

const emptySettings: SettingsState = {
  inner_city_fee: 0,
  outer_city_fee: 0,
  other_province_fee: 0,
  free_shipping_enabled: true,
  free_shipping_min_order_value: 0,
  shop_province: '',
  shop_province_code: '',
  inner_city_districts: [],
  inner_city_district_codes: [],
  bank_code: '',
  bank_name: '',
  account_number: '',
  account_name: '',
  transfer_template: 'TienProSport {{order_code}}',
};

export function AdminPaymentShippingSettings() {
  const [settings, setSettings] = useState<SettingsState>(emptySettings);
  const [provinces, setProvinces] = useState<AdministrativeUnit[]>([]);
  const [districts, setDistricts] = useState<AdministrativeUnit[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    Promise.all([adminService.getPaymentShippingSettings(), orderService.getProvinces()])
      .then(([data, provinceList]) => {
        setProvinces(provinceList);
        setSettings({
          inner_city_fee: Number(data.inner_city_fee ?? 0),
          outer_city_fee: Number(data.outer_city_fee ?? 0),
          other_province_fee: Number(data.other_province_fee ?? 0),
          free_shipping_enabled: Boolean(data.free_shipping_enabled),
          free_shipping_min_order_value: Number(data.free_shipping_min_order_value ?? 0),
          shop_province: data.shop_province ?? '',
          shop_province_code: data.shop_province_code ?? '',
          inner_city_districts: data.inner_city_districts ?? [],
          inner_city_district_codes: (data.inner_city_district_codes ?? []).map(String),
          bank_code: data.bank_code ?? '',
          bank_name: data.bank_name ?? '',
          account_number: data.account_number ?? '',
          account_name: data.account_name ?? '',
          transfer_template: data.transfer_template ?? 'TienProSport {{order_code}}',
        });
      })
      .catch(() => toast.error('Không thể tải cấu hình.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!settings.shop_province_code) {
      setDistricts([]);
      return;
    }
    orderService.getDistricts(settings.shop_province_code)
      .then(setDistricts)
      .catch(() => toast.error('Không thể tải danh sách quận/huyện của shop.'));
  }, [settings.shop_province_code]);

  const update = <K extends keyof SettingsState>(key: K, value: SettingsState[K]) => {
    setSettings((current) => ({ ...current, [key]: value }));
  };

  const toggleDistrict = (district: AdministrativeUnit) => {
    setSettings((current) => {
      const exists = current.inner_city_district_codes.includes(district.code);
      return {
        ...current,
        inner_city_district_codes: exists
          ? current.inner_city_district_codes.filter((code) => code !== district.code)
          : [...current.inner_city_district_codes, district.code],
        inner_city_districts: exists
          ? current.inner_city_districts.filter((name) => name !== district.name)
          : [...current.inner_city_districts, district.name],
      };
    });
  };

  const changeShopProvince = (provinceCode: string) => {
    const province = provinces.find((item) => item.code === provinceCode);
    setSettings((current) => ({
      ...current,
      shop_province_code: provinceCode,
      shop_province: province?.name ?? '',
      inner_city_districts: [],
      inner_city_district_codes: [],
    }));
  };

  const submit = async () => {
    setSaving(true);
    try {
      await adminService.updatePaymentShippingSettings({
        inner_city_fee: settings.inner_city_fee,
        outer_city_fee: settings.outer_city_fee,
        other_province_fee: settings.other_province_fee,
        free_shipping_enabled: settings.free_shipping_enabled,
        free_shipping_min_order_value: settings.free_shipping_min_order_value,
        shop_province: settings.shop_province,
        shop_province_code: settings.shop_province_code,
        inner_city_districts: settings.inner_city_districts,
        inner_city_district_codes: settings.inner_city_district_codes,
        bank_code: settings.bank_code,
        bank_name: settings.bank_name,
        account_number: settings.account_number,
        account_name: settings.account_name,
        transfer_template: settings.transfer_template,
      });
      toast.success('Đã lưu cấu hình.');
    } catch (error: any) {
      toast.error(error.response?.data?.message ?? 'Không thể lưu cấu hình.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="bg-white rounded-xl border p-8 text-center">Đang tải cấu hình...</div>;
  }

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Vận chuyển & thanh toán</h2>
        <p className="text-sm text-muted-foreground">Cấu hình phí ship theo khu vực và thông tin QR chuyển khoản thủ công.</p>
      </div>

      <section className="bg-white rounded-xl border p-5 space-y-4">
        <h3 className="font-semibold">Phí ship theo khu vực</h3>
        <div className="grid md:grid-cols-3 gap-4">
          <NumberField label="Phí nội thành" value={settings.inner_city_fee} onChange={(value) => update('inner_city_fee', value)} />
          <NumberField label="Phí ngoại thành" value={settings.outer_city_fee} onChange={(value) => update('outer_city_fee', value)} />
          <NumberField label="Phí tỉnh khác" value={settings.other_province_fee} onChange={(value) => update('other_province_fee', value)} />
        </div>
        <label className="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" checked={settings.free_shipping_enabled} onChange={(event) => update('free_shipping_enabled', event.target.checked)} />
          Bật miễn phí ship theo ngưỡng đơn hàng
        </label>
        <div className="grid md:grid-cols-2 gap-4">
          <NumberField label="Ngưỡng miễn phí ship" value={settings.free_shipping_min_order_value} onChange={(value) => update('free_shipping_min_order_value', value)} />
          <label className="block">
            <span className="text-sm font-medium">Tỉnh/thành của shop</span>
            <select value={settings.shop_province_code} onChange={(event) => changeShopProvince(event.target.value)} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-white">
              <option value="">Chọn tỉnh/thành</option>
              {provinces.map((province) => <option key={province.code} value={province.code}>{province.name}</option>)}
            </select>
          </label>
        </div>
        <div>
          <p className="text-sm font-medium mb-2">Quận/huyện nội thành</p>
          {!settings.shop_province_code ? (
            <p className="text-sm text-muted-foreground">Chọn tỉnh/thành của shop trước.</p>
          ) : (
            <div className="grid md:grid-cols-3 gap-2 max-h-72 overflow-y-auto border rounded-lg p-3">
              {districts.map((district) => (
                <label key={district.code} className="inline-flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={settings.inner_city_district_codes.includes(district.code)}
                    onChange={() => toggleDistrict(district)}
                  />
                  {district.name}
                </label>
              ))}
            </div>
          )}
        </div>
      </section>

      <section className="bg-white rounded-xl border p-5 space-y-4">
        <h3 className="font-semibold">Tài khoản nhận chuyển khoản QR</h3>
        <div className="grid md:grid-cols-2 gap-4">
          <TextField label="Mã ngân hàng VietQR" value={settings.bank_code} onChange={(value) => update('bank_code', value)} />
          <TextField label="Tên ngân hàng" value={settings.bank_name} onChange={(value) => update('bank_name', value)} />
          <TextField label="Số tài khoản" value={settings.account_number} onChange={(value) => update('account_number', value)} />
          <TextField label="Tên chủ tài khoản" value={settings.account_name} onChange={(value) => update('account_name', value)} />
        </div>
        <TextField label="Mẫu nội dung chuyển khoản" value={settings.transfer_template} onChange={(value) => update('transfer_template', value)} />
        <p className="text-xs text-muted-foreground">Dùng <code>{'{{order_code}}'}</code> để chèn mã đơn hàng, ví dụ: TienProSport {'{{order_code}}'}.</p>
      </section>

      <div className="flex justify-end">
        <Button onClick={submit} disabled={saving} className="bg-orange-600 hover:bg-orange-700">
          {saving ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <Save className="w-4 h-4 mr-2" />}
          Lưu cấu hình
        </Button>
      </div>
    </div>
  );
}

function NumberField({ label, value, onChange }: { label: string; value: number; onChange: (value: number) => void }) {
  return (
    <label className="block">
      <span className="text-sm font-medium">{label}</span>
      <input type="number" min={0} value={value} onChange={(event) => onChange(Number(event.target.value))} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm" />
    </label>
  );
}

function TextField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <label className="block">
      <span className="text-sm font-medium">{label}</span>
      <input value={value} onChange={(event) => onChange(event.target.value)} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm" />
    </label>
  );
}
