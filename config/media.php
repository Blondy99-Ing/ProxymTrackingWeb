<?php

return [
    'disk' => 'public',
    'root_folder' => 'uploads',

    'images' => [
        'max_width'  => 1600,
        'max_height' => 1600,

        // Qualité JPG/WebP (lossy)
        'jpeg_quality' => 75,   // 1-100
        'webp_quality' => 75,   // 1-100

        // PNG = compression lossless (0-9)
        'png_compression' => 6,

        // Si Imagick dispo, compresse les GIF animés sans casser l’animation
        'compress_animated_gif' => true,
    ],
];

