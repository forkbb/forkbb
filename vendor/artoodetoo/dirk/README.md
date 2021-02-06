# Dirk PHP Templates

Tiny and powerfull template engine with syntax almost the same as in laravel/blade.

### Installation

The package can be installed via Composer by requiring the "artoodetoo/dirk" package in your project's composer.json.

```json
{
    "require": {
        "artoodetoo/dirk": "dev-master"
    }
}
```

### Usage

**/views/hello.dirk.html**
```html
@extends('layout/main')

<h1>Hello {{{ $name }}}!<h1>

{{ $timestamp or 'Timestamp not defined' }}

@section('sidebar')

  @foreach($list as $l)
    <p>{{ $l }} @if($l == 3) is equal 3 ! @endif</p>
  @endforeach

@endsection
```

**/views/layout/main.dirk.html**
```html
<!DOCTYPE html>
<html>
<head>
<title>Example</title>
</head>
<body>

<sidebar>

@yield('sidebar', 'Default sidebar text')

</sidebar>

@yield('content')

</body>
</html>
```

**/web/index.php**
```php
<?php

require 'vendor/autoload.php';

use R2\Templating\Dirk;

$view = new Dirk([
    'views' => __DIR__.'/views',
    'cache' => __DIR__.'/cache'
]);

$name = '<artoodetoo>';
$list = [1, 2, 3, 4, 5];

$view->render('hello', compact('name', 'list'));
```

### Feature list

Echoes and comments
  * *{{ $var }}* - Echo. NOTE: it's escaped by default, like in Laravel 5!
  * *{!! $var !!}* - Raw echo without escaping
  * *{{ $var or 'default' }}* - Echo content with a default value
  * *{{{ $var }}}* - Echo escaped content
  * *{{-- Comment --}}* - A comment (in code, not in output)

Conditionals
  * *@if(condition)* - Starts an if block
  * *@else*
  * *@elseif(condition)*
  * *@endif*
  * *@unless(condition)* - Starts an unless block
  * *@endunless*
  * *@isset(condition)* - Starts an isset block
  * *@endisset*
  * *@empty(condition)* - Starts an empty block
  * *@endempty*


Loops
  * *@foreach($list as $key => $val)* - Starts a foreach block
  * *@endforeach*
  * *@forelse($list as $key => $val)* - Starts a foreach with empty block
  * *@empty*
  * *@endforelse*
  * *@for($i = 0; $i < 10; $i++)* - Starts a for block
  * *@endfor*
  * *@while(condition)* - Starts a while block
  * *@endwhile*

Inheritance and sections
  * *@include(file)* - Includes another template
  * *@extends('layout')* - Extends a template with a layout
  * *@section('name')* - Starts a section
  * *@endsection* - Ends section
  * *@yield('section')* - Yields content of a section.
  * *@show* - Ends section and yields its content
  * *@stop* - Ends section
  * *@append* - Ends section and appends it to existing of section of same name
  * *@overwrite* - Ends section, overwriting previous section of same name

### License

The Dirk is open-source software, licensed under the [MIT license](http://opensource.org/licenses/MIT)
