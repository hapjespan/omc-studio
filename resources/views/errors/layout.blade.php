<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>@yield('title')</h1>
        <p>@yield('text')</p>
        <a href="/">{{ __('errors.overview') }}</a>
    </main>
</body>
</html>
