<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="{!! $Timeout !!};URL={{ $Link }}">
  <title>{{ $pageTitle }}</title>
  <link rel="stylesheet" type="text/css" href="http://forkbb.local/public/style/ForkBB/style.css">
@foreach($pageHeads as $v)
  {!! $v !!}
@endforeach
</head>
<body>
  <div class="f-wrap">
    <section class="f-main f-redirect">
      <h2>{!! __('Redirecting') !!}</h2>
      <p>{!! $Message !!}</p>
      <p><a href="{{ $Link }}">{!! __('Click redirect') !!}</a></p>
    </section>
<!-- debuginfo -->
  </div>
</body>
</html>
