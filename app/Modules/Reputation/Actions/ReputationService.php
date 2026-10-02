<?php

namespace App\Modules\Reputation\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Reputation\Exceptions\ReputationException;
use App\Modules\Reputation\Models\Review;
use App\Modules\Reputation\Models\ReviewHelpfulVote;
use App\Modules\Reputation\Models\VendorFollow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReputationService
{
    /** Buyer reviews a product from one of their COMPLETED orders, once. */
    public function createReview(User $buyer, Order $order, Product $product, int $rating, ?string $body): Review
    {
        return DB::transaction(function () use ($buyer, $order, $product, $rating, $body) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== Order::STATUS_COMPLETED) {
                throw new ReputationException('Only products from completed orders can be reviewed.', 409);
            }
            if (! $order->items()->where('product_id', $product->id)->exists()) {
                throw new ReputationException('This product is not part of that order.', 422);
            }
            if (Review::query()->where('product_id', $product->id)->where('buyer_id', $buyer->id)->exists()) {
                throw new ReputationException('You have already reviewed this product.', 409);
            }

            $review = new Review();
            $review->product_id = $product->id;
            $review->order_id = $order->id;
            $review->buyer_id = $buyer->id;
            $review->vendor_id = $order->vendor_id;
            $review->rating = $rating;
            $review->body = $body;
            try {
                $review->save();
            } catch (QueryException) {
                throw new ReputationException('You have already reviewed this product.', 409);
            }

            $this->refreshAggregates($product->id, $order->vendor_id);

            return $review->refresh();
        });
    }

    /** Vendor posts their single public reply. */
    public function reply(Review $review, string $reply): Review
    {
        return DB::transaction(function () use ($review, $reply) {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            if ($review->vendor_reply !== null) {
                throw new ReputationException('This review already has a reply.', 409);
            }
            $review->vendor_reply = $reply;
            $review->vendor_replied_at = now();
            $review->save();

            return $review;
        });
    }

    public function vote(User $user, Review $review): Review
    {
        return DB::transaction(function () use ($user, $review) {
            $vote = ReviewHelpfulVote::query()->firstOrCreate(['review_id' => $review->id, 'user_id' => $user->id]);
            if ($vote->wasRecentlyCreated) {
                $review->increment('helpful_count');
            }

            return $review->refresh();
        });
    }

    public function unvote(User $user, Review $review): Review
    {
        return DB::transaction(function () use ($user, $review) {
            if (ReviewHelpfulVote::query()->where('review_id', $review->id)->where('user_id', $user->id)->delete()) {
                $review->decrement('helpful_count');
            }

            return $review->refresh();
        });
    }

    public function follow(User $user, User $vendor): void
    {
        if ($user->id === $vendor->id) {
            throw new ReputationException('You cannot follow yourself.', 422);
        }
        VendorFollow::query()->firstOrCreate(['follower_id' => $user->id, 'vendor_id' => $vendor->id]);
    }

    public function unfollow(User $user, User $vendor): void
    {
        VendorFollow::query()->where('follower_id', $user->id)->where('vendor_id', $vendor->id)->delete();
    }

    /** Recompute denormalised avg/count for the product and its vendor. */
    private function refreshAggregates(int $productId, int $vendorId): void
    {
        foreach ([['products', 'product_id', $productId], ['users', 'vendor_id', $vendorId]] as [$table, $col, $id]) {
            $agg = Review::query()->where($col, $id)->selectRaw('AVG(rating) as a, COUNT(*) as c')->first();
            DB::table($table)->where('id', $id)->update([
                'rating_avg' => $agg->c ? round((float) $agg->a, 2) : null,
                'rating_count' => (int) $agg->c,
            ]);
        }
    }
}
