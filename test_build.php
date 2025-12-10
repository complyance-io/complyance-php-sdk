<?php

require_once 'vendor/autoload.php';

echo "=== Complyance PHP SDK Build Test ===\n";

try {
    // Test if main SDK class can be loaded
    $reflection = new ReflectionClass('ComplyanceSDK\\GETSUnifySDK');
    echo "✅ GETSUnifySDK class loaded successfully\n";
    
    // Test if models can be loaded
    $reflection = new ReflectionClass('ComplyanceSDK\\Models\\SDKConfig');
    echo "✅ SDKConfig model loaded successfully\n";
    
    // Test if exceptions can be loaded
    $reflection = new ReflectionClass('ComplyanceSDK\\Exceptions\\SDKException');
    echo "✅ SDKException loaded successfully\n";
    
    // Test if enums can be loaded
    $reflection = new ReflectionClass('ComplyanceSDK\\Enums\\Environment');
    echo "✅ Environment enum loaded successfully\n";
    
    echo "\n🎉 All core SDK classes are available!\n";
    echo "📦 SDK build completed successfully\n";
    echo "🚀 Ready for distribution\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
