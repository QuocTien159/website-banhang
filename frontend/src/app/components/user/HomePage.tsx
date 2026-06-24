import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router';
import { ArrowRight, Bell, Star, Shield, Truck, RefreshCw, Headphones, X } from 'lucide-react';
import { productService, type ApiProduct } from '../../services/productService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { commerceService, type Announcement } from '../../services/commerceService';

const formatPrice = (p: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p);

const HERO_IMAGE = 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=1400&q=80';

const CATEGORY_IMAGES: Record<string, string> = {
  'Áo': 'https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=600&q=80',
  'Quần': 'https://images.unsplash.com/photo-1591195853828-11db59a44f6b?w=600&q=80',
  'Giày': 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80',
  'Phụ kiện': 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?w=600&q=80',
};

export function HomePage() {
  const navigate = useNavigate();
  const [featuredProducts, setFeaturedProducts] = useState<ApiProduct[]>([]);
  const [newestProducts, setNewestProducts] = useState<ApiProduct[]>([]);
  const [categories, setCategories] = useState<{ id: string; name: string; count: number }[]>([]);
  const [loading, setLoading] = useState(true);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [showAnnouncements, setShowAnnouncements] = useState(false);

  useEffect(() => {
    // Load featured products, newest, and categories in parallel
    Promise.all([
      productService.getProducts({ per_page: 8 }),
      productService.getProducts({ sort: 'newest', per_page: 6 }),
      productService.getCategories(),
    ])
      .then(([featured, newest, cats]) => {
        setFeaturedProducts(featured.data);
        setNewestProducts(newest.data);
        setCategories(Array.isArray(cats) ? cats : []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));

    commerceService.announcements().then((items) => {
      setAnnouncements(items);
      if (items.length > 0) setShowAnnouncements(true);
    }).catch(() => setAnnouncements([]));
  }, []);

  return (
    <div>
      {/* Hero */}
      <section className="relative min-h-[600px] lg:min-h-[720px] flex items-center overflow-hidden">
        <ImageWithFallback
          src={HERO_IMAGE}
          alt="TienProSport Hero"
          className="absolute inset-0 w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-[#030213]/95 via-[#030213]/70 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-t from-[#030213]/40 via-transparent to-transparent" />
        
        <div className="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 py-20">
          <div className="max-w-2xl">
            <div className="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-white/10 backdrop-blur-md border border-white/20 mb-8 shadow-xl">
              <span className="relative flex h-2.5 w-2.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
              </span>
              <span className="text-xs sm:text-sm font-semibold tracking-wider text-white uppercase">Bộ sưu tập 2026</span>
            </div>
            
            <h1 className="text-5xl sm:text-6xl lg:text-[5rem] font-black tracking-tight text-white mb-6 leading-[1.1]">
              Vượt Qua <br className="hidden sm:block" />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600 drop-shadow-sm">
                Giới Hạn
              </span> Bản Thân
            </h1>
            
            <p className="text-lg sm:text-xl text-gray-300/90 mb-10 max-w-xl font-medium leading-relaxed">
              Trang bị thể thao chuyên nghiệp dành cho những người không bao giờ bỏ cuộc. Khám phá ngay hàng trăm sản phẩm chính hãng hàng đầu.
            </p>
            
            <div className="flex flex-wrap items-center gap-4">
              <Button 
                onClick={() => navigate('/products')} 
                className="h-14 px-8 rounded-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white text-base font-bold shadow-lg shadow-orange-500/30 hover:shadow-orange-500/50 transition-all hover:-translate-y-0.5 border-0 gap-2"
              >
                Khám phá ngay <ArrowRight className="w-5 h-5" />
              </Button>
              <Button 
                variant="outline" 
                onClick={() => navigate('/products?category=Áo')} 
                className="h-14 px-8 rounded-full border-2 border-white/30 text-white bg-transparent hover:bg-white hover:text-[#030213] text-base font-bold backdrop-blur-sm transition-all hover:-translate-y-0.5"
              >
                Xem áo thể thao
              </Button>
            </div>

            <div className="mt-14 flex items-center gap-6 text-white/60">
              <div className="flex items-center gap-2">
                <div className="flex -space-x-2">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="w-8 h-8 rounded-full bg-gray-800 border-2 border-[#030213] flex items-center justify-center overflow-hidden">
                      <ImageWithFallback src={`https://i.pravatar.cc/100?img=${i + 10}`} alt="User" className="w-full h-full object-cover" />
                    </div>
                  ))}
                </div>
                <span className="text-sm font-medium">Hơn 10k+ khách hàng</span>
              </div>
              <div className="w-1.5 h-1.5 rounded-full bg-white/30"></div>
              <div className="flex items-center gap-1">
                <Star className="w-4 h-4 text-orange-400 fill-orange-400" />
                <span className="text-sm font-bold text-white">4.9/5</span>
                <span className="text-sm">Đánh giá</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Trust badges */}
      <section className="bg-[#030213] text-white py-4">
        <div className="max-w-7xl mx-auto px-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {[
              { icon: Truck, label: 'Miễn phí vận chuyển', sub: 'Đơn từ 500.000đ' },
              { icon: Shield, label: 'Bảo hành chính hãng', sub: 'Lên đến 24 tháng' },
              { icon: RefreshCw, label: 'Đổi trả dễ dàng', sub: 'Trong vòng 30 ngày' },
              { icon: Headphones, label: 'Hỗ trợ 24/7', sub: '1800 1234 (miễn phí)' },
            ].map(({ icon: Icon, label, sub }) => (
              <div key={label} className="flex items-center gap-3 py-1">
                <div className="w-9 h-9 rounded-full flex items-center justify-center shrink-0" style={{ backgroundColor: 'rgba(234,92,33,0.2)' }}>
                  <Icon className="w-4 h-4" style={{ color: '#ea5c21' }} />
                </div>
                <div>
                  <p className="text-sm font-medium text-white">{label}</p>
                  <p className="text-xs text-gray-400">{sub}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {showAnnouncements && announcements.length > 0 && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6">
          <button aria-label="Đóng thông báo" className="absolute inset-0 bg-black/65 backdrop-blur-[2px]" onClick={() => setShowAnnouncements(false)} />
          <section role="dialog" aria-modal="true" aria-labelledby="announcements-title" className="relative w-full max-w-4xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <header className="shrink-0 px-5 sm:px-7 py-5 border-b bg-gradient-to-r from-orange-50 to-white">
              <button aria-label="Đóng" onClick={() => setShowAnnouncements(false)} className="absolute top-4 right-4 w-9 h-9 rounded-full bg-white border shadow-sm grid place-items-center hover:bg-gray-50">
                <X className="w-5 h-5" />
              </button>
              <span className="inline-block text-xs uppercase tracking-wide text-orange-700 bg-orange-100 rounded-full px-3 py-1 mb-2">TienProSport</span>
              <h2 id="announcements-title" className="text-xl sm:text-2xl font-semibold pr-12">Thông báo</h2>
              <p className="text-sm text-muted-foreground">{announcements.length} thông báo mới nhất từ hệ thống</p>
            </header>
            <div className="overflow-y-auto px-4 sm:px-7 py-5 space-y-5">
              {announcements.map((announcement, announcementIndex) => (
                <article key={announcement.id} className={`pb-6 ${announcementIndex < announcements.length - 1 ? 'border-b' : ''}`}>
                  <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-3">
                    <div>
                      <span className="inline-block text-[11px] uppercase tracking-wide text-orange-700 bg-orange-50 rounded-full px-2.5 py-1 mb-2">{announcement.type}</span>
                      <h3 className="text-lg sm:text-xl font-semibold">{announcement.title}</h3>
                    </div>
                    <time className="text-xs sm:text-sm text-muted-foreground shrink-0">
                      {new Date(announcement.published_at).toLocaleString('vi-VN')}
                    </time>
                  </div>
                  <p className="text-sm sm:text-base leading-7 whitespace-pre-line">{announcement.content}</p>
                  {announcement.images?.length > 0 && (
                    <div className="mt-4 space-y-4">
                      {announcement.images.map((image, imageIndex) => (
                        <ImageWithFallback
                          key={image.id ?? image.url}
                          src={image.url}
                          alt={`${announcement.title} ${imageIndex + 1}`}
                          className="w-full max-h-[520px] object-contain bg-gray-50 border rounded-xl"
                        />
                      ))}
                    </div>
                  )}
                </article>
              ))}
            </div>
          </section>
        </div>
      )}

      {!showAnnouncements && announcements.length > 0 && (
        <button
          onClick={() => setShowAnnouncements(true)}
          className="fixed right-4 bottom-4 sm:right-6 sm:bottom-6 z-40 flex items-center gap-2 rounded-full bg-orange-600 px-4 py-3 text-sm font-medium text-white shadow-lg hover:bg-orange-700"
          aria-label="Mở thông báo"
        >
          <Bell className="w-4 h-4" />
          <span>Thông báo ({announcements.length})</span>
        </button>
      )}

      {/* Categories from API */}
      <section className="max-w-7xl mx-auto px-4 py-12">
        <div className="flex items-center justify-between mb-6">
          <h2>Danh mục sản phẩm</h2>
          <Link to="/products" className="text-sm flex items-center gap-1 hover:underline" style={{ color: '#ea5c21' }}>
            Xem tất cả <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 auto-rows-[160px] md:auto-rows-[220px]">
          {categories.length > 0
            ? categories.map((cat, index) => {
                let gridClass = "col-span-2 md:col-span-1";
                let tag = "";
                if (index === 0) {
                  gridClass = "col-span-2 md:col-span-2 md:row-span-2";
                  tag = "🔥 Nổi bật";
                } else if (index === 1) {
                  gridClass = "col-span-2 md:col-span-2 md:row-span-1";
                  tag = "⚡ Xu hướng";
                } else if (index === 2) {
                  gridClass = "col-span-1 md:col-span-1 md:row-span-1";
                } else if (index === 3) {
                  gridClass = "col-span-1 md:col-span-1 md:row-span-1";
                }

                return (
                  <Link
                    key={cat.id}
                    to={`/products?category=${encodeURIComponent(cat.name)}`}
                    className={`group relative overflow-hidden rounded-2xl flex flex-col justify-between p-5 hover:shadow-2xl hover:shadow-orange-500/20 transition-all duration-500 border border-transparent hover:border-orange-500/30 ${gridClass}`}
                  >
                    <div className="absolute inset-0 z-0">
                      <ImageWithFallback
                        src={CATEGORY_IMAGES[cat.name] || 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=600&q=80'}
                        alt={cat.name}
                        className="w-full h-full object-cover group-hover:scale-105 group-hover:-rotate-1 transition-transform duration-700 ease-out"
                      />
                      <div className="absolute inset-0 bg-gradient-to-t from-[#030213]/90 via-[#030213]/40 to-transparent opacity-80 group-hover:opacity-90 transition-opacity duration-500"></div>
                    </div>
                    
                    <div className="relative z-10 flex items-start justify-between">
                      {tag ? (
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-semibold text-white bg-black/40 backdrop-blur-md border border-white/20">
                          {tag}
                        </span>
                      ) : <div />}
                    </div>

                    <div className="relative z-10 flex items-end justify-between translate-y-2 group-hover:translate-y-0 transition-transform duration-500">
                      <div>
                        <h3 className="text-white text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight mb-1 group-hover:text-orange-400 transition-colors duration-300">
                          {cat.name}
                        </h3>
                        <p className="text-gray-300/80 text-sm font-medium">
                          {cat.count} Sản phẩm
                        </p>
                      </div>
                      <div className="hidden sm:flex items-center gap-2 bg-white/10 hover:bg-orange-500 backdrop-blur-md text-white px-4 py-2 rounded-full opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-x-4 group-hover:translate-x-0">
                        <span className="text-sm font-medium">Khám phá</span>
                        <ArrowRight className="w-4 h-4" />
                      </div>
                    </div>
                  </Link>
                );
              })
            : Array.from({ length: 4 }).map((_, i) => {
                let gridClass = "col-span-2 md:col-span-1";
                if (i === 0) gridClass = "col-span-2 md:col-span-2 md:row-span-2";
                else if (i === 1) gridClass = "col-span-2 md:col-span-2 md:row-span-1";
                
                return (
                  <div key={i} className={`animate-pulse bg-gray-100 rounded-2xl ${gridClass}`} />
                );
              })
          }
        </div>
      </section>

      {/* Featured Products */}
      <section className="max-w-7xl mx-auto px-4 pb-12">
        <div className="flex items-center justify-between mb-6">
          <h2>Sản phẩm nổi bật</h2>
          <Link to="/products" className="text-sm flex items-center gap-1 hover:underline" style={{ color: '#ea5c21' }}>
            Xem tất cả <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {loading
            ? Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="animate-pulse bg-gray-100 rounded-xl h-64" />
              ))
            : featuredProducts.map(product => (
                <div key={product.id} className="bg-white rounded-xl border border-border overflow-hidden group hover:shadow-lg transition-shadow">
                  <div
                    className="relative aspect-square overflow-hidden cursor-pointer bg-gray-50"
                    onClick={() => navigate(`/products/${product.id}`)}
                  >
                    <ImageWithFallback
                      src={product.image ?? ''}
                      alt={product.name}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                    {product.original_price > product.price && (
                      <span className="absolute top-2 left-2 px-2 py-0.5 rounded-md text-xs text-white" style={{ backgroundColor: '#ea5c21' }}>
                        -{Math.round((1 - product.price / product.original_price) * 100)}%
                      </span>
                    )}
                    {product.stock === 0 && (
                      <div className="absolute inset-0 bg-white/60 flex items-center justify-center">
                        <span className="text-sm font-medium text-gray-500">Hết hàng</span>
                      </div>
                    )}
                  </div>
                  <div className="p-3">
                    <p className="text-xs text-muted-foreground mb-1">{product.category}</p>
                    <h4
                      className="text-sm cursor-pointer hover:text-orange-600 transition-colors line-clamp-2 mb-2"
                      onClick={() => navigate(`/products/${product.id}`)}
                    >
                      {product.name}
                    </h4>
                    <div className="flex items-center gap-1 mb-2">
                      <Star className="w-3 h-3 fill-yellow-400 text-yellow-400" />
                      <span className="text-xs text-muted-foreground">{product.rating} ({product.review_count})</span>
                    </div>
                    <div className="flex items-center gap-1 mb-2">
                      <span className="text-sm font-semibold" style={{ color: '#ea5c21' }}>{formatPrice(product.price)}</span>
                      {product.original_price > product.price && (
                        <span className="text-xs text-muted-foreground line-through">{formatPrice(product.original_price)}</span>
                      )}
                    </div>
                    <Button
                      size="sm"
                      className="w-full text-xs text-white"
                      style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}
                      onClick={() => navigate(`/products/${product.id}`)}
                      disabled={product.stock === 0}
                    >
                      {product.stock === 0 ? 'Hết hàng' : 'Xem chi tiết'}
                    </Button>
                  </div>
                </div>
              ))
          }
        </div>
      </section>

      {/* Promo Banner */}
      <section className="max-w-7xl mx-auto px-4 pb-12">
        <div
          className="rounded-2xl overflow-hidden relative h-48 flex items-center"
          style={{ background: 'linear-gradient(135deg, #030213 0%, #1a1a3e 50%, #2d1b00 100%)' }}
        >
          <div className="px-8 md:px-12 relative z-10">
            <div className="inline-block px-3 py-1 rounded-full text-xs text-white mb-3" style={{ backgroundColor: '#ea5c21' }}>
              ƯU ĐÃI ĐẶC BIỆT
            </div>
            <h3 className="text-white mb-2" style={{ fontSize: '1.4rem' }}>
              Giảm <span style={{ color: '#ea5c21' }}>20%</span> cho đơn hàng đầu tiên
            </h3>
            <p className="text-gray-400 text-sm mb-4">Nhập mã <strong className="text-white">SPORT20</strong> khi thanh toán</p>
            <Button size="sm" onClick={() => navigate('/products')} className="text-white" style={{ backgroundColor: '#ea5c21', borderColor: '#ea5c21' }}>
              Mua ngay
            </Button>
          </div>
          <div className="absolute right-0 bottom-0 text-[120px] opacity-10 select-none pointer-events-none pr-8">🏆</div>
        </div>
      </section>

      {/* Newest Products */}
      <section className="max-w-7xl mx-auto px-4 pb-16">
        <div className="flex items-center justify-between mb-6">
          <h2>Sản phẩm mới nhất</h2>
          <Link to="/products" className="text-sm flex items-center gap-1 hover:underline" style={{ color: '#ea5c21' }}>
            Xem tất cả <ArrowRight className="w-4 h-4" />
          </Link>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          {newestProducts.map(product => (
            <Link
              key={product.id}
              to={`/products/${product.id}`}
              className="group bg-white rounded-xl border border-border overflow-hidden hover:shadow-md transition-shadow"
            >
              <div className="aspect-square overflow-hidden bg-gray-50">
                <ImageWithFallback
                  src={product.image ?? ''}
                  alt={product.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
              </div>
              <div className="p-2">
                <p className="text-xs truncate text-muted-foreground">{product.name}</p>
                <p className="text-xs font-semibold mt-0.5" style={{ color: '#ea5c21' }}>{formatPrice(product.price)}</p>
              </div>
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
