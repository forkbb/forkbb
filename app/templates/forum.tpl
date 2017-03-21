@section('crumbs')
    <section class="f-crumbs">
      <ul>
@foreach($crumbs as $cur)
@if($cur[2])
        <li class="f-crumb"><a href="{!! $cur[0] !!}" class="active">{{ $cur[1] }}</a></li>
@else
        <li class="f-crumb"><a href="{!! $cur[0] !!}">{{ $cur[1] }}</a></li>
@endif
@endforeach
      </ul>
    </section>
@endsection
@extends('layouts/main')
@if($forums)
@yield('crumbs')
    <section class="f-subforums">
      <ol class="f-forumlist">
@foreach($forums as $id => $cat)
        <li id="id-subforums{!! $id !!}" class="f-category">
          <h2>{{ __('Sub forum', 2) }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Sub forum', 1) !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
@include('layouts/subforums')
          </ol>
        </li>
@endforeach
      </ol>
    </section>
@endif
@yield('crumbs')
