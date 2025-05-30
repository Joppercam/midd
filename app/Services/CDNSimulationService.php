<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CDNSimulationService
{
    protected array $config;
    protected string $baseUrl;
    protected array $supportedTypes;
    protected array $optimizationSettings;

    public function __construct()
    {
        $this->config = config('performance.assets', [
            'enable_cdn_simulation' => false,
            'cdn_base_url' => 'https://cdn.crecepyme.cl',
            'enable_asset_versioning' => true,
            'enable_asset_minification' => true,
        ]);

        $this->baseUrl = $this->config['cdn_base_url'];
        
        $this->supportedTypes = [
            'css' => ['text/css', 'text/plain'],
            'js' => ['application/javascript', 'text/javascript'],
            'images' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            'fonts' => ['font/woff', 'font/woff2', 'font/ttf', 'font/otf']
        ];

        $this->optimizationSettings = [
            'css' => [
                'minify' => true,
                'compress' => true,
                'cache_headers' => ['max-age' => 31536000] // 1 year
            ],
            'js' => [
                'minify' => true,
                'compress' => true,
                'cache_headers' => ['max-age' => 31536000]
            ],
            'images' => [
                'optimize' => true,
                'webp_conversion' => true,
                'cache_headers' => ['max-age' => 31536000]
            ],
            'fonts' => [
                'compress' => true,
                'cache_headers' => ['max-age' => 31536000]
            ]
        ];
    }

    /**
     * Get optimized asset URL
     */
    public function getAssetUrl(string $assetPath, array $options = []): string
    {
        if (!$this->config['enable_cdn_simulation']) {
            return asset($assetPath);
        }

        $assetInfo = $this->analyzeAsset($assetPath);
        
        // Generate versioned URL if enabled
        if ($this->config['enable_asset_versioning']) {
            $version = $this->getAssetVersion($assetPath);
            $assetPath = $this->addVersionToPath($assetPath, $version);
        }

        // Apply transformations based on asset type
        $transformedPath = $this->applyAssetTransformations($assetPath, $assetInfo, $options);

        return $this->baseUrl . '/' . ltrim($transformedPath, '/');
    }

    /**
     * Process and optimize assets
     */
    public function processAsset(string $assetPath): array
    {
        $assetInfo = $this->analyzeAsset($assetPath);
        $fullPath = public_path($assetPath);

        if (!file_exists($fullPath)) {
            throw new \InvalidArgumentException("Asset not found: {$assetPath}");
        }

        $originalSize = filesize($fullPath);
        $optimized = false;
        $newPath = $assetPath;

        try {
            switch ($assetInfo['type']) {
                case 'css':
                    $newPath = $this->processCSSAsset($assetPath, $fullPath);
                    $optimized = true;
                    break;
                    
                case 'js':
                    $newPath = $this->processJSAsset($assetPath, $fullPath);
                    $optimized = true;
                    break;
                    
                case 'images':
                    $newPath = $this->processImageAsset($assetPath, $fullPath);
                    $optimized = true;
                    break;
                    
                case 'fonts':
                    $newPath = $this->processFontAsset($assetPath, $fullPath);
                    $optimized = true;
                    break;
            }

            $newFullPath = public_path($newPath);
            $newSize = file_exists($newFullPath) ? filesize($newFullPath) : $originalSize;

            return [
                'original_path' => $assetPath,
                'optimized_path' => $newPath,
                'original_size' => $originalSize,
                'optimized_size' => $newSize,
                'savings' => $originalSize - $newSize,
                'savings_percentage' => $originalSize > 0 ? round((($originalSize - $newSize) / $originalSize) * 100, 2) : 0,
                'optimized' => $optimized,
                'type' => $assetInfo['type']
            ];

        } catch (\Exception $e) {
            Log::warning('Asset optimization failed', [
                'asset' => $assetPath,
                'error' => $e->getMessage()
            ]);

            return [
                'original_path' => $assetPath,
                'optimized_path' => $assetPath,
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'savings' => 0,
                'savings_percentage' => 0,
                'optimized' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process CSS assets
     */
    protected function processCSSAsset(string $assetPath, string $fullPath): string
    {
        $content = file_get_contents($fullPath);
        
        if ($this->config['enable_asset_minification']) {
            $content = $this->minifyCSS($content);
        }

        // Process URLs within CSS
        $content = $this->processCSSUrls($content);

        // Generate optimized filename
        $optimizedPath = $this->generateOptimizedPath($assetPath, 'min');
        $optimizedFullPath = public_path($optimizedPath);

        // Ensure directory exists
        $this->ensureDirectoryExists(dirname($optimizedFullPath));

        file_put_contents($optimizedFullPath, $content);

        return $optimizedPath;
    }

    /**
     * Process JavaScript assets
     */
    protected function processJSAsset(string $assetPath, string $fullPath): string
    {
        $content = file_get_contents($fullPath);
        
        if ($this->config['enable_asset_minification']) {
            $content = $this->minifyJS($content);
        }

        $optimizedPath = $this->generateOptimizedPath($assetPath, 'min');
        $optimizedFullPath = public_path($optimizedPath);

        $this->ensureDirectoryExists(dirname($optimizedFullPath));

        file_put_contents($optimizedFullPath, $content);

        return $optimizedPath;
    }

    /**
     * Process image assets
     */
    protected function processImageAsset(string $assetPath, string $fullPath): string
    {
        $imageInfo = getimagesize($fullPath);
        
        if (!$imageInfo) {
            return $assetPath; // Not a valid image
        }

        $mimeType = $imageInfo['mime'];
        
        // Check if we should convert to WebP
        if ($this->shouldConvertToWebP($mimeType)) {
            return $this->convertToWebP($assetPath, $fullPath);
        }

        // Optimize existing format
        return $this->optimizeImage($assetPath, $fullPath, $mimeType);
    }

    /**
     * Process font assets
     */
    protected function processFontAsset(string $assetPath, string $fullPath): string
    {
        // For fonts, we mainly add proper headers and potentially convert formats
        // For now, just return the original path as font optimization is complex
        return $assetPath;
    }

    /**
     * Minify CSS content
     */
    protected function minifyCSS(string $content): string
    {
        // Remove comments
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        
        // Remove whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove unnecessary spaces around specific characters
        $content = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $content);
        
        // Remove trailing semicolon before }
        $content = preg_replace('/;}/', '}', $content);
        
        return trim($content);
    }

    /**
     * Minify JavaScript content (basic minification)
     */
    protected function minifyJS(string $content): string
    {
        // Remove single-line comments (be careful with URLs)
        $content = preg_replace('~//[^\r\n]*~', '', $content);
        
        // Remove multi-line comments
        $content = preg_replace('~/\*.*?\*/~s', '', $content);
        
        // Remove unnecessary whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove spaces around operators and punctuation
        $content = preg_replace('/\s*([=+\-*\/{}();,:])\s*/', '$1', $content);
        
        return trim($content);
    }

    /**
     * Process URLs within CSS
     */
    protected function processCSSUrls(string $content): string
    {
        return preg_replace_callback('/url\([\'"]?([^\'")]+)[\'"]?\)/', function ($matches) {
            $url = $matches[1];
            
            // Skip external URLs
            if (Str::startsWith($url, ['http://', 'https://', '//', 'data:'])) {
                return $matches[0];
            }
            
            // Convert relative URLs to CDN URLs
            $optimizedUrl = $this->getAssetUrl($url);
            
            return "url('{$optimizedUrl}')";
        }, $content);
    }

    /**
     * Check if image should be converted to WebP
     */
    protected function shouldConvertToWebP(string $mimeType): bool
    {
        return in_array($mimeType, ['image/jpeg', 'image/png']) &&
               function_exists('imagewebp') &&
               $this->optimizationSettings['images']['webp_conversion'];
    }

    /**
     * Convert image to WebP format
     */
    protected function convertToWebP(string $assetPath, string $fullPath): string
    {
        $webpPath = $this->generateOptimizedPath($assetPath, 'webp', 'webp');
        $webpFullPath = public_path($webpPath);

        $this->ensureDirectoryExists(dirname($webpFullPath));

        $imageInfo = getimagesize($fullPath);
        $mimeType = $imageInfo['mime'];

        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($fullPath);
                break;
            default:
                return $assetPath; // Unsupported type
        }

        if ($image && imagewebp($image, $webpFullPath, 80)) {
            imagedestroy($image);
            return $webpPath;
        }

        return $assetPath; // Conversion failed
    }

    /**
     * Optimize image in original format
     */
    protected function optimizeImage(string $assetPath, string $fullPath, string $mimeType): string
    {
        $optimizedPath = $this->generateOptimizedPath($assetPath, 'opt');
        $optimizedFullPath = public_path($optimizedPath);

        $this->ensureDirectoryExists(dirname($optimizedFullPath));

        // For basic optimization, we'll just copy the file
        // In a real implementation, you'd use libraries like ImageMagick or similar
        copy($fullPath, $optimizedFullPath);

        return $optimizedPath;
    }

    /**
     * Generate optimized asset path
     */
    protected function generateOptimizedPath(string $originalPath, string $suffix, ?string $newExtension = null): string
    {
        $pathInfo = pathinfo($originalPath);
        
        $directory = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '';
        $filename = $pathInfo['filename'];
        $extension = $newExtension ?? $pathInfo['extension'];

        return $directory . $filename . '.' . $suffix . '.' . $extension;
    }

    /**
     * Analyze asset to determine type and characteristics
     */
    protected function analyzeAsset(string $assetPath): array
    {
        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        
        $type = match ($extension) {
            'css' => 'css',
            'js' => 'js',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'images',
            'woff', 'woff2', 'ttf', 'otf', 'eot' => 'fonts',
            default => 'other'
        };

        return [
            'type' => $type,
            'extension' => $extension,
            'path' => $assetPath
        ];
    }

    /**
     * Get asset version for cache busting
     */
    protected function getAssetVersion(string $assetPath): string
    {
        $cacheKey = "asset_version:{$assetPath}";
        
        return Cache::remember($cacheKey, 3600, function () use ($assetPath) {
            $fullPath = public_path($assetPath);
            
            if (file_exists($fullPath)) {
                return substr(md5_file($fullPath), 0, 8);
            }
            
            return substr(md5($assetPath), 0, 8);
        });
    }

    /**
     * Add version parameter to asset path
     */
    protected function addVersionToPath(string $assetPath, string $version): string
    {
        $separator = str_contains($assetPath, '?') ? '&' : '?';
        return $assetPath . $separator . 'v=' . $version;
    }

    /**
     * Apply asset transformations based on options
     */
    protected function applyAssetTransformations(string $assetPath, array $assetInfo, array $options): string
    {
        // Apply responsive image transformations for images
        if ($assetInfo['type'] === 'images' && !empty($options)) {
            if (isset($options['width']) || isset($options['height'])) {
                $assetPath = $this->addImageDimensions($assetPath, $options);
            }
            
            if (isset($options['quality'])) {
                $assetPath = $this->addImageQuality($assetPath, $options['quality']);
            }
        }

        return $assetPath;
    }

    /**
     * Add image dimensions to path
     */
    protected function addImageDimensions(string $assetPath, array $options): string
    {
        $params = [];
        
        if (isset($options['width'])) {
            $params[] = 'w=' . (int) $options['width'];
        }
        
        if (isset($options['height'])) {
            $params[] = 'h=' . (int) $options['height'];
        }

        if (!empty($params)) {
            $separator = str_contains($assetPath, '?') ? '&' : '?';
            $assetPath .= $separator . implode('&', $params);
        }

        return $assetPath;
    }

    /**
     * Add image quality to path
     */
    protected function addImageQuality(string $assetPath, int $quality): string
    {
        $quality = max(1, min(100, $quality));
        $separator = str_contains($assetPath, '?') ? '&' : '?';
        
        return $assetPath . $separator . 'q=' . $quality;
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Batch process multiple assets
     */
    public function batchProcessAssets(array $assetPaths): array
    {
        $results = [];
        $totalOriginalSize = 0;
        $totalOptimizedSize = 0;

        foreach ($assetPaths as $assetPath) {
            try {
                $result = $this->processAsset($assetPath);
                $results[] = $result;
                
                $totalOriginalSize += $result['original_size'];
                $totalOptimizedSize += $result['optimized_size'];
                
            } catch (\Exception $e) {
                $results[] = [
                    'original_path' => $assetPath,
                    'error' => $e->getMessage(),
                    'optimized' => false
                ];
            }
        }

        return [
            'assets' => $results,
            'summary' => [
                'total_assets' => count($assetPaths),
                'optimized_assets' => count(array_filter($results, fn($r) => $r['optimized'] ?? false)),
                'total_original_size' => $totalOriginalSize,
                'total_optimized_size' => $totalOptimizedSize,
                'total_savings' => $totalOriginalSize - $totalOptimizedSize,
                'total_savings_percentage' => $totalOriginalSize > 0 ? 
                    round((($totalOriginalSize - $totalOptimizedSize) / $totalOriginalSize) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get CDN simulation statistics
     */
    public function getStatistics(): array
    {
        return [
            'enabled' => $this->config['enable_cdn_simulation'],
            'base_url' => $this->baseUrl,
            'supported_types' => array_keys($this->supportedTypes),
            'optimization_features' => [
                'versioning' => $this->config['enable_asset_versioning'],
                'minification' => $this->config['enable_asset_minification'],
                'webp_conversion' => $this->optimizationSettings['images']['webp_conversion'],
                'compression' => true
            ]
        ];
    }

    /**
     * Clear asset cache
     */
    public function clearCache(): void
    {
        $pattern = 'asset_version:*';
        $redis = Cache::getRedis();
        $keys = $redis->keys($pattern);
        
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }
}