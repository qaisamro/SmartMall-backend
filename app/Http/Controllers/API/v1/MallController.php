<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Mall;
use App\Models\User;
use App\Repositories\MallRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MallController extends Controller
{
    protected $mallRepository;

    public function __construct(MallRepository $mallRepository)
    {
        $this->mallRepository = $mallRepository;
    }

    public function index(Request $request)
    {
        $search = $request->query('search');
        return response()->json($this->mallRepository->getActiveMalls($search));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            // ... more validation
        ]);

        $data = $request->all();
        $data['owner_id'] = auth()->id();

        $mall = $this->mallRepository->create($data);

        return response()->json($mall, 201);
    }

    public function show($id)
    {
        return response()->json($this->mallRepository->find($id)->load('branches', 'categories', 'theme'));
    }

    public function myMalls()
    {
        return response()->json($this->mallRepository->getByOwner(auth()->id()));
    }

    public function adminIndex()
    {
        return response()->json(Mall::with('owner')->latest()->get());
    }

    public function adminStore(Request $request)
    {
        $request->validate([
            'mall_name_ar' => 'required|string|max:255',
            'mall_name_en' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|string|email|max:255|unique:users,email',
            'owner_password' => 'required|string|min:8',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'location_arabic' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->owner_name,
            'email' => $request->owner_email,
            'password' => Hash::make($request->owner_password),
        ]);
        $user->assignRole('mall-owner');

        $mall = Mall::create([
            'owner_id' => $user->id,
            'name_ar' => $request->mall_name_ar,
            'name_en' => $request->mall_name_en,
            'slug' => Str::slug($request->mall_name_en ?: $request->mall_name_ar) . '-' . time(),
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'location_arabic' => $request->location_arabic,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $user->update(['mall_id' => $mall->id]);

        return response()->json($mall->load('owner'), 201);
    }

    public function showBySlug($slug)
    {
        $mall = \App\Models\Mall::with('branches', 'categories', 'theme')->where('slug', $slug)->firstOrFail();
        return response()->json($mall);
    }

    public function update(Request $request, $id)
    {
        $mall = \App\Models\Mall::findOrFail($id);

        // Ensure owner owns it
        if ($mall->owner_id !== auth()->id() && !auth()->user()->hasRole('super-admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate only text fields — file handled manually below
        $request->validate([
            'name_ar'          => 'sometimes|required|string|max:255',
            'name_en'          => 'sometimes|nullable|string|max:255',
            'description'      => 'sometimes|nullable|string',
            'slug'             => 'sometimes|nullable|string|max:255',
            'location_arabic'  => 'sometimes|nullable|string|max:255',
        ]);

        $data = $request->only(['name_ar', 'name_en', 'description', 'slug', 'location_arabic']);

        // Handle cover image manually (bypasses PHP fileinfo issues)
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            if ($file->isValid()) {
                $path = $file->store('malls/covers', 'public');
                $data['cover_image'] = $path;
            }
        }

        $mall->update($data);
        return response()->json($mall);
    }

    public function generateQR(Request $request, $id, \App\Services\QRService $qrService)
    {
        $mall = \App\Models\Mall::findOrFail($id);

        if ($mall->owner_id !== auth()->id() && !auth()->user()->hasRole('super-admin') && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Generate the URL that points to the mall public page
        $url = env('FRONTEND_URL', 'http://localhost:5173') . '/mall/' . $mall->slug;
        $path = $qrService->generateQR($url, 'mall_' . $mall->id);

        $mall->update(['qr_code_path' => $path]);

        return response()->json(['qr_code_path' => $path]);
    }

    public function handleScan(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = $request->code;

        // Check if it's an order QR code (contains order data in a URL or raw format)
        if (str_contains($code, '?data=') || str_contains($code, 'data=')) {
            return response()->json([
                'type' => 'order',
                'redirect_url' => str_contains($code, 'http') ? $code : '/owner/pos?data=' . (str_contains($code, 'data=') ? explode('data=', $code)[1] : $code),
            ]);
        }

        // Check if it's a mall QR code (contains mall slug or mall URL)
        if (str_contains($code, '/mall/') || str_contains($code, 'mall/')) {
            // Extract slug from URL
            $slug = basename(parse_url($code, PHP_URL_PATH));
            $mall = \App\Models\Mall::where('slug', $slug)->first();

            if ($mall) {
                return response()->json([
                    'type' => 'mall',
                    'mall_id' => $mall->id,
                    'slug' => $mall->slug,
                    'name_ar' => $mall->name_ar,
                    'redirect_url' => '/mall/' . $mall->slug,
                ]);
            }
        }

        // If not a mall QR, try to find it as a product barcode
        $product = \App\Models\Product::where('barcode', $code)->first();

        if ($product) {
            return response()->json([
                'type' => 'product',
                'product' => $product->load('category', 'mall'),
            ]);
        }

        // If not found, return error
        return response()->json([
            'message' => 'QR code not recognized',
        ], 404);
    }
}
