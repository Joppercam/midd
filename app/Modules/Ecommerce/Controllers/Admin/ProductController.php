<?php

namespace App\Modules\Ecommerce\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Ecommerce\Models\EcommerceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ecommerce.catalog.manage');
    }

    /**
     * Display a listing of e-commerce products
     */
    public function index(Request $request)
    {
        $query = EcommerceProduct::with(['product', 'category'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'published':
                    $query->where('is_published', true);
                    break;
                case 'draft':
                    $query->where('is_published', false);
                    break;
                case 'featured':
                    $query->where('is_featured', true);
                    break;
                case 'on_sale':
                    $query->onSale();
                    break;
            }
        }

        $products = $query->paginate(20);
        $categories = EcommerceCategory::active()->get();

        $stats = [
            'total' => EcommerceProduct::count(),
            'published' => EcommerceProduct::where('is_published', true)->count(),
            'draft' => EcommerceProduct::where('is_published', false)->count(),
            'featured' => EcommerceProduct::where('is_featured', true)->count(),
            'on_sale' => EcommerceProduct::onSale()->count(),
        ];

        return Inertia::render('Ecommerce/Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new e-commerce product
     */
    public function create()
    {
        $categories = EcommerceCategory::active()->get();
        $availableProducts = Product::whereDoesntHave('ecommerceProduct')->get();

        return Inertia::render('Ecommerce/Admin/Products/Create', [
            'categories' => $categories,
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Store a newly created e-commerce product
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'category_id' => 'nullable|exists:ecommerce_categories,id',
            'online_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'short_description' => 'nullable|string|max:500',
            'full_description' => 'nullable|string',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'variants' => 'nullable|array',
            'attributes' => 'nullable|array',
            'seo_meta' => 'nullable|array',
        ]);

        // Handle image uploads
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('ecommerce/products', 'public');
                $imagePaths[] = $path;
            }
        }

        $ecommerceProduct = EcommerceProduct::create([
            'tenant_id' => tenant()->id,
            'product_id' => $request->product_id,
            'category_id' => $request->category_id,
            'online_price' => $request->online_price,
            'sale_price' => $request->sale_price,
            'sale_start_date' => $request->sale_start_date,
            'sale_end_date' => $request->sale_end_date,
            'images' => $imagePaths,
            'variants' => $request->variants ?? [],
            'attributes' => $request->attributes ?? [],
            'short_description' => $request->short_description,
            'full_description' => $request->full_description,
            'seo_meta' => $request->seo_meta ?? [],
            'is_published' => $request->boolean('is_published'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return redirect()->route('ecommerce.admin.products.show', $ecommerceProduct)
            ->with('success', 'Product added to e-commerce catalog successfully.');
    }

    /**
     * Display the specified e-commerce product
     */
    public function show(EcommerceProduct $product)
    {
        $product->load(['product', 'category', 'reviews.customer']);

        $analytics = [
            'views' => $product->views_count,
            'rating' => $product->rating_average,
            'reviews_count' => $product->reviews_count,
            'sales_count' => $this->getSalesCount($product),
            'revenue' => $this->getRevenue($product),
        ];

        return Inertia::render('Ecommerce/Admin/Products/Show', [
            'product' => $product,
            'analytics' => $analytics,
        ]);
    }

    /**
     * Show the form for editing the specified e-commerce product
     */
    public function edit(EcommerceProduct $product)
    {
        $product->load(['product', 'category']);
        $categories = EcommerceCategory::active()->get();

        return Inertia::render('Ecommerce/Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified e-commerce product
     */
    public function update(Request $request, EcommerceProduct $product)
    {
        $request->validate([
            'category_id' => 'nullable|exists:ecommerce_categories,id',
            'online_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'short_description' => 'nullable|string|max:500',
            'full_description' => 'nullable|string',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'existing_images' => 'nullable|array',
            'variants' => 'nullable|array',
            'attributes' => 'nullable|array',
            'seo_meta' => 'nullable|array',
        ]);

        // Handle image updates
        $imagePaths = $request->existing_images ?? [];
        
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $image) {
                $path = $image->store('ecommerce/products', 'public');
                $imagePaths[] = $path;
            }
        }

        // Remove deleted images from storage
        $currentImages = $product->images ?? [];
        $imagesToDelete = array_diff($currentImages, $imagePaths);
        
        foreach ($imagesToDelete as $imageToDelete) {
            Storage::disk('public')->delete($imageToDelete);
        }

        $product->update([
            'category_id' => $request->category_id,
            'online_price' => $request->online_price,
            'sale_price' => $request->sale_price,
            'sale_start_date' => $request->sale_start_date,
            'sale_end_date' => $request->sale_end_date,
            'images' => $imagePaths,
            'variants' => $request->variants ?? [],
            'attributes' => $request->attributes ?? [],
            'short_description' => $request->short_description,
            'full_description' => $request->full_description,
            'seo_meta' => $request->seo_meta ?? [],
            'is_published' => $request->boolean('is_published'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

        return redirect()->route('ecommerce.admin.products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Toggle product publication status
     */
    public function togglePublished(EcommerceProduct $product)
    {
        $product->update(['is_published' => !$product->is_published]);

        $status = $product->is_published ? 'published' : 'unpublished';
        
        return redirect()->back()
            ->with('success', "Product {$status} successfully.");
    }

    /**
     * Toggle product featured status
     */
    public function toggleFeatured(EcommerceProduct $product)
    {
        $product->update(['is_featured' => !$product->is_featured]);

        $status = $product->is_featured ? 'featured' : 'unfeatured';
        
        return redirect()->back()
            ->with('success', "Product {$status} successfully.");
    }

    /**
     * Remove the specified e-commerce product
     */
    public function destroy(EcommerceProduct $product)
    {
        // Delete associated images
        if ($product->images) {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $product->delete();

        return redirect()->route('ecommerce.admin.products.index')
            ->with('success', 'Product removed from e-commerce catalog.');
    }

    /**
     * Bulk actions for products
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:publish,unpublish,feature,unfeature,delete',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:ecommerce_products,id',
        ]);

        $products = EcommerceProduct::whereIn('id', $request->product_ids)->get();

        switch ($request->action) {
            case 'publish':
                $products->each(fn($product) => $product->update(['is_published' => true]));
                $message = 'Products published successfully.';
                break;
            case 'unpublish':
                $products->each(fn($product) => $product->update(['is_published' => false]));
                $message = 'Products unpublished successfully.';
                break;
            case 'feature':
                $products->each(fn($product) => $product->update(['is_featured' => true]));
                $message = 'Products featured successfully.';
                break;
            case 'unfeature':
                $products->each(fn($product) => $product->update(['is_featured' => false]));
                $message = 'Products unfeatured successfully.';
                break;
            case 'delete':
                foreach ($products as $product) {
                    if ($product->images) {
                        foreach ($product->images as $image) {
                            Storage::disk('public')->delete($image);
                        }
                    }
                    $product->delete();
                }
                $message = 'Products deleted successfully.';
                break;
        }

        return redirect()->back()->with('success', $message);
    }

    // Helper methods

    protected function getSalesCount(EcommerceProduct $product): int
    {
        // TODO: Implement when EcommerceOrderItem is created
        return 0;
    }

    protected function getRevenue(EcommerceProduct $product): float
    {
        // TODO: Implement when EcommerceOrderItem is created
        return 0;
    }
}