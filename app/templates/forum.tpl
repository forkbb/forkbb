@section('crumbs')
      <ul class="f-crumbs">
@foreach($crumbs as $cur)
@if($cur[2])
        <li class="f-crumb"><a href="{!! $cur[0] !!}" class="active">{{ $cur[1] }}</a></li>
@else
        <li class="f-crumb"><a href="{!! $cur[0] !!}">{{ $cur[1] }}</a></li>
@endif
@endforeach
      </ul>
@endsection
@section('linkpost')
@if($newTopic)
        <div class="f-link-post">
          <a class="f-btn" href="{!! $newTopic !!}">{!! __('Post topic') !!}</a>
        </div>
@endif
@endsection
@section('pages')
        <nav class="f-pages">
@foreach($pages as $cur)
@if($cur[2])
          <span class="f-page active">{{ $cur[1] }}</span>
@elseif($cur[1] === 'space')
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
@elseif($cur[1] === 'prev')
          <a rel="prev" class="f-page f-pprev" href="{!! $cur[0] !!}">{!! __('Previous') !!}</a>
@elseif($cur[1] === 'next')
          <a rel="next" class="f-page f-pnext" href="{!! $cur[0] !!}">{!! __('Next') !!}</a>
@else
          <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
@endif
@endforeach
        </nav>
@endsection
@extends('layouts/main')
@if($forums)
    <div class="f-nav-links">
@yield('crumbs')
    </div>
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
    <div class="f-nav-links">
@yield('crumbs')
@if($newTopic)
      <div class="f-links-b clearfix">
@yield('pages')
@yield('linkpost')
      </div>
@endif
    </div>
@if(empty($topics))
    <section class="f-main f-message">
      <h2>{!! __('Empty forum') !!}</h2>
    </section>
@else
    <section class="f-main f-forum">
      <h2>{{ $forumName }}</h2>
    </section>
    <div class="f-nav-links">
@if($newTopic || $pages)
      <div class="f-links-a clearfix">
@yield('linkpost')
@yield('pages')
      </div>
@endif
@yield('crumbs')
    </div>
@endif
