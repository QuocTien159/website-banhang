import { useState } from 'react';
import Cropper, { type Area } from 'react-easy-crop';
import { RotateCcw, RotateCw, Undo2 } from 'lucide-react';
import { Button } from './button';

export interface ImageCrop {
  x: number; y: number; width: number; height: number; rotation: number;
}

export function ImageCropDialog({ image, aspect, title, onCancel, onConfirm }: { image: string; aspect: number; title: string; onCancel: () => void; onConfirm: (crop: ImageCrop) => void }) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [pixels, setPixels] = useState<Area | null>(null);
  const reset = () => { setCrop({ x: 0, y: 0 }); setZoom(1); setRotation(0); };
  return <div className="fixed inset-0 z-[80] grid place-items-center bg-black/70 p-4">
    <section className="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-xl">
      <header className="border-b px-5 py-4"><h2 className="font-semibold">{title}</h2></header>
      <div className="relative h-[50vh] min-h-72 bg-neutral-900"><Cropper image={image} crop={crop} zoom={zoom} rotation={rotation} aspect={aspect} onCropChange={setCrop} onZoomChange={setZoom} onCropComplete={(_, area) => setPixels(area)} /></div>
      <div className="space-y-3 p-5">
        <label className="block text-sm">Thu phóng <input className="ml-3 w-2/3 align-middle" type="range" min="1" max="3" step="0.05" value={zoom} onChange={(event) => setZoom(Number(event.target.value))} /></label>
        <div className="flex items-center gap-2"><Button type="button" variant="outline" size="icon" onClick={() => setRotation((value) => value - 90)} title="Xoay trái"><RotateCcw className="size-4" /></Button><Button type="button" variant="outline" size="icon" onClick={() => setRotation((value) => value + 90)} title="Xoay phải"><RotateCw className="size-4" /></Button><Button type="button" variant="outline" size="sm" onClick={reset}><Undo2 className="mr-1 size-4" />Đặt lại</Button><div className="ml-auto flex gap-2"><Button type="button" variant="outline" onClick={onCancel}>Hủy</Button><Button type="button" className="bg-orange-600 hover:bg-orange-700" disabled={!pixels} onClick={() => pixels && onConfirm({ x: Math.round(pixels.x), y: Math.round(pixels.y), width: Math.round(pixels.width), height: Math.round(pixels.height), rotation })}>Xác nhận crop</Button></div></div>
      </div>
    </section>
  </div>;
}
