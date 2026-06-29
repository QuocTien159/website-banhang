import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  ArrowUpRight,
  Clock,
  Package,
  ShoppingBag,
  TrendingUp,
  Users,
} from "lucide-react";
import { adminService } from "../../services/orderService";
import { ORDER_STATUS_COLORS, ORDER_STATUS_LABELS } from "../../constants/status";
import { formatCurrency } from "../../utils/formatters";

const STATUS_COLORS: Record<string, string> = {
  pending: "bg-yellow-100 text-yellow-700",
  confirmed: "bg-blue-100 text-blue-700",
  shipping: "bg-purple-100 text-purple-700",
  delivered: "bg-green-100 text-green-700",
  cancelled: "bg-red-100 text-red-700",
};

const STATUS_LABELS: Record<string, string> = {
  pending: "Chờ xác nhận",
  confirmed: "Đã xác nhận",
  shipping: "Đang giao",
  delivered: "Đã giao",
  cancelled: "Đã hủy",
};

type RecentOrder = {
  id: string;
  status: string;
  customer?: string;
  customer_name?: string;
  date?: string;
  created_at?: string;
  total: number;
};

type TopProduct = {
  id: string;
  name: string;
  sold: number;
  price: number;
};

export function AdminDashboard() {
  const navigate = useNavigate();
  const [summary, setSummary] = useState<Record<string, any>>({});
  const [revenue, setRevenue] = useState<
    { month: string; revenue: number; orders: number }[]
  >([]);
  const [recentOrders, setRecentOrders] = useState<RecentOrder[]>([]);
  const [topProducts, setTopProducts] = useState<TopProduct[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      adminService.getSummary(),
      adminService.getRevenue(),
      adminService.getAdminOrders({ per_page: 6, sort: "newest" }),
      adminService.getAdminProducts({ per_page: 5, sort: "sold_desc" }),
    ])
      .then(([sum, rev, orders, products]) => {
        setSummary(sum?.stats ?? {});
        setRevenue(rev?.monthly ?? []);
        setRecentOrders(orders?.data ?? sum?.recent_orders ?? []);
        setTopProducts(sum?.top_products ?? products?.data ?? []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const formatDate = (value?: string) => {
    if (!value) return "";
    return new Date(value).toLocaleDateString("vi-VN", {
      day: "2-digit",
      month: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const stats = [
    {
      label: "Doanh thu",
      value: formatCurrency(summary.total_revenue ?? 0),
      sub: "Chỉ tính đơn đã hoàn thành",
      icon: TrendingUp,
      color: "#ea5c21",
      bg: "bg-orange-50",
    },
    {
      label: "Đơn hàng",
      value: summary.total_orders ?? 0,
      sub: `${summary.pending_orders ?? 0} chờ xác nhận`,
      icon: ShoppingBag,
      color: "#3b82f6",
      bg: "bg-blue-50",
    },
    {
      label: "Khách hàng",
      value: summary.total_customers ?? 0,
      sub: "Tổng khách hàng",
      icon: Users,
      color: "#10b981",
      bg: "bg-green-50",
    },
    {
      label: "Sản phẩm",
      value: summary.total_products ?? 0,
      sub: `${summary.low_stock ?? 0} SKU sắp hết`,
      icon: Package,
      color: "#8b5cf6",
      bg: "bg-purple-50",
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-semibold">Tổng quan</h2>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat) => (
          <div
            key={stat.label}
            className="bg-white rounded-xl border border-border p-4"
          >
            <div className="flex items-start justify-between mb-3">
              <div
                className={`w-10 h-10 rounded-xl ${stat.bg} flex items-center justify-center`}
              >
                <stat.icon className="w-5 h-5" style={{ color: stat.color }} />
              </div>
              <ArrowUpRight className="w-4 h-4 text-green-500" />
            </div>
            <p className="text-xl font-bold">{loading ? "..." : stat.value}</p>
            <p className="text-sm text-muted-foreground">{stat.label}</p>
            <p className="text-xs text-muted-foreground mt-1">{stat.sub}</p>
          </div>
        ))}
      </div>

      {revenue.length > 0 && (
        <div className="grid lg:grid-cols-2 gap-4">
          <div className="bg-white rounded-xl border border-border p-4">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h4 className="font-semibold">Doanh thu theo tháng</h4>
                <p className="text-xs text-muted-foreground">
                  Chỉ tính đơn đã hoàn thành
                </p>
              </div>
              <span className="text-xs text-muted-foreground">VNĐ</span>
            </div>
            <ResponsiveContainer width="100%" height={200}>
              <BarChart data={revenue}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                <YAxis
                  tick={{ fontSize: 12 }}
                  tickFormatter={(value) => `${(value / 1000000).toFixed(0)}M`}
                />
                <Tooltip
                  formatter={(value: number) => [
                    formatCurrency(value),
                    "Doanh thu",
                  ]}
                />
                <Bar dataKey="revenue" fill="#ea5c21" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>

          <div className="bg-white rounded-xl border border-border p-4">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h4 className="font-semibold">Đơn hoàn thành theo tháng</h4>
                <p className="text-xs text-muted-foreground">
                  Dùng cùng điều kiện tính doanh thu
                </p>
              </div>
              <span className="text-xs text-muted-foreground">Số đơn</span>
            </div>
            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={revenue}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                <YAxis tick={{ fontSize: 12 }} />
                <Tooltip
                  formatter={(value: number) => [value, "Đơn hoàn thành"]}
                />
                <Line
                  type="monotone"
                  dataKey="orders"
                  stroke="#3b82f6"
                  strokeWidth={2}
                  dot={{ fill: "#3b82f6", r: 4 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}

      <div className="grid lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 bg-white rounded-xl border border-border">
          <div className="flex items-center justify-between p-4 border-b border-border">
            <h4 className="font-semibold flex items-center gap-2">
              <Clock className="w-4 h-4" style={{ color: "#ea5c21" }} />
              Đơn hàng gần đây
            </h4>
            <button
              onClick={() => navigate("/admin/orders")}
              className="text-xs hover:underline"
              style={{ color: "#ea5c21" }}
            >
              Xem tất cả →
            </button>
          </div>
          <div className="divide-y divide-border">
            {loading ? (
              Array.from({ length: 4 }).map((_, index) => (
                <div
                  key={index}
                  className="p-4 h-16 animate-pulse bg-gray-50"
                />
              ))
            ) : recentOrders.length === 0 ? (
              <p className="p-4 text-sm text-muted-foreground text-center">
                Chưa có đơn hàng
              </p>
            ) : (
              recentOrders.map((order) => (
                <div
                  key={order.id}
                  className="p-4 flex items-center justify-between gap-4"
                >
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium">#{order.id}</span>
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full ${
                          ORDER_STATUS_COLORS[order.status] ?? ""
                        }`}
                      >
                        {ORDER_STATUS_LABELS[order.status] ?? order.status}
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      {order.customer_name ?? order.customer ?? "Khách hàng"} ·{" "}
                      {formatDate(order.created_at ?? order.date)}
                    </p>
                  </div>
                  <span
                    className="text-sm font-semibold shrink-0"
                    style={{ color: "#ea5c21" }}
                  >
                    {formatCurrency(order.total)}
                  </span>
                </div>
              ))
            )}
          </div>
        </div>

        <div className="bg-white rounded-xl border border-border">
          <div className="flex items-center justify-between p-4 border-b border-border">
            <div>
              <h4 className="font-semibold">Bán chạy nhất</h4>
              <p className="text-xs text-muted-foreground">
                Chỉ tính đơn đã hoàn thành
              </p>
            </div>
            <button
              onClick={() => navigate("/admin/products")}
              className="text-xs hover:underline"
              style={{ color: "#ea5c21" }}
            >
              Xem tất cả →
            </button>
          </div>
          <div className="divide-y divide-border">
            {loading ? (
              Array.from({ length: 5 }).map((_, index) => (
                <div
                  key={index}
                  className="p-3 h-14 animate-pulse bg-gray-50"
                />
              ))
            ) : topProducts.length === 0 ? (
              <p className="p-4 text-sm text-muted-foreground text-center">
                Chưa có dữ liệu bán hàng
              </p>
            ) : (
              topProducts.map((product, index) => (
                <div key={product.id} className="p-3 flex items-center gap-3">
                  <span
                    className="w-5 h-5 rounded flex items-center justify-center text-xs font-bold shrink-0"
                    style={{ color: index < 3 ? "#ea5c21" : "#999" }}
                  >
                    {index + 1}
                  </span>
                  <div className="flex-1 min-w-0">
                    <p className="text-xs truncate">{product.name}</p>
                    <p className="text-xs text-muted-foreground">
                      Đã bán: {product.sold}
                    </p>
                  </div>
                  <span
                    className="text-xs font-medium shrink-0"
                    style={{ color: "#ea5c21" }}
                  >
                    {formatCurrency(product.price)}
                  </span>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      <div className="bg-white rounded-xl border border-border p-4">
        <h4 className="font-semibold mb-4">Trạng thái đơn hàng</h4>
        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
          {Object.entries(ORDER_STATUS_LABELS).map(([status, label]) => {
            const count = summary[`status_${status}`] ?? 0;
            return (
              <div
                key={status}
                className={`rounded-xl p-3 text-center ${ORDER_STATUS_COLORS[status]}`}
              >
                <p className="text-2xl font-bold">{count}</p>
                <p className="text-xs mt-0.5">{label}</p>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
