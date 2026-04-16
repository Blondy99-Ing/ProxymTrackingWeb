<?php

namespace App\Http\Requests\HistoriquePosition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HistoriquePositionFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'vehicle_id'  => ['nullable', 'integer', 'exists:voitures,id'],
            'search'      => ['nullable', 'string', 'max:255'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],

            'mode'        => ['nullable', 'in:exact,range'],
            'view'        => ['nullable', 'in:position,trajet'],

            'target_at'   => ['nullable', 'date'],
            'target_time' => ['nullable', 'date_format:H:i:s'],

            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date'],
            'start_time'  => ['nullable', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('mode') ?: 'exact';
        $view = $this->input('view');
        $targetTime = $this->input('target_time');

        if (!$view) {
            $view = $mode === 'range' ? 'trajet' : 'position';
        }

        if (is_string($targetTime) && preg_match('/^\d{2}:\d{2}$/', $targetTime)) {
            $targetTime .= ':00';
        }

        $this->merge([
            'search'      => is_string($this->search) ? trim($this->search) : $this->search,
            'mode'        => $mode,
            'view'        => $view,
            'target_time' => $targetTime,
            'per_page'    => $this->per_page ?: 20,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $vehicleId = $this->input('vehicle_id');
            $mode = $this->input('mode', 'exact');

            if (empty($vehicleId)) {
                return;
            }

            if ($mode === 'exact') {
                if (!$this->filled('target_at') || !$this->filled('target_time')) {
                    $validator->errors()->add(
                        'target_at',
                        "La date et l'heure sont obligatoires pour retrouver la position à un instant donné."
                    );
                }
            }

            if ($mode === 'range') {
                if (!$this->filled('start_date') || !$this->filled('end_date')) {
                    $validator->errors()->add(
                        'start_date',
                        'Les dates de début et de fin sont obligatoires pour afficher le trajet.'
                    );
                }
            }
        });
    }

    public function wantsHistory(): bool
    {
        $vehicleId = $this->input('vehicle_id');
        $mode = $this->input('mode', 'exact');

        if (empty($vehicleId)) {
            return false;
        }

        if ($mode === 'exact') {
            return $this->filled('target_at') && $this->filled('target_time');
        }

        if ($mode === 'range') {
            return $this->filled('start_date') && $this->filled('end_date');
        }

        return false;
    }
}