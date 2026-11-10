<?php

namespace App\Http\Controllers;

use App\Application\OperationsReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController
{
    public function csv(Request $request, OperationsReport $reports): StreamedResponse
    {
        $report = $reports->summarize($request->query());

        return response()->streamDownload(static function () use ($report): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new \RuntimeException('無法開啟 CSV 輸出。');
            }
            fwrite($output, "\xEF\xBB\xBF");
            $write = static fn (array $row) => fputcsv($output, array_map(OperationsReport::csvCell(...), $row), ',', '"', '');
            $write(['分類', '項目', '數值', '附加資訊']);
            foreach ($report['metrics'] as $label => $value) {
                $write(['摘要', $label, $value, null]);
            }
            foreach ($report['sales'] as $sale) {
                $write(['通路銷售（分）', $sale->store_name ?? $sale->channel, (int) $sale->amount, (int) $sale->count]);
            }
            foreach ($report['providers'] as $supplier) {
                $write(['供應商成功數', $supplier->name, (int) $supplier->succeeded, (int) $supplier->total]);
                $write(['供應商失敗數', $supplier->name, (int) $supplier->failed, (int) $supplier->retries]);
                $write(['供應商平均確認秒數', $supplier->name, $supplier->latency === null ? null : (float) $supplier->latency, null]);
            }
            foreach ($report['movements'] as $movement) {
                $write(['庫存流水', __('status.'.$movement->kind), (int) $movement->delta, (int) $movement->count]);
            }
            fclose($output);
        }, 'travelops-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
