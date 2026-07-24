import { Outlet } from 'react-router';
import { Header } from './Header';
import { Footer } from './Footer';
import { Toaster } from '../ui/sonner';
import { SupportChatWidget } from '../user/SupportChatWidget';

export function UserLayout() {
  return (
    <div className="min-h-screen flex flex-col bg-background">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
      <SupportChatWidget />
      <Toaster richColors position="bottom-right" />
    </div>
  );
}
