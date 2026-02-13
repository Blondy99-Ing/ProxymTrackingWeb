<?php

return [
    'app_tz' => env('TRACKS_APP_TZ', config('app.timezone', 'Africa/Douala')),
    'db_tz'  => env('TRACKS_DB_TZ', 'UTC'),

    'window_before_min' => (int) env('TRACKS_WINDOW_BEFORE_MIN', 2),
    'window_after_min'  => (int) env('TRACKS_WINDOW_AFTER_MIN', 2),
    'fallback_end_hours'=> (int) env('TRACKS_FALLBACK_END_HOURS', 3),

    'max_points'        => (int) env('TRACK_MAX_POINTS', 1500),
    'max_points_focus'  => (int) env('TRACK_MAX_POINTS_FOCUS', 9999999),

    'max_db_rows'       => (int) env('TRACK_MAX_DB_ROWS', 20000),
    'max_db_rows_focus' => (int) env('TRACK_MAX_DB_ROWS_FOCUS', 300000),

    'disable_reduction_focus' => (bool) env('TRACK_DISABLE_REDUCTION_FOCUS', true),

    'filter_outliers'   => (bool) env('TRACK_FILTER_OUTLIERS', true),
    'max_jump_kmh'      => (float) env('TRACK_MAX_JUMP_KMH', 140),
    'max_jump_meters'   => (float) env('TRACK_MAX_JUMP_METERS', 250),
    'max_jump_seconds'  => (int) env('TRACK_MAX_JUMP_SECONDS', 10),
];