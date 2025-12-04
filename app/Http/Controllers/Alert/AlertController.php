<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    protected function typeLabel(?string $type): string
    {
        if (!$type) return 'Unknown';

        return match($type) {
            'geofence'     => 'GeoFence Breach',
            'safe_zone'    => 'Safe Zone',
            'speed'        => 'Speeding',
            'engine'       => 'Engine Alert',
            'unauthorized' => 'Unauthorized Time',
            default        => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function index()
    {
        $alerts = Alert::with(['voiture.utilisateur'])
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->get()
            ->map(function (Alert $a) {
                $voiture = $a->voiture;
                $users = collect();

                if ($voiture && $voiture->utilisateur) {
                    $users = $voiture->utilisateur
                        ->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                        ->filter()
                        ->values();
                }

                return [
                    'id' => $a->id,
                    'voiture_id' => $a->voiture_id,
                    'type' => $a->type,
                    'type_label' => $this->typeLabel($a->type),
                    'message' => $a->message,
                    'location' => $a->location ?? $a->message,
                    'read' => (bool) $a->read,
                    'processed' => (bool) $a->processed,
                    'processed_by' => $a->processed_by,
                    'alerted_at_human' => $a->alerted_at ? $a->alerted_at->format('d/m/Y H:i:s') : '-',
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
            'data' => $alerts,
        ]);
    }

    /**
     * 🔥 NOUVELLE MÉTHODE POUR LOG LE POLYGON EXACTEMENT REÇU
     */
    public function receivePolygon(Request $request)
    {
        // On récupère tout le JSON envoyé
        $polygon = $request->all();

        // LOG EXACT du polygon
        Log::info('Polygon reçu depuis le frontend : ', $polygon);

        return response()->json([
            'status' => 'success',
            'message' => 'Polygon reçu et logué',
            'polygon_received' => $polygon
        ]);
    }

    public function markAsProcessed(Request $request, $id)
    {
        $alert = Alert::findOrFail($id);

        $alert->processed = true;
        $alert->processed_by = Auth::id();
        $alert->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Alerte marquée comme traitée',
            'data' => [
                'id' => $alert->id,
                'processed' => true,
                'processed_by' => $alert->processed_by,
            ]
        ]);
    }
}
