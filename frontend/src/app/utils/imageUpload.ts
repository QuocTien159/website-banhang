export type ImageUploadKind = 'product' | 'announcement';

const maxFileSize = 5 * 1024 * 1024;

const dimensionsFor = (file: File) => new Promise<{ width: number; height: number }>((resolve, reject) => {
  const image = new Image();
  const objectUrl = URL.createObjectURL(file);
  image.onload = () => {
    URL.revokeObjectURL(objectUrl);
    resolve({ width: image.naturalWidth, height: image.naturalHeight });
  };
  image.onerror = () => {
    URL.revokeObjectURL(objectUrl);
    reject(new Error(`Không thể đọc kích thước ảnh ${file.name}.`));
  };
  image.src = objectUrl;
});

export async function validateImageFiles(files: File[], kind: ImageUploadKind): Promise<void> {
  for (const file of files) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      throw new Error(`${file.name} không phải ảnh JPG, PNG hoặc WebP.`);
    }
    if (file.size > maxFileSize) {
      throw new Error(`${file.name} vượt quá dung lượng 5 MB.`);
    }

    const { width, height } = await dimensionsFor(file);
    if (kind === 'product' && (width < 800 || height < 800)) {
      throw new Error('Ảnh sản phẩm cần tối thiểu 800 x 800 px để hiển thị rõ nét.');
    }
    if (kind === 'announcement' && Math.max(width, height) < 1000) {
      throw new Error('Ảnh thông báo cần có cạnh dài tối thiểu 1000 px để hiển thị rõ nét.');
    }
  }
}
