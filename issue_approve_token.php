<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$o = App\Models\Order::find(37);
if (!$o) { echo "NO_ORDER_37\n"; exit; }
echo "order37: mall_id=" . $o->mall_id . " method=" . $o->delivery_method . " ds=" . $o->delivery_status . " approved_at=" . ($o->approved_at ?? 'null') . "\n";
$m = App\Models\Mall::find($o->mall_id);
$owner = $m ? $m->owner : null;
if (!$owner) { echo "NO_OWNER\n"; exit; }
$owner->tokens()->delete();
$t = $owner->createToken('qa-approve2')->plainTextToken;
echo "TOKEN=" . $t . "\n";
echo "owner_id=" . $owner->id . " mall=" . ($m ? $m->name_ar : '?') . "\n";