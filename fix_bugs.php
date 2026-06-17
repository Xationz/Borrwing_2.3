<?php
$files = ['admins.php', 'categorie.php', 'equipment.php', 'admin_dashboard.php', 'user_dashboard.php', 'calendar.php', 'borrowing_dashboard.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Fix calendarEl
    $content = preg_replace(
        '/var calendarEl = document\.getElementById\(\'calendar\'\);\s*var calendar = new FullCalendar\.Calendar\(calendarEl,\s*\{/',
        "var calendarEl = document.getElementById('calendar');\n            if (calendarEl) {\n                var calendar = new FullCalendar.Calendar(calendarEl, {",
        $content
    );
    $content = preg_replace(
        '/calendar\.render\(\);\s*\}\);/',
        "calendar.render();\n            }\n        });",
        $content
    );

    // Fix Swal.fire echoing before library is loaded.
    // Replace: echo "<script>Swal.fire('...', '...', '...');</script>";
    // With: $swal_msg = "Swal.fire('...', '...', '...');";
    $content = preg_replace(
        '/echo\s+"<script>(Swal\.fire\([^>]+)<\/script>";/',
        '$swal_msg = "$1";',
        $content
    );

    // Now inject the $swal_msg rendering right after sweetalert2 script tag
    // <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    if (strpos($content, '$swal_msg') !== false && strpos($content, '<?php if(!empty($swal_msg))') === false) {
        $injection = "<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11\"></script>\n    <?php if(!empty(\$swal_msg)): ?>\n    <script>\n        <?php echo \$swal_msg; ?>\n    </script>\n    <?php endif; ?>";
        $content = str_replace('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>', $injection, $content);
    }
    
    file_put_contents($file, $content);
    echo "Fixed $file\n";
}
