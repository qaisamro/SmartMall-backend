<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\CurrencyEntry;
use App\Models\GoldEntry;
use App\Models\HomeSection;
use App\Models\NewsEntry;
use App\Models\Pharmacy;
use App\Models\PrayerTime;
use App\Models\RoadCondition;
use App\Models\WeatherEntry;
use Illuminate\Http\Request;

class AdminHomeWidgetsController extends Controller
{
    protected function ensureDefaultSections(): void
    {
        if (HomeSection::count() > 0) return;

        $defaults = [
            ['key' => 'weather', 'label_ar' => 'الطقس', 'label_en' => 'Weather', 'icon' => 'Sun', 'sort_order' => 1],
            ['key' => 'currencies', 'label_ar' => 'العملات', 'label_en' => 'Currencies', 'icon' => 'DollarSign', 'sort_order' => 2],
            ['key' => 'gold', 'label_ar' => 'الذهب', 'label_en' => 'Gold', 'icon' => 'Coins', 'sort_order' => 3],
            ['key' => 'prayer_times', 'label_ar' => 'أوقات الصلاة', 'label_en' => 'Prayer Times', 'icon' => 'Clock', 'sort_order' => 4],
            ['key' => 'pharmacies', 'label_ar' => 'الصيدليات', 'label_en' => 'Pharmacies', 'icon' => 'Pill', 'sort_order' => 5],
            ['key' => 'news', 'label_ar' => 'الأخبار', 'label_en' => 'News', 'icon' => 'Newspaper', 'sort_order' => 6],
            ['key' => 'alerts', 'label_ar' => 'التنبيهات', 'label_en' => 'Alerts', 'icon' => 'AlertTriangle', 'sort_order' => 7],
            ['key' => 'road_conditions', 'label_ar' => 'حالة الطرق', 'label_en' => 'Road Conditions', 'icon' => 'Map', 'sort_order' => 8],
        ];

        foreach ($defaults as $section) {
            HomeSection::create($section);
        }
    }

    // ---- Sections ----
    public function sections() {
        $this->ensureDefaultSections();
        return response()->json(HomeSection::orderBy('sort_order')->get());
    }

    public function updateSections(Request $r)
    {
        foreach ($r->input('sections', []) as $data) {
            HomeSection::where('key', $data['key'])->update($data);
        }
        return response()->json(['message' => 'تم حفظ الترتيب والإظهار']);
    }

    // ---- Weather ----
    public function indexWeather() { return response()->json(WeatherEntry::latest()->get()); }
    public function storeWeather(Request $r)
    {
        return response()->json(WeatherEntry::create($r->validate([
            'city' => 'nullable|string|max:255', 'temperature' => 'nullable|numeric',
            'condition' => 'nullable|string|max:255', 'icon' => 'nullable|string|max:255',
            'humidity' => 'nullable|numeric', 'wind_speed' => 'nullable|numeric',
            'forecast_short' => 'nullable|string', 'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateWeather(Request $r, $id)
    {
        $e = WeatherEntry::findOrFail($id);
        $e->update($r->all());
        return response()->json($e);
    }
    public function destroyWeather($id) { WeatherEntry::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Currencies ----
    public function indexCurrencies() { return response()->json(CurrencyEntry::orderBy('code')->get()); }
    public function storeCurrency(Request $r)
    {
        return response()->json(CurrencyEntry::create($r->validate([
            'city' => 'nullable|string|max:255', 'code' => 'required|string|max:10',
            'name' => 'required|string|max:255', 'buy_rate' => 'required|numeric',
            'sell_rate' => 'required|numeric', 'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateCurrency(Request $r, $id) { $e = CurrencyEntry::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyCurrency($id) { CurrencyEntry::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Gold ----
    public function indexGold() { return response()->json(GoldEntry::orderBy('type')->get()); }
    public function storeGold(Request $r)
    {
        return response()->json(GoldEntry::create($r->validate([
            'city' => 'nullable|string|max:255', 'type' => 'required|string|max:50',
            'price' => 'required|numeric', 'change' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateGold(Request $r, $id) { $e = GoldEntry::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyGold($id) { GoldEntry::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Prayer Times ----
    public function indexPrayers() { return response()->json(PrayerTime::latest('date')->get()); }
    public function storePrayer(Request $r)
    {
        return response()->json(PrayerTime::create($r->validate([
            'city' => 'nullable|string|max:255', 'date' => 'required|date',
            'fajr' => 'required|string|max:20', 'sunrise' => 'required|string|max:20',
            'dhuhr' => 'required|string|max:20', 'asr' => 'required|string|max:20',
            'maghrib' => 'required|string|max:20', 'isha' => 'required|string|max:20',
            'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updatePrayer(Request $r, $id) { $e = PrayerTime::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyPrayer($id) { PrayerTime::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Pharmacies ----
    public function indexPharmacies() { return response()->json(Pharmacy::orderBy('order')->get()); }
    public function storePharmacy(Request $r)
    {
        return response()->json(Pharmacy::create($r->validate([
            'city' => 'nullable|string|max:255', 'name' => 'required|string|max:255',
            'address' => 'nullable|string', 'phone' => 'nullable|string|max:50',
            'is_on_duty' => 'nullable|boolean',
            'duty_date' => 'nullable|date', 'lat' => 'nullable|numeric', 'lng' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updatePharmacy(Request $r, $id) { $e = Pharmacy::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyPharmacy($id) { Pharmacy::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- News ----
    public function indexNews() { return response()->json(NewsEntry::latest('published_at')->get()); }
    public function storeNews(Request $r)
    {
        return response()->json(NewsEntry::create($r->validate([
            'title' => 'required|string|max:255', 'summary' => 'nullable|string',
            'source' => 'nullable|string|max:255', 'source_url' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255', 'published_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateNews(Request $r, $id) { $e = NewsEntry::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyNews($id) { NewsEntry::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Alerts ----
    public function indexAlerts() { return response()->json(Alert::orderBy('order')->get()); }
    public function storeAlert(Request $r)
    {
        return response()->json(Alert::create($r->validate([
            'type' => 'nullable|string|max:50', 'title' => 'required|string|max:255',
            'body' => 'nullable|string', 'icon' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date', 'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateAlert(Request $r, $id) { $e = Alert::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyAlert($id) { Alert::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Road Conditions ----
    public function indexRoads() { return response()->json(RoadCondition::orderBy('order')->get()); }
    public function storeRoad(Request $r)
    {
        return response()->json(RoadCondition::create($r->validate([
            'city' => 'nullable|string|max:255', 'road_name' => 'required|string|max:255',
            'status' => 'required|string|max:50', 'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ])), 201);
    }
    public function updateRoad(Request $r, $id) { $e = RoadCondition::findOrFail($id); $e->update($r->all()); return response()->json($e); }
    public function destroyRoad($id) { RoadCondition::findOrFail($id)->delete(); return response()->json(['message' => 'تم الحذف']); }

    // ---- Public endpoint ----
    public function publicWidgets(Request $r)
    {
        $this->ensureDefaultSections();
        $sections = HomeSection::where('is_visible', true)->orderBy('sort_order')->get()->keyBy('key');

        $data = [];

        if ($sections->has('weather')) {
            $data['weather'] = WeatherEntry::where('is_active', true)->latest()->first();
        }
        if ($sections->has('currencies')) {
            $data['currencies'] = CurrencyEntry::where('is_active', true)->get();
        }
        if ($sections->has('gold')) {
            $data['gold'] = GoldEntry::where('is_active', true)->get();
        }
        if ($sections->has('prayer_times')) {
            $data['prayer_times'] = PrayerTime::where('is_active', true)->where('date', today())->first();
        }
        if ($sections->has('pharmacies')) {
            $data['pharmacies'] = Pharmacy::where('is_active', true)->where('is_on_duty', true)->where('duty_date', today())->orderBy('order')->get();
        }
        if ($sections->has('news')) {
            $data['news'] = NewsEntry::where('is_active', true)->latest('published_at')->take(5)->get();
        }
        if ($sections->has('alerts')) {
            $data['alerts'] = Alert::where('is_active', true)->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()); })->orderBy('order')->get();
        }
        if ($sections->has('road_conditions')) {
            $data['road_conditions'] = RoadCondition::where('is_active', true)->orderBy('order')->get();
        }

        return response()->json(['sections' => $sections->values(), 'data' => $data]);
    }
}
