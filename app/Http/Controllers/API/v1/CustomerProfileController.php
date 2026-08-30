<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
        ]);

        $user->update($request->only([
            'name', 'email', 'phone', 'whatsapp', 'address', 'birthdate', 'gender'
        ]));

        return response()->json($user);
    }

    public function updateWhatsapp(Request $request)
    {
        $request->validate([
            'whatsapp' => 'required|string|max:20',
        ]);

        $user = $request->user();
        $user->update(['whatsapp' => $request->whatsapp]);

        return response()->json([
            'message' => 'تم حفظ رقم واتساب بنجاح',
            'whatsapp' => $user->whatsapp,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'تم تحديث كلمة المرور بنجاح']);
    }

    public function completionPercentage(Request $request)
    {
        $user = $request->user();
        $fields = ['name', 'email', 'phone', 'address', 'birthdate', 'gender'];
        $filled = 0;

        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $filled++;
            }
        }

        $percentage = round(($filled / count($fields)) * 100);

        return response()->json([
            'percentage' => $percentage,
            'filled_fields' => $filled,
            'total_fields' => count($fields),
            'missing_fields' => array_values(array_filter($fields, fn($f) => empty($user->$f))),
        ]);
    }
}
