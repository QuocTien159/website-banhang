import { useEffect, useState } from 'react';
import { Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import { adminService, orderService, type AdministrativeUnit } from '../../services/orderService';
import { Button } from '../ui/button';

type SettingsState = {
  shipping_provider: 'ghn';
  ghn_enabled: boolean;
  ghn_environment: 'sandbox' | 'production';
  ghn_shop_id: string;
  ghn_token_configured: boolean;
  pickup_name: string;
  pickup_phone: string;
  pickup_province_id: string;
  pickup_province_name: string;
  pickup_district_id: string;
  pickup_district_name: string;
  pickup_ward_code: string;
  pickup_ward_name: string;
  pickup_address: string;
  default_weight_gram: number;
  default_length_cm: number;
  default_width_cm: number;
  default_height_cm: number;
  free_shipping_enabled: boolean;
  free_shipping_min_order_value: number;
  bank_code: string;
  bank_name: string;
  account_number: string;
  account_name: string;
  transfer_template: string;
};

const emptySettings: SettingsState = {
  shipping_provider: 'ghn',
  ghn_enabled: false,
  ghn_environment: 'sandbox',
  ghn_shop_id: '',
  ghn_token_configured: false,
  pickup_name: '',
  pickup_phone: '',
  pickup_province_id: '',
  pickup_province_name: '',
  pickup_district_id: '',
  pickup_district_name: '',
  pickup_ward_code: '',
  pickup_ward_name: '',
  pickup_address: '',
  default_weight_gram: 500,
  default_length_cm: 25,
  default_width_cm: 20,
  default_height_cm: 10,
  free_shipping_enabled: true,
  free_shipping_min_order_value: 0,
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
  const [wards, setWards] = useState<AdministrativeUnit[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [addressError, setAddressError] = useState('');

  useEffect(() => {
    Promise.all([adminService.getPaymentShippingSettings(), orderService.getProvinces()])
      .then(([data, provinceList]) => {
        setProvinces(provinceList);
        setSettings({
          shipping_provider: 'ghn',
          ghn_enabled: Boolean(data.ghn_enabled),
          ghn_environment: data.ghn_environment ?? 'sandbox',
          ghn_shop_id: data.ghn_shop_id ?? '',
          ghn_token_configured: Boolean(data.ghn_token_configured),
          pickup_name: data.pickup_name ?? '',
          pickup_phone: data.pickup_phone ?? '',
          pickup_province_id: data.pickup_province_id ? String(data.pickup_province_id) : '',
          pickup_province_name: data.pickup_province_name ?? '',
          pickup_district_id: data.pickup_district_id ? String(data.pickup_district_id) : '',
          pickup_district_name: data.pickup_district_name ?? '',
          pickup_ward_code: data.pickup_ward_code ?? '',
          pickup_ward_name: data.pickup_ward_name ?? '',
          pickup_address: data.pickup_address ?? '',
          default_weight_gram: Number(data.default_weight_gram ?? 500),
          default_length_cm: Number(data.default_length_cm ?? 25),
          default_width_cm: Number(data.default_width_cm ?? 20),
          default_height_cm: Number(data.default_height_cm ?? 10),
          free_shipping_enabled: Boolean(data.free_shipping_enabled),
          free_shipping_min_order_value: Number(data.free_shipping_min_order_value ?? 0),
          bank_code: data.bank_code ?? '',
          bank_name: data.bank_name ?? '',
          account_number: data.account_number ?? '',
          account_name: data.account_name ?? '',
          transfer_template: data.transfer_template ?? 'TienProSport {{order_code}}',
        });
      })
      .catch(() => setAddressError('Không thể tải cấu hình hoặc dữ liệu địa chỉ GHN.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    const provinceCode = settings.pickup_province_id;
    if (!provinceCode) {
      setDistricts([]);
      return;
    }
    orderService.getDistricts(provinceCode)
      .then(setDistricts)
      .catch(() => setAddressError('Không thể tải danh sách quận/huyện của kho từ GHN.'));
  }, [settings.pickup_province_id]);

  useEffect(() => {
    setWards([]);
    if (!settings.pickup_district_id) return;
    orderService.getWards(settings.pickup_district_id)
      .then(setWards)
      .catch(() => setAddressError('Không thể tải danh sách phường/xã của kho từ GHN.'));
  }, [settings.pickup_district_id]);

  const update = <K extends keyof SettingsState>(key: K, value: SettingsState[K]) => {
    setSettings((current) => ({ ...current, [key]: value }));
  };

  const changeShopProvince = (provinceCode: string) => {
    const province = provinces.find((item) => item.code === provinceCode);
    setSettings((current) => ({
      ...current,
      pickup_province_id: provinceCode,
      pickup_province_name: province?.name ?? '',
      pickup_district_id: '',
      pickup_district_name: '',
      pickup_ward_code: '',
      pickup_ward_name: '',
    }));
  };

  const changePickupDistrict = (districtCode: string) => {
    const district = districts.find((item) => item.code === districtCode);
    setSettings((current) => ({
      ...current,
      pickup_district_id: districtCode,
      pickup_district_name: district?.name ?? '',
      pickup_ward_code: '',
      pickup_ward_name: '',
    }));
  };

  const changePickupWard = (wardCode: string) => {
    const ward = wards.find((item) => item.code === wardCode);
    setSettings((current) => ({
      ...current,
      pickup_ward_code: wardCode,
      pickup_ward_name: ward?.name ?? '',
    }));
  };

  const submit = async () => {
    setSaving(true);
    try {
      await adminService.updatePaymentShippingSettings({
        shipping_provider: settings.shipping_provider,
        ghn_enabled: settings.ghn_enabled,
        ghn_environment: settings.ghn_environment,
        ghn_shop_id: settings.ghn_shop_id,
        pickup_name: settings.pickup_name,
        pickup_phone: settings.pickup_phone,
        pickup_province_id: settings.pickup_province_id ? Number(settings.pickup_province_id) : null,
        pickup_province_name: settings.pickup_province_name,
        pickup_district_id: settings.pickup_district_id ? Number(settings.pickup_district_id) : null,
        pickup_district_name: settings.pickup_district_name,
        pickup_ward_code: settings.pickup_ward_code,
        pickup_ward_name: settings.pickup_ward_name,
        pickup_address: settings.pickup_address,
        default_weight_gram: settings.default_weight_gram,
        default_length_cm: settings.default_length_cm,
        default_width_cm: settings.default_width_cm,
        default_height_cm: settings.default_height_cm,
        free_shipping_enabled: settings.free_shipping_enabled,
        free_shipping_min_order_value: settings.free_shipping_min_order_value,
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
        <div>
          <h3 className="font-semibold">GHN</h3>
          <p className="text-xs text-muted-foreground">Token lấy từ file .env. Shop ID có thể lấy từ .env hoặc nhập tại đây.</p>
        </div>
        <div className="grid md:grid-cols-3 gap-4">
          <label className="block">
            <span className="text-sm font-medium">Nhà vận chuyển</span>
            <input value="GHN" disabled className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-gray-100" />
          </label>
          <label className="block">
            <span className="text-sm font-medium">Môi trường</span>
            <select value={settings.ghn_environment} onChange={(event) => update('ghn_environment', event.target.value as SettingsState['ghn_environment'])} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-white">
              <option value="sandbox">Sandbox</option>
              <option value="production">Production</option>
            </select>
          </label>
          <TextField label="Shop ID GHN" value={settings.ghn_shop_id} onChange={(value) => update('ghn_shop_id', value)} />
        </div>
        <label className="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" checked={settings.ghn_enabled} onChange={(event) => update('ghn_enabled', event.target.checked)} />
          Bật tính phí vận chuyển bằng GHN
        </label>
        <p className={`text-xs ${settings.ghn_token_configured ? 'text-green-600' : 'text-red-600'}`}>
          {settings.ghn_token_configured ? 'Token GHN đã được cấu hình trong .env.' : 'Chưa có token GHN trong .env.'}
        </p>
        {addressError && <p className="text-xs text-red-600">{addressError}</p>}

        <div className="grid md:grid-cols-2 gap-4">
          <TextField label="Tên người gửi/kho" value={settings.pickup_name} onChange={(value) => update('pickup_name', value)} />
          <TextField label="Số điện thoại kho" value={settings.pickup_phone} onChange={(value) => update('pickup_phone', value)} />
          <label className="block">
            <span className="text-sm font-medium">Tỉnh/thành kho lấy hàng</span>
            <select value={settings.pickup_province_id} onChange={(event) => changeShopProvince(event.target.value)} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-white">
              <option value="">Chọn tỉnh/thành</option>
              {provinces.map((province) => <option key={province.code} value={province.code}>{province.name}</option>)}
            </select>
          </label>
          <label className="block">
            <span className="text-sm font-medium">Quận/huyện kho lấy hàng</span>
            <select value={settings.pickup_district_id} onChange={(event) => changePickupDistrict(event.target.value)} disabled={!settings.pickup_province_id} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100">
              <option value="">Chọn quận/huyện</option>
              {districts.map((district) => <option key={district.code} value={district.code}>{district.name}</option>)}
            </select>
          </label>
          <label className="block">
            <span className="text-sm font-medium">Phường/xã kho lấy hàng</span>
            <select value={settings.pickup_ward_code} onChange={(event) => changePickupWard(event.target.value)} disabled={!settings.pickup_district_id} className="mt-1 w-full border rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100">
              <option value="">Chọn phường/xã</option>
              {wards.map((ward) => <option key={ward.code} value={ward.code}>{ward.name}</option>)}
            </select>
          </label>
          <TextField label="Địa chỉ chi tiết kho" value={settings.pickup_address} onChange={(value) => update('pickup_address', value)} />
        </div>
        <div className="grid md:grid-cols-4 gap-4">
          <NumberField label="Cân nặng mặc định (gram)" value={settings.default_weight_gram} onChange={(value) => update('default_weight_gram', value)} />
          <NumberField label="Dài mặc định (cm)" value={settings.default_length_cm} onChange={(value) => update('default_length_cm', value)} />
          <NumberField label="Rộng mặc định (cm)" value={settings.default_width_cm} onChange={(value) => update('default_width_cm', value)} />
          <NumberField label="Cao mặc định (cm)" value={settings.default_height_cm} onChange={(value) => update('default_height_cm', value)} />
        </div>
      </section>

      <section className="bg-white rounded-xl border p-5 space-y-4">
        <h3 className="font-semibold">Chính sách miễn phí vận chuyển</h3>
        <label className="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" checked={settings.free_shipping_enabled} onChange={(event) => update('free_shipping_enabled', event.target.checked)} />
          Bật miễn phí ship theo ngưỡng đơn hàng
        </label>
        <div className="grid md:grid-cols-2 gap-4">
          <NumberField label="Ngưỡng miễn phí ship" value={settings.free_shipping_min_order_value} onChange={(value) => update('free_shipping_min_order_value', value)} />
        </div>
        <p className="text-xs text-muted-foreground">Phí vận chuyển được GHN báo theo địa chỉ nhận hàng. Các mức phí khu vực cũ không còn được sử dụng.</p>
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
