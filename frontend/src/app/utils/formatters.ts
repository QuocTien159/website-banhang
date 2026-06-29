export const formatCurrency = (value: number) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

export const parseVietnameseDate = (value: string): Date | null => {
  const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
  if (!match) return null;

  const [, day, month, year, hour = '00', minute = '00'] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day), Number(hour), Number(minute));

  return Number.isNaN(date.getTime()) ? null : date;
};

export const formatDateTime = (value?: string | null, fallback = 'Không xác định') => {
  if (!value) return fallback;

  const nativeDate = new Date(value);
  const date = Number.isNaN(nativeDate.getTime()) ? parseVietnameseDate(value) : nativeDate;

  if (!date) return fallback;

  return date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

export const formatDate = (value?: string | null, fallback = 'Không xác định') => {
  if (!value) return fallback;

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? fallback : date.toLocaleDateString('vi-VN');
};
