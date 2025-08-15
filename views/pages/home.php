<?php
/**
 * views/home.php
 * 
 * No direct access allowed!
 * 
 * @since 8.3
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 * 
 */

// define the primary app path if not already defined
defined( 'KPT_PATH' ) || die( 'Direct Access is not allowed!' );

use KPT\KPT;
use KPT\Cache;
use KPT\Cache_Config;
use KPT\Cache_KeyManager;

// pull in the header
KPT::pull_header( );

?>
<h3 class="me">Welcome to my stream manager.</h3>
<p>Please understand that I built this primarily for me as practice to keep my PHP and MySQL coding skills up to snuff. I decided to make it publicly available in case anyone else feels they could use something similar.</p>
<p>Now, that that is out of the way, please understand that I can only minimally support this app, if you decide to use it, you agree that it is at your own discretion and that I am under no obligation to help you, fix your items, or fix this website if it seems broken to you.</p>
<p>You also understand that I do not host, nor have I ever hosted any kind of media for public consumption or use. Thus said, do not ask me to provide you with anything related to that.</p>
<p>I also make this statement that this tool is to be used to legitimate IPTV purposes, and data stored that violates this is beyond my control.</p>
<p>You can send suggestions through this <a href="https://kevp.us/contact" target="_blank">Contact Us</a> form, but please understand that I may not answer you.</p>
<?php
// Debug exactly what happens during SHMOP delete

echo "=== DEBUG SHMOP DELETE OPERATION ===<br /><br />";

// 1. Set test data and capture the exact shmop_key used
echo "1. Setting SHMOP test data...<br />";
Cache::setToTier('debug_shmop', 'debug_data', 60, 'shmop');

// 2. Get the stored shmop_key from tracking
$reflection = new ReflectionClass('KPT\Cache');
$shmop_property = $reflection->getProperty('_shmop_segments');
$shmop_property->setAccessible(true);
$shmop_segments = $shmop_property->getValue();

echo "2. Stored SHMOP segments:<br />";
print_r($shmop_segments);

if (isset($shmop_segments['debug_shmop'])) {
    $stored_shmop_key = $shmop_segments['debug_shmop'];
    echo "Stored shmop_key for 'debug_shmop': {$stored_shmop_key}<br />";
    
    // 3. Test if we can open this segment
    echo "<br />3. Testing if segment exists and is readable:<br />";
    $segment = @shmop_open($stored_shmop_key, 'a', 0, 0);
    if ($segment !== false) {
        $size = shmop_size($segment);
        echo "   ✓ Segment exists, size: {$size} bytes<br />";
        
        if ($size > 0) {
            $data = shmop_read($segment, 0, $size);
            $unserialized = @unserialize(trim($data, "\0"));
            echo "   Data in segment: " . var_export($unserialized, true) . "<br />";
        }
        @shmop_close($segment);
    } else {
        echo "   ✗ Cannot open segment with key {$stored_shmop_key}<br />";
    }
    
    // 4. Test deleteFromShmopInternal directly
    echo "<br />4. Testing deleteFromShmopInternal directly:<br />";
    
    // Use reflection to call the private method
    $deleteMethod = $reflection->getMethod('deleteFromShmopInternal');
    $deleteMethod->setAccessible(true);
    
    echo "   Calling deleteFromShmopInternal({$stored_shmop_key})...<br />";
    $delete_result = $deleteMethod->invoke(null, $stored_shmop_key);
    echo "   Delete result: " . var_export($delete_result, true) . "<br />";
    
    // 5. Check if segment still exists after delete
    echo "<br />5. Checking if segment still exists after delete:<br />";
    $segment_after = @shmop_open($stored_shmop_key, 'a', 0, 0);
    if ($segment_after !== false) {
        echo "   ✗ Segment STILL EXISTS after delete!<br />";
        @shmop_close($segment_after);
    } else {
        echo "   ✓ Segment successfully deleted<br />";
    }
    
    // 6. Test Cache::getFromTier after direct delete
    echo "<br />6. Testing Cache::getFromTier after direct delete:<br />";
    $data_after = Cache::getFromTier('debug_shmop', 'shmop');
    echo "   Data after delete: " . var_export($data_after, true) . "<br />";
    
    if ($data_after !== false) {
        echo "   ✗ Cache still returns data - delete failed or data cached elsewhere<br />";
    } else {
        echo "   ✓ Cache returns false - delete successful<br />";
    }
    
} else {
    echo "No SHMOP segments found in tracking array!<br />";
}

// 7. Show current system shared memory segments
echo "<br />7. Current shared memory segments (if ipcs available):<br />";
$ipcs_output = @shell_exec('ipcs -m 2>/dev/null');
if ($ipcs_output) {
    echo $ipcs_output;
} else {
    echo "   ipcs command not available or failed<br />";
}

echo "<br />=== END DEBUG ===<br />";

echo "=== Testing MMAP Path Configuration ===<br />";
    
    $test_key = 'path_test';
    
    // Test 1: Default path (before setting custom path)
    $default_path = Cache_KeyManager::generateSpecialKey( $test_key, 'mmap' );
    echo "Default path: $default_path<br />";
    
    // Test 2: Set custom cache path
    $custom_path = '/tmp/custom_kpt_cache/';
    $set_result = Cache::setCachePath( $custom_path );
    echo "Set custom cache path ($custom_path): " . ($set_result ? 'Success' : 'Failed') . "<br />";
    
    // Test 3: Generate MMAP path after setting custom path
    $custom_mmap_path = Cache_KeyManager::generateSpecialKey( $test_key, 'mmap' );
    echo "Custom MMAP path: $custom_mmap_path<br />";
    
    // Test 4: Verify it's using the custom path
    $expected_prefix = $custom_path . 'mmap/';
    $is_using_custom = strpos( $custom_mmap_path, $expected_prefix ) === 0;
    echo "Using custom path: " . ($is_using_custom ? 'YES ✅' : 'NO ❌') . "<br />";
    
    // Test 5: Test actual MMAP operations with custom path
    Cache::clearTier( 'mmap' );
    $set_result = Cache::setToTier( $test_key, 'path_test_data', 3600, 'mmap' );
    echo "Set with custom path: " . ($set_result ? 'Success' : 'Failed') . "<br />";
    
    $get_result = Cache::getFromTier( $test_key, 'mmap' );
    echo "Get with custom path: " . ($get_result === 'path_test_data' ? 'Success' : 'Failed') . "<br />";
    
    // Test 6: Verify file actually exists in the expected location
    $file_exists = file_exists( $custom_mmap_path );
    echo "File exists at expected location: " . ($file_exists ? 'YES ✅' : 'NO ❌') . "<br />";
    
    // Cleanup
    Cache::clearTier( 'mmap' );
    
    echo "=== MMAP Path Configuration Test Complete ===<br />";

// pull in the footer
KPT::pull_footer( );
