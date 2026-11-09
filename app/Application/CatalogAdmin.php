<?php

namespace App\Application;

use App\Domain\RuleViolation;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CatalogAdmin
{
    /** @param array<string, mixed> $input */
    public function product(User $actor, array $input, ?Product $record): void
    {
        Gate::forUser($actor)->authorize('administer');
        $data = Validator::validate($input, [
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'name' => 'required|string|max:160',
            'slug' => ['required', 'alpha_dash:ascii', 'max:160', Rule::unique('products')->ignore($record?->id)],
            'kind' => 'required|in:single,bundle',
            'description' => 'required|string|max:2500',
            'price' => 'required|integer|between:1,100000000',
            'valid_days' => 'required|integer|between:1,365',
            'listed' => 'required|boolean',
            'venue_ids' => 'required|array|min:1|max:20',
            'venue_ids.*' => 'required|integer|distinct|exists:venues,id',
            'available' => $record === null ? 'required|integer|between:0,1000000' : 'prohibited',
        ]);
        if ($data['kind'] === 'single' ? count($data['venue_ids']) !== 1 : count($data['venue_ids']) < 2) {
            throw new RuleViolation('venue_count', '單館票須選一個場館，聯票至少選兩個。', 422);
        }

        DB::transaction(function () use ($actor, $record, $data): void {
            $product = $record === null ? new Product : Product::query()->lockForUpdate()->findOrFail($record->id);
            $product->fill(collect($data)->except(['venue_ids', 'available'])->all())->save();
            $product->venues()->sync($data['venue_ids']);
            if ($record === null) {
                $product->inventory()->create(['available' => $data['available']]);
            }
            Audit::record('catalog.product_saved', $actor->id, null, ['product_id' => $product->id]);
        }, 5);
    }

    public function adjustStock(User $actor, Product $product, int $delta): void
    {
        Gate::forUser($actor)->authorize('administer');
        DB::transaction(function () use ($actor, $product, $delta): void {
            Product::query()->lockForUpdate()->findOrFail($product->id);
            $stock = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $available = $stock->available + $delta;
            if ($delta === 0 || $available < 0 || $available > 1000000) {
                throw new RuleViolation('invalid_stock', '調整量不可為零，且可售庫存須介於 0 到 1,000,000。', 422);
            }
            $stock->update(['available' => $available]);
            Audit::record('catalog.stock_adjusted', $actor->id, null, ['product_id' => $product->id, 'delta' => $delta]);
        }, 5);
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, Supplier|Venue $record, array $data): void
    {
        Gate::forUser($actor)->authorize('administer');
        DB::transaction(function () use ($actor, $record, $data): void {
            $record->fill($data)->save();
            Audit::record('catalog.'.$record->getTable().'_saved', $actor->id, null, ['id' => $record->id]);
        }, 5);
    }

    /** @param array<string, mixed> $data */
    public function user(User $actor, array $data, ?User $record): void
    {
        Gate::forUser($actor)->authorize('administer');
        if ($record?->id === $actor->id) {
            throw new RuleViolation('self_role', '請由另一位管理員修改你的帳號。', 403);
        }

        $data = Validator::validate($data, [
            'name' => 'required|string|max:160',
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($record?->id)],
            'password' => [$record === null ? 'required' : 'nullable', 'string', 'min:12', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('密碼長度不得超過 72 bytes。');
                }
            }],
            'role' => 'required|in:admin,operator,merchant,customer',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'integer|distinct|exists:venues,id',
        ]);

        DB::transaction(function () use ($actor, $data, $record): void {
            $user = $record === null ? new User : User::query()->lockForUpdate()->findOrFail($record->id);
            $user->fill(collect($data)->only(['name', 'email', 'role'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->venues()->sync($user->role === 'merchant' ? ($data['venue_ids'] ?? []) : []);
            Audit::record('access.user_saved', $actor->id, null, ['target_user_id' => $user->id, 'role' => $user->role]);
        }, 5);
    }
}
