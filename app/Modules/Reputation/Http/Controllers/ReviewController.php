<?php

namespace App\Modules\Reputation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Reputation\Actions\ReputationService;
use App\Modules\Reputation\Http\Requests\ReplyRequest;
use App\Modules\Reputation\Http\Requests\StoreReviewRequest;
use App\Modules\Reputation\Http\Resources\ReviewResource;
use App\Modules\Reputation\Http\Resources\VendorProfileResource;
use App\Modules\Reputation\Models\Review;
use App\Modules\Reputation\Models\VendorFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private readonly ReputationService $service) {}

    /** List reviews of a product (public). Paginated, newest first. */
    public function productReviews(Request $request, Product $product): AnonymousResourceCollection
    {
        abort_unless($product->status === Product::STATUS_ACTIVE, 404);

        return ReviewResource::collection(
            Review::query()->where('product_id', $product->id)->with('buyer:id,handle')
                ->latest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /** List reviews received by a vendor (public). Paginated, newest first. */
    public function vendorReviews(Request $request, User $vendor): AnonymousResourceCollection
    {
        abort_unless($vendor->isVendor(), 404);

        return ReviewResource::collection(
            Review::query()->where('vendor_id', $vendor->id)->with('buyer:id,handle')
                ->latest('id')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    /** Public vendor profile with rating average, rating count and follower count. */
    public function vendor(User $vendor): VendorProfileResource
    {
        abort_unless($vendor->isVendor(), 404);

        $vendor->setAttribute('followers_count', VendorFollow::query()->where('vendor_id', $vendor->id)->count());

        return new VendorProfileResource($vendor);
    }

    /**
     * Review a purchased product. Requires `orders:write`.
     *
     * Only the buyer of a completed order containing the product may review it, once.
     * Rating is an integer 1-5; `body` is optional.
     */
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $order = Order::query()->findOrFail($request->integer('order_id'));
        $this->authorize('createFromOrder', [Review::class, $order]);

        $review = $this->service->createReview(
            $request->user(), $order, $product, $request->integer('rating'), $request->input('body')
        );

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }

    /** Vendor posts one public reply to a review of their product. Requires `vendor:manage`. */
    public function reply(ReplyRequest $request, Review $review): ReviewResource
    {
        $this->authorize('reply', $review);

        return new ReviewResource($this->service->reply($review, $request->string('reply')->value()));
    }

    /** Mark a review helpful (one vote per user, not your own review). Idempotent. */
    public function vote(Request $request, Review $review): ReviewResource
    {
        $this->authorize('vote', $review);

        return new ReviewResource($this->service->vote($request->user(), $review));
    }

    /** Remove your helpful vote. */
    public function unvote(Request $request, Review $review): ReviewResource
    {
        return new ReviewResource($this->service->unvote($request->user(), $review));
    }

    /**
     * Vendors you follow. Requires `profile`.
     *
     * Newest follow first. Paginated.
     */
    public function following(Request $request): AnonymousResourceCollection
    {
        $vendors = User::query()
            ->join('vendor_follows', 'vendor_follows.vendor_id', '=', 'users.id')
            ->where('vendor_follows.follower_id', $request->user()->id)
            ->orderByDesc('vendor_follows.id')
            ->select('users.*')
            ->paginate(min($request->integer('per_page', 50), 100));

        return VendorProfileResource::collection($vendors);
    }

    /** Follow a vendor. Idempotent. */
    public function follow(Request $request, User $vendor): JsonResponse
    {
        abort_unless($vendor->isVendor(), 404);
        $this->service->follow($request->user(), $vendor);

        return response()->json(['following' => true]);
    }

    /** Unfollow a vendor. */
    public function unfollow(Request $request, User $vendor): JsonResponse
    {
        abort_unless($vendor->isVendor(), 404);
        $this->service->unfollow($request->user(), $vendor);

        return response()->json(['following' => false]);
    }
}
