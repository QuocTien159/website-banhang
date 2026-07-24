<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuocTroChuyenHoTro;
use App\Models\KhachHang;
use App\Models\TinNhanHoTro;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportChatController extends Controller
{
    public function customerConversation(Request $request)
    {
        $this->ensureCustomer($request);
        $conversation = CuocTroChuyenHoTro::with(['customer', 'assignee', 'messages.sender'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->first();

        if (! $conversation) {
            return response()->json(['data' => null]);
        }

        $conversation->update(['khach_hang_da_doc_luc' => now()]);
        return response()->json(['data' => $this->detail($conversation, 'customer')]);
    }

    public function sendCustomerMessage(Request $request)
    {
        $this->ensureCustomer($request);
        $data = $request->validate(['content' => ['required', 'string', 'max:4000']]);
        $user = $request->user();

        $conversation = DB::transaction(function () use ($user, $data) {
            $conversation = CuocTroChuyenHoTro::where('ma_kh', $user->ma_kh)->lockForUpdate()->first();
            if (! $conversation) {
                $conversation = CuocTroChuyenHoTro::create([
                    'ma_kh' => $user->ma_kh,
                    'trang_thai' => 'waiting',
                    'ngay_tao' => now(),
                ]);
            }

            if ($conversation->trang_thai === 'closed') {
                $conversation->trang_thai = $conversation->ma_nv_phu_trach ? 'in_progress' : 'waiting';
            }

            TinNhanHoTro::create([
                'ma_ct' => $conversation->ma_ct,
                'ma_nguoi_gui' => $user->ma_kh,
                'vai_tro_nguoi_gui' => UserRole::CUSTOMER,
                'noi_dung' => trim($data['content']),
                'ngay_gui' => now(),
            ]);

            $conversation->update([
                'trang_thai' => $conversation->trang_thai,
                'tin_nhan_cuoi_luc' => now(),
                'khach_hang_da_doc_luc' => now(),
                'ngay_cap_nhat' => now(),
            ]);

            return $conversation;
        });

        return response()->json(['data' => $this->detail($conversation->fresh(['customer', 'assignee', 'messages.sender']), 'customer')], 201);
    }

    public function index(Request $request)
    {
        $actor = $request->user();
        $filter = $request->input('filter', 'waiting');
        if (! in_array($filter, ['waiting', 'mine', 'closed', 'all'], true)) {
            throw ValidationException::withMessages(['filter' => 'Bộ lọc hội thoại không hợp lệ.']);
        }

        $query = CuocTroChuyenHoTro::with(['customer', 'assignee', 'messages' => fn ($q) => $q->latest('ngay_gui')->limit(1)]);
        if ($actor->roleName() !== UserRole::ADMIN) {
            if ($filter === 'waiting') {
                $query->where('trang_thai', 'waiting');
            } elseif ($filter === 'closed') {
                $query->where('ma_nv_phu_trach', $actor->ma_kh)->where('trang_thai', 'closed');
            } else {
                $query->where('ma_nv_phu_trach', $actor->ma_kh)->where('trang_thai', 'in_progress');
            }
        } elseif ($filter !== 'all') {
            $query->where('trang_thai', $filter === 'mine' ? 'in_progress' : $filter);
            if ($filter === 'mine') {
                $query->where('ma_nv_phu_trach', $actor->ma_kh);
            }
        }

        $conversations = $query->orderByDesc('tin_nhan_cuoi_luc')->paginate($request->integer('per_page', 30));
        return response()->json([
            'data' => $conversations->getCollection()->map(fn (CuocTroChuyenHoTro $item) => $this->summary($item)),
            'meta' => ['total' => $conversations->total()],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $conversation = CuocTroChuyenHoTro::with(['customer', 'assignee', 'messages.sender'])->findOrFail($id);
        $this->ensureBackOfficeAccess($request, $conversation);
        $conversation->update(['nhan_vien_da_doc_luc' => now()]);
        return response()->json(['data' => $this->detail($conversation, 'staff')]);
    }

    public function claim(Request $request, string $id)
    {
        $conversation = DB::transaction(function () use ($id, $request) {
            $conversation = CuocTroChuyenHoTro::lockForUpdate()->findOrFail($id);
            if ($conversation->trang_thai !== 'waiting' || $conversation->ma_nv_phu_trach) {
                throw ValidationException::withMessages(['conversation' => 'Hội thoại này đã được nhân viên khác nhận xử lý.']);
            }
            $conversation->update([
                'ma_nv_phu_trach' => $request->user()->ma_kh,
                'trang_thai' => 'in_progress',
                'ngay_cap_nhat' => now(),
            ]);
            return $conversation;
        });
        return response()->json(['data' => $this->detail($conversation->fresh(['customer', 'assignee', 'messages.sender']), 'staff')]);
    }

    public function sendStaffMessage(Request $request, string $id)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:4000']]);
        $actor = $request->user();
        $conversation = DB::transaction(function () use ($id, $actor, $data, $request) {
            $conversation = CuocTroChuyenHoTro::lockForUpdate()->findOrFail($id);
            $this->ensureBackOfficeAccess($request, $conversation);
            if (! $conversation->ma_nv_phu_trach) {
                $conversation->ma_nv_phu_trach = $actor->ma_kh;
            }
            $conversation->trang_thai = 'in_progress';
            TinNhanHoTro::create([
                'ma_ct' => $conversation->ma_ct,
                'ma_nguoi_gui' => $actor->ma_kh,
                'vai_tro_nguoi_gui' => $actor->roleName(),
                'noi_dung' => trim($data['content']),
                'ngay_gui' => now(),
            ]);
            $conversation->tin_nhan_cuoi_luc = now();
            $conversation->nhan_vien_da_doc_luc = now();
            $conversation->ngay_cap_nhat = now();
            $conversation->save();
            return $conversation;
        });
        return response()->json(['data' => $this->detail($conversation->fresh(['customer', 'assignee', 'messages.sender']), 'staff')], 201);
    }

    public function close(Request $request, string $id)
    {
        $conversation = CuocTroChuyenHoTro::findOrFail($id);
        $this->ensureBackOfficeAccess($request, $conversation);
        $conversation->update(['trang_thai' => 'closed', 'ngay_cap_nhat' => now()]);
        return response()->json(['data' => $this->detail($conversation->fresh(['customer', 'assignee', 'messages.sender']), 'staff')]);
    }

    public function transfer(Request $request, string $id)
    {
        $data = $request->validate(['staff_id' => ['required', 'string', Rule::exists('khach_hang', 'ma_kh')]]);
        $staff = KhachHang::findOrFail($data['staff_id']);
        if (! $staff->isStaff() || ! $staff->trang_thai) {
            throw ValidationException::withMessages(['staff_id' => 'Chỉ có thể chuyển cho nhân viên đang hoạt động.']);
        }
        $conversation = CuocTroChuyenHoTro::findOrFail($id);
        $conversation->update(['ma_nv_phu_trach' => $staff->ma_kh, 'trang_thai' => 'in_progress', 'ngay_cap_nhat' => now()]);
        return response()->json(['data' => $this->detail($conversation->fresh(['customer', 'assignee', 'messages.sender']), 'staff')]);
    }

    public function staffOptions()
    {
        return response()->json(['data' => KhachHang::query()->where('role', UserRole::STAFF)->where('trang_thai', true)->orderBy('ten_kh')->get(['ma_kh', 'ten_kh'])->map(fn ($user) => ['id' => $user->ma_kh, 'name' => $user->ten_kh])]);
    }

    private function ensureBackOfficeAccess(Request $request, CuocTroChuyenHoTro $conversation): void
    {
        if ($request->user()->roleName() === UserRole::ADMIN) return;
        if ($conversation->ma_nv_phu_trach !== $request->user()->ma_kh) {
            throw ValidationException::withMessages(['conversation' => 'Bạn cần nhận hội thoại này trước khi thao tác.']);
        }
    }

    private function ensureCustomer(Request $request): void
    {
        if ($request->user()->roleName() !== UserRole::CUSTOMER) {
            abort(403, 'Chức năng này chỉ dành cho khách hàng.');
        }
    }

    private function summary(CuocTroChuyenHoTro $conversation): array
    {
        $last = $conversation->messages->first();
        return [
            'id' => $conversation->ma_ct,
            'status' => $conversation->trang_thai,
            'customer' => ['id' => $conversation->customer->ma_kh, 'name' => $conversation->customer->ten_kh],
            'assignee' => $conversation->assignee ? ['id' => $conversation->assignee->ma_kh, 'name' => $conversation->assignee->ten_kh] : null,
            'last_message' => $last?->noi_dung,
            'last_message_at' => $conversation->tin_nhan_cuoi_luc?->toISOString(),
        ];
    }

    private function detail(CuocTroChuyenHoTro $conversation, string $viewer): array
    {
        $unreadSince = $viewer === 'customer' ? $conversation->khach_hang_da_doc_luc : $conversation->nhan_vien_da_doc_luc;
        return array_merge($this->summary($conversation), [
            'unread_count' => $conversation->messages->filter(fn ($message) => $message->ngay_gui && (! $unreadSince || $message->ngay_gui->gt($unreadSince)) && ($viewer === 'customer' ? $message->vai_tro_nguoi_gui !== UserRole::CUSTOMER : $message->vai_tro_nguoi_gui === UserRole::CUSTOMER))->count(),
            'messages' => $conversation->messages->map(fn (TinNhanHoTro $message) => [
                'id' => $message->ma_tn,
                'content' => $message->noi_dung,
                'sender_id' => $message->ma_nguoi_gui,
                'sender_name' => $message->sender?->ten_kh ?? 'Cửa hàng',
                'sender_role' => $message->vai_tro_nguoi_gui,
                'sent_at' => $message->ngay_gui?->toISOString(),
            ])->values(),
        ]);
    }
}
