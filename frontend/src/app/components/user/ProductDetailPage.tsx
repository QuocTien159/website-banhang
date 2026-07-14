import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { Heart, Minus, Plus, ShoppingCart, Star } from 'lucide-react';
import { toast } from 'sonner';
import { productService, type ApiProductDetail, type ApiVariant } from '../../services/productService';
import { useAuth, useCart } from '../../store/AppContext';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { commerceService, type MyReview } from '../../services/commerceService';

const formatPrice = (price: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);

const variantHas = (variant: ApiVariant, name: string, value: string) =>
  variant.attributes.some((attribute) => attribute.name === name && attribute.value === value);

export function ProductDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { addToCart, isLoading: cartLoading } = useCart();
  const [product, setProduct] = useState<ApiProductDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<Record<string, string>>({});
  const [quantity, setQuantity] = useState(1);
  const [adding, setAdding] = useState(false);
  const [wishlisted, setWishlisted] = useState(false);
  const [myReview, setMyReview] = useState<MyReview | null>(null);
  const [activeImage, setActiveImage] = useState('');

  useEffect(() => {
    if (!id) return;
    setLoading(true);
    productService
      .getProduct(id)
      .then((data) => {
        setProduct(data);
        setSelected({});
        setMyReview(null);
        setActiveImage(data.image ?? data.images[0]?.detail_url ?? '');
        if (isAuthenticated) {
          commerceService.wishlistStatus(data.id).then(setWishlisted).catch(() => {});
          commerceService.myReviews()
            .then((reviews) => setMyReview(reviews.find((review) => review.product?.id === data.id) ?? null))
            .catch(() => setMyReview(null));
        }
      })
      .catch(() => setProduct(null))
      .finally(() => setLoading(false));
  }, [id, isAuthenticated]);

  const options = useMemo(() => {
    if (!product) return {};
    return product.required_attributes.reduce<Record<string, string[]>>((result, name) => {
      result[name] = Array.from(
        new Set(
          product.variants.flatMap((variant) =>
            variant.attributes.filter((attribute) => attribute.name === name).map((attribute) => attribute.value)
          )
        )
      );
      return result;
    }, {});
  }, [product]);

  const selectionComplete =
    !!product && product.required_attributes.every((name) => Boolean(selected[name]));

  const selectedVariant = useMemo(() => {
    if (!product || !selectionComplete) return null;
    return (
      product.variants.find((variant) =>
        product.required_attributes.every((name) => variantHas(variant, name, selected[name]))
      ) ?? null
    );
  }, [product, selected, selectionComplete]);

  useEffect(() => {
    if (!product || !selectedVariant) return;
    const variantImage = product.images.find((image) => image.variant_id === selectedVariant.id);
    setActiveImage(variantImage?.detail_url ?? selectedVariant.image ?? product.image ?? '');
  }, [product, selectedVariant]);

  const optionAvailable = (attributeName: string, value: string) => {
    if (!product) return false;
    const candidateSelection = { ...selected, [attributeName]: value };
    return product.variants.some(
      (variant) =>
        variant.stock > 0 &&
        Object.entries(candidateSelection).every(([name, selectedValue]) =>
          variantHas(variant, name, selectedValue)
        )
    );
  };

  const addSelectedVariant = async (): Promise<boolean> => {
    if (!isAuthenticated) {
      toast.error('Vui lòng đăng nhập để mua hàng.');
      navigate('/login');
      return false;
    }
    if (!selectionComplete || !selectedVariant) {
      toast.error('Vui lòng chọn đầy đủ thuộc tính sản phẩm.');
      return false;
    }
    if (selectedVariant.stock === 0) {
      toast.error('Biến thể này đã hết hàng.');
      return false;
    }

    setAdding(true);
    try {
      await addToCart(selectedVariant.id, quantity);
      toast.success(`Đã thêm ${quantity} sản phẩm vào giỏ hàng.`);
      return true;
    } catch (error: unknown) {
      const responseError = error as { response?: { data?: { message?: string } } };
      toast.error(responseError.response?.data?.message ?? 'Không thể thêm vào giỏ hàng.');
      return false;
    } finally {
      setAdding(false);
    }
  };

  if (loading) {
    return <div className="max-w-7xl mx-auto px-4 py-24 text-center">Đang tải sản phẩm...</div>;
  }

  if (!product) {
    return (
      <div className="max-w-7xl mx-auto px-4 py-24 text-center">
        <h2>Không tìm thấy sản phẩm</h2>
        <Button className="mt-4" onClick={() => navigate('/products')}>Quay lại cửa hàng</Button>
      </div>
    );
  }

  const currentPrice = selectedVariant?.price ?? product.price;
  const currentStock = selectedVariant?.stock ?? product.stock;
  const canPurchase = selectionComplete && !!selectedVariant && selectedVariant.stock > 0;

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <nav className="text-sm text-muted-foreground mb-6 flex gap-2">
        <Link to="/">Trang chủ</Link><span>/</span>
        <Link to="/products">Sản phẩm</Link><span>/</span>
        <Link to={`/products?category=${encodeURIComponent(product.category)}`}>{product.category}</Link>
      </nav>

      <div className="grid md:grid-cols-2 gap-8 mb-12">
        <div>
          <a
            href={product.images.find((image) => image.detail_url === activeImage)?.original_url ?? activeImage}
            target="_blank"
            rel="noreferrer"
            className="block aspect-square rounded-2xl overflow-hidden border border-border bg-gray-50"
          >
            <ImageWithFallback src={activeImage} alt={product.name} className="w-full h-full object-contain" />
          </a>
          {product.images.length > 1 && (
            <div className="flex gap-2 mt-3">
              {product.images.map((image) => (
                <button
                  key={image.id}
                  onClick={() => setActiveImage(image.detail_url ?? image.original_url ?? '')}
                  className={`w-16 h-16 rounded-lg overflow-hidden border ${
                    activeImage === image.detail_url ? 'border-orange-500' : 'border-border'
                  }`}
                >
                  <ImageWithFallback src={image.thumbnail_url ?? image.detail_url ?? ''} alt={product.name} className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        <section>
          <div className="flex justify-between gap-4">
            <div>
              <p className="text-sm text-orange-600">{product.category} · {product.brand}</p>
              <h1 className="text-2xl font-bold mt-1">{product.name}</h1>
            </div>
            <button
              onClick={async () => {
                if (!isAuthenticated) {
                  toast.error('Vui lòng đăng nhập để sử dụng danh sách yêu thích.');
                  return;
                }
                try {
                  const result = await commerceService.toggleWishlist(product.id);
                  setWishlisted(result.wishlisted);
                  toast.success(result.message);
                } catch {
                  toast.error('Không thể cập nhật danh sách yêu thích.');
                }
              }}
              className="w-10 h-10 rounded-full border border-border grid place-items-center"
            >
              <Heart className={`w-5 h-5 ${wishlisted ? 'fill-red-500 text-red-500' : ''}`} />
            </button>
          </div>

          <div className="flex items-center gap-2 my-4 text-sm text-muted-foreground">
            <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
            <span>{product.rating} ({product.review_count} đánh giá)</span>
            <span>· Đã bán {product.sold}</span>
          </div>

          <div className="bg-orange-50 rounded-xl p-4 mb-5">
            <strong className="text-3xl text-orange-600">{formatPrice(currentPrice)}</strong>
            {selectedVariant && <span className="ml-3 text-sm text-muted-foreground">SKU: {selectedVariant.sku}</span>}
          </div>

          <div className="space-y-4">
            {Object.entries(options).map(([name, values]) => (
              <div key={name}>
                <p className="font-medium text-sm mb-2">
                  {name}: <span className="text-orange-600">{selected[name] ?? 'Chưa chọn'}</span>
                </p>
                <div className="flex flex-wrap gap-2">
                  {values.map((value) => {
                    const available = optionAvailable(name, value);
                    const active = selected[name] === value;
                    return (
                      <button
                        key={value}
                        disabled={!available}
                        onClick={() => {
                          setSelected((current) => ({ ...current, [name]: value }));
                          setQuantity(1);
                        }}
                        className={`px-3 py-2 rounded-lg border text-sm ${
                          active
                            ? 'border-orange-500 bg-orange-50 text-orange-700 font-medium'
                            : available
                              ? 'border-border hover:border-orange-300'
                              : 'border-gray-200 text-gray-400 line-through cursor-not-allowed'
                        }`}
                      >
                        {value}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>

          <div className="mt-5 text-sm">
            {!selectionComplete
              ? 'Hãy chọn đầy đủ thuộc tính để xem tồn kho và SKU.'
              : selectedVariant?.stock
                ? `Còn ${selectedVariant.stock} sản phẩm`
                : 'Biến thể đã hết hàng'}
          </div>

          <div className="flex items-center gap-4 my-5">
            <span className="text-sm font-medium">Số lượng</span>
            <div className="flex border border-border rounded-lg overflow-hidden">
              <button className="w-9 h-9 grid place-items-center" onClick={() => setQuantity(Math.max(1, quantity - 1))}>
                <Minus className="w-4 h-4" />
              </button>
              <span className="w-12 grid place-items-center">{quantity}</span>
              <button
                className="w-9 h-9 grid place-items-center"
                disabled={!canPurchase || quantity >= currentStock}
                onClick={() => setQuantity(Math.min(currentStock, quantity + 1))}
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>
          </div>

          <div className="flex gap-3">
            <Button
              variant="outline"
              className="flex-1"
              disabled={!canPurchase || adding || cartLoading}
              onClick={addSelectedVariant}
            >
              <ShoppingCart className="w-4 h-4 mr-2" />
              {adding ? 'Đang thêm...' : 'Thêm vào giỏ'}
            </Button>
            <Button
              className="flex-1 bg-orange-600 hover:bg-orange-700"
              disabled={!canPurchase || adding}
              onClick={async () => {
                if (await addSelectedVariant()) navigate('/cart');
              }}
            >
              Mua ngay
            </Button>
          </div>
        </section>
      </div>

      <Tabs defaultValue="description">
        <TabsList>
          <TabsTrigger value="description">Mô tả</TabsTrigger>
          <TabsTrigger value="specs">Thuộc tính</TabsTrigger>
          <TabsTrigger value="reviews">Đánh giá ({product.review_count})</TabsTrigger>
        </TabsList>
        <TabsContent value="description" className="p-5 border rounded-xl mt-4">
          <p className="text-sm leading-7">{product.description}</p>
        </TabsContent>
        <TabsContent value="specs" className="p-5 border rounded-xl mt-4">
          {product.specs.map((spec) => (
            <div key={spec.label} className="flex py-2 border-b last:border-0">
              <span className="w-40 text-muted-foreground">{spec.label}</span>
              <span>{spec.value}</span>
            </div>
          ))}
        </TabsContent>
        <TabsContent value="reviews" className="mt-4 space-y-3">
          {isAuthenticated && myReview && (
            <div className="p-4 border border-orange-200 bg-orange-50 rounded-xl flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <div>
                <p className="font-medium text-orange-800">Bạn đã đánh giá sản phẩm này.</p>
                <p className="text-sm text-orange-700">Nếu muốn cập nhật nội dung, hãy sửa đánh giá cũ để gửi duyệt lại.</p>
              </div>
              <Button onClick={() => navigate('/reviews')} className="bg-orange-600 hover:bg-orange-700">
                Xem/Sửa đánh giá của bạn
              </Button>
            </div>
          )}
          {product.reviews.length === 0 ? (
            <div className="p-8 border rounded-xl text-center text-muted-foreground">Chưa có đánh giá.</div>
          ) : product.reviews.map((review) => (
            <article key={review.id} className="p-4 border rounded-xl">
              <div className="flex justify-between">
                <strong>{review.name}</strong><span className="text-sm text-muted-foreground">{review.date}</span>
              </div>
              <div className="flex gap-1 mt-2">
                {[1, 2, 3, 4, 5].map((star) => (
                  <Star key={star} className={`w-4 h-4 ${star <= review.rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                ))}
              </div>
              <p className="text-sm mt-2">{review.comment}</p>
              {review.images && review.images.length > 0 && (
                <div className="flex flex-wrap gap-2 mt-3">
                  {review.images.map((image) => (
                    <a key={image} href={image} target="_blank" rel="noreferrer" className="w-20 h-20 rounded-lg overflow-hidden border bg-gray-50">
                      <ImageWithFallback src={image} alt="Ảnh đánh giá" className="w-full h-full object-cover" />
                    </a>
                  ))}
                </div>
              )}
              {review.admin_reply && (
                <div className="mt-4 rounded-xl border border-orange-100 bg-orange-50 p-3 text-sm">
                  <p className="font-semibold text-orange-700">Phản hồi từ Admin</p>
                  <p className="mt-1 text-gray-700 leading-6">{review.admin_reply}</p>
                </div>
              )}
            </article>
          ))}
        </TabsContent>
      </Tabs>
    </div>
  );
}
