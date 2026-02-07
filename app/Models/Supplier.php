<?php

namespace App\Models;

use App\Services\FinancialTransactionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'commission',
    ];

    protected $casts = [
        'commission' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function maids(): HasMany
    {
        return $this->hasMany(Maid::class, 'supplier_id');
    }

    public function getTotalCostOfMaid(): float
    {
        $supplierCommission = (float) ($this->commission ?? 0);

        return $this->maids->sum(function ($maid) use ($supplierCommission) {
            $cost = (float) ($maid->cost_of_maid ?? 0);
            $maidCommission = $maid->commission;

            $commission = $maidCommission !== null
                ? (float) $maidCommission
                : $supplierCommission;

            return $cost - $commission;
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($supplier) {
            if ($supplier->isDirty(['commission'])) {
                $maids = $supplier->maids()->whereNull('commission')->get();

                $financialTransactionService = app(FinancialTransactionService::class);

                foreach ($maids as $maid) {
                    $financialTransactionService->updateMaidExpense($maid);
                }
            }
        });

        static::deleting(function ($supplier) {
            $maids = $supplier->maids;

            $financialTransactionService = app(FinancialTransactionService::class);

            foreach ($maids as $maid) {
                $financialTransactionService->updateMaidExpense($maid);
            }
        });
    }
}
