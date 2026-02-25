<?php
require_once('../../../wp-load.php');

global $wpdb;

echo "\n--- Sign Statuses ---\n";
$statuses = $wpdb->get_results("SELECT post_status, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = 'sign' GROUP BY post_status");
foreach ($statuses as $s) {
    echo $s->post_status . ": " . $s->count . "\n";
}

echo "\n--- City Info ---\n";
$empty_city_ids = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sign_city_id')
    WHERE p.post_type = 'sign' AND pm.meta_value = ''
");
echo "Empty _sign_city_id: $empty_city_ids\n";

$null_city_ids = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_sign_city_id')
    WHERE p.post_type = 'sign' AND pm.meta_value IS NULL
");
echo "Null/Missing _sign_city_id: $null_city_ids\n";

echo "\n--- Example MO-282226 ---\n";
$mo = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_title = 'MO-282226' AND post_type='sign'");
if ($mo) {
    echo "ID: {$mo->ID}, Status: {$mo->post_status}\n";
    $metas = get_post_custom($mo->ID);
    foreach($metas as $k => $v) {
        if (strpos($k, '_sign') === 0) {
            echo "$k => " . print_r($v, true);
        }
    }
} else {
    echo "MO-282226 not found!\n";
}

$mo2 = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_title = 'MO-282227'  AND post_type='sign'");
if ($mo2) {
    echo "\nID: {$mo2->ID}, Status: {$mo2->post_status}\n";
    $metas = get_post_custom($mo2->ID);
    foreach($metas as $k => $v) {
        if (strpos($k, '_sign') === 0) {
            echo "$k => " . print_r($v, true);
        }
    }
} else {
    echo "MO-282227 not found!\n";
}
