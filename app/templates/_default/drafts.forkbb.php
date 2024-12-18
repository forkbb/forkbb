@include ('layouts/crumbs')
@section ('pagination')
    @if ($p->pagination)
        <nav class="f-pages">
        @foreach ($p->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{{ __('Previous') }}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{{ __('Next') }}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Drafts') !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->pagination)
      <div class="f-nlinks-b">
    @yield ('pagination')
      </div>
@endif
    </div>
    <!-- PRE linksBAfter -->
    <!-- PRE mainBefore -->
    <section id="fork-topic-ins" class="f-main">
      <h2>{!! __('Post list') !!}</h2>
@foreach ($p->drafts as $id => $post)
    @empty ($post->id)
        @php $iswev = [FORK_MESS_ERR => [['Message %s was not found in the database', $id]]]; @endphp
        @include ('layouts/iswev')
    @else
      <article id="p{!! (int) $post->id !!}" class="f-post f-post-search @if (FORK_GEN_MAN == $post->user->gender) f-user-male @elseif (FORK_GEN_FEM == $post->user->gender) f-user-female @endif @if ($post->user->online) f-user-online @endif">
        <header class="f-post-header">
          <h3 class="f-phead-h3">
            <span class="f-psh-forum"><a href="{{ $post->parent->parent->link }}" title="{{ __('Go to forum') }}">{{ $post->parent->parent->forum_name }}</a></span>
            <span class="f-sep"><small>»</small></span>
            <span class="f-psh-topic"><a href="{{ $post->parent->link }}" title="{{ __('Go to topic') }}">{{ $post->parent->name }}</a></span>
          </h3>
          <span class="f-post-number">#{{ $post->postNumber }}</span>
        </header>
        <address class="f-post-user">
          <div class="f-post-usticky">
            <ul hidden class="f-user-info-first">
        @if ($p->userRules->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
            </ul>
            <ul class="f-user-info">
        @if ($p->userRules->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
              <li class="f-usertitle">{{ $post->user->title() }}</li>
            </ul>
            <ul class="f-post-search-info">
              <li class="f-psi-forum">{!! __('Forum') !!}: <a href="{{ $post->parent->parent->link }}">{{ $post->parent->parent->forum_name }}</a></li>
              <li class="f-psi-topic">{!! __('Topic') !!}: <a href="{{ $post->parent->link }}">{{ $post->parent->name }}</a></li>
            </ul>
          </div>
        </address>
        <div class="f-post-body">
          <div class="f-post-main" @if (! empty($p->model->queryRegexp) && 2 !== $p->searchInValue) data-search-regexp="{{ $p->model->queryRegexp }}" @endif>
            {!! $post->html() !!}
          </div>
          <aside class="f-post-bfooter">
        @php $showPostReaction = $p->userRules->showReaction && (! empty($post->reactions) || $post->useReaction) && ! empty($reactions = $post->reactionData(false)) @endphp
        @if ($showPostReaction)
            <div class="f-post-reaction">
            @include ('layouts/reaction')
            </div>
        @endif
            <div class="f-post-btns">
              <small>{!! __('ACTIONS') !!}</small>
              <small>-</small>
              <a class="f-btn f-postedit" title="{{ __('Edit') }}" href="{{ $post->link }}" rel="nofollow"><span>{!! __('Edit') !!}</span></a>
              <small>-</small>
              <a class="f-btn f-postdelete" title="{{ __('Delete') }}" href="{{ $post->linkDelete }}" rel="nofollow"><span>{!! __('Delete') !!}</span></a>
            </div>
          </aside>
        </div>
      </article>
    @endempty
@endforeach
    </section>
    <!-- PRE mainAfter -->
    <!-- PRE linksABefore -->
    <div class="f-nav-links">
@if ($p->pagination)
      <div class="f-nlinks-a">
    @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
    <!-- PRE linksAAfter -->
    <!-- PRE end -->
