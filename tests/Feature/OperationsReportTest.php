<?php

namespace Tests\Feature;

use App\Application\OperationsReport;
use App\Application\PointOfSale;
use App\Application\RefundOrder;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\CreatesOrders;
use Tests\TestCase;

final class OperationsReportTest extends TestCase
{
    use CreatesOrders;
    use RefreshDatabase;

    public function test_report_applies_taipei_date_and_scope_and_csv_neutralizes_formulae(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 00:03:00', 'Asia/Taipei'));
        $product = Product::factory()->stocked(3)->create(['price' => 24000]);
        $product->supplier->update(['name' => '=1+1']);
        $operator = User::factory()->create(['role' => 'operator']);
        $online = $this->place($product);
        $this->pay($online);
        $this->issue($online);
        $refunds = app(RefundOrder::class);
        $refund = $refunds->review($operator, $refunds->request($operator, $online), true);
        $refunds->process($refund->id);
        $store = Store::create(['name' => '+灣岸展示']);
        $clerk = User::factory()->create(['role' => 'pos_clerk']);
        $clerk->stores()->attach($store);
        $pos = app(PointOfSale::class)->sell($clerk, $store, $product->id, 2, (string) Str::uuid(), 'mock_card');
        $this->issue($pos);

        $day = ['from' => '2026-11-09', 'to' => '2026-11-09'];
        $this->actingAs($operator);
        $reports = app(OperationsReport::class);
        $metrics = $reports->summarize($day)['metrics'];
        self::assertSame(2, (int) $metrics['成功交易筆數']);
        self::assertSame(72000, $metrics['成功交易額（分）']);
        self::assertSame(24000, $metrics['成功退款額（分）']);
        self::assertSame(48000, $metrics['淨銷售額（分）']);
        $posOnly = $reports->summarize($day + ['channel' => 'pos', 'store' => $store->id, 'supplier' => $product->supplier_id])['metrics'];
        self::assertSame(48000, $posOnly['成功交易額（分）']);
        self::assertSame(0, $posOnly['成功退款額（分）']);
        self::assertSame(0, $reports->summarize(['from' => '2026-11-08', 'to' => '2026-11-08'])['metrics']['成功交易額（分）']);

        $this->get('/ops/reports?'.http_build_query($day))->assertOk()->assertSee('營運摘要');
        $csv = $this->get('/ops/reports.csv?'.http_build_query($day))->assertOk()->assertDownload('travelops-report.csv')->streamedContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        self::assertStringContainsString("'=1+1", $csv);
        self::assertStringContainsString("'+灣岸展示", $csv);
        self::assertStringNotContainsString($online->tickets()->firstOrFail()->code, $csv);
        $this->actingAs($clerk)->get('/ops/reports')->assertForbidden();
        $this->get('/ops/reports.csv')->assertForbidden();
    }
}
