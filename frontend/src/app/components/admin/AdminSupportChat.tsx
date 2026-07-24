import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { CheckCircle2, MessageSquare, Send, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../../store/AppContext';
import { adminSupportChatService, type SupportConversation } from '../../services/supportChatService';
import { Button } from '../ui/button';

type Filter = 'waiting' | 'mine' | 'closed' | 'all';
const labels: Record<Filter, string> = { waiting: 'Chờ nhận', mine: 'Tôi xử lý', closed: 'Đã đóng', all: 'Tất cả' };
const statusLabels: Record<string, string> = { waiting: 'Chờ nhận', in_progress: 'Đang xử lý', closed: 'Đã đóng' };
const formatDate = (value: string | null) => value ? new Date(value).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }) : '';

export function AdminSupportChat() {
  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';
  const [filter, setFilter] = useState<Filter>('waiting');
  const [items, setItems] = useState<SupportConversation[]>([]);
  const [selected, setSelected] = useState<SupportConversation | null>(null);
  const [content, setContent] = useState('');
  const [staff, setStaff] = useState<{ id: string; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [working, setWorking] = useState(false);

  const loadList = useCallback(async (keepSelection = true) => {
    try {
      const data = await adminSupportChatService.list(filter);
      setItems(data);
      if (keepSelection && selected) {
        const updated = data.find((item) => item.id === selected.id);
        if (!updated && filter !== 'all') setSelected(null);
      }
    } catch {
      toast.error('Không thể tải danh sách hội thoại.');
    } finally { setLoading(false); }
  }, [filter, selected]);

  const openConversation = async (id: string) => {
    try { setSelected(await adminSupportChatService.get(id)); }
    catch { toast.error('Không thể mở hội thoại.'); }
  };

  useEffect(() => { setLoading(true); setSelected(null); void loadList(false); }, [filter]);
  useEffect(() => {
    const timer = window.setInterval(() => { void loadList(); if (selected) void openConversation(selected.id); }, 5000);
    return () => window.clearInterval(timer);
  }, [loadList, selected?.id]);
  useEffect(() => { if (isAdmin) void adminSupportChatService.staffOptions().then(setStaff).catch(() => {}); }, [isAdmin]);

  const canReply = useMemo(() => {
    if (!selected || selected.status === 'closed') return false;
    return isAdmin || selected.assignee?.id === user?.id;
  }, [isAdmin, selected, user?.id]);

  const run = async (action: () => Promise<SupportConversation>, success: string) => {
    setWorking(true);
    try {
      const updated = await action();
      setSelected(updated);
      await loadList(false);
      toast.success(success);
    } catch (error: unknown) {
      const message = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data;
      toast.error(message?.errors ? Object.values(message.errors).flat().join(' ') : message?.message ?? 'Thao tác không thành công.');
    } finally { setWorking(false); }
  };

  const send = async (event: FormEvent) => {
    event.preventDefault();
    if (!selected || !content.trim() || !canReply) return;
    await run(() => adminSupportChatService.sendMessage(selected.id, content.trim()), 'Đã gửi tin nhắn.');
    setContent('');
  };

  return (
    <div className="flex h-[calc(100vh-7rem)] min-h-[620px] flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Hỗ trợ khách hàng</h1>
        <p className="text-sm text-muted-foreground">Nhận và xử lý các cuộc trò chuyện của khách hàng.</p>
      </div>
      <div className="flex flex-wrap gap-2">
        {(Object.keys(labels) as Filter[]).filter((item) => isAdmin || item !== 'all').map((item) => <Button key={item} variant={filter === item ? 'default' : 'outline'} size="sm" onClick={() => setFilter(item)}>{labels[item]}</Button>)}
      </div>
      <div className="grid min-h-0 flex-1 overflow-hidden rounded-lg border bg-background lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside className="min-h-0 overflow-y-auto border-b lg:border-b-0 lg:border-r">
          {loading && <p className="p-4 text-sm text-muted-foreground">Đang tải hội thoại...</p>}
          {!loading && !items.length && <p className="p-6 text-center text-sm text-muted-foreground">Không có hội thoại trong mục này.</p>}
          {items.map((item) => <button type="button" key={item.id} onClick={() => void openConversation(item.id)} className={`w-full border-b p-4 text-left hover:bg-muted/50 ${selected?.id === item.id ? 'bg-muted' : ''}`}>
            <div className="flex items-center justify-between gap-2"><span className="truncate font-medium">{item.customer.name}</span><span className="shrink-0 text-xs text-muted-foreground">{formatDate(item.last_message_at)}</span></div>
            <p className="mt-1 truncate text-sm text-muted-foreground">{item.last_message || 'Chưa có tin nhắn'}</p>
            <div className="mt-2 flex items-center justify-between text-xs"><span className="rounded bg-muted px-2 py-0.5">{statusLabels[item.status]}</span>{item.assignee && <span className="truncate text-muted-foreground">{item.assignee.name}</span>}</div>
          </button>)}
        </aside>
        {!selected ? <div className="flex flex-col items-center justify-center gap-2 p-8 text-center text-muted-foreground"><MessageSquare className="size-8" /><p className="text-sm">Chọn một hội thoại để xem nội dung.</p></div> :
          <section className="flex min-h-0 flex-col">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
              <div><h2 className="font-semibold">{selected.customer.name}</h2><p className="text-xs text-muted-foreground">{selected.assignee ? `Đang phụ trách: ${selected.assignee.name}` : 'Chưa có người xử lý'}</p></div>
              <div className="flex flex-wrap items-center gap-2">
                {selected.status === 'waiting' && <Button size="sm" disabled={working} onClick={() => void run(() => adminSupportChatService.claim(selected.id), 'Bạn đã nhận hội thoại.')}>Nhận xử lý</Button>}
                {isAdmin && selected.status !== 'closed' && <select value={selected.assignee?.id ?? ''} onChange={(event) => { if (event.target.value) void run(() => adminSupportChatService.transfer(selected.id, event.target.value), 'Đã chuyển người xử lý.'); }} className="h-8 max-w-40 rounded-md border bg-background px-2 text-xs"><option value="">Chuyển cho staff</option>{staff.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}</select>}
                {selected.status !== 'closed' && (isAdmin || selected.assignee?.id === user?.id) && <Button size="sm" variant="outline" disabled={working} onClick={() => void run(() => adminSupportChatService.close(selected.id), 'Đã đóng hội thoại.')}><CheckCircle2 /> Đóng</Button>}
              </div>
            </header>
            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto bg-muted/20 p-4">
              {selected.messages?.map((message) => {
                const own = message.sender_id === user?.id;
                return <div key={message.id} className={`flex ${own ? 'justify-end' : 'justify-start'}`}><div className={`max-w-[78%] rounded-lg px-3 py-2 text-sm ${own ? 'bg-primary text-primary-foreground' : 'border bg-background'}`}><p className={`mb-1 text-xs font-medium ${own ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}>{message.sender_name}</p><p className="whitespace-pre-wrap break-words">{message.content}</p><p className={`mt-1 text-right text-[11px] ${own ? 'text-primary-foreground/75' : 'text-muted-foreground'}`}>{formatDate(message.sent_at)}</p></div></div>;
              })}
            </div>
            <form onSubmit={send} className="flex gap-2 border-t p-3"><textarea value={content} onChange={(event) => setContent(event.target.value)} disabled={!canReply || working} rows={2} maxLength={4000} placeholder={selected.status === 'closed' ? 'Hội thoại đã đóng' : canReply ? 'Nhập phản hồi...' : 'Bạn cần nhận hội thoại trước'} className="min-h-10 flex-1 resize-none rounded-md border bg-background px-3 py-2 text-sm outline-none focus:border-primary disabled:bg-muted" /><Button type="submit" disabled={!canReply || !content.trim() || working} size="icon" title="Gửi phản hồi"><Send /></Button></form>
          </section>}
      </div>
    </div>
  );
}
