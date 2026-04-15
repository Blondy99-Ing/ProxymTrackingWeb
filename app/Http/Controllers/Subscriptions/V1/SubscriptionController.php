<?php

namespace App\Http\Controllers\Subscriptions\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\StoreCashSubscriptionRequest;
use App\Http\Requests\Subscriptions\SubscriptionIndexRequest;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service
    ) {
    }

     public function index(SubscriptionIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('subscriptions.v1.index', [
            'filters' => [
                'status_filter' => $filters['status_filter'] ?? 'active',
                'search' => $filters['search'] ?? '',
                'per_page' => $filters['per_page'] ?? 15,
            ],
            'subscriptions' => $this->service->paginateSubscriptions($filters),
            'plans' => $this->service->getEnabledPlans(),
        ]);
    }

    public function eligibleVehicles(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json([
            'data' => $this->service->searchEligibleVehicles($term),
        ]);
    }

public function storeCash(StoreCashSubscriptionRequest $request): RedirectResponse
{
    try {
        $employeId = (int) $request->user()->id;

        $subscription = $this->service->createCashSubscription(
            $request->validated(),
            $employeId
        );

        return redirect()
            ->route('subscriptions.v1.index')
            ->with('success', 'Paiement cash enregistré et abonnement activé pour '.$subscription->vehicle?->immatriculation.'.');
    } catch (RuntimeException $e) {
        return back()
            ->withInput()
            ->with('error', $e->getMessage());
    }
}

}