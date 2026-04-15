<?php

namespace App\Services\Subscriptions;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionService
{
    public function paginateSubscriptions(array $filters = []): LengthAwarePaginator
    {
        $statusFilter = $filters['status_filter'] ?? 'active';
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 5), 100);

        $query = Subscription::query()
            ->with([
                'vehicle:id,immatriculation,marque,model',
                'user:id,nom,prenom,phone',
                'plan:id,label,duration_months,price,currency',
                'payment:id,subscription_id,recorded_by,method,status,paid_at',
                'payment.recorder:id,nom,prenom',
            ]);

        if ($statusFilter === 'active') {
            $query->activeNow();
        } elseif ($statusFilter === 'inactive') {
            $query->inactiveNow();
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('user', function (Builder $uq) use ($search) {
                    $uq->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(COALESCE(prenom,''), ' ', COALESCE(nom,'')) like ?", ["%{$search}%"])
                        ->orWhere('phone', 'like', "%{$search}%");
                })->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                    $vq->where('immatriculation', 'like', "%{$search}%")
                        ->orWhere('marque', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('voiture_unique_id', 'like', "%{$search}%");
                });
            });
        }

        return $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getEnabledPlans()
    {
        return SubscriptionPlan::query()
            ->enabled()
            ->where('billing_mode', 'MONTH')
            ->orderBy('duration_months')
            ->get();
    }

    public function searchEligibleVehicles(string $term, int $limit = 10)
    {
        $term = trim($term);

        return Voiture::query()
            ->with(['utilisateur:id,nom,prenom,phone'])
            ->whereDoesntHave('subscriptions', function (Builder $q) {
                $q->activeNow();
            })
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $q) use ($term) {
                    $q->where('immatriculation', 'like', "%{$term}%")
                        ->orWhere('marque', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('voiture_unique_id', 'like', "%{$term}%")
                        ->orWhereHas('utilisateur', function (Builder $uq) use ($term) {
                            $uq->where('nom', 'like', "%{$term}%")
                                ->orWhere('prenom', 'like', "%{$term}%")
                                ->orWhereRaw("CONCAT(COALESCE(prenom,''), ' ', COALESCE(nom,'')) like ?", ["%{$term}%"])
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy('immatriculation')
            ->limit($limit)
            ->get()
            ->map(function (Voiture $vehicle) {
                $owner = $vehicle->utilisateur->first();

                return [
                    'id' => $vehicle->id,
                    'label' => trim(($owner?->prenom ?? '') . ' ' . ($owner?->nom ?? '')) . ' — ' . $vehicle->immatriculation,
                    'vehicle' => [
                        'id' => $vehicle->id,
                        'immatriculation' => $vehicle->immatriculation,
                        'marque' => $vehicle->marque,
                        'model' => $vehicle->model,
                        'voiture_unique_id' => $vehicle->voiture_unique_id,
                    ],
                    'owner' => $owner ? [
                        'id' => $owner->id,
                        'nom' => $owner->nom,
                        'prenom' => $owner->prenom,
                        'phone' => $owner->phone,
                    ] : null,
                ];
            })
            ->values();
    }

   public function createCashSubscription(array $data, int $recordedByEmployeId): Subscription
{
    return DB::transaction(function () use ($data, $recordedByEmployeId) {
        /** @var Voiture $vehicle */
        $vehicle = Voiture::query()
            ->with(['utilisateur:id,nom,prenom,phone'])
            ->lockForUpdate()
            ->findOrFail($data['vehicle_id']);

        if ($vehicle->subscriptions()->activeNow()->exists()) {
            throw new RuntimeException('Ce véhicule possède déjà un abonnement actif.');
        }

        /** @var SubscriptionPlan $plan */
        $plan = SubscriptionPlan::query()
            ->enabled()
            ->where('billing_mode', 'MONTH')
            ->findOrFail($data['plan_id']);

        $owner = $vehicle->utilisateur->first();

        if (!$owner) {
            throw new RuntimeException('Ce véhicule n’est rattaché à aucun utilisateur.');
        }

        $paidAt = !empty($data['paid_at'])
            ? Carbon::parse($data['paid_at'])
            : now();

        $startDate = $paidAt->copy()->startOfMinute();
        $endDate = $startDate->copy()->addMonthsNoOverflow((int) $plan->duration_months);

        $payment = Payment::create([
            'user_id' => $owner->id,
            'recorded_by' => $recordedByEmployeId,
            'vehicle_id' => $vehicle->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency ?? 'XAF',
            'method' => 'CASH',
            'provider' => null,
            'phone_number' => null,
            'transaction_ref' => $this->generateCashReference(),
            'transaction_id' => null,
            'status' => 'SUCCESS',
            'paid_at' => $paidAt,
            'notes' => $data['notes'] ?? null,
        ]);

        $subscription = Subscription::create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'ACTIVE',
            'auto_renew' => false,
        ]);

        $payment->update([
            'subscription_id' => $subscription->id,
        ]);

        return $subscription->load([
            'vehicle:id,immatriculation,marque,model',
            'user:id,nom,prenom,phone',
            'plan:id,label,duration_months,price,currency',
            'payment:id,subscription_id,recorded_by,method,status,paid_at',
            'payment.recorder:id,nom,prenom',
        ]);
    });
}
    private function generateCashReference(): string
    {
        return 'CASH-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
    }
}