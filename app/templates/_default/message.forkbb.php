@extends ('layouts/main')
    <!-- PRE start -->
@if ($p->back)
    <div id="fork-bcklnk">
      <p id="id-bcklnk-p"><a class="f-go-back" href="{{ $p->fRootLink }}">{!! __('Go back') !!}</a></p>
    </div>
@endif
    <!-- PRE end -->
