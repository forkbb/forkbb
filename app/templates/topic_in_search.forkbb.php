@include ('layouts/crumbs')
@section ('pagination')
    @if ($p->model->pagination)
        <nav class="f-pages">
        @foreach ($p->model->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{!! __('Previous') !!}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{!! __('Next') !!}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->model->pagination)
      <div class="f-nlinks-b">
    @yield ('pagination')
      </div>
@endif
    </div>
    <section id="fork-topic-ins" class="f-main">
      <h2>{!! __($p->model->name) !!}</h2>
@foreach ($p->posts as $id => $post)
    @if (empty($post->id) && $iswev = ['e' => [['Message %s was not found in the database', $id]]])
        @include ('layouts/iswev')
    @else
      <article id="p{{ $post->id }}" class="f-post f-post-search @if (1 == $post->user->gender) f-user-male @elseif (2 == $post->user->gender) f-user-female @endif @if ($post->user->online) f-user-online @endif">
        <header class="f-post-header">
          <h3 class="f-phead-h3">
            <span class="f-psh-forum"><a href="{{ $post->parent->parent->link }}" title="{{ __('Go to forum') }}">{{ $post->parent->parent->forum_name }}</a></span>
            <span class="f-sep"><small>»</small></span>
            <span class="f-psh-topic"><a href="{{ $post->parent->link }}" title="{{ __('Go to topic') }}">@if ($post->id !== $post->parent->first_post_id){!! __('Re') !!} @endif{{ $post->parent->name }}</a></span>
            <span class="f-sep"><small>»</small></span>
            <span class="f-post-posted"><a href="{{ $post->link }}" title="{{ __('Go to post') }}" rel="bookmark"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></a></span>
          </h3>
          <span class="f-post-number">#{{ $post->postNumber }}</span>
        </header>
        <address class="f-post-user">
          <div class="f-post-usticky">
            <ul hidden class="f-user-info-first">
        @if ($p->user->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
            </ul>
            <ul class="f-user-info">
        @if ($p->user->viewUsers && $post->user->link)
              <li class="f-username"><a href="{{ $post->user->link }}">{{ $post->user->username }}</a></li>
        @else
              <li class="f-username">{{ $post->user->username }}</li>
        @endif
              <li class="f-usertitle">{{ $post->user->title() }}</li>
            </ul>
            <ul class="f-post-search-info">
              <li class="f-psi-forum">{!! __('Forum') !!}: <a href="{{ $post->parent->parent->link }}">{{ $post->parent->parent->forum_name }}</a></li>
              <li class="f-psi-topic">{!! __('Topic') !!}: <a href="{{ $post->parent->link }}">{{ $post->parent->name }}</a></li>
              <li class="f-psi-reply">{!! __(['%s Reply', $post->parent->num_replies, num($post->parent->num_replies)]) !!}</li>
        @if ($post->parent->showViews)
              <li class="f-psi-view">{!! __(['%s View', $post->parent->num_views, num($post->parent->num_views)]) !!}</li>
        @endif
            </ul>
          </div>
        </address>
        <div class="f-post-body">
          <div class="f-post-main">
            {!! $post->html() !!}
          </div>
          <aside class="f-post-btns">
            <ul>
              <li class="f-posttotopic"><a class="f-btn" href="{{ $post->parent->link }}">{!! __('Go to topic') !!}</a></li>
              <li class="f-posttopost"><a class="f-btn" href="{{ $post->link }}">{!! __('Go to post') !!}</a></li>
            </ul>
          </aside>
        </div>
      </article>
    @endif
@endforeach
    </section>
    <div class="f-nav-links">
@if ($p->model->pagination)
      <div class="f-nlinks-a">
    @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
