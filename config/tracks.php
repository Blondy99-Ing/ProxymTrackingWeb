<?php

return [

    // ---- Timezone / parsing ----
    'db_tz'  => env('TRACKS_DB_TZ', 'UTC'),
    'app_tz' => env('TRACKS_APP_TZ', 'Africa/Douala'),

    // ---- Query window around trip ----
    'window_before_min' => (int) env('TRACKS_WINDOW_BEFORE_MIN', 2),
    'window_after_min'  => (int) env('TRACKS_WINDOW_AFTER_MIN', 2),
    'fallback_end_hours'=> (int) env('TRACKS_FALLBACK_END_HOURS', 3),

    // ---- Limits ----
    'max_points'   => (int) env('TRACK_MAX_POINTS', 1500),
    'max_db_rows'  => (int) env('TRACK_MAX_DB_ROWS', 20000),

    'max_points_focus'  => (int) env('TRACK_MAX_POINTS_FOCUS', 20000),
    'max_db_rows_focus' => (int) env('TRACK_MAX_DB_ROWS_FOCUS', 120000),
    'disable_reduction_focus' => (bool) env('TRACK_DISABLE_REDUCTION_FOCUS', false),

    // ---- Optimization toggles ----
    'optimize' => [
        'filter_outliers' => filter_var(env('TRACK_FILTER_OUTLIERS', true), FILTER_VALIDATE_BOOLEAN),
        'simplify'        => filter_var(env('TRACK_SIMPLIFY', true), FILTER_VALIDATE_BOOLEAN),
        'smoothing'       => filter_var(env('TRACK_SMOOTHING', true), FILTER_VALIDATE_BOOLEAN),

        // Best result: snap to roads
        'snap_to_roads'   => filter_var(env('TRACK_SNAP_TO_ROADS', false), FILTER_VALIDATE_BOOLEAN),
        'snap_focus_only' => filter_var(env('TRACK_SNAP_FOCUS_ONLY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // ---- Outlier thresholds (robust) ----
    'outliers' => [
        // Drop micro-jitter
        'min_move_m' => (float) env('TRACK_MIN_MOVE_METERS', 3),

        // Hard speed cap (km/h) (bike/voiture à toi de régler)
        'max_speed_kmh' => (float) env('TRACK_MAX_JUMP_KMH', 140),

        // If distance > max_jump_m in <= max_jump_s -> drop
        'max_jump_m' => (float) env('TRACK_MAX_JUMP_METERS', 250),
        'max_jump_s' => (int)   env('TRACK_MAX_JUMP_SECONDS', 10),

        // Acceleration cap (m/s²) -> filtre “virages impossibles”
        'max_acc_ms2' => (float) env('TRACK_MAX_ACCEL_MS2', 6.0),

        // If heading change too big while speed high -> suspect point
        'max_turn_deg' => (float) env('TRACK_MAX_TURN_DEG', 130),
        'min_speed_for_turn_kmh' => (float) env('TRACK_MIN_SPEED_FOR_TURN_KMH', 25),
    ],

    // ---- Simplification ----
    'simplify_cfg' => [
        // tolerance in meters for Douglas-Peucker
        'tolerance_m' => (float) env('TRACK_SIMPLIFY_TOLERANCE_M', 6.0),
    ],

    // ---- Smoothing ----
    'smooth_cfg' => [
        // window size (odd number recommended)
        'window' => (int) env('TRACK_SMOOTH_WINDOW', 5),
    ],
];