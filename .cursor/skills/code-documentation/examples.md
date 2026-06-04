# Documentation Examples

Contoh lengkap dokumentasi untuk berbagai jenis file dalam project.

## Laravel Service Class

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ProductionMetricsDTO;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Order Service
|--------------------------------------------------------------------------
|
| Service layer untuk mengelola business logic terkait Order produksi
| Pita Cukai, yang mencakup: CRUD operations, status transitions,
| dan kalkulasi production metrics.
|
| Dependencies:
| - OrderRepositoryInterface untuk data access layer
| - Cache untuk menyimpan hasil kalkulasi metrics
|
| Usage:
|   $service = app(OrderService::class);
|   $metrics = $service->calculateMetrics($order);
|
*/
class OrderService
{
    /**
     * Cache key prefix untuk production metrics.
     */
    private const METRICS_CACHE_PREFIX = 'order_metrics_';

    /**
     * Duration cache dalam detik (5 menit).
     * Balance antara data freshness dan performance.
     */
    private const CACHE_TTL = 300;

    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Menghitung production metrics untuk order tertentu.
     *
     * Kalkulasi metrics yang mencakup:
     * - Perfect prints (cetak sempurna setelah dikurangi damaged)
     * - Defect rate (persentase HCTS terhadap rencana cetak)
     * - Remaining order (sisa yang perlu diproduksi)
     * - Completion percentage (persentase penyelesaian)
     *
     * @param Order $order Order yang akan dihitung metricsnya
     * @param bool $useCache Gunakan cache untuk hasil kalkulasi (default: true)
     * @return ProductionMetricsDTO Data metrics dalam bentuk DTO
     *
     * @throws InvalidOrderStateException Jika order dalam status cancelled
     *
     * @example
     *   $metrics = $orderService->calculateMetrics($order);
     *   echo $metrics->defectRate; // 2.5 (dalam persen)
     */
    public function calculateMetrics(Order $order, bool $useCache = true): ProductionMetricsDTO
    {
        // Validasi state - cancelled orders tidak bisa dihitung metricsnya
        // karena data produksi sudah tidak relevan
        if ($order->status === OrderStatus::Cancelled) {
            throw new InvalidOrderStateException(
                "Cannot calculate metrics for cancelled order: {$order->po_number}"
            );
        }

        $cacheKey = self::METRICS_CACHE_PREFIX . $order->id;

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Perfect Prints = HCS Verif - HCS Sisa (yang rusak setelah verifikasi)
        $perfectPrints = $order->good_verified - $order->good_damaged;

        // Defect Rate = (HCTS Verif / Rencana Cetak) * 100
        // Guard against division by zero untuk order yang belum ada rencana
        $defectRate = $order->planned_print_quantity > 0
            ? ($order->defect_verified / $order->planned_print_quantity) * 100
            : 0;

        // Remaining = Order Qty - Perfect Prints
        $remainingOrder = $order->order_quantity - $perfectPrints;

        // Completion = (Perfect Prints / Order Qty) * 100
        $completionPercentage = $order->order_quantity > 0
            ? ($perfectPrints / $order->order_quantity) * 100
            : 0;

        $metrics = new ProductionMetricsDTO(
            perfectPrints: $perfectPrints,
            defectRate: round($defectRate, 2),
            remainingOrder: max(0, $remainingOrder), // Tidak boleh negatif
            completionPercentage: min(100, round($completionPercentage, 2))
        );

        if ($useCache) {
            Cache::put($cacheKey, $metrics, self::CACHE_TTL);
        }

        return $metrics;
    }

    /**
     * Transition status order ke tahap berikutnya dalam production pipeline.
     *
     * Valid transitions:
     * - pending -> printing
     * - printing -> verifying
     * - verifying -> packaging
     * - packaging -> shipping
     * - shipping -> completed
     *
     * @param Order $order Order yang akan di-transition
     * @return Order Order dengan status baru
     *
     * @throws InvalidOrderStateException Jika transition tidak valid
     */
    public function transitionToNextStatus(Order $order): Order
    {
        $nextStatus = $this->getNextStatus($order->status);

        if ($nextStatus === null) {
            throw new InvalidOrderStateException(
                "Order {$order->po_number} sudah dalam status final: {$order->status->value}"
            );
        }

        return $this->orderRepository->updateStatus($order, $nextStatus);
    }

    /**
     * Mendapatkan status berikutnya dalam production pipeline.
     *
     * @param OrderStatus $currentStatus Status saat ini
     * @return OrderStatus|null Status berikutnya atau null jika sudah final
     */
    private function getNextStatus(OrderStatus $currentStatus): ?OrderStatus
    {
        // Mapping status transitions sesuai production workflow
        // Flow didesain berdasarkan proses actual di lantai produksi
        return match ($currentStatus) {
            OrderStatus::Pending => OrderStatus::Printing,
            OrderStatus::Printing => OrderStatus::Verifying,
            OrderStatus::Verifying => OrderStatus::Packaging,
            OrderStatus::Packaging => OrderStatus::Shipping,
            OrderStatus::Shipping => OrderStatus::Completed,
            OrderStatus::Completed, OrderStatus::Cancelled => null,
        };
    }
}
```

## Laravel Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/*
|--------------------------------------------------------------------------
| Order Model
|--------------------------------------------------------------------------
|
| Eloquent model yang merepresentasikan order produksi Pita Cukai.
| Order merupakan unit tracking utama dalam sistem Sirine, dimana
| setiap order melalui lifecycle produksi hingga pengiriman.
|
| Product Types:
| - PCHT: Pita Cukai Hasil Tembakau (rokok, cerutu)
| - MMEA: Minuman Mengandung Etil Alkohol
|
| Status Flow:
| pending -> printing -> verifying -> packaging -> shipping -> completed
|
| Relationships:
| - belongsTo: Machine (mesin produksi)
| - hasOne: OrderSpecification (detail spesifikasi produk)
| - hasMany: DefectRecords (catatan HCTS per order)
| - hasMany: Verifications (data verifikasi harian)
|
| Key Attributes:
| - po_number: Nomor PO unik (primary business key)
| - obc_number: Nomor dari sistem eksternal DJBC
| - product_type: PCHT atau MMEA
|
*/
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'obc_number',
        'product_type',
        'product_variant',
        'order_date',
        'due_date',
        'order_quantity',
        'planned_print_quantity',
        'status',
        'machine_id',
    ];

    /**
     * Attribute casting untuk proper type handling.
     * Menggunakan PHP Enums untuk type safety pada status dan product_type.
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'due_date' => 'date',
            'order_quantity' => 'integer',
            'planned_print_quantity' => 'integer',
            'printed_quantity' => 'integer',
            'good_verified' => 'integer',
            'defect_verified' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Mesin produksi yang digunakan untuk mencetak order ini.
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Detail spesifikasi produk (warna, pabrik, tarif, dll).
     * One-to-one karena setiap order memiliki satu set spesifikasi unik.
     */
    public function specification(): HasOne
    {
        return $this->hasOne(OrderSpecification::class);
    }

    /**
     * Catatan defect (HCTS) yang ditemukan selama inspeksi.
     * Satu order bisa memiliki multiple defect records jika ada re-inspeksi.
     */
    public function defectRecords(): HasMany
    {
        return $this->hasMany(DefectRecord::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope untuk filter order berdasarkan product type.
     *
     * @param Builder $query
     * @param ProductType $type Product type yang diinginkan
     * @return Builder
     */
    public function scopeOfType($query, ProductType $type)
    {
        return $query->where('product_type', $type);
    }

    /**
     * Scope untuk order yang masih dalam proses (belum completed/cancelled).
     */
    public function scopeInProgress($query)
    {
        return $query->whereNotIn('status', [
            OrderStatus::Completed,
            OrderStatus::Cancelled,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Hitung perfect prints (cetak sempurna) untuk order ini.
     * Formula: Good Verified - Good Damaged
     */
    public function getPerfectPrintsAttribute(): int
    {
        return $this->good_verified - $this->good_damaged;
    }

    /**
     * Hitung defect rate (persentase HCTS) untuk order ini.
     * Formula: (Defect Verified / Planned Print Qty) * 100
     */
    public function getDefectRateAttribute(): float
    {
        if ($this->planned_print_quantity === 0) {
            return 0;
        }

        return round(
            ($this->defect_verified / $this->planned_print_quantity) * 100,
            2
        );
    }
}
```

## Vue Component

```vue
<script setup lang="ts">
/**
 * OrderTable - Data table untuk menampilkan daftar order produksi.
 *
 * Komponen ini menyediakan interface untuk viewing dan interacting
 * dengan data order, yang mencakup: filtering, sorting, pagination,
 * dan row selection untuk bulk actions.
 *
 * Features:
 * - Virtual scrolling untuk handling large datasets (>1000 rows)
 * - Column-based sorting dengan visual indicator
 * - Multi-filter: status, product type, date range
 * - Row selection dengan checkbox untuk bulk operations
 * - Responsive design dengan horizontal scroll di mobile
 *
 * @example Basic usage
 * <OrderTable
 *   :orders="orders"
 *   :loading="isLoading"
 *   @row-click="handleRowClick"
 * />
 *
 * @example With selection
 * <OrderTable
 *   :orders="orders"
 *   selectable
 *   @selection-change="handleSelection"
 * />
 */

import { computed, ref } from 'vue'
import { useFormatters } from '@/Composables/useFormatters'
import DataTable from '@/Components/UI/DataTable.vue'
import Badge from '@/Components/UI/Badge.vue'
import type { Order, OrderStatus, SortDirection } from '@/Types/models'

/**
 * Props yang diterima komponen.
 */
interface Props {
  /** Array order yang akan ditampilkan dalam table */
  orders: Order[]
  /** State loading - menampilkan skeleton saat true */
  loading?: boolean
  /** Enable row selection dengan checkbox */
  selectable?: boolean
  /** Kolom yang bisa di-sort (default: semua kolom sortable) */
  sortableColumns?: Array<keyof Order>
  /** Jumlah rows per page untuk pagination */
  pageSize?: number
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  selectable: false,
  sortableColumns: () => ['po_number', 'order_date', 'status', 'order_quantity'],
  pageSize: 20,
})

/**
 * Events yang di-emit komponen.
 */
const emit = defineEmits<{
  /** Emit ketika row diklik - payload berisi order yang diklik */
  'row-click': [order: Order]
  /** Emit ketika selection berubah - payload berisi array selected orders */
  'selection-change': [orders: Order[]]
  /** Emit ketika sorting berubah */
  'sort-change': [column: keyof Order, direction: SortDirection]
}>()

// Composables
const { formatCurrency, formatDate } = useFormatters()

// Internal state untuk sorting
const sortColumn = ref<keyof Order>('order_date')
const sortDirection = ref<SortDirection>('desc')

// Selected rows untuk bulk actions
const selectedOrders = ref<Order[]>([])

/**
 * Computed property untuk data yang sudah di-sort.
 * Sorting dilakukan di client-side untuk responsiveness,
 * dengan fallback ke server-side jika data > 1000 rows.
 */
const sortedOrders = computed(() => {
  // Untuk large datasets, hindari sorting di client
  // dan emit event untuk server-side sorting
  if (props.orders.length > 1000) {
    return props.orders
  }

  return [...props.orders].sort((a, b) => {
    const aVal = a[sortColumn.value]
    const bVal = b[sortColumn.value]

    // Handle null/undefined values
    if (aVal == null) return sortDirection.value === 'asc' ? 1 : -1
    if (bVal == null) return sortDirection.value === 'asc' ? -1 : 1

    // Compare based on type
    const comparison = aVal > bVal ? 1 : aVal < bVal ? -1 : 0
    return sortDirection.value === 'asc' ? comparison : -comparison
  })
})

/**
 * Handle column header click untuk sorting.
 *
 * @param column - Kolom yang diklik
 */
function handleSort(column: keyof Order): void {
  if (!props.sortableColumns.includes(column)) {
    return
  }

  // Toggle direction jika kolom yang sama, otherwise reset ke desc
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDirection.value = 'desc'
  }

  emit('sort-change', column, sortDirection.value)
}

/**
 * Handle row click event.
 *
 * @param order - Order yang diklik
 */
function handleRowClick(order: Order): void {
  emit('row-click', order)
}

/**
 * Handle checkbox selection change.
 *
 * @param order - Order yang di-select/unselect
 * @param selected - State selection baru
 */
function handleSelectionChange(order: Order, selected: boolean): void {
  if (selected) {
    selectedOrders.value.push(order)
  } else {
    selectedOrders.value = selectedOrders.value.filter((o) => o.id !== order.id)
  }

  emit('selection-change', selectedOrders.value)
}

/**
 * Get badge variant berdasarkan order status.
 * Mapping color untuk visual distinction antar status.
 *
 * @param status - Status order
 * @returns Badge variant string
 */
function getStatusBadgeVariant(
  status: OrderStatus
): 'default' | 'success' | 'warning' | 'danger' {
  // Mapping status ke badge color untuk visual clarity
  // di production monitoring dashboard
  const variantMap: Record<OrderStatus, 'default' | 'success' | 'warning' | 'danger'> = {
    pending: 'default',
    printing: 'warning',
    verifying: 'warning',
    packaging: 'warning',
    shipping: 'warning',
    completed: 'success',
    cancelled: 'danger',
  }

  return variantMap[status] ?? 'default'
}
</script>

<template>
  <DataTable
    :loading="loading"
    :columns="columns"
    :data="sortedOrders"
    :selectable="selectable"
    @row-click="handleRowClick"
  >
    <!-- Status column dengan badge -->
    <template #cell-status="{ value }">
      <Badge :variant="getStatusBadgeVariant(value)">
        {{ value }}
      </Badge>
    </template>

    <!-- Order quantity dengan formatting -->
    <template #cell-order_quantity="{ value }">
      {{ formatCurrency(value, false) }}
    </template>

    <!-- Date formatting -->
    <template #cell-order_date="{ value }">
      {{ formatDate(value) }}
    </template>
  </DataTable>
</template>
```

## TypeScript Types

```typescript
/**
 * @file Order-related type definitions untuk Sirine system.
 *
 * File ini berisi semua type definitions yang terkait dengan
 * Order domain, yang mencakup: Order entity, status enum,
 * dan related interfaces.
 */

/**
 * Status order dalam production pipeline.
 *
 * Flow: pending -> printing -> verifying -> packaging -> shipping -> completed
 *
 * Special status:
 * - cancelled: Order dibatalkan, tidak bisa di-process lagi
 */
export type OrderStatus =
  | 'pending'
  | 'printing'
  | 'verifying'
  | 'packaging'
  | 'shipping'
  | 'completed'
  | 'cancelled'

/**
 * Tipe produk Pita Cukai.
 *
 * - PCHT: Pita Cukai Hasil Tembakau (rokok, cerutu, dll)
 * - MMEA: Minuman Mengandung Etil Alkohol
 */
export type ProductType = 'PCHT' | 'MMEA'

/**
 * Order entity - unit utama tracking produksi.
 *
 * Order merepresentasikan satu batch produksi pita cukai
 * yang di-track dari pemesanan hingga pengiriman.
 */
export interface Order {
  /** Database ID */
  id: number

  /** Nomor PO - unique business identifier */
  po_number: number

  /** Nomor OBC dari sistem DJBC */
  obc_number: string

  /** Tipe produk (PCHT/MMEA) */
  product_type: ProductType

  /** Varian produk (SKM, SPM untuk PCHT) */
  product_variant: string | null

  /** Tanggal order diterima */
  order_date: string

  /** Tanggal jatuh tempo pengiriman */
  due_date: string

  /** Jumlah yang dipesan */
  order_quantity: number

  /** Jumlah rencana cetak */
  planned_print_quantity: number

  /** Jumlah yang sudah dicetak */
  printed_quantity: number

  /** Jumlah hasil verifikasi bagus (HCS) */
  good_verified: number

  /** Jumlah defect dari verifikasi (HCTS) */
  defect_verified: number

  /** Status dalam production pipeline */
  status: OrderStatus

  /** ID mesin produksi */
  machine_id: number | null

  /** Timestamps */
  created_at: string
  updated_at: string
}

/**
 * Production metrics result dari kalkulasi.
 *
 * Metrics ini digunakan untuk dashboard dan reporting
 * performance produksi.
 */
export interface ProductionMetrics {
  /** Jumlah cetak sempurna (HCS - damaged) */
  perfect_prints: number

  /** Persentase defect (HCTS/planned * 100) */
  defect_rate: number

  /** Sisa order yang perlu diproduksi */
  remaining_order: number

  /** Persentase penyelesaian order */
  completion_percentage: number
}

/**
 * Filter options untuk querying orders.
 */
export interface OrderFilters {
  /** Filter by status (multiple allowed) */
  status?: OrderStatus[]

  /** Filter by product type */
  product_type?: ProductType

  /** Date range filter */
  date_from?: string
  date_to?: string

  /** Search by PO or OBC number */
  search?: string
}

/**
 * Sort direction untuk table/list.
 */
export type SortDirection = 'asc' | 'desc'
```

## README untuk Feature Module

```markdown
# Orders Module

Module untuk mengelola order produksi Pita Cukai dalam sistem Sirine.

## Overview

Module ini menangani lifecycle order dari pemesanan hingga pengiriman,
yang mencakup tracking status, kalkulasi metrics, dan reporting.

## Directory Structure

```
Pages/Orders/
├── Index.vue              # List semua orders dengan filter dan pagination
├── Show.vue               # Detail view single order
├── Partials/
│   ├── OrderFilters.vue   # Filter component (status, date, product type)
│   ├── OrderTable.vue     # Data table dengan sorting
│   ├── OrderCard.vue      # Card view untuk mobile
│   ├── MetricsPanel.vue   # Production metrics display
│   └── StatusTimeline.vue # Visual timeline status order
```

## Features

### Order List (Index.vue)
- Filter by: status, product type, date range
- Sort by: PO number, date, status, quantity
- Pagination dengan infinite scroll
- Export ke Excel

### Order Detail (Show.vue)
- Complete order information
- Production metrics dengan charts
- Status history timeline
- Related defect records
- Action buttons (transition status, print, etc.)

## API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/orders` | List orders dengan pagination |
| GET | `/orders/{id}` | Get single order detail |
| POST | `/orders` | Create new order |
| PUT | `/orders/{id}` | Update order |
| POST | `/orders/{id}/transition` | Transition status |

## Props & Events

### OrderTable

```typescript
interface Props {
  orders: Order[]
  loading?: boolean
  selectable?: boolean
}

// Events
@row-click: (order: Order) => void
@selection-change: (orders: Order[]) => void
```

## Usage Example

```vue
<script setup>
import { ref } from 'vue'
import OrderTable from './Partials/OrderTable.vue'

const orders = ref<Order[]>([])
const loading = ref(false)

function handleRowClick(order: Order) {
  router.visit(`/orders/${order.id}`)
}
</script>

<template>
  <OrderTable
    :orders="orders"
    :loading="loading"
    @row-click="handleRowClick"
  />
</template>
```

## Related Modules

- **Verifications** - Data verifikasi per order
- **DefectRecords** - Catatan HCTS per order
- **Reports** - Production reporting berdasarkan order data

## Maintainer

Zulfikar Hidayatullah - +62 857-1583-8733
```
