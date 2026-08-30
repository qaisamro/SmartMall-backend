<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\ComplaintNewMessage;
use App\Notifications\ComplaintResponded;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index()
    {
        return response()->json(
            Complaint::with('user:id,name,email,phone,address,birthdate,gender', 'mall:id,name_ar')
                ->withCount('messages')
                ->latest()
                ->get()
        );
    }

    public function myComplaints(Request $request)
    {
        return response()->json(
            Complaint::with('mall:id,name_ar')
                ->withCount('messages')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
        );
    }

    private const STATUS_LABELS = [
        'pending' => 'قيد المراجعة',
        'in_progress' => 'قيد المعالجة',
        'replied' => 'تم الرد',
        'resolved' => 'تم الحل',
        'rejected' => 'مرفوض',
    ];

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'order_id' => 'nullable|string',
            'mall_id' => 'nullable|exists:malls,id',
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'order_id' => $request->order_id,
            'mall_id' => $request->mall_id,
        ]);

        // Notify all super-admins about the new complaint
        try {
            $admins = User::role('super-admin')->get();
            $senderName = $request->user()->name ?? 'زبون';
            $preview = mb_substr($request->description, 0, 100);
            foreach ($admins as $admin) {
                $admin->notify(new ComplaintNewMessage($complaint, $senderName, $preview, '/admin/complaints'));
            }
        } catch (\Throwable $e) {
            \Log::warning('New complaint admin notification failed', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
        }

        return response()->json($complaint, 201);
    }

    public function messages(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);

        if (!$request->user()->hasRole('super-admin') && $complaint->user_id !== $request->user()->id) {
            abort(403);
        }

        $messages = $complaint->messages()
            ->with('user:id,name')
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $complaint = Complaint::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('super-admin') && $complaint->user_id !== $user->id) {
            abort(403);
        }

        $message = $complaint->messages()->create([
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        $message->load('user:id,name');

        if ($complaint->status === 'pending') {
            $complaint->update(['status' => 'in_progress']);
        }

        $isAdmin = $user->hasRole('super-admin');
        $preview = mb_substr($request->message, 0, 100);

        try {
            if ($isAdmin) {
                if ($complaint->user) {
                    $complaint->user->notify(new ComplaintNewMessage($complaint, $user->name, $preview, '/complaints'));
                }
            } else {
                $admins = User::role('super-admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new ComplaintNewMessage($complaint, $user->name, $preview, '/admin/complaints'));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Complaint message notification failed', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
        }

        return response()->json($message, 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,replied,resolved,rejected',
        ]);

        $complaint = Complaint::findOrFail($id);
        $oldStatus = $complaint->status;
        $complaint->update(['status' => $request->status]);

        $user = $request->user();
        $oldLabel = self::STATUS_LABELS[$oldStatus] ?? $oldStatus;
        $newLabel = self::STATUS_LABELS[$request->status] ?? $request->status;
        $complaint->messages()->create([
            'user_id' => $user->id,
            'message' => 'تم تغيير الحالة من "' . $oldLabel . '" إلى "' . $newLabel . '"',
        ]);

        if ($complaint->user && $user->id !== $complaint->user_id) {
            try {
                $complaint->user->notify(new ComplaintNewMessage($complaint, 'النظام', 'تم تحديث حالة شكواك إلى: ' . $newLabel, '/complaints'));
            } catch (\Throwable $e) {
                \Log::warning('Complaint status notification failed', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json($complaint);
    }

    public function respond(Request $request, $id)
    {
        $request->validate([
            'admin_response' => 'required|string',
            'status' => 'required|in:in_progress,replied,resolved,rejected',
        ]);

        $complaint = Complaint::with('user')->findOrFail($id);
        $complaint->update([
            'admin_response' => $request->admin_response,
            'status' => $request->status,
        ]);

        $complaint->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->admin_response,
        ]);

        if ($complaint->user) {
            $complaint->user->notify(new ComplaintResponded($complaint));
        }

        return response()->json($complaint->load('user:id,name,email,phone,address,birthdate,gender', 'mall:id,name_ar'));
    }
}
