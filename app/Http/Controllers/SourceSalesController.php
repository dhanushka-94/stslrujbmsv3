<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SourceSalesController extends Controller
{
    private const SOURCE_CONNECTION = 'source';

    private const PER_PAGE = 50;

    /**
     * Paginated list of all POS sales (sma_sales) from the read-only source DB, with optional search.
     * No filters on payment status, POS flag, or date — full catalog for inspection.
     */
    public function index(Request $request): View
    {
        $conn = self::SOURCE_CONNECTION;
        $db = config("database.connections.{$conn}.database");

        if (empty($db)) {
            return view('settings.pos-sales', [
                'sales' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1),
                'error' => 'Source database not configured. Set DB_SOURCE_DATABASE in .env.',
                'q' => '',
            ]);
        }

        $q = trim((string) $request->input('q', ''));

        try {
            $scoped = function () use ($conn, $q) {
                $query = DB::connection($conn)->table('sma_sales');

                if ($q !== '') {
                    $query->where(function ($w) use ($q) {
                        $like = '%'.$q.'%';
                        $w->where('reference_no', 'like', $like)
                            ->orWhere('customer', 'like', $like)
                            ->orWhere('biller', 'like', $like)
                            ->orWhere('note', 'like', $like)
                            ->orWhere('staff_note', 'like', $like)
                            ->orWhere('sale_status', 'like', $like)
                            ->orWhere('payment_status', 'like', $like)
                            ->orWhere('payment_method', 'like', $like);

                        if (ctype_digit($q)) {
                            $w->orWhere('id', (int) $q);
                        }
                    });
                }

                return $query;
            };

            $total = $scoped()->count();

            $page = max(1, (int) $request->input('page', 1));
            $items = $scoped()
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->forPage($page, self::PER_PAGE)
                ->get([
                    'id',
                    'date',
                    'reference_no',
                    'customer',
                    'biller',
                    'grand_total',
                    'paid',
                    'sale_status',
                    'payment_status',
                    'payment_method',
                    'due_date',
                    'pos',
                    'note',
                ]);

            $sales = new LengthAwarePaginator(
                $items,
                $total,
                self::PER_PAGE,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $q !== '' ? ['q' => $q] : [],
                ]
            );
        } catch (\Throwable $e) {
            return view('settings.pos-sales', [
                'sales' => new LengthAwarePaginator([], 0, self::PER_PAGE, 1),
                'error' => $e->getMessage(),
                'q' => $q,
            ]);
        }

        return view('settings.pos-sales', [
            'sales' => $sales,
            'error' => null,
            'q' => $q,
        ]);
    }
}
