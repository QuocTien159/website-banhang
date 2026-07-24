import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { MessageCircle, Minus, Send, X } from 'lucide-react';
import { useLocation, useNavigate } from 'react-router';
import { toast } from 'sonner';
import { useAuth } from '../../store/AppContext';
import { supportChatService, type SupportConversation } from '../../services/supportChatService';

const formatTime = (value: string | null) => value ? new Date(value).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) : '';

export function SupportChatWidget() {
  const { user, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [open, setOpen] = useState(false);
  const [conversation, setConversation] = useState<SupportConversation | null>(null);
  const [content, setContent] = useState('');
  const [sending, setSending] = useState(false);
  const endRef = useRef<HTMLDivElement>(null);

  const load = useCallback(async () => {
    if (!isAuthenticated || user?.role !== 'customer') return;
    try {
      const data = await supportChatService.getConversation();
      setConversation(data);
    } catch {
      // The launcher must not interrupt normal shopping when the chat API is unavailable.
    }
  }, [isAuthenticated, user?.role]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => {
    if (!open) return;
    void load();
    const timer = window.setInterval(() => void load(), 5000);
    return () => window.clearInterval(timer);
  }, [open, load]);
  useEffect(() => { if (open) endRef.current?.scrollIntoView({ block: 'end' }); }, [open, conversation?.messages?.length]);

  if (user && user.role !== 'customer') return null;

  const openChat = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    setOpen(true);
  };

  const send = async (event: FormEvent) => {
    event.preventDefault();
    const trimmed = content.trim();
    if (!trimmed || sending) return;
    setSending(true);
    try {
      setConversation(await supportChatService.sendMessage(trimmed));
      setContent('');
    } catch {
      toast.error('Không thể gửi tin nhắn. Vui lòng thử lại.');
    } finally {
      setSending(false);
    }
  };

  return (
    <>
      {open && (
        <section className="fixed bottom-36 right-4 z-50 flex h-[min(560px,calc(100vh-11rem))] w-[min(390px,calc(100vw-2rem))] flex-col overflow-hidden rounded-lg border bg-background shadow-2xl sm:bottom-40 sm:right-6" aria-label="Chat với cửa hàng">
          <header className="flex items-center justify-between bg-primary px-4 py-3 text-primary-foreground">
            <div><p className="font-semibold">Cửa hàng TienProSport</p><p className="text-xs opacity-85">Hỗ trợ mua sắm và đơn hàng</p></div>
            <div className="flex items-center gap-1">
              <button type="button" className="rounded p-1.5 hover:bg-white/15" title="Thu nhỏ" onClick={() => setOpen(false)}><Minus className="size-4" /></button>
              <button type="button" className="rounded p-1.5 hover:bg-white/15" title="Đóng" onClick={() => setOpen(false)}><X className="size-4" /></button>
            </div>
          </header>
          <div className="flex-1 space-y-3 overflow-y-auto bg-muted/30 p-3">
            {!conversation?.messages?.length && <p className="mx-4 mt-8 text-center text-sm text-muted-foreground">Gửi tin nhắn để cửa hàng có thể hỗ trợ bạn.</p>}
            {conversation?.messages?.map((message) => {
              const own = message.sender_role === 'customer';
              return <div key={message.id} className={`flex ${own ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[82%] rounded-lg px-3 py-2 text-sm ${own ? 'bg-primary text-primary-foreground' : 'border bg-background'}`}>
                  {!own && <p className="mb-0.5 text-xs font-medium text-muted-foreground">{message.sender_name}</p>}
                  <p className="whitespace-pre-wrap break-words">{message.content}</p>
                  <p className={`mt-1 text-right text-[11px] ${own ? 'text-primary-foreground/75' : 'text-muted-foreground'}`}>{formatTime(message.sent_at)}</p>
                </div>
              </div>;
            })}
            <div ref={endRef} />
          </div>
          <form onSubmit={send} className="flex gap-2 border-t bg-background p-3">
            <textarea value={content} onChange={(event) => setContent(event.target.value)} rows={2} maxLength={4000} placeholder="Nhập tin nhắn..." className="min-h-10 flex-1 resize-none rounded-md border bg-background px-3 py-2 text-sm outline-none focus:border-primary" />
            <button disabled={!content.trim() || sending} type="submit" className="self-end rounded-md bg-primary p-2.5 text-primary-foreground disabled:opacity-50" title="Gửi tin nhắn"><Send className="size-4" /></button>
          </form>
        </section>
      )}
      <button type="button" onClick={openChat} className="fixed bottom-20 right-4 z-50 flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg hover:bg-primary/90 sm:bottom-24 sm:right-6" title="Chat với cửa hàng" aria-label="Chat với cửa hàng">
        <MessageCircle className="size-5" />
        {!!conversation?.unread_count && !open && <span className="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-destructive text-[11px] text-white">{conversation.unread_count > 9 ? '9+' : conversation.unread_count}</span>}
      </button>
    </>
  );
}
