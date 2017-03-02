<!DOCTYPE html>
<html lang="{!! $fLang !!}" dir="{!! $fDirection !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $pageTitle }}</title>
  <link rel="stylesheet" type="text/css" href="http://forkbb.local/public/style/ForkBB/style.css">
@foreach($pageHeads as $v)
  {!! $v !!}
@endforeach
</head>
<body>
  <div class="f-wrap">
    <header class="f-header">
      <div class="f-title">
        <h1><a href="{!! $fRootLink !!}">{{ $fTitle }}</a></h1>
        <p class="f-description">{!! $fDescription !!}</p>
      </div>
@if(!empty($fNavigation))
      <nav class="main-nav f-menu">
        <input id="main-nav-checkbox" style="display: none;" type="checkbox">
        <label class="f-menu-toggle" for="main-nav-checkbox"></label>
        <ul class="f-menu-items">
@foreach($fNavigation as $key => $val)
@if($key == $fIndex)
          <li><a id="nav-{{ $key }}" class="active" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
@else
          <li><a id="nav-{{ $key }}" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
@endif
@endforeach
        </ul>
      </nav>
@endif
    </header>
@if($fAnnounce)
    <section class="f-announce">
      <h2>{!! __('Announcement') !!}</h2>
      <p class="f-ancontent">{!! $fAnnounce !!}</p>
    </section>
@endif
@if($fIswev)
@include('layouts/iswev')
@endif
@yield('content')
    <footer class="f-footer clearfix">
      <div class="left">
      </div>
      <div class="right">
        <p class="poweredby">{!! __('Powered by') !!}</p>
      </div>
    </footer>
<!-- debuginfo -->
  </div>
</body>
</html>
