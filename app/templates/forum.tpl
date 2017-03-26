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
      <ol class="f-ftlist">
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
@if($newTopic || $pages)
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
      <div class="f-ftlist">
        <ol class="f-table">
          <li class="f-row f-thead" value="0">
            <div class="f-hcell f-cmain">{!! __('Topic', 1) !!}</div>
            <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
            <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
          </li>
@foreach($topics as $topic)
@if($topic['moved_to'])
          <li id="topic-{!! $topic['id']!!}" class="f-row f-fredir">
            <div class="f-cell f-cmain">
              <div class="f-ficon"></div>
              <div class="f-finfo">
                <h3><span class="f-fredirtext">{!! __('Moved') !!}</span> <a class="f-ftname" href="{!! $topic['link'] !!}">{{ $topic['subject'] }}</a></h3>
              </div>
            </div>
          </li>
@else
          <li id="topic-{!! $topic['id'] !!}" class="f-row<!--inline-->
@if($topic['link_new']) f-fnew
@endif
@if($topic['link_unread']) f-funread
@endif
@if($topic['sticky']) f-fsticky
@endif
@if($topic['closed']) f-fclosed
@endif
@if($topic['poll_type']) f-fpoll
@endif
@if($topic['dot']) f-fposted
@endif
            "><!--endinline-->
            <div class="f-cell f-cmain">
              <div class="f-ficon"></div>
              <div class="f-finfo">
                <h3>
@if($topic['dot'])
                  <span class="f-tdot">·</span>
@endif
@if($topic['sticky'])
                  <span class="f-stickytxt">{!! __('Sticky') !!}</span>
@endif
@if($topic['closed'])
                  <span class="f-closedtxt">{!! __('Closed') !!}</span>
@endif
@if($topic['poll_type'])
                  <span class="f-polltxt">{!! __('Poll') !!}</span>
@endif
                  <a class="f-ftname" href="{!! $topic['link'] !!}">{{ $topic['subject'] }}</a>
@if($topic['pages'])
                  <span class="f-tpages">
@foreach($topic['pages'] as $cur)
@if($cur[1] === 'space')
                    <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
@else
                    <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
@endif
@endforeach
                  </span>
@endif
@if($topic['link_new'])
                  <span class="f-newtxt"><a href="{!! $topic['link_new'] !!}" title="{!! __('New posts info') !!}">{!! __('New posts') !!}</a></span>
@endif
                </h3>
                <p class="f-cmposter">{!! __('by') !!} {{ $topic['poster'] }}</p>
              </div>
            </div>
            <div class="f-cell f-cstats">
              <ul>
                <li>{!! __('%s Reply', $topic['num_replies'], $topic['num_replies']) !!}</li>
@if(null !== $topic['num_views'])
                <li>{!! __('%s View', $topic['num_views'], $topic['num_views'])!!}</li>
@endif
              </ul>
            </div>
            <div class="f-cell f-clast">
              <ul>
                <li class="f-cltopic"><a href="{!! $topic['link_last'] !!}" title="&quot;{{ $topic['subject'] }}&quot; - {!! __('Last post') !!}">{{ $topic['last_post'] }}</a></li>
                <li class="f-clposter">{!! __('by') !!} {{ $topic['last_poster'] }}</li>
              </ul>
            </div>
          </li>
@endif
@endforeach
        </ol>
      </div>
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
