<?php

namespace App\Services\Reports;

use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Collection;

class SalesReportQuery extends BaseReportQuery
{
    public function getData(array $parameters = []): array
    {
        $this->setParameters($parameters);

        $query = TaxDocument::query()
            ->with(['customer', 'items.product'])
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'invoice')
            ->whereNotIn('status', ['draft', 'cancelled']);

        // Apply date range
        $this->applyDateRange($query, 'created_at');

        // Apply filters
        $this->applyFilters($query, [
            'customer_id' => 'customer_id',
            'status' => 'status',
        ]);

        $invoices = $query->orderBy('created_at', 'desc')->get();

        return [
            'summary' => $this->getSummary($invoices),
            'invoices' => $this->formatInvoices($invoices),
            'top_customers' => $this->getTopCustomers($invoices),
            'top_products' => $this->getTopProducts($invoices),
            'sales_by_period' => $this->getSalesByPeriod($invoices),
            'payment_methods' => $this->getPaymentMethodStats($invoices),
            'charts' => $this->getChartData($invoices),
        ];
    }

    public function getTitle(array $parameters = []): string
    {
        $this->setParameters($parameters);
        $dateRange = $this->getDateRange();
        
        return "Reporte de Ventas - {$this->formatDate($dateRange['from'])} al {$this->formatDate($dateRange['to'])}";
    }

    public function getDescription(array $parameters = []): string
    {
        return 'Análisis detallado de ventas, incluyendo facturas, clientes principales, productos más vendidos y tendencias de ventas.';
    }

    protected function getSummary(Collection $invoices): array
    {
        $totalSales = $invoices->sum('total_amount');
        $totalInvoices = $invoices->count();
        $averageTicket = $totalInvoices > 0 ? $totalSales / $totalInvoices : 0;
        $totalTax = $invoices->sum('tax_amount');
        $netAmount = $totalSales - $totalTax;

        // Get comparison data
        $comparisonRange = $this->getComparisonData('previous');
        $previousSales = 0;
        $previousInvoices = 0;

        if (!empty($comparisonRange)) {
            $previousQuery = TaxDocument::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('type', 'invoice')
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->whereBetween('created_at', [$comparisonRange['from'], $comparisonRange['to']]);

            $previousData = $previousQuery->get();
            $previousSales = $previousData->sum('total_amount');
            $previousInvoices = $previousData->count();
        }

        return [
            'total_sales' => $totalSales,
            'total_sales_formatted' => $this->formatCurrency($totalSales),
            'total_invoices' => $totalInvoices,
            'average_ticket' => $averageTicket,
            'average_ticket_formatted' => $this->formatCurrency($averageTicket),
            'total_tax' => $totalTax,
            'total_tax_formatted' => $this->formatCurrency($totalTax),
            'net_amount' => $netAmount,
            'net_amount_formatted' => $this->formatCurrency($netAmount),
            'sales_growth' => $this->calculatePercentageChange($totalSales, $previousSales),
            'invoices_growth' => $this->calculatePercentageChange($totalInvoices, $previousInvoices),
            'unique_customers' => $invoices->unique('customer_id')->count(),
            'paid_invoices' => $invoices->where('payment_status', 'paid')->count(),
            'pending_invoices' => $invoices->where('payment_status', 'pending')->count(),
            'overdue_invoices' => $invoices->where('payment_status', 'overdue')->count(),
        ];
    }

    protected function formatInvoices(Collection $invoices): array
    {
        $includeDetails = $this->getParameter('include_details', true);

        return $invoices->map(function ($invoice) use ($includeDetails) {
            $data = [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'folio' => $invoice->folio,
                'date' => $this->formatDate($invoice->created_at),
                'customer_name' => $invoice->customer->name ?? 'Cliente General',
                'customer_rut' => $invoice->customer->rut ?? '',
                'total_amount' => $invoice->total_amount,
                'total_amount_formatted' => $this->formatCurrency($invoice->total_amount),
                'tax_amount' => $invoice->tax_amount,
                'tax_amount_formatted' => $this->formatCurrency($invoice->tax_amount),
                'net_amount' => $invoice->total_amount - $invoice->tax_amount,
                'net_amount_formatted' => $this->formatCurrency($invoice->total_amount - $invoice->tax_amount),
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'payment_method' => $invoice->payment_method,
                'due_date' => $this->formatDate($invoice->due_date),
            ];

            if ($includeDetails && $invoice->items) {
                $data['items'] = $invoice->items->map(function ($item) {
                    return [
                        'product_name' => $item->product->name ?? $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'unit_price_formatted' => $this->formatCurrency($item->unit_price),
                        'total' => $item->total,
                        'total_formatted' => $this->formatCurrency($item->total),
                    ];
                })->toArray();
            }

            return $data;
        })->toArray();
    }

    protected function getTopCustomers(Collection $invoices): array
    {
        $customerSales = $invoices->groupBy('customer_id')->map(function ($customerInvoices) {
            $customer = $customerInvoices->first()->customer;
            return [
                'customer_id' => $customer->id ?? null,
                'customer_name' => $customer->name ?? 'Cliente General',
                'customer_rut' => $customer->rut ?? '',
                'total_sales' => $customerInvoices->sum('total_amount'),
                'total_sales_formatted' => $this->formatCurrency($customerInvoices->sum('total_amount')),
                'invoice_count' => $customerInvoices->count(),
                'average_ticket' => $customerInvoices->avg('total_amount'),
                'average_ticket_formatted' => $this->formatCurrency($customerInvoices->avg('total_amount')),
                'last_purchase' => $this->formatDate($customerInvoices->max('created_at')),
            ];
        })->sortByDesc('total_sales')->take(10)->values();

        return $customerSales->toArray();
    }

    protected function getTopProducts(Collection $invoices): array
    {
        $productSales = collect();

        foreach ($invoices as $invoice) {
            if ($invoice->items) {
                foreach ($invoice->items as $item) {
                    $productId = $item->product_id ?? 'unknown';
                    $existing = $productSales->firstWhere('product_id', $productId);

                    if ($existing) {
                        $existing['quantity_sold'] += $item->quantity;
                        $existing['total_sales'] += $item->total;
                    } else {
                        $productSales->push([
                            'product_id' => $productId,
                            'product_name' => $item->product->name ?? $item->description,
                            'product_sku' => $item->product->sku ?? '',
                            'quantity_sold' => $item->quantity,
                            'total_sales' => $item->total,
                            'average_price' => $item->unit_price,
                        ]);
                    }
                }
            }
        }

        return $productSales->map(function ($product) {
            $product['total_sales_formatted'] = $this->formatCurrency($product['total_sales']);
            $product['average_price_formatted'] = $this->formatCurrency($product['average_price']);
            return $product;
        })->sortByDesc('total_sales')->take(10)->values()->toArray();
    }

    protected function getSalesByPeriod(Collection $invoices): array
    {
        $period = $this->getParameter('group_by', 'day');
        $grouped = $this->groupByDatePeriod($invoices, 'created_at', $period);

        return $grouped->map(function ($periodInvoices, $period) {
            return [
                'period' => $period,
                'period_formatted' => $this->formatPeriod($period),
                'total_sales' => $periodInvoices->sum('total_amount'),
                'total_sales_formatted' => $this->formatCurrency($periodInvoices->sum('total_amount')),
                'invoice_count' => $periodInvoices->count(),
                'average_ticket' => $periodInvoices->avg('total_amount'),
                'average_ticket_formatted' => $this->formatCurrency($periodInvoices->avg('total_amount')),
            ];
        })->values()->toArray();
    }

    protected function getPaymentMethodStats(Collection $invoices): array
    {
        $paymentStats = $invoices->groupBy('payment_method')->map(function ($methodInvoices, $method) {
            return [
                'payment_method' => $method ?: 'No especificado',
                'total_sales' => $methodInvoices->sum('total_amount'),
                'total_sales_formatted' => $this->formatCurrency($methodInvoices->sum('total_amount')),
                'invoice_count' => $methodInvoices->count(),
                'percentage' => 0, // Will be calculated below
            ];
        });

        $totalSales = $paymentStats->sum('total_sales');

        return $paymentStats->map(function ($stats) use ($totalSales) {
            $stats['percentage'] = $totalSales > 0 ? round(($stats['total_sales'] / $totalSales) * 100, 1) : 0;
            $stats['percentage_formatted'] = $stats['percentage'] . '%';
            return $stats;
        })->sortByDesc('total_sales')->values()->toArray();
    }

    protected function getChartData(Collection $invoices): array
    {
        // Sales by period chart
        $salesByPeriod = $this->getSalesByPeriod($invoices);
        $salesChart = $this->buildChartData(
            collect($salesByPeriod),
            'period_formatted',
            'total_sales',
            ['type' => 'line']
        );

        // Top customers chart
        $topCustomers = collect($this->getTopCustomers($invoices))->take(5);
        $customersChart = $this->buildChartData(
            $topCustomers,
            'customer_name',
            'total_sales',
            ['type' => 'bar']
        );

        // Payment methods chart
        $paymentMethods = collect($this->getPaymentMethodStats($invoices));
        $paymentChart = $this->buildChartData(
            $paymentMethods,
            'payment_method',
            'total_sales',
            ['type' => 'pie']
        );

        return [
            'sales_trend' => $salesChart,
            'top_customers' => $customersChart,
            'payment_methods' => $paymentChart,
        ];
    }

    protected function formatPeriod(string $period): string
    {
        // Try to parse as date first
        try {
            $date = \Carbon\Carbon::parse($period);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            // If it's not a date, return as is
            return $period;
        }
    }
}