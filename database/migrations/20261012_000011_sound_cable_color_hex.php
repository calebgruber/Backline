<?php

declare(strict_types=1);

return [
    'key' => '20261012_000011_sound_cable_color_hex',
    'name' => 'Add hex values to sound cable colors',
    'sql' => [
        'ALTER TABLE sound_cable_colors
            ADD COLUMN IF NOT EXISTS hex_code VARCHAR(7) NULL AFTER name',
        'UPDATE sound_cable_colors SET hex_code = "#111111" WHERE LOWER(name) = "black" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#e53935" WHERE LOWER(name) = "red" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#1e88e5" WHERE LOWER(name) = "blue" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#43a047" WHERE LOWER(name) = "green" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#fdd835" WHERE LOWER(name) = "yellow" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#ffffff" WHERE LOWER(name) = "white" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#fb8c00" WHERE LOWER(name) = "orange" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#8e24aa" WHERE LOWER(name) = "purple" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#9e9e9e" WHERE LOWER(name) = "gray" AND (hex_code IS NULL OR hex_code = "")',
        'UPDATE sound_cable_colors SET hex_code = "#1f9ad1" WHERE hex_code IS NULL OR hex_code = ""',
    ],
];

