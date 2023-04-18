@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __($p->formTitle) !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
@if ($p->previewHtml)
    <section class="f-preview">
      <h2>{!! __('Post preview') !!}</h2>
      <div class="f-post-body">
        <div class="f-post-main">
          {!! $p->previewHtml !!}
    @if ($poll = $p->poll)
        @include ('layouts/poll')
    @endif
        </div>
      </div>
    </section>
@endif
@if ($form = $p->form)
    <section class="f-post-form">
      <h2>{!! __($p->formTitle) !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
@if ($p->posts)
    <section id="fork-view-posts">
      <h2>{!! __($p->postsTitle) !!}</h2>
    @foreach ($p->posts as $post)
        @if ($post->id)
      <article id="p{{ $post->id }}" class="f-post @if (1 == $post->user->gender) f-user-male @elseif (2 == $post->user->gender) f-user-female @endif">
        <header class="f-post-header">
          <h3 class="f-phead-h3">@if ($post->postNumber > 1){!! __('Re') !!} @endif{{ $post->parent->name }}</h3>
          <span class="f-post-posted"><time datetime="{{ \gmdate('c', $post->posted) }}">{{ dt($post->posted) }}</time></span>
          <span class="f-post-number"><a href="{{ $post->link }}" rel="bookmark">#{{ $post->postNumber }}</a></span>
        </header>
        <address class="f-post-user">
          <div class="f-post-usticky">
            <ul hidden class="f-user-info-first">
              <li class="f-username">{{ $post->user->username }}</li>
            </ul>
            <ul class="f-user-info">
              <li class="f-username">{{ $post->user->username }}</li>
            </ul>
          </div>
        </address>
        <div class="f-post-body">
          <div class="f-post-main">
            {!! $post->html() !!}
          </div>
        </div>
      </article>
        @endif
    @endforeach
    </section>
@endif
