import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, ImagePlus, Loader2, Pencil, Search, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import { adminCommerceService } from '../../services/commerceService';
import { Button } from '../ui/button';
import { ImageWithFallback } from '../figma/ImageWithFallback';
import { validateImageFiles } from '../../utils/imageUpload';
import { ImageCropDialog, type ImageCrop } from '../ui/ImageCropDialog';

interface AnnouncementImage {
  id?: string;
  url: string;
  original_url?: string | null;
  thumbnail_url?: string | null;
  announcement_url?: string | null;
  banner_url?: string | null;
  detail_url?: string | null;
  crop?: { x: number; y: number; width: number; height: number; rotation: number };
  width?: number | null;
  height?: number | null;
  path?: string;
  upload_token?: string | null;
}

interface AnnouncementForm {
  title: string;
  content: string;
  type: 'update' | 'promotion' | 'maintenance' | 'general';
  status: 'draft' | 'published' | 'hidden';
  published_at: string;
  images: AnnouncementImage[];
  cover_image?: AnnouncementImage | null;
}

const emptyForm = (): AnnouncementForm => ({
  title: '', content: '', type: 'general', status: 'draft', published_at: '', images: [],
});

const errorMessage = (error: any) =>
  error.response?.data?.message ??
  Object.values(error.response?.data?.errors ?? {}).flat().join(' ') ??
  'Không thể thực hiện thao tác.';

export function AdminAnnouncements() {
  const [items, setItems] = useState<any[]>([]);
  const [form, setForm] = useState<AnnouncementForm>(emptyForm);
  const [editing, setEditing] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [coverCropSource, setCoverCropSource] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    try {
      setItems(await adminCommerceService.announcements.list({
        ...(search ? { search } : {}),
        ...(statusFilter ? { status: statusFilter } : {}),
      }));
    } finally { setLoading(false); }
  };

  useEffect(() => {
    const timer = window.setTimeout(load, 250);
    return () => window.clearTimeout(timer);
  }, [search, statusFilter]);

  const reset = () => { setEditing(null); setForm(emptyForm()); };

  const moveImage = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= form.images.length) return;
    const images = [...form.images];
    [images[index], images[target]] = [images[target], images[index]];
    setForm({ ...form, images });
  };

  const save = async () => {
    setSaving(true);
    try {
      editing
        ? await adminCommerceService.announcements.update(editing, form)
        : await adminCommerceService.announcements.create(form);
      toast.success(editing ? 'Đã cập nhật thông báo.' : 'Đã tạo thông báo.');
      reset();
      await load();
    } catch (error: any) {
      toast.error(errorMessage(error));
    } finally { setSaving(false); }
  };
  const confirmCoverCrop = async (crop: ImageCrop) => { if (!coverFile) return; setUploading(true); try { const [image] = await adminCommerceService.announcements.uploadImages([coverFile]); setForm({ ...form, cover_image: { ...image, crop } }); } catch (error: any) { toast.error(errorMessage(error)); } finally { URL.revokeObjectURL(coverCropSource!); setCoverCropSource(null); setCoverFile(null); setUploading(false); } };

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-2xl font-semibold">Quản lý thông báo</h2>
        <p className="text-sm text-muted-foreground">Tạo popup thông báo kèm nhiều hình ảnh cho trang chủ.</p>
      </div>

      <section className="bg-white border rounded-xl p-5 space-y-4">
        <div className="flex justify-between items-center">
          <h3 className="font-semibold">{editing ? 'Chỉnh sửa thông báo' : 'Tạo thông báo mới'}</h3>
          {editing && <Button variant="outline" onClick={reset}>Hủy chỉnh sửa</Button>}
        </div>
        <div className="grid md:grid-cols-2 gap-4">
          <label className="text-sm">Tiêu đề *
            <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="mt-1 w-full border rounded-lg p-2" />
          </label>
          <label className="text-sm">Ngày đăng
            <input type="datetime-local" value={form.published_at} onChange={(e) => setForm({ ...form, published_at: e.target.value })} className="mt-1 w-full border rounded-lg p-2" />
          </label>
          <label className="text-sm">Loại
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as AnnouncementForm['type'] })} className="mt-1 w-full border rounded-lg p-2">
              <option value="update">Cập nhật</option><option value="promotion">Khuyến mãi</option>
              <option value="maintenance">Bảo trì</option><option value="general">Thông báo chung</option>
            </select>
          </label>
          <label className="text-sm">Trạng thái
            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as AnnouncementForm['status'] })} className="mt-1 w-full border rounded-lg p-2">
              <option value="draft">Bản nháp</option><option value="published">Đang hoạt động</option><option value="hidden">Đã ẩn</option>
            </select>
          </label>
        </div>
        <label className="text-sm block">Nội dung *
          <textarea value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} rows={6} className="mt-1 w-full border rounded-lg p-3 resize-y" />
        </label>

        <div className="border-t pt-4"><div className="mb-3 flex items-center justify-between"><div><p className="text-sm font-medium">Ảnh bìa thông báo</p><p className="text-xs text-muted-foreground">Crop 16:9, độc lập với ảnh trong nội dung.</p></div><label className="cursor-pointer"><input className="hidden" type="file" accept="image/jpeg,image/png,image/webp" onChange={async (event) => { const file = event.target.files?.[0]; if (!file) return; try { await validateImageFiles([file], 'announcement'); setCoverFile(file); setCoverCropSource(URL.createObjectURL(file)); } catch (error: any) { toast.error(error.message); } finally { event.target.value = ''; } }} /><span className="inline-flex items-center border rounded-lg px-3 py-2 text-sm"><ImagePlus className="mr-2 size-4" />{form.cover_image ? 'Thay ảnh bìa' : 'Tải ảnh bìa'}</span></label></div>{form.cover_image && <div className="relative max-w-md"><ImageWithFallback src={form.cover_image.banner_url ?? form.cover_image.detail_url ?? form.cover_image.url} alt="Ảnh bìa" className="aspect-video w-full rounded-lg object-cover" /><button className="absolute right-2 top-2 rounded bg-white px-2 py-1 text-xs text-red-600" onClick={() => setForm({ ...form, cover_image: null })}>Xóa</button></div>}</div>

        <div>
          <div className="flex items-center justify-between mb-3">
            <div><p className="text-sm font-medium">Hình ảnh minh họa</p><p className="text-xs text-muted-foreground">JPG, PNG hoặc WebP; tối đa 5 MB/ảnh, cạnh dài từ 1000 px, tối đa 10 ảnh.</p></div>
            <label className="cursor-pointer">
              <input type="file" multiple accept="image/jpeg,image/png,image/webp" className="hidden" onChange={async (event) => {
                const files = Array.from(event.target.files ?? []);
                if (!files.length) return;
                setUploading(true);
                try {
                  await validateImageFiles(files, 'announcement');
                  const uploaded = await adminCommerceService.announcements.uploadImages(files);
                  setForm((current) => ({ ...current, images: [...current.images, ...uploaded] }));
                  toast.success('Đã tải ảnh lên.');
                } catch (error: any) { toast.error(errorMessage(error)); }
                finally { setUploading(false); event.target.value = ''; }
              }} />
              <span className="inline-flex items-center border rounded-lg px-3 py-2 text-sm">
                {uploading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : <ImagePlus className="w-4 h-4 mr-2" />}Tải hình ảnh
              </span>
            </label>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
            {form.images.map((image, index) => (
              <div key={image.id ?? `${image.url}-${index}`} className="border rounded-xl overflow-hidden bg-gray-50">
                <ImageWithFallback src={image.thumbnail_url ?? image.url} alt={`Ảnh ${index + 1}`} className="w-full aspect-video object-cover" />
                <div className="flex items-center justify-between p-2">
                  <span className="text-xs">Ảnh {index + 1}</span>
                  <div className="flex">
                    <button onClick={() => moveImage(index, -1)} disabled={index === 0} className="p-1 disabled:opacity-30"><ArrowUp className="w-4 h-4" /></button>
                    <button onClick={() => moveImage(index, 1)} disabled={index === form.images.length - 1} className="p-1 disabled:opacity-30"><ArrowDown className="w-4 h-4" /></button>
                    <button onClick={async () => {
                      if (!image.id && (image.path || image.upload_token)) {
                        try { await adminCommerceService.announcements.deleteUploadedImage({ path: image.path, upload_token: image.upload_token }); }
                        catch { toast.error('Không thể xóa tệp ảnh vừa tải.'); return; }
                      }
                      setForm({ ...form, images: form.images.filter((_, i) => i !== index) });
                    }} className="p-1 text-red-600"><X className="w-4 h-4" /></button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
        <Button onClick={save} disabled={saving || uploading || !form.title.trim() || !form.content.trim()} className="bg-orange-600 hover:bg-orange-700">
          {saving && <Loader2 className="w-4 h-4 animate-spin mr-2" />}{editing ? 'Lưu thay đổi' : 'Tạo thông báo'}
        </Button>
      </section>

      <section className="bg-white border rounded-xl overflow-hidden">
        <div className="p-4 border-b grid md:grid-cols-[1fr_220px] gap-3">
          <div className="relative"><Search className="absolute left-3 top-2.5 w-4 h-4 text-muted-foreground" /><input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Tìm thông báo..." className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm" /></div>
          <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="border rounded-lg px-3 py-2 text-sm"><option value="">Tất cả trạng thái</option><option value="published">Đang hoạt động</option><option value="draft">Bản nháp</option><option value="hidden">Đã ẩn</option></select>
        </div>
        {loading ? <div className="p-10 text-center">Đang tải...</div> : <div className="divide-y">
          {items.map((item) => (
            <article key={item.id} className="p-4 flex gap-4">
              {item.images?.[0] && <ImageWithFallback src={item.images[0].thumbnail_url ?? item.images[0].url} alt={item.title} className="w-28 h-20 object-cover rounded-lg shrink-0" />}
              <div className="flex-1 min-w-0"><div className="flex justify-between gap-3"><h3 className="font-medium">{item.title}</h3><span className="text-xs uppercase">{item.status}</span></div>
                <p className="text-sm text-muted-foreground line-clamp-2 mt-1">{item.content}</p><p className="text-xs text-muted-foreground mt-2">{item.images?.length ?? 0} ảnh · {item.published_at ? new Date(item.published_at).toLocaleString('vi-VN') : 'Chưa đăng'}</p></div>
              <div className="flex items-start">
                <button onClick={() => { setEditing(item.id); setForm({ title: item.title, content: item.content, type: item.type, status: item.status, published_at: item.published_at?.slice(0, 16) ?? '', images: item.images ?? [], cover_image: item.cover_image ?? null }); window.scrollTo({ top: 0, behavior: 'smooth' }); }} className="p-2 text-blue-600"><Pencil className="w-4 h-4" /></button>
                <button onClick={async () => { if (!confirm(`Xóa thông báo "${item.title}"?`)) return; try { await adminCommerceService.announcements.remove(item.id); toast.success('Đã xóa thông báo.'); await load(); } catch (error: any) { toast.error(errorMessage(error)); } }} className="p-2 text-red-600"><Trash2 className="w-4 h-4" /></button>
              </div>
            </article>
          ))}
        </div>}
      </section>
      {coverCropSource && <ImageCropDialog image={coverCropSource} aspect={16 / 9} title="Crop ảnh bìa thông báo" onCancel={() => { URL.revokeObjectURL(coverCropSource); setCoverCropSource(null); setCoverFile(null); }} onConfirm={confirmCoverCrop} />}
    </div>
  );
}
