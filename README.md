# Zog PHP

A tiny, fast, PHP-first view engine with **hybrid static caching**.

- Simple template syntax on top of plain PHP
- Precompiles templates to PHP (no `eval`)
- Full-page (or fragment) static cache with automatic expiry
- Optional **lazy data factory** so your database is only hit when needed

Designed to be lightweight enough for small projects and powerful enough for real apps.


## Installation

Via Composer (recommended):

```bash
composer require zogjs/zog-php
````

In your PHP code:

```php
use Zog\Zog;
```

> Adjust the namespace in examples to match how you wire the library into your project.

---

## Quick Start

### 1. Configure directories

```php
use ZogPhp\Zog;

Zog::setViewDir(__DIR__ . '/views');
Zog::setStaticDir(__DIR__ . '/storage/cache/zog_static');
Zog::setCompiledDir(__DIR__ . '/storage/cache/zog_compiled');

// Optional: default TTL for hybrid cache (in seconds)
Zog::setDefaultHybridCacheTtl(Zog::CACHE_A_WEEK);
```

> `setViewDir()` and `setStaticDir()` accept either absolute or relative paths.
> Directories will be created on demand (for static dir).

### 2. Create a view

`views/productView.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@{{ $title }}</title>
</head>
<body>

<h1>@{{ $heading }}</h1>

<div class="product-list">
    <div class="product-item" zp-for="$product, $index of $products">
        <h2>@{{ $product['name'] }}</h2>

        <div zp-if="$product['is_free'] === true">
            This product is completely free.
        </div>
        <div zp-else-if="$product['is_free'] === 'today'">
            Free just for today.
        </div>
        <div zp-else>
            Price: @{{ $product['price'] }} Toman
        </div>

        @php( foreach ($product['tags'] as $tag) )
            <span class="tag">@{{ $tag }}</span>
        @php( endforeach )
    </div>
</div>

<script>
    // Pass PHP data into JS as JSON
    const PRODUCTS = @json($products);
</script>

</body>
</html>
```

### 3. Render it

```php
$products = [
    [
        'name'    => 'VIP Course',
        'price'   => 750000,
        'is_free' => false,
        'tags'    => ['video', 'lifetime'],
    ],
    [
        'name'    => 'Gift E-Book',
        'price'   => 0,
        'is_free' => true,
        'tags'    => ['ebook', 'download'],
    ],
];

echo Zog::render('productView.php', [
    'title'    => 'Products',
    'heading'  => 'Our Products',
    'products' => $products,
]);
```

---

## Template Syntax

Zog templates are just `.php` files with a small DSL on top.

### Escaped output — `@{{ ... }}`

Safely print HTML-escaped content:

```html
<h1>@{{ $title }}</h1>
<p>@{{ $user['name'] }}</p>
```

This compiles to something like:

```php
<h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
```

### Raw (unescaped) output — `@raw(...)`

Use when you **know** the content is safe HTML:

```html
<div class="content">
    @raw($post['html_body'])
</div>
```

> Be careful: `@raw()` bypasses HTML escaping. Do **not** feed untrusted user input into it.

### JSON for JavaScript — `@json(...)`

Conveniently embed PHP structures into JS:

```html
<script>
    const PRODUCTS = @json($products);
    const USER     = @json($user);
</script>
```

This compiles to:

```php
<?php echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
```

### Inline PHP — `@php(...)`

Inject any PHP statement:

```html
@php( $year = (int) date('Y'); )

<footer>
    &copy; @{{ $year }} My Company
</footer>
```

Or loop constructs:

```html
@php( foreach ($products as $product) )
    <div>@{{ $product['name'] }}</div>
@php( endforeach )
```

> You can keep `@php` usage minimal and rely mostly on `zp-for`, `zp-if`, etc., for prettier templates.

---

## Loops — `zp-for`

Zog adds a `zp-for` attribute on HTML elements to generate `foreach` loops.

Basic form:

```html
<div zp-for="$product of $products">
    <h2>@{{ $product['name'] }}</h2>
</div>
```

With index:

```html
<div zp-for="$product, $index of $products">
    <div class="product-index">@{{ $index }}</div>
    <div class="product-name">@{{ $product['name'] }}</div>
</div>
```

Roughly compiles to:

```php
<?php foreach ($products as $index => $product): ?>
    <div>
        ...
    </div>
<?php endforeach; ?>
```

* The element that has `zp-for` is the **loop body**.
* `$product` and `$index` are normal PHP variables inside that element (and its children).

---

## Conditionals — `zp-if`, `zp-else-if`, `zp-else`

Use `zp-if`, `zp-else-if`, `zp-else` on sibling elements to build if/else chains.

Example:

```html
<div zp-if="$product['is_free'] === true">
    This product is completely free.
</div>

<div zp-else-if="$product['is_free'] === 'today'">
    Free just for today.
</div>

<div zp-else>
    Price: @{{ $product['price'] }} Toman
</div>
```

Compiles to something like:

```php
<?php if ($product['is_free'] === true): ?>
    <div>...</div>
<?php elseif ($product['is_free'] === 'today'): ?>
    <div>...</div>
<?php else: ?>
    <div>...</div>
<?php endif; ?>
```

Rules:

* `zp-if` must appear first in the chain.
* Any number of `zp-else-if` can follow.
* At most one `zp-else` (and it must be the last branch).
* Only whitespace or comments are allowed between branches.

If a `zp-else-if` or `zp-else` is found without a preceding `zp-if` at the same level, Zog throws a `ZogTemplateException`.

---

## Rendering Views

### `Zog::render(string $view, array $data = []): string`

Render a view with data:

```php
$html = Zog::render('productView.php', [
    'title'    => 'Products',
    'heading'  => 'Our Products',
    'products' => $products,
]);

echo $html;
```

* `$view` is relative to the directory set with `Zog::setViewDir()`.
* Keys in `$data` are extracted as local variables inside the view (`$title`, `$products`, …).
* The entire array is also available as `$zogData` inside the template.

Internally, Zog:

1. Reads the raw template.
2. Parses HTML + directives to compiled PHP.
3. Caches the compiled PHP (no `eval`).
4. `require`s the compiled PHP with your data in scope.

---

## Serving Raw Static Files

Sometimes you just want to output a static snippet stored under the configured static directory.

### `Zog::static(string $relativePath): string`

Alias to `Zog::staticFile()` via `__callStatic`.

```php
// Reads /path/to/static/dir/terms.html and returns its content as-is.
echo Zog::static('terms.html');
```

If the file doesn’t exist or can’t be read, Zog throws a `ZogException`.

---

## Hybrid Static Cache

The **hybrid cache** is designed for pages that:

* Are generated from dynamic data (database, APIs…)
* But do **not** change on every request

Typical use-case: blog posts, landing pages, product pages.

Zog will:

1. Render the view once.
2. Store the full HTML (plus an expiry comment) as a static file.
3. On subsequent requests, serve the static file directly, **without hitting the database**, until it expires.

### Cache TTL constants

Available duration constants (in seconds):

```php
Zog::CACHE_NONE       // 0
Zog::CACHE_A_MINUTE   // 60
Zog::CACHE_AN_HOUR    // 3600
Zog::CACHE_A_DAY      // 86400
Zog::CACHE_A_WEEK     // 604800

// Legacy alias kept for backward compatibility:
Zog::CACH_A_WEEK      // 604800
```

You can also pass any custom TTL (integer seconds).

### Cache key — string or array

`hybrid()` understands two forms of cache key:

1. **String key** – nice for simple slugs:

   ```php
   'my-post-slug'
   ```

2. **Array key** – for multi-dimensional caching (slug + lang + device…):

   ```php
   [
       'slug'   => $slug,
       'lang'   => $lang,
       'device' => $isMobile ? 'm' : 'd',
   ]
   ```

Array keys are normalized and hashed internally, so order of keys doesn’t matter.

---

### Usage Modes

#### 1) Read-only mode

Try to use an existing static file; if missing/expired, you get `false`:

```php
$html = Zog::hybrid('postView.php', $slug);

if ($html !== false) {
    echo $html;
    return;
}

// No valid cache → you decide what to do (e.g. fetch data & re-render)
```

Signature:

```php
Zog::hybrid(string $view, string|array $key): string|false;
```

---

#### 2) Force re-render with explicit data

Always render and overwrite the static cache with the given data:

```php
$html = Zog::hybrid(
    'postView.php',
    $slug,
    [
        'title' => $post->title,
        'post'  => $post,
    ],
    Zog::CACHE_A_DAY
);

echo $html;
```

Signature:

```php
Zog::hybrid(
    string       $view,
    string|array $key,
    array        $data,
    ?int         $cacheTtl = null
): string;
```

* If `$cacheTtl` is `null`, Zog uses the default TTL set via `setDefaultHybridCacheTtl()`.
* If TTL is `0` or `Zog::CACHE_NONE`, the file is treated as “never expires” and gets a far future expiry date.

---

#### 3) Lazy data factory (recommended)

This is the nicest way to integrate hybrid caching with database access.

You pass a **callable** instead of a plain array.
Zog will only call it when needed (cache miss or expired):

```php
echo Zog::hybrid(
    'postView.php',
    ['slug' => $slug],
    function () use ($slug) {
        $post = Post::where('slug', $slug)->firstOrFail();

        $related = Post::where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->latest()
            ->limit(5)
            ->get();

        return [
            'title'   => $post->title,
            'post'    => $post,
            'related' => $related,
        ];
    },
    Zog::CACHE_A_WEEK
);
```

* If a **valid** static file exists → Zog returns it immediately.
  The factory is **not** called; your DB is not touched.
* If the cache is **missing or expired** → Zog:

  1. Calls the factory
  2. Expects an `array` of view data
  3. Renders the view
  4. Stores the static file and returns the resulting HTML

Signature:

```php
Zog::hybrid(
    string          $view,
    string|array    $key,
    callable|array  $dataOrFactory,
    ?int            $cacheTtl = null
): string;
```

If the callable does not return an array, a `ZogException` is thrown.

---

### Where are static files stored?

All hybrid static files live under the directory configured with:

```php
Zog::setStaticDir(__DIR__ . '/storage/zog_static');
```

Zog uses the view name and the cache key to build a filename:

* String key:

  ```txt
  postView-my-post-slug.php
  ```

* Array key:

  ```txt
  postView-h-e4f1c2a3b7c9d812.php
  ```

Each static file starts with an HTML comment containing the expiry date:

```html
<!-- Automatically generated by zog: [ex:2025-12-09] -->
<!DOCTYPE html>
<html>...</html>
```

This comment:

* Is ignored by browsers and search engines
* Lets Zog quickly decide whether a cached file is still valid
* Keeps expiry metadata **inside** the static file (no extra meta files needed)

---

## Clearing Static Cache

To remove all static files under the configured static directory:

```php
Zog::clearStatics();
```

This:

* Does **not** delete the directory itself
* Only unlinks files directly inside that directory (no recursive subfolders)

Use this for:

* Deployment / release scripts
* Admin “clear cache” buttons

---

## Configuration Summary

Available configuration methods:

```php
Zog::setViewDir(string $dir);                 // View templates directory
Zog::setStaticDir(string $dir);           // Hybrid static cache directory
Zog::setDefaultHybridCacheTtl(?int $s);   // Default TTL for hybrid() when none is given
```

---

## Exceptions & Error Handling

Zog throws custom exceptions:

* `ZogException`
  Base runtime exception for:

  * Missing view files
  * IO problems
  * Invalid arguments
  * Rendering failures, etc.

* `ZogTemplateException`
  For template compilation errors:

  * Invalid `zp-for` or `zp-if` expressions
  * Orphan `zp-else` / `zp-else-if`
  * Bad `@json()` usage, etc.

Typical handling:

```php
try {
    echo Zog::render('productView.php', [...]);
} catch (ZogTemplateException $e) {
    // Template error (developer issue)
} catch (ZogException $e) {
    // Runtime error (missing file, IO, etc.)
}
```

---

## Notes & Limitations

* Zog is intentionally small and **PHP-first**:

  * Expressions inside `@{{ ... }}`, `@raw(...)`, `@json(...)`,
    `zp-if`, and `zp-for` are plain PHP.
* Avoid feeding untrusted user input into `@raw()` or complex PHP expressions; use `@{{ ... }}` for safe HTML output.
* Hybrid cache is **filesystem-based**:

  * Make sure your static directory is writable by PHP.
  * Do not expose compiled or static directories directly to public if you don’t intend to.

---

## License

MIT (or whatever license you choose).

---

## Contributing

* Open issues and pull requests on GitHub.
* Ideas, bug reports, and feature suggestions are very welcome.
* Logo Design
* Document Improvement
