import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import {
  ArrowDownRight, ArrowRight, ArrowUpRight, AlertTriangle, ClipboardCheck,
  Clock3, Package, PackageSearch, RefreshCw, RotateCcw, ShoppingBag,
  TrendingUp, Users, Warehouse,
} from 'lucide-react';
import {
  Cell, ComposedChart, Legend, Line, Pie, PieChart, ResponsiveContainer,
  Tooltip, XAxis, YAxis,
} from 'recharts';
import { adminService } from '../../services/orderService';
import { ORDER_STATUS_COLORS, ORDER_STATUS_LABELS } from '../../constants/status';
import { formatCurrency, formatDateTime } from '../../utils/formatters';
import { Button } from '../ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Tooltip as UiTooltip, TooltipContent, TooltipTrigger } from '../ui/tooltip';
import { ImageWithFallback } from '../figma/ImageWithFallback';

type RangeKey = 'today' | 'week' | 'month' | 'year' | 'custom';
type Metric = { value: number; previous_value: number; change_percent: number | null; direction: 'up' | 'down' | 'neutral' };
type DashboardData = {
  period: { from: string; to: string; previous_from: string; previous_to: string };
  kpis: Record<string, Metric>;
  actions: { key: string; count: number; secondary_count?: number; label: string; description: string; href: string; priority: string }[];
  charts: {
    revenue_and_completed_orders: { label: string; revenue: number; completed_orders: number }[];
    revenue_by_category: { name: string; revenue: number; percent: number }[];
  };
  recent_orders: { id: string; customer: string; created_at: string; total: number; status: string; last_processed_by?: string | null }[];
  top_products: { id: string; name: string; sku?: string | null; image?: string | null; sold: number; revenue: number; stock: number }[];
  meta: { revenue_rule: string };
};

const donutColors = ['#ea5c21', '#2563eb', '#16a34a', '#9333ea', '#0f766e', '#64748b'];
const rangeLabels: Record<RangeKey, string> = { today: 'Hôm nay', week: 'Tuần này', month: 'Tháng này', year: 'Năm nay', custom: 'Tùy chỉnh' };

function toDateInput(date: Date) {
  const offset = date.getTimezoneOffset();
  return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 10);
}

function rangeDates(range: RangeKey, customFrom?: string, customTo?: string) {
  const now = new Date();
  const end = toDateInput(now);
  if (range === 'custom') return { from: customFrom || end, to: customTo || end };
  if (range === 'today') return { from: end, to: end };
  if (range === 'week') {
    const start = new Date(now);
    start.setDate(now.getDate() - ((now.getDay() + 6) % 7));
    return { from: toDateInput(start), to: end };
  }
  if (range === 'month') return { from: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`, to: end };
  return { from: `${now.getFullYear()}-01-01`, to: end };
}

const actionIcons = { pending_orders: ClipboardCheck, pending_returns: RotateCcw, pending_receipts: Warehouse, low_stock: AlertTriangle };

export function AdminDashboard() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const parsedRange = searchParams.get('range');
  const range: RangeKey = parsedRange === 'week' || parsedRange === 'month' || parsedRange === 'year' || parsedRange === 'custom' ? parsedRange : 'today';
  const initialDates = rangeDates(range, searchParams.get('from') ?? undefined, searchParams.get('to') ?? undefined);
  const [customFrom, setCustomFrom] = useState(initialDates.from);
  const [customTo, setCustomTo] = useState(initialDates.to);
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const appliedDates = useMemo(() => rangeDates(range, customFrom, customTo), [range, customFrom, customTo]);
  const updateRange = (nextRange: RangeKey, from?: string, to?: string) => {
    const dates = rangeDates(nextRange, from, to);
    setCustomFrom(dates.from);
    setCustomTo(dates.to);
    setSearchParams({ range: nextRange, from: dates.from, to: dates.to });
  };

  useEffect(() => {
    let active = true;
    setLoading(true);
    setError('');
    adminService.getDashboard(appliedDates)
      .then((data) => active && setDashboard(data))
      .catch((requestError: any) => active && setError(requestError.response?.data?.message ?? 'Không thể tải dữ liệu tổng quan.'))
      .finally(() => active && setLoading(false));
    return () => { active = false; };
  }, [appliedDates.from, appliedDates.to]);

  const metricCards = [
    { key: 'revenue', label: 'Doanh thu', icon: TrendingUp, format: (value: number) => formatCurrency(value), hint: 'Chỉ tính đơn đã giao, đã trừ hàng trả.' },
    { key: 'orders', label: 'Đơn hàng', icon: ShoppingBag, format: (value: number) => value.toLocaleString('vi-VN'), hint: 'Tất cả đơn phát sinh trong khoảng thời gian.' },
    { key: 'new_customers', label: 'Khách hàng mới', icon: Users, format: (value: number) => value.toLocaleString('vi-VN'), hint: 'Khách hàng đăng ký mới trong khoảng thời gian.' },
    { key: 'active_products', label: 'Sản phẩm đang kinh doanh', icon: Package, format: (value: number) => value.toLocaleString('vi-VN'), hint: 'Sản phẩm hiển thị tính đến cuối kỳ.' },
  ];
  const periodText = dashboard ? `${new Date(`${dashboard.period.from}T00:00:00`).toLocaleDateString('vi-VN')} - ${new Date(`${dashboard.period.to}T00:00:00`).toLocaleDateString('vi-VN')}` : '';

  return (
    <div className="space-y-6 pb-4">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-2xl font-semibold tracking-normal">Tổng quan</h2>
          <p className="mt-1 text-sm text-muted-foreground">Theo dõi tình hình bán hàng và các việc cần xử lý.</p>
        </div>
        <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
          <select value={range} onChange={(event) => updateRange(event.target.value as RangeKey)} className="h-9 rounded-md border bg-white px-3 text-sm outline-none focus:border-orange-500">
            {(Object.keys(rangeLabels) as RangeKey[]).map((key) => <option key={key} value={key}>{rangeLabels[key]}</option>)}
          </select>
          {range === 'custom' && <div className="flex items-center gap-2">
            <input aria-label="Từ ngày" type="date" value={customFrom} max={customTo} onChange={(event) => setCustomFrom(event.target.value)} className="h-9 min-w-0 rounded-md border bg-white px-2 text-sm" />
            <span className="text-xs text-muted-foreground">đến</span>
            <input aria-label="Đến ngày" type="date" value={customTo} min={customFrom} max={toDateInput(new Date())} onChange={(event) => setCustomTo(event.target.value)} className="h-9 min-w-0 rounded-md border bg-white px-2 text-sm" />
          </div>}
          <Button variant="outline" size="icon" onClick={() => updateRange(range, customFrom, customTo)} title="Tải lại dữ liệu"><RefreshCw className={`size-4 ${loading ? 'animate-spin' : ''}`} /></Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
        <Clock3 className="size-3.5" />
        <span>Khoảng áp dụng: <span className="font-medium text-foreground">{loading ? 'Đang cập nhật...' : periodText}</span></span>
      </div>

      {error ? <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div> : <>
        <section aria-label="Việc cần xử lý" className="border-y border-border bg-white py-4">
          <div className="mb-3 flex items-center justify-between px-1">
            <div><h3 className="font-semibold">Việc cần xử lý</h3><p className="text-xs text-muted-foreground">Ưu tiên xử lý các mục đang chờ trước.</p></div>
          </div>
          <div className="grid gap-px overflow-hidden rounded-lg border bg-border sm:grid-cols-2 xl:grid-cols-4">
            {(dashboard?.actions ?? []).map((item) => {
              const Icon = actionIcons[item.key as keyof typeof actionIcons] ?? AlertTriangle;
              const urgent = item.priority === 'danger';
              return <button key={item.key} onClick={() => navigate(item.href)} className="flex min-h-28 items-start gap-3 bg-white p-4 text-left transition-colors hover:bg-orange-50">
                <span className={`mt-0.5 grid size-9 shrink-0 place-items-center rounded-md ${urgent ? 'bg-red-50 text-red-600' : item.count > 0 ? 'bg-orange-50 text-orange-600' : 'bg-slate-100 text-slate-500'}`}><Icon className="size-4" /></span>
                <span className="min-w-0 flex-1"><span className="flex items-center justify-between gap-2"><strong className="text-xl">{loading ? '—' : item.count}</strong><ArrowRight className="size-4 text-muted-foreground" /></span><span className="mt-1 block text-sm font-medium">{item.label}</span><span className="mt-1 block text-xs text-muted-foreground">{item.count === 0 ? 'Hiện không có mục cần xử lý' : item.secondary_count ? `${item.secondary_count} SKU đã hết hàng` : item.description}</span></span>
              </button>;
            })}
          </div>
        </section>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {metricCards.map(({ key, label, icon: Icon, format, hint }) => {
            const metric = dashboard?.kpis[key];
            const ChangeIcon = metric?.direction === 'up' ? ArrowUpRight : metric?.direction === 'down' ? ArrowDownRight : ArrowRight;
            const changeClass = metric?.direction === 'up' ? 'text-emerald-700' : metric?.direction === 'down' ? 'text-red-600' : 'text-muted-foreground';
            return <Card key={key} className="gap-0 rounded-lg"><CardContent className="p-4"><div className="flex items-start justify-between"><div><p className="text-sm text-muted-foreground">{label}</p><p className="mt-2 text-2xl font-semibold">{loading ? '—' : format(metric?.value ?? 0)}</p></div><UiTooltip><TooltipTrigger asChild><span className="grid size-9 place-items-center rounded-md bg-orange-50 text-orange-600"><Icon className="size-4" /></span></TooltipTrigger><TooltipContent>{hint}</TooltipContent></UiTooltip></div><div className={`mt-3 flex items-center gap-1 text-xs ${changeClass}`}><ChangeIcon className="size-3.5" />{metric?.change_percent === null ? <span>Chưa có dữ liệu kỳ trước</span> : <span>{Math.abs(metric?.change_percent ?? 0).toLocaleString('vi-VN')}% so với kỳ trước</span>}</div></CardContent></Card>;
          })}
        </section>

        <section className="grid gap-4 xl:grid-cols-[minmax(0,1.75fr)_minmax(300px,0.9fr)]">
          <Card className="gap-0 rounded-lg"><CardHeader className="px-5 py-4"><CardTitle className="text-base">Doanh thu và đơn hoàn thành</CardTitle><p className="text-xs text-muted-foreground">{dashboard?.meta.revenue_rule}</p></CardHeader><CardContent className="px-3 pb-4 sm:px-5">
            {loading ? <div className="h-72 animate-pulse rounded-md bg-slate-50" /> : dashboard?.charts.revenue_and_completed_orders.length ? <ResponsiveContainer width="100%" height={288}><ComposedChart data={dashboard.charts.revenue_and_completed_orders} margin={{ top: 8, right: 12, left: 4, bottom: 0 }}><XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} /><YAxis yAxisId="revenue" tickFormatter={(value) => `${Math.round(value / 1_000_000)}tr`} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} width={45} /><YAxis yAxisId="orders" orientation="right" allowDecimals={false} tick={{ fontSize: 11 }} tickLine={false} axisLine={false} width={28} /><Tooltip formatter={(value: number, name: string) => [name === 'Doanh thu' ? formatCurrency(value) : value, name]} /><Legend wrapperStyle={{ fontSize: 12 }} /><Line yAxisId="revenue" type="monotone" name="Doanh thu" dataKey="revenue" stroke="#ea5c21" strokeWidth={2.5} dot={false} /><Line yAxisId="orders" type="monotone" name="Đơn hoàn thành" dataKey="completed_orders" stroke="#2563eb" strokeWidth={2} dot={false} /></ComposedChart></ResponsiveContainer> : <EmptyChart text="Chưa có đơn đã giao trong khoảng thời gian này." />}
          </CardContent></Card>
          <Card className="gap-0 rounded-lg"><CardHeader className="px-5 py-4"><CardTitle className="text-base">Tỷ trọng doanh thu theo danh mục</CardTitle></CardHeader><CardContent className="px-4 pb-4">
            {loading ? <div className="h-72 animate-pulse rounded-md bg-slate-50" /> : dashboard?.charts.revenue_by_category.length ? <><div className="relative h-48"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={dashboard.charts.revenue_by_category} dataKey="revenue" nameKey="name" innerRadius={54} outerRadius={78} paddingAngle={2}>{dashboard.charts.revenue_by_category.map((category, index) => <Cell key={category.name} fill={donutColors[index % donutColors.length]} />)}</Pie><Tooltip formatter={(value: number) => formatCurrency(value)} /></PieChart></ResponsiveContainer><div className="pointer-events-none absolute inset-0 grid place-items-center text-center"><div><p className="text-xs text-muted-foreground">Tổng doanh thu</p><p className="text-sm font-semibold">{formatCurrency(dashboard.charts.revenue_by_category.reduce((sum, item) => sum + item.revenue, 0))}</p></div></div></div><div className="space-y-2">{dashboard.charts.revenue_by_category.map((item, index) => <div key={item.name} className="flex items-center gap-2 text-xs"><span className="size-2.5 rounded-full" style={{ backgroundColor: donutColors[index % donutColors.length] }} /><span className="min-w-0 flex-1 truncate">{item.name}</span><span className="text-muted-foreground">{item.percent}%</span><span className="w-20 text-right font-medium">{formatCurrency(item.revenue)}</span></div>)}</div></> : <EmptyChart text="Chưa có doanh thu theo danh mục." compact />}
          </CardContent></Card>
        </section>

        <section className="grid gap-4 xl:grid-cols-2">
          <Card className="gap-0 rounded-lg"><CardHeader className="flex flex-row items-center justify-between px-5 py-4"><CardTitle className="text-base">Đơn hàng gần đây</CardTitle><Button variant="ghost" size="sm" onClick={() => navigate('/admin/orders')}>Xem tất cả<ArrowRight /></Button></CardHeader><CardContent className="px-0 pb-0"><div className="overflow-x-auto"><table className="w-full min-w-[680px] text-sm"><thead className="border-y bg-slate-50 text-xs text-muted-foreground"><tr><th className="px-5 py-3 text-left font-medium">Mã đơn</th><th className="px-3 py-3 text-left font-medium">Khách hàng</th><th className="px-3 py-3 text-left font-medium">Thời gian</th><th className="px-3 py-3 text-right font-medium">Tổng tiền</th><th className="px-5 py-3 text-right font-medium">Trạng thái</th></tr></thead><tbody>{loading ? <TableSkeleton columns={5} /> : dashboard?.recent_orders.length ? dashboard.recent_orders.map((order) => <tr key={order.id} className="border-b last:border-0 hover:bg-slate-50"><td className="px-5 py-3 font-medium">#{order.id}</td><td className="px-3 py-3"><p className="max-w-32 truncate">{order.customer}</p>{order.last_processed_by && <p className="text-xs text-muted-foreground">Xử lý: {order.last_processed_by}</p>}</td><td className="px-3 py-3 text-xs text-muted-foreground">{formatDateTime(order.created_at)}</td><td className="px-3 py-3 text-right font-medium">{formatCurrency(order.total)}</td><td className="px-5 py-3 text-right"><span className={`inline-flex rounded-md px-2 py-1 text-xs ${ORDER_STATUS_COLORS[order.status] ?? 'bg-slate-100 text-slate-700'}`}>{ORDER_STATUS_LABELS[order.status] ?? order.status}</span></td></tr>) : <EmptyRow columns={5} text="Chưa có đơn hàng trong khoảng thời gian này." />}</tbody></table></div></CardContent></Card>
          <Card className="gap-0 rounded-lg"><CardHeader className="flex flex-row items-center justify-between px-5 py-4"><CardTitle className="text-base">Sản phẩm bán chạy</CardTitle><Button variant="ghost" size="sm" onClick={() => navigate('/admin/products')}>Xem tất cả<ArrowRight /></Button></CardHeader><CardContent className="px-0 pb-0"><div className="overflow-x-auto"><table className="w-full min-w-[650px] text-sm"><thead className="border-y bg-slate-50 text-xs text-muted-foreground"><tr><th className="px-5 py-3 text-left font-medium">Sản phẩm / SKU</th><th className="px-3 py-3 text-right font-medium">Đã bán</th><th className="px-3 py-3 text-right font-medium">Doanh thu</th><th className="px-5 py-3 text-right font-medium">Tồn kho</th></tr></thead><tbody>{loading ? <TableSkeleton columns={4} /> : dashboard?.top_products.length ? dashboard.top_products.map((product) => <tr key={product.id} className="border-b last:border-0 hover:bg-slate-50"><td className="px-5 py-3"><div className="flex items-center gap-3"><ImageWithFallback src={product.image ?? ''} alt={product.name} className="size-9 rounded-md object-cover" /><div className="min-w-0"><p className="max-w-48 truncate font-medium">{product.name}</p><p className="text-xs text-muted-foreground">{product.sku || 'Chưa có SKU'}</p></div></div></td><td className="px-3 py-3 text-right">{product.sold}</td><td className="px-3 py-3 text-right font-medium">{formatCurrency(product.revenue)}</td><td className={`px-5 py-3 text-right font-medium ${product.stock === 0 ? 'text-red-600' : 'text-foreground'}`}>{product.stock}</td></tr>) : <EmptyRow columns={4} text="Chưa có sản phẩm bán ra trong khoảng thời gian này." />}</tbody></table></div></CardContent></Card>
        </section>
      </>}
    </div>
  );
}

function EmptyChart({ text, compact = false }: { text: string; compact?: boolean }) {
  return <div className={`grid place-items-center text-center text-sm text-muted-foreground ${compact ? 'h-72' : 'h-72'}`}><div><PackageSearch className="mx-auto mb-2 size-7 text-slate-300" /><p>{text}</p></div></div>;
}

function TableSkeleton({ columns }: { columns: number }) {
  return <>{Array.from({ length: 4 }).map((_, index) => <tr key={index} className="border-b"><td colSpan={columns} className="h-14 animate-pulse bg-slate-50" /></tr>)}</>;
}

function EmptyRow({ columns, text }: { columns: number; text: string }) {
  return <tr><td colSpan={columns} className="px-5 py-12 text-center text-sm text-muted-foreground">{text}</td></tr>;
}
