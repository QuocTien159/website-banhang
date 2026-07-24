import apiClient from './apiClient';

export type SupportMessage = {
  id: string;
  content: string;
  sender_id: string | null;
  sender_name: string;
  sender_role: 'customer' | 'staff' | 'admin';
  sent_at: string | null;
};

export type SupportConversation = {
  id: string;
  status: 'waiting' | 'in_progress' | 'closed';
  customer: { id: string; name: string };
  assignee: { id: string; name: string } | null;
  last_message: string | null;
  last_message_at: string | null;
  unread_count?: number;
  messages?: SupportMessage[];
};

const unwrap = <T>(response: { data: { data: T } }) => response.data.data;

export const supportChatService = {
  async getConversation(): Promise<SupportConversation | null> {
    return unwrap(await apiClient.get('/support/conversation'));
  },
  async sendMessage(content: string): Promise<SupportConversation> {
    return unwrap(await apiClient.post('/support/messages', { content }));
  },
};

export const adminSupportChatService = {
  async list(filter: 'waiting' | 'mine' | 'closed' | 'all'): Promise<SupportConversation[]> {
    return unwrap(await apiClient.get('/admin/support/conversations', { params: { filter } }));
  },
  async get(id: string): Promise<SupportConversation> {
    return unwrap(await apiClient.get(`/admin/support/conversations/${id}`));
  },
  async claim(id: string): Promise<SupportConversation> {
    return unwrap(await apiClient.post(`/admin/support/conversations/${id}/claim`));
  },
  async sendMessage(id: string, content: string): Promise<SupportConversation> {
    return unwrap(await apiClient.post(`/admin/support/conversations/${id}/messages`, { content }));
  },
  async close(id: string): Promise<SupportConversation> {
    return unwrap(await apiClient.put(`/admin/support/conversations/${id}/close`));
  },
  async transfer(id: string, staffId: string): Promise<SupportConversation> {
    return unwrap(await apiClient.put(`/admin/support/conversations/${id}/transfer`, { staff_id: staffId }));
  },
  async staffOptions(): Promise<{ id: string; name: string }[]> {
    return unwrap(await apiClient.get('/admin/support/staff'));
  },
};
