<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    protected function normalizeType(?string $type): string
    {
        $t = strtolower(trim((string) $type));
        if ($t === 'unauthorized') return 'offline';
        return $t !== '' ? $t : 'unknown';
    }

    protected function typeLabel(?string $type): string
    {
        $t = $this->normalizeType($type);

        return match($t) {
            'geofence'      => 'GeoFence',
            'safe_zone'     => 'Safe Zone',
            'speed'         => 'Speeding',
            'engine'        => 'Engine Alert',
            'offline'       => 'Offline',
            'time_zone'     => 'Time Zone',
            'stolen'        => 'Stolen / Theft',
            'low_battery'   => 'Low Battery',
            default         => ucfirst(str_replace('_', ' ', $t)),
        };
    }

    public function index(Request $request)
    {
        // ✅ page HTML
        if (!$request->expectsJson()) {
            return view('alerts.index');
        }

        // ✅ JSON: PLUS RÉCENT EN HAUT (pas de priorité)
        $alerts = Alert::with(['voiture.utilisateur', 'processedBy'])
            ->select('alerts.*')
            ->orderBy('alerted_at', 'desc')
            ->orderBy('id', 'desc') // sécurité si alerted_at identique
            ->get()
            ->map(function (Alert $a) {
                $voiture = $a->voiture;
                $users = collect();

                if ($voiture && $voiture->utilisateur) {
                    $users = $voiture->utilisateur
                        ->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '') .'     ' .  ($u->phone ?? '')))
                        ->filter()
                        ->values();
                }

                $rawType = $a->alert_type ?? $a->type ?? null;
                $type = $this->normalizeType($rawType);

                return [
                    'id' => $a->id,
                    'voiture_id' => $a->voiture_id,

                    // ✅ type normalisé (offline même si base = unauthorized)
                    'type' => $type,
                    'type_label' => $this->typeLabel($type),

                    'message' => $a->message,
                    'location' => $a->location ?? $a->message,

                    'read' => (bool) $a->read,
                    'processed' => (bool) $a->processed,
                    'processed_by' => $a->processed_by,
                    'processed_by_name' => optional($a->processedBy)->name ?? null,

                    'alerted_at_human' => $a->alerted_at
                        ? $a->alerted_at->format('d/m/Y H:i:s')
                        : '-',

                    'voiture' => $voiture ? [
                        'id' => $voiture->id,
                        'immatriculation' => $voiture->immatriculation,
                        'marque' => $voiture->marque,
                        'model' => $voiture->model,
                        'couleur' => $voiture->couleur,
                        'photo' => $voiture->photo,
                    ] : null,

                    'users_labels' => $users->isEmpty() ? null : $users->implode(', '),
                    'user_id' => $voiture?->utilisateur?->first()?->id ?? null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $alerts,
        ]);
    }

    public function receivePolygon(Request $request)
    {
        $polygon = $request->all();
        Log::info('Polygon reçu depuis le frontend : ', $polygon);

        return response()->json([
            'status' => 'success',
            'message' => 'Polygon reçu et logué',
            'polygon_received' => $polygon
        ]);
    }

    public function markAsProcessed(Request $request, $id)
    {
        $data = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $alert = Alert::findOrFail($id);

        $alert->processed = true;
        $alert->processed_by = Auth::id();
        $alert->commentaire = $data['commentaire'] ?? null;
        $alert->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Alerte marquée comme traitée',
            'data' => [
                'id' => $alert->id,
                'processed' => true,
                'processed_by' => $alert->processed_by,
                'commentaire' => $alert->commentaire,
            ]
        ]);
    }
}
