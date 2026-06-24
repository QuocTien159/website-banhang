import { Link } from 'react-router';
import { Facebook, Instagram, Youtube, Phone, Mail, MapPin } from 'lucide-react';

export function Footer() {
  return (
    <footer className="relative bg-gradient-to-b from-[#030213] via-[#0a0822] to-[#030213] text-white mt-16 border-t border-white/5 overflow-hidden">
      {/* Background Glow */}
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-orange-600/5 blur-[120px] rounded-full pointer-events-none" />

      <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-10 lg:gap-8">
          {/* Brand & Newsletter (spans 4 cols) */}
          <div className="lg:col-span-4">
            <div className="flex items-center gap-2.5 mb-6 group">
              <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-600 shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform duration-300">
                <span className="text-white text-sm font-black tracking-wider">TPS</span>
              </div>
              <span className="text-2xl font-extrabold tracking-tight text-white">
                TienPro<span className="text-orange-500">Sport</span>
              </span>
            </div>
            <p className="text-sm text-gray-400 leading-relaxed mb-6 font-medium">
              Hệ thống cửa hàng phân phối dụng cụ và thời trang thể thao cao cấp. Đồng hành cùng bạn chinh phục mọi giới hạn.
            </p>
            <div className="flex gap-3">
              {[
                { icon: Facebook, href: 'https://www.facebook.com/quoc.tien.872680/?locale=vi_VN' },
                { icon: Instagram, href: 'https://www.facebook.com/quoc.tien.872680/?locale=vi_VN' },
                { icon: Youtube, href: 'https://www.facebook.com/quoc.tien.872680/?locale=vi_VN' },
              ].map(({ icon: Icon, href }, i) => (
                <a key={i} href={href} className="w-10 h-10 rounded-full bg-white/5 border border-white/10 hover:border-orange-500/50 hover:bg-orange-500 hover:text-white text-gray-400 flex items-center justify-center transition-all duration-300 hover:-translate-y-1">
                  <Icon className="w-4 h-4" />
                </a>
              ))}
            </div>
          </div>

          {/* Links (spans 2 cols) */}
          <div className="lg:col-span-2 lg:col-start-6">
            <h4 className="text-base font-bold mb-6 text-white tracking-wide">Khám phá</h4>
            <ul className="space-y-3.5">
              {['Áo thể thao', 'Quần tập luyện', 'Giày chạy bộ', 'Phụ kiện Gym'].map(cat => (
                <li key={cat}>
                  <Link
                    to={`/products?category=${encodeURIComponent(cat.split(' ')[0])}`}
                    className="text-sm text-gray-400 hover:text-orange-400 hover:translate-x-1 inline-block transition-all duration-300 font-medium"
                  >
                    {cat}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Policy (spans 2 cols) */}
          <div className="lg:col-span-2">
            <h4 className="text-base font-bold mb-6 text-white tracking-wide">Hỗ trợ</h4>
            <ul className="space-y-3.5">
              {[
                'Chính sách giao hàng',
                'Chính sách đổi trả',
                'Chính sách bảo hành',
                'Hướng dẫn mua hàng',
                'Câu hỏi thường gặp',
              ].map(item => (
                <li key={item}>
                  <a href="#" className="text-sm text-gray-400 hover:text-orange-400 hover:translate-x-1 inline-block transition-all duration-300 font-medium">
                    {item}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact (spans 3 cols) */}
          <div className="lg:col-span-3">
            <h4 className="text-base font-bold mb-6 text-white tracking-wide">Thông tin liên hệ</h4>
            <ul className="space-y-4">
              <li className="flex items-start gap-3 group">
                <div className="mt-0.5 p-1.5 rounded-lg bg-orange-500/10 text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                  <MapPin className="w-4 h-4" />
                </div>
                <span className="text-sm text-gray-400 leading-relaxed group-hover:text-gray-300 transition-colors">
                  4/40 Bình Đức<br/>Phường Phú Định, Quận 8<br/>TP. Hồ Chí Minh
                </span>
              </li>
              <li className="flex items-center gap-3 group">
                <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                  <Phone className="w-4 h-4" />
                </div>
                <span className="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">
                  0903006340 (Miễn phí)
                </span>
              </li>
              <li className="flex items-center gap-3 group">
                <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                  <Mail className="w-4 h-4" />
                </div>
                <span className="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">
                  quoctien15904@gmail.com
                </span>
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-white/10 mt-16 pt-8 flex flex-col md:flex-row items-center justify-between gap-6">
          <p className="text-sm text-gray-500 font-medium">
            © {new Date().getFullYear()} TienProSport. Mọi quyền được bảo lưu.
          </p>
          <div className="flex items-center gap-3 opacity-60 grayscale hover:grayscale-0 transition-all duration-500">
            <div className="bg-white p-1.5 rounded-md">
              <img src="https://cdn.haitrieu.com/wp-content/uploads/2022/10/Logo-VNPAY-QR-1.png" alt="VNPAY" className="h-4 object-contain" />
            </div>
            <div className="bg-white p-1.5 rounded-md flex items-center">
              <img src="https://upload.wikimedia.org/wikipedia/commons/a/a0/MoMo_Logo_App.svg" alt="MoMo" className="h-4 object-contain rounded-[3px]" />
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
}
