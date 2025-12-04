# Zog PHP

**Zog** is a lightweight PHP view engine with:

- **DOM-based template compilation** (no `eval`)
- **Hybrid static caching** (HTML is pre-rendered to static files with TTL)
- A small set of **Blade-like directives** (`@section`, `@yield`, `@component`, `@{{ }}`, etc.)
- A safe way to **disable Zog processing** in parts of the DOM (`zp-nozog`)

It is designed to be:

- **Tiny & framework-agnostic** – just one class you can drop into any project.
- **Fast in production** – compiled templates + optional static HTML cache.
- **Safe by default** – escaped output and no `eval`.

---

## Requirements

- PHP **8.1+**
- `ext-dom` / `DOMDocument` (standard in most PHP installations)
- `libxml` (standard in most PHP installations)

---

## Installation

Copy `Zog.php` (and the accompanying `View.php` file if you use layouts/components) into your project and load it via your autoloader or a simple `require`:

```php
require __DIR__ . '/src/Zog.php';
require __DIR__ . '/src/View.php'; // if you use layouts/components

Configure the directories once at bootstrap time:

```php
use Zog\Zog;

Zog::setViewDir(__DIR__ . '/views');
Zog::setStaticDir(__DIR__ . '/static');       // for hybrid cache files
Zog::setCompiledDir(__DIR__ . '/storage/zog'); // for compiled templates
```

> The directories will be created automatically if they do not exist.

---

## Quick Start

### 1. Simple render

**views/hello.php**

```html
<h1>Hello @{{ $name }}!</h1>
<p>Today is @{{ $today }}.</p>
```

**index.php**

```php
use Zog\Zog;

echo Zog::render('hello.php', [
    'name'  => 'Reza',
    'today' => date('Y-m-d'),
]);
```

---

### 2. Layout + section example

**views/layouts/main.php**

```html
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@{{ $title }}</title>
</head>
<body>
    <header>
        <h1>My Site</h1>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
```

**views/pages/home.php**

```html
@section('content')
    <h2>Welcome, @{{ $userName }}</h2>
    <p>This is the home page.</p>
@endsection
```

**index.php**

```php
use Zog\Zog;

echo Zog::renderLayout(
    'layouts/main.php',
    'pages/home.php',
    [
        'title'    => 'Home',
        'userName' => 'Reza',
    ]
);
```

---

## Template Syntax & Directives

Zog parses your HTML with `DOMDocument` and then rewrites special attributes / directives into PHP code.

### Escaped output – `@{{ ... }}`

Escaped output is the default:

```html
<p>@{{ $user->name }}</p>
```

Compiles to:

```php
<?php echo htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8'); ?>
```

---

### Raw output – `@raw(...)`

Use raw output only when you are sure the content is safe:

```html
<div>@raw($html)</div>
```

Compiles to:

```php
<?php echo $html; ?>
```

---

### Raw PHP – `@php(...)`

You can inject raw PHP (enabled by default):

```html
@php($i = 0)

<ul>
    @php(for ($i = 0; $i < 3; $i++)):
        <li>@{{ $i }}</li>
    @php(endfor;)
</ul>
```

If you want to **disable** this directive for security reasons:

```php
Zog::allowRawPhpDirective(false);
```

Any use of `@php(...)` after that will throw a `ZogTemplateException`.

---

### JSON / JavaScript – `@json(...)` and `@tojs(...)`

Both directives are equivalent and produce `json_encode`’d output:

```html
<script>
    const items = @json($items);
    const user  = @tojs($user);
</script>
```

Compiles to:

```php
<?php echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
<?php echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
```

---

### Layouts – `@section`, `@endsection`, `@yield`

#### In child view

```html
@section('content')
    <h2>Dashboard</h2>
    <p>Hello @{{ $user->name }}!</p>
@endsection
```

#### In layout

```html
<body>
    @yield('content')
</body>
```

At runtime, `Zog\View` handles section buffering and rendering.

---

### Components – `@component(...)`

You can render partials/components from within a template:

```html
<div class="card">
    @component('components/user-card.php', ['user' => $user])
</div>
```

Or call it directly from PHP:

```php
$html = Zog::component('components/user-card.php', [
    'user' => $user,
]);
```

---

### Loops – `zp-for`

Use `zp-for` on an element to generate a `foreach`:

```html
<ul>
    <li zp-for="item, index of $items">
        @{{ $index }} – @{{ $item }}
    </li>
</ul>
```

Supports:

* `item of $items`
* `item, key of $items`

Behind the scenes, this becomes:

```php
<?php foreach ($items as $index => $item): ?>
    <li>...</li>
<?php endforeach; ?>
```

---

### Conditionals – `zp-if`, `zp-else-if`, `zp-else`

Chain conditional attributes at the **same DOM level**:

```html
<p zp-if="$user->isAdmin">
    You are an admin.
</p>
<p zp-else-if="$user->isModerator">
    You are a moderator.
</p>
<p zp-else>
    You are a regular user.
</p>
```

Compiles roughly to:

```php
<?php if ($user->isAdmin): ?>
    <p>You are an admin.</p>
<?php elseif ($user->isModerator): ?>
    <p>You are a moderator.</p>
<?php else: ?>
    <p>You are a regular user.</p>
<?php endif; ?>
```

If a `zp-else-if` or `zp-else` is found without a preceding `zp-if` at the same level, a `ZogTemplateException` is thrown.

---

### Disabling Zog in a subtree – `zp-nozog`

Sometimes you want Zog to **leave a part of the DOM untouched**, especially when embedding the markup of another templating system or frontend framework (e.g. Vue, Alpine, etc.).

Add `zp-nozog` to any element to disable **DOM-level** Zog processing for that element and all its descendants:

```html
<div zp-nozog>
    <!-- Zog does NOT compile this zp-if -->
    <p zp-if="$user->isAdmin">
        This will be rendered exactly as-is in the final HTML.
    </p>
</div>
```

Behavior:

* Zog does **not** convert `zp-if`, `zp-for`, `zp-else-if`, or `zp-else` inside this subtree into PHP.
* The attribute `zp-nozog` itself is removed from the final HTML.
* Inline text directives (such as `@{{ $something }}` or `@raw($something)`) still work, because text processing is independent of DOM-level control attributes.

This is especially useful when you want to keep attributes for a frontend framework:

```html
<div zp-nozog>
    <button v-if="isAdmin">Admin button</button>
</div>
```

Zog will not attempt to interpret `v-if="isAdmin"`.

---

## Hybrid Static Cache

Zog’s hybrid cache lets you render a page once, save it as a static file, and serve that file on future requests until a TTL (time to live) expires.

Signature:

```php
Zog::hybrid(
    string $view,
    string|array $key,
    array|callable|null $dataOrFactory = null,
    ?int $cacheTtl = null
);
```

### Cache TTL constants

```php
use Zog\Zog;

Zog::CACHE_NONE    // 0
Zog::CACHE_A_MINUTE
Zog::CACHE_AN_HOUR
Zog::CACHE_A_DAY
Zog::CACHE_A_WEEK
```

You can also override the default TTL:

```php
Zog::setDefaultHybridCacheTtl(Zog::CACHE_A_DAY);
// or disable default TTL (TTL must be explicit in hybrid calls)
Zog::setDefaultHybridCacheTtl(null);
```

> Internally, static files start with a comment that holds the expiry date, for example:
> `<!-- Automatically generated by zog: [ex:2025-12-09] -->`
> This is just an HTML comment and is ignored by browsers and search engines.

---

### Mode 1 – Direct data (always re-render)

```php
$html = Zog::hybrid(
    'pages/home.php',
    'home',
    [
        'title' => 'Home',
        'user'  => $user,
    ],
    Zog::CACHE_A_HOUR
);
```

* Always **re-renders** the view.
* Writes/overwrites the static file.
* Returns the rendered HTML (including the header comment).

This mode behaves like `render()` plus “also save the result to a static file”.

---

### Mode 2 – Lazy factory (only run when needed)

This is the recommended mode when fetching data from a database or an API.

```php
$html = Zog::hybrid(
    'pages/home.php',
    'home',
    function () use ($db, $userId) {
        // This closure is called only when:
        // - there is no static file, or
        // - it has expired.
        $user = $db->getUserById($userId);

        return [
            'title' => 'Home',
            'user'  => $user,
        ];
    },
    Zog::CACHE_A_HOUR
);
```

Workflow:

1. If a valid static file exists and has not expired, Zog:

   * Returns its contents.
   * **Does not call** the factory.
2. If the file is missing or expired, Zog:

   * Calls the factory.
   * Expects an `array` of data.
   * Renders the view.
   * Writes a new static file with an updated expiry comment.
   * Returns the fresh HTML.

If the factory does not return an array, Zog throws a `ZogException`.

---

### Mode 3 – Read-only access

You can check or serve an existing static file without rendering or running any data logic:

```php
$content = Zog::hybrid(
    'pages/home.php',
    'home',
    null // read-only mode
);

if ($content === false) {
    // no valid cache yet
    // you can decide to build it here or fall back to render()
    $content = Zog::render('pages/home.php', [
        'title' => 'Home',
        'user'  => $user,
    ]);
}

echo $content;
```

* Returns `false` if:

  * The static file does not exist.
  * The static file is unreadable.
  * The static file is expired or has an invalid expiry comment.

---

### Static file naming

Static files are stored under the static directory configured with `Zog::setStaticDir()`.

* If `$key` is a **string**, Zog creates a slug-like filename:
  `viewName-your-key.php`
* If `$key` is an **array**, Zog:

  * Normalizes the array (sorts associative keys, recursively).
  * JSON-encodes it.
  * Hashes the JSON.
  * Uses a short `sha1` prefix, e.g. `viewName-zog-0123456789abcdef.php`.

This guarantees that the same logical key always maps to the same static file.

---

## Directory Helpers

```php
// Change where views are loaded from
Zog::setViewDir(__DIR__ . '/views');

// Change where static cache files are written
Zog::setStaticDir(__DIR__ . '/static');

// Change where compiled PHP templates are stored
Zog::setCompiledDir(__DIR__ . '/storage/zog');
```

### Clearing caches

```php
// Remove all static HTML files (does not delete the directory itself)
Zog::clearStatics();

// Remove all compiled template files (does not delete the directory itself)
Zog::clearCompiled();
```

---

## Error Handling

Zog uses exceptions for all error conditions:

* `ZogException` – base exception for general runtime issues:

  * bad directories
  * missing view files
  * I/O failures
  * invalid hybrid usage
* `ZogTemplateException` – template compilation errors:

  * invalid `zp-for` / `zp-if` syntax
  * unmatched parentheses in directives
  * `zp-else` without a preceding `zp-if`
  * misuse of section/component directives
  * disabled `@php()` still being used

Example:

```php
try {
    echo Zog::render('pages/home.php', ['user' => $user]);
} catch (\Zog\ZogTemplateException $e) {
    // render a friendly error page for template errors
} catch (\Zog\ZogException $e) {
    // log and render a generic error page
}
```

---

## Notes

* Zog does **not** use `eval`; compiled templates are normal PHP files that are `require`d.
* All data passed into `render()` (or via hybrid) is available as:

  * Individual variables (`$user`, `$title`, etc.).
  * A full array `$zogData` if you prefer to access everything as an array.

---




## License

MIT (or whatever license you choose).

---

## Contributing

* Open issues and pull requests on GitHub.
* Ideas, bug reports, and feature suggestions are very welcome.
* Logo Design
* Document Improvement
