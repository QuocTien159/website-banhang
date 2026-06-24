import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Heart, SlidersHorizontal, Star, X } from 'lucide-react';
import {
  productService,
  type ApiCategory,
  type ApiProduct,
  type ProductFilters,
} from '../../services/productService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { commerceService } from '../../services/commerceService';
import { useAuth } from '../../store/AppContext';
import { toast } from 'sonner';

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

const PRICE_RANGES = [
  { label: 'Tất cả', min: undefined, max: undefined },
  { label: 'Dưới 500.000đ', min: 0, max: 500000 },
  { label: '500.000đ - 1.000.000đ', min: 500000, max: 1000000 },
  { label: '1.000.000đ - 2.000.000đ', min: 1000000, max: 2000000 },
  { label: 'Trên 2.000.000đ', min: 2000000, max: undefined },
];

const FILTER_PARAMETERS: Record<string, keyof ProductFilters> = {
  'Thương hiệu': 'brand',
  'Màu sắc': 'color',
  'Kích thước': 'size',
  'Khối lượng': 'weight',
  'Độ đàn hồi': 'resistance',
};

const CATEGORY_ATTRIBUTE_FILTERS: Record<string, string[]> = {
  'Áo': ['Màu sắc', 'Kích thước'],
  'Quần': ['Màu sắc', 'Kích thước'],
  'Giày': ['Màu sắc', 'Kích thước'],
  'Phụ kiện': [],
};

const ALL_PRODUCT_BRANDS = ['Nike', 'Adidas', 'Puma', 'Khác'];

const FILTER_PLACEHOLDERS: Record<string, string> = {
  'Thương hiệu': 'Tất cả hãng',
  'Màu sắc': 'Tất cả màu sắc',
  'Kích thước': 'Tất cả kích thước',
  'Khối lượng': 'Tất cả khối lượng',
  'Độ đàn hồi': 'Tất cả mức đàn hồi',
};

export function ProductsPage() {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [products, setProducts] = useState<ApiProduct[]>([]);
  const [categories, setCategories] = useState<ApiCategory[]>([]);
  const [availableFilters, setAvailableFilters] = useState<Record<string, string[]>>({});
  const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({});
  const [priceRange, setPriceRange] = useState<{ min?: number; max?: number }>({});
  const [sort, setSort] = useState<ProductFilters['sort']>('newest');
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [showFilters, setShowFilters] = useState(false);
  const [wishlistIds, setWishlistIds] = useState<Set<string>>(new Set());

  const category = searchParams.get('category') ?? '';
  const search = searchParams.get('q') ?? '';
  const visibleAttributeNames = category
    ? (CATEGORY_ATTRIBUTE_FILTERS[category] ?? [])
    : ['Thương hiệu'];

  useEffect(() => {
    productService.getCategories().then(setCategories).catch(() => setCategories([]));
    if (isAuthenticated) commerceService.wishlist().then((items) => setWishlistIds(new Set(items.map((item) => item.id)))).catch(() => {});
  }, [isAuthenticated]);

  useEffect(() => {
    const allowedAttributes = category
      ? (CATEGORY_ATTRIBUTE_FILTERS[category] ?? [])
      : ['Thương hiệu'];

    setSelectedAttributes((current) => {
      const next = Object.fromEntries(
        Object.entries(current).filter(([name]) => allowedAttributes.includes(name))
      );
      return Object.keys(next).length === Object.keys(current).length ? current : next;
    });
  }, [category]);

  useEffect(() => {
    const attributeParams = Object.entries(selectedAttributes).reduce<ProductFilters>(
      (params, [name, value]) => {
        const parameter = FILTER_PARAMETERS[name];
        if (parameter) Object.assign(params, { [parameter]: value });
        return params;
      },
      {}
    );

    setLoading(true);
    productService
      .getProducts({
        search: search || undefined,
        category: category || undefined,
        min_price: priceRange.min,
        max_price: priceRange.max,
        sort,
        page: currentPage,
        per_page: 16,
        ...attributeParams,
      })
      .then(({ data, meta }) => {
        setProducts(data);
        setTotal(meta.total);
        setLastPage(meta.last_page);
        setAvailableFilters(meta.filters ?? {});
      })
      .catch(() => setProducts([]))
      .finally(() => setLoading(false));
  }, [category, search, priceRange, sort, currentPage, selectedAttributes]);

  const changeCategory = (value: string) => {
    const params = new URLSearchParams(searchParams);
    if (value) params.set('category', value);
    else params.delete('category');
    setSearchParams(params);
    const allowedAttributes = value
      ? (CATEGORY_ATTRIBUTE_FILTERS[value] ?? [])
      : ['Thương hiệu'];
    setSelectedAttributes((current) =>
      Object.fromEntries(
        Object.entries(current).filter(([name]) => allowedAttributes.includes(name))
      )
    );
    setCurrentPage(1);
  };

  const clearFilters = () => {
    setSearchParams({});
    setSelectedAttributes({});
    setPriceRange({});
    setCurrentPage(1);
  };

  const FilterPanel = () => (
    <div className="space-y-6">
      <div>
        <h4 className="text-sm font-semibold mb-3">Danh mục</h4>
        <div className="space-y-1">
          <button
            onClick={() => changeCategory('')}
            className={`w-full text-left px-3 py-2 rounded-lg text-sm ${
              !category ? 'bg-orange-50 text-orange-600 font-medium' : 'hover:bg-accent'
            }`}
          >
            Tất cả
          </button>
          {categories.map((item) => (
            <button
              key={item.id}
              onClick={() => changeCategory(item.name)}
              className={`w-full flex justify-between px-3 py-2 rounded-lg text-sm ${
                category === item.name
                  ? 'bg-orange-50 text-orange-600 font-medium'
                  : 'hover:bg-accent'
              }`}
            >
              <span>{item.name}</span>
              <span className="text-muted-foreground">{item.count}</span>
            </button>
          ))}
        </div>
      </div>

      {Object.entries(availableFilters)
        .filter(([name]) => visibleAttributeNames.includes(name))
        .map(([name, values]) => {
          const displayedValues =
            !category && name === 'Thương hiệu' ? ALL_PRODUCT_BRANDS : values;

          return (
        values.length > 0 ? (
          <div key={name}>
            <h4 className="text-sm font-semibold mb-2">
              {name === 'Thương hiệu' ? 'Hãng' : name}
            </h4>
            <select
              value={selectedAttributes[name] ?? ''}
              onChange={(event) => {
                setSelectedAttributes((current) => ({
                  ...current,
                  [name]: event.target.value,
                }));
                setCurrentPage(1);
              }}
              className="w-full border border-border rounded-lg px-3 py-2 text-sm bg-white"
            >
              <option value="">{FILTER_PLACEHOLDERS[name] ?? 'Tất cả'}</option>
              {displayedValues.map((value) => (
                <option key={value} value={value}>{value}</option>
              ))}
            </select>
          </div>
        ) : null
          );
        })}

      <div>
        <h4 className="text-sm font-semibold mb-2">Khoảng giá</h4>
        <div className="space-y-1">
          {PRICE_RANGES.map((range) => (
            <button
              key={range.label}
              onClick={() => {
                setPriceRange({ min: range.min, max: range.max });
                setCurrentPage(1);
              }}
              className={`w-full text-left px-3 py-2 rounded-lg text-sm ${
                priceRange.min === range.min && priceRange.max === range.max
                  ? 'bg-orange-50 text-orange-600 font-medium'
                  : 'hover:bg-accent'
              }`}
            >
              {range.label}
            </button>
          ))}
        </div>
      </div>

      <button
        onClick={clearFilters}
        className="w-full border border-red-200 text-red-500 rounded-lg py-2 text-sm flex justify-center gap-2"
      >
        <X className="w-4 h-4" /> Xóa bộ lọc
      </button>
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="flex justify-between items-center gap-3 mb-6">
        <div>
          <h2>{category || (search ? `Kết quả cho “${search}”` : 'Sản phẩm thể thao')}</h2>
          <p className="text-sm text-muted-foreground">{total} sản phẩm</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" className="md:hidden" onClick={() => setShowFilters(true)}>
            <SlidersHorizontal className="w-4 h-4 mr-2" /> Bộ lọc
          </Button>
          <select
            value={sort}
            onChange={(event) => {
              setSort(event.target.value as ProductFilters['sort']);
              setCurrentPage(1);
            }}
            className="border border-border rounded-lg px-3 py-2 text-sm bg-white"
          >
            <option value="newest">Mới nhất</option>
            <option value="price_asc">Giá tăng dần</option>
            <option value="price_desc">Giá giảm dần</option>
            <option value="name">Tên sản phẩm</option>
          </select>
        </div>
      </div>

      <div className="flex gap-6">
        <aside className="hidden md:block w-60 shrink-0">
          <div className="sticky top-20 bg-white border border-border rounded-xl p-4">
            <FilterPanel />
          </div>
        </aside>

        {showFilters && (
          <div className="fixed inset-0 z-50 md:hidden">
            <button className="absolute inset-0 bg-black/40" onClick={() => setShowFilters(false)} />
            <div className="absolute right-0 top-0 bottom-0 w-80 bg-white p-5 overflow-y-auto">
              <div className="flex justify-between mb-5">
                <h3>Bộ lọc</h3>
                <button onClick={() => setShowFilters(false)}><X /></button>
              </div>
              <FilterPanel />
            </div>
          </div>
        )}

        <main className="flex-1">
          {loading ? (
            <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {Array.from({ length: 8 }).map((_, index) => (
                <div key={index} className="h-80 bg-gray-100 rounded-xl animate-pulse" />
              ))}
            </div>
          ) : products.length === 0 ? (
            <div className="text-center py-20">
              <h3>Không tìm thấy sản phẩm phù hợp</h3>
              <Button variant="outline" className="mt-4" onClick={clearFilters}>Xóa bộ lọc</Button>
            </div>
          ) : (
            <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              {products.map((product) => (
                <article
                  key={product.id}
                  onClick={() => navigate(`/products/${product.id}`)}
                  className="bg-white border border-border rounded-xl overflow-hidden cursor-pointer group hover:shadow-lg"
                >
                  <div className="relative aspect-square bg-gray-50 overflow-hidden">
                    <ImageWithFallback
                      src={product.image ?? ''}
                      alt={product.name}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    {product.stock === 0 && (
                      <div className="absolute inset-0 bg-white/70 grid place-items-center font-medium">
                        Hết hàng
                      </div>
                    )}
                    <button
                      onClick={async (event) => {
                        event.stopPropagation();
                        if (!isAuthenticated) { toast.error('Vui lòng đăng nhập để lưu sản phẩm.'); navigate('/login'); return; }
                        const result = await commerceService.toggleWishlist(product.id);
                        setWishlistIds((current) => {
                          const next = new Set(current);
                          result.wishlisted ? next.add(product.id) : next.delete(product.id);
                          return next;
                        });
                      }}
                      className="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 shadow grid place-items-center"
                      aria-label="Yêu thích"
                    >
                      <Heart className={`w-4 h-4 ${wishlistIds.has(product.id) ? 'fill-red-500 text-red-500' : ''}`} />
                    </button>
                  </div>
                  <div className="p-3">
                    <p className="text-xs text-muted-foreground">{product.category} · {product.brand}</p>
                    <h4 className="text-sm line-clamp-2 my-1">{product.name}</h4>
                    <div className="flex items-center text-xs text-muted-foreground mb-2">
                      <Star className="w-3 h-3 text-yellow-400 fill-yellow-400 mr-1" />
                      {product.rating} ({product.review_count})
                    </div>
                    <strong className="text-orange-600 text-sm">{formatPrice(product.price)}</strong>
                  </div>
                </article>
              ))}
            </div>
          )}

          {lastPage > 1 && (
            <div className="flex justify-center gap-2 mt-8">
              {Array.from({ length: lastPage }, (_, index) => index + 1).map((page) => (
                <button
                  key={page}
                  onClick={() => setCurrentPage(page)}
                  className={`w-9 h-9 rounded-lg border ${
                    page === currentPage ? 'border-orange-500 bg-orange-50 text-orange-600' : ''
                  }`}
                >
                  {page}
                </button>
              ))}
            </div>
          )}
        </main>
      </div>
    </div>
  );
}
